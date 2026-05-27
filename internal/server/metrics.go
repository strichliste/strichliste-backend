package server

import (
	"net/http"
	"time"

	"github.com/gin-gonic/gin"
	"github.com/strichliste/strichliste-backend/internal/apierror"
	"github.com/strichliste/strichliste-backend/internal/model"
)

func (s *Server) registerMetrics(api *gin.RouterGroup) {
	api.GET("/metrics", s.metrics)
	api.GET("/user/:userId/metrics", s.userMetrics)
}

func (s *Server) metrics(c *gin.Context) {
	days := queryInt(c, "days", 30)

	var balance int64
	s.db.Model(&model.User{}).Where("disabled = ?", false).Select("COALESCE(SUM(balance),0)").Scan(&balance)

	var transactionCount int64
	s.db.Model(&model.Transaction{}).Count(&transactionCount)

	var userCount int64
	s.db.Model(&model.User{}).Count(&userCount)

	var articles []model.Article
	articlePreloads(s.db).Where("active = ?", true).Order("usage_count DESC").Find(&articles)

	c.JSON(http.StatusOK, gin.H{
		"balance":          balance,
		"transactionCount": transactionCount,
		"userCount":        userCount,
		"articles":         s.serializeArticles(articles, 0),
		"days":             s.transactionsPerDay(days, time.Now()),
	})
}

// transactionsPerDay builds the per-day breakdown. Empty days keep scalar zero
// charged/spent fields; active days render them as objects (a preserved quirk).
func (s *Server) transactionsPerDay(days int, now time.Time) []map[string]any {
	startOfToday := time.Date(now.Year(), now.Month(), now.Day(), 0, 0, 0, 0, now.Location())
	beginDate := startOfToday.AddDate(0, 0, -days)

	// Ordered date keys from beginDate..today inclusive.
	var dates []string
	entries := map[string]map[string]any{}
	for d := beginDate; !d.After(startOfToday); d = d.AddDate(0, 0, 1) {
		key := d.Format("2006-01-02")
		dates = append(dates, key)
		entries[key] = map[string]any{
			"date":          key,
			"transactions":  0,
			"distinctUsers": 0,
			"balance":       0,
			"charged":       0,
			"spent":         0,
		}
	}

	type row struct {
		Created time.Time
		Amount  int
		UserID  *uint
	}
	var rows []row
	s.db.Model(&model.Transaction{}).
		Select("created", "amount", "user_id").
		Where("created >= ?", beginDate).
		Find(&rows)

	type agg struct {
		count, balance             int
		countCharged, countSpent   int
		amountCharged, amountSpent int
		users                      map[uint]struct{}
	}
	buckets := map[string]*agg{}
	for _, r := range rows {
		key := r.Created.Format("2006-01-02")
		b := buckets[key]
		if b == nil {
			b = &agg{users: map[uint]struct{}{}}
			buckets[key] = b
		}
		b.count++
		b.balance += r.Amount
		if r.Amount >= 0 {
			b.countCharged++
			b.amountCharged += r.Amount
		} else {
			b.countSpent++
			b.amountSpent += r.Amount
		}
		if r.UserID != nil {
			b.users[*r.UserID] = struct{}{}
		}
	}

	for key, b := range buckets {
		entry, ok := entries[key]
		if !ok {
			continue
		}
		entry["transactions"] = b.count
		entry["distinctUsers"] = len(b.users)
		entry["balance"] = b.balance
		entry["charged"] = map[string]any{"amount": b.amountCharged, "transactions": b.countCharged}
		entry["spent"] = map[string]any{"amount": b.amountSpent * -1, "transactions": b.countSpent}
	}

	// Reverse: most recent day first.
	out := make([]map[string]any, 0, len(dates))
	for i := len(dates) - 1; i >= 0; i-- {
		out = append(out, entries[dates[i]])
	}
	return out
}

func (s *Server) userMetrics(c *gin.Context) {
	userID := c.Param("userId")
	user, err := s.findUserByIdentifier(userID)
	if err != nil {
		fail(c, err)
		return
	}
	if user == nil {
		fail(c, apierror.UserNotFound(userID))
		return
	}

	// Per-article aggregation.
	type artRow struct {
		ArticleID uint
		Cnt       int
		Amt       int
	}
	var artRows []artRow
	s.db.Model(&model.Transaction{}).
		Select("article_id, COUNT(id) as cnt, SUM(amount)*-1 as amt").
		Where("user_id = ? AND article_id IS NOT NULL", user.ID).
		Group("article_id").
		Order("cnt DESC").
		Scan(&artRows)

	articles := make([]map[string]any, 0, len(artRows))
	for _, ar := range artRows {
		a, err := s.loadArticle(ar.ArticleID)
		if err != nil {
			continue
		}
		articles = append(articles, map[string]any{
			"article": s.ser.Article(a, 0),
			"count":   ar.Cnt,
			"amount":  ar.Amt,
		})
	}

	var transactionCount int64
	s.db.Model(&model.Transaction{}).Where("user_id = ? AND deleted = ?", user.ID, false).Count(&transactionCount)

	outgoing := s.directionAggregate(user.ID, "recipient_transaction_id IS NOT NULL")
	incoming := s.directionAggregate(user.ID, "sender_transaction_id IS NOT NULL")

	c.JSON(http.StatusOK, gin.H{
		"balance":  user.Balance,
		"articles": articles,
		"transactions": gin.H{
			"count":    transactionCount,
			"outgoing": outgoing,
			"incoming": incoming,
		},
	})
}

func (s *Server) directionAggregate(userID uint, cond string) gin.H {
	var res struct {
		Cnt int
		Amt int
	}
	s.db.Model(&model.Transaction{}).
		Select("COUNT(id) as cnt, COALESCE(SUM(amount),0) as amt").
		Where("user_id = ? AND deleted = ?", userID, false).
		Where(cond).
		Scan(&res)
	return gin.H{"count": res.Cnt, "amount": res.Amt}
}
