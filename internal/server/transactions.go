package server

import (
	"errors"
	"net/http"
	"sort"
	"strconv"
	"unicode/utf8"

	"github.com/gin-gonic/gin"
	"github.com/strichliste/strichliste-backend/internal/apierror"
	"github.com/strichliste/strichliste-backend/internal/model"
	"github.com/strichliste/strichliste-backend/internal/rules"
	"gorm.io/gorm"
	"gorm.io/gorm/clause"
)

func (s *Server) registerTransactions(api *gin.RouterGroup) {
	api.GET("/transaction", s.listTransactions)
	api.POST("/user/:userId/transaction", s.createUserTransaction)
	api.GET("/user/:userId/transaction", s.listUserTransactions)
	api.GET("/user/:userId/transaction/:transactionId", s.getUserTransaction)
	api.DELETE("/user/:userId/transaction/:transactionId", s.deleteTransaction)
}

// withTxPreloads adds the associations needed to serialize a transaction.
func withTxPreloads(q *gorm.DB) *gorm.DB {
	return q.
		Preload("User").
		Preload("Article.Barcodes").
		Preload("Article.ArticleTags.Tag").
		Preload("Article.Precursor").
		Preload("RecipientTransaction.User").
		Preload("SenderTransaction.User")
}

func (s *Server) loadTransaction(id uint) (*model.Transaction, error) {
	var t model.Transaction
	if err := withTxPreloads(s.db).First(&t, id).Error; err != nil {
		return nil, err
	}
	return &t, nil
}

func (s *Server) listTransactions(c *gin.Context) {
	limit := queryInt(c, "limit", 25)
	offset := queryIntPtr(c, "offset")

	var count int64
	if err := s.db.Model(&model.Transaction{}).Count(&count).Error; err != nil {
		fail(c, err)
		return
	}

	q := withTxPreloads(s.db).Order("id").Limit(limit)
	if offset != nil {
		q = q.Offset(*offset)
	}
	var txs []model.Transaction
	if err := q.Find(&txs).Error; err != nil {
		fail(c, err)
		return
	}

	c.JSON(http.StatusOK, gin.H{"count": count, "transactions": s.serializeTransactions(txs)})
}

func (s *Server) listUserTransactions(c *gin.Context) {
	userID, ok := parseUintParam(c, "userId")
	if !ok {
		fail(c, apierror.UserNotFound(c.Param("userId")))
		return
	}
	var user model.User
	if err := s.db.First(&user, userID).Error; err != nil {
		fail(c, apierror.UserNotFound(c.Param("userId")))
		return
	}

	limit := queryInt(c, "limit", 25)
	offset := queryIntPtr(c, "offset")

	var count int64
	s.db.Model(&model.Transaction{}).Where("user_id = ?", userID).Count(&count)

	q := withTxPreloads(s.db).Where("user_id = ?", userID).Order("id DESC").Limit(limit)
	if offset != nil {
		q = q.Offset(*offset)
	}
	var txs []model.Transaction
	if err := q.Find(&txs).Error; err != nil {
		fail(c, err)
		return
	}

	c.JSON(http.StatusOK, gin.H{"count": count, "transactions": s.serializeTransactions(txs)})
}

func (s *Server) getUserTransaction(c *gin.Context) {
	userID, ok := parseUintParam(c, "userId")
	if !ok {
		fail(c, apierror.UserNotFound(c.Param("userId")))
		return
	}
	var user model.User
	if err := s.db.First(&user, userID).Error; err != nil {
		fail(c, apierror.UserNotFound(c.Param("userId")))
		return
	}

	txID, _ := parseUintParam(c, "transactionId")
	t, err := s.loadTransaction(txID)
	if errors.Is(err, gorm.ErrRecordNotFound) {
		fail(c, apierror.TransactionNotFound(int(txID)))
		return
	}
	if err != nil {
		fail(c, err)
		return
	}
	c.JSON(http.StatusOK, gin.H{"transaction": s.ser.Transaction(t)})
}

func (s *Server) createUserTransaction(c *gin.Context) {
	p, err := parseParams(c)
	if err != nil {
		fail(c, apierror.ParameterInvalid("body"))
		return
	}

	amount := p.IntPtr("amount")
	quantity := p.IntPtr("quantity")
	comment := p.StringPtr("comment")
	recipientID := p.IntPtr("recipientId")
	articleID := p.IntPtr("articleId")

	if comment != nil && utf8.RuneCountInString(*comment) > 255 {
		fail(c, apierror.ParameterInvalid("comment"))
		return
	}

	userID, ok := parseUintParam(c, "userId")
	if !ok {
		fail(c, apierror.UserNotFound(c.Param("userId")))
		return
	}
	var user model.User
	if err := s.db.First(&user, userID).Error; err != nil {
		fail(c, apierror.UserNotFound(c.Param("userId")))
		return
	}

	created, err := s.doTransaction(&user, amount, comment, quantity, articleID, recipientID)
	if err != nil {
		fail(c, err)
		return
	}

	t, err := s.loadTransaction(created.ID)
	if err != nil {
		fail(c, err)
		return
	}
	c.JSON(http.StatusOK, gin.H{"transaction": s.ser.Transaction(t)})
}

func (s *Server) deleteTransaction(c *gin.Context) {
	// Note: userId is intentionally ignored (matches PHP).
	txID, _ := parseUintParam(c, "transactionId")
	reverted, err := s.revertTransaction(int(txID))
	if err != nil {
		fail(c, err)
		return
	}
	// In delete mode the row no longer exists; fall back to the in-memory object.
	t, loadErr := s.loadTransaction(reverted.ID)
	if errors.Is(loadErr, gorm.ErrRecordNotFound) {
		t = reverted
	} else if loadErr != nil {
		fail(c, loadErr)
		return
	}
	c.JSON(http.StatusOK, gin.H{"transaction": s.ser.Transaction(t)})
}

// doTransaction performs a balance movement (deposit/dispense/purchase/transfer)
// inside a locked DB transaction, mirroring the PHP TransactionService.
func (s *Server) doTransaction(sender *model.User, amount *int, comment *string, quantity, articleID, recipientID *int) (*model.Transaction, error) {
	if (recipientID != nil || articleID != nil) && amount != nil && *amount > 0 {
		return nil, apierror.TransactionInvalid("Amount can't be positive when sending money or buying an article")
	}

	var result *model.Transaction
	err := s.db.Transaction(func(tx *gorm.DB) error {
		// Lock all involved users in ascending id order.
		userIDs := []int{int(sender.ID)}
		if recipientID != nil {
			userIDs = append(userIDs, *recipientID)
		}
		sort.Ints(userIDs)
		locked := map[int]*model.User{}
		for _, id := range userIDs {
			u, err := lockUser(tx, id)
			if err != nil {
				return err
			}
			locked[id] = u
		}

		main := &model.Transaction{Comment: comment}

		// Article handling.
		var article *model.Article
		if articleID != nil {
			var a model.Article
			if err := tx.Clauses(clause.Locking{Strength: "UPDATE"}).First(&a, *articleID).Error; err != nil {
				if errors.Is(err, gorm.ErrRecordNotFound) {
					return apierror.ArticleNotFound(strconv.Itoa(*articleID))
				}
				return err
			}
			if !a.Active {
				return apierror.ArticleInactive(a.Name, int(a.ID))
			}
			q := 1
			if quantity != nil && *quantity != 0 {
				q = *quantity
			}
			main.Quantity = &q
			if amount == nil {
				v := a.Amount * q * -1
				amount = &v
			}
			aid := a.ID
			main.ArticleID = &aid
			a.UsageCount++
			if err := tx.Save(&a).Error; err != nil {
				return err
			}
			article = &a
		}

		amountVal := 0
		if amount != nil {
			amountVal = *amount
		}

		// Recipient leg (transfer).
		var recipientTx *model.Transaction
		if recipientID != nil {
			recipient := locked[*recipientID]
			rid := recipient.ID
			recipientTx = &model.Transaction{
				Amount:  amountVal * -1,
				Comment: comment,
				UserID:  &rid,
			}
			if article != nil {
				aid := article.ID
				recipientTx.ArticleID = &aid
			}
			recipient.AddBalance(amountVal * -1)
			if err := rules.CheckAccountBalanceBoundary(s.settings, int(recipient.ID), recipient.Balance); err != nil {
				return err
			}
		}

		// Sender leg.
		senderUser := locked[int(sender.ID)]
		sid := senderUser.ID
		main.UserID = &sid
		main.Amount = amountVal
		if err := rules.CheckTransactionBoundary(s.settings, amountVal); err != nil {
			return err
		}
		senderUser.AddBalance(amountVal)
		if err := rules.CheckAccountBalanceBoundary(s.settings, int(senderUser.ID), senderUser.Balance); err != nil {
			return err
		}

		// Persist: recipient tx first (for linking), then main, then back-link.
		if recipientTx != nil {
			if err := tx.Create(recipientTx).Error; err != nil {
				return err
			}
			main.RecipientTransactionID = &recipientTx.ID
		}
		if err := tx.Create(main).Error; err != nil {
			return err
		}
		if recipientTx != nil {
			recipientTx.SenderTransactionID = &main.ID
			if err := tx.Save(recipientTx).Error; err != nil {
				return err
			}
			if err := tx.Save(locked[*recipientID]).Error; err != nil {
				return err
			}
		}
		if err := tx.Save(senderUser).Error; err != nil {
			return err
		}

		result = main
		return nil
	})
	return result, err
}

// revertTransaction undoes a transaction (and its paired leg, if any), mirroring
// the PHP TransactionService::revertTransaction.
func (s *Server) revertTransaction(transactionID int) (*model.Transaction, error) {
	var result *model.Transaction
	err := s.db.Transaction(func(tx *gorm.DB) error {
		var primary model.Transaction
		if err := tx.First(&primary, transactionID).Error; err != nil {
			if errors.Is(err, gorm.ErrRecordNotFound) {
				return apierror.TransactionNotFound(transactionID)
			}
			return err
		}

		txIDs := []int{int(primary.ID)}
		userIDs := []int{}
		if primary.UserID != nil {
			userIDs = append(userIDs, int(*primary.UserID))
		}
		pairedID := primary.RecipientTransactionID
		if pairedID == nil {
			pairedID = primary.SenderTransactionID
		}
		if pairedID != nil {
			var paired model.Transaction
			if err := tx.First(&paired, *pairedID).Error; err != nil {
				return err
			}
			txIDs = append(txIDs, int(paired.ID))
			if paired.UserID != nil {
				userIDs = append(userIDs, int(*paired.UserID))
			}
		}
		sort.Ints(txIDs)
		sort.Ints(userIDs)

		lockedTx := map[int]*model.Transaction{}
		for _, id := range txIDs {
			var t model.Transaction
			if err := tx.Clauses(clause.Locking{Strength: "UPDATE"}).First(&t, id).Error; err != nil {
				if errors.Is(err, gorm.ErrRecordNotFound) {
					return apierror.TransactionNotFound(id)
				}
				return err
			}
			lockedTx[id] = &t
		}
		lockedUsers := map[int]*model.User{}
		for _, id := range userIDs {
			u, err := lockUser(tx, id)
			if err != nil {
				return err
			}
			lockedUsers[id] = u
		}

		if primary.ArticleID != nil {
			var a model.Article
			if err := tx.Clauses(clause.Locking{Strength: "UPDATE"}).First(&a, *primary.ArticleID).Error; err != nil {
				if errors.Is(err, gorm.ErrRecordNotFound) {
					return apierror.ArticleNotFound(strconv.Itoa(int(*primary.ArticleID)))
				}
				return err
			}
			a.UsageCount--
			if err := tx.Save(&a).Error; err != nil {
				return err
			}
		}

		deleteMode := s.settings.GetBool("payment.undo.delete", false)
		for _, id := range txIDs {
			t := lockedTx[id]
			if t.Deleted {
				return apierror.TransactionNotDeletable(int(t.ID))
			}
			var user *model.User
			if t.UserID != nil {
				user = lockedUsers[int(*t.UserID)]
			}
			if err := rules.CheckTransactionBoundary(s.settings, t.Amount); err != nil {
				return err
			}
			if user != nil {
				user.AddBalance(t.Amount * -1)
				if err := rules.CheckAccountBalanceBoundary(s.settings, int(user.ID), user.Balance); err != nil {
					return err
				}
			}
			if deleteMode {
				if err := tx.Delete(&model.Transaction{}, t.ID).Error; err != nil {
					return err
				}
			} else {
				t.Deleted = true
				if err := tx.Save(t).Error; err != nil {
					return err
				}
			}
			if user != nil {
				if err := tx.Save(user).Error; err != nil {
					return err
				}
			}
		}

		result = lockedTx[transactionID]
		return nil
	})
	return result, err
}

func lockUser(tx *gorm.DB, id int) (*model.User, error) {
	var u model.User
	if err := tx.Clauses(clause.Locking{Strength: "UPDATE"}).First(&u, id).Error; err != nil {
		if errors.Is(err, gorm.ErrRecordNotFound) {
			return nil, apierror.UserNotFound(strconv.Itoa(id))
		}
		return nil, err
	}
	return &u, nil
}

func (s *Server) serializeTransactions(txs []model.Transaction) []map[string]any {
	out := make([]map[string]any, 0, len(txs))
	for i := range txs {
		out = append(out, s.ser.Transaction(&txs[i]))
	}
	return out
}

func parseUintParam(c *gin.Context, name string) (uint, bool) {
	v, err := strconv.Atoi(c.Param(name))
	if err != nil || v < 0 {
		return 0, false
	}
	return uint(v), true
}
