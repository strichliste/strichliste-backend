package server

import (
	"errors"
	"net/http"
	"strings"

	"github.com/gin-gonic/gin"
	"github.com/strichliste/strichliste-backend/internal/apierror"
	"github.com/strichliste/strichliste-backend/internal/model"
	"gorm.io/gorm"
)

func (s *Server) registerArticles(api *gin.RouterGroup) {
	api.GET("/article", s.listArticles)
	api.POST("/article", s.createArticle)
	api.GET("/article/search", s.searchArticles)
	api.GET("/article/:articleId", s.getArticle)
	api.POST("/article/:articleId", s.updateArticle)
	api.DELETE("/article/:articleId", s.deleteArticle)
}

// articlePreloads loads the associations needed to serialize an article,
// including one level of precursor with its own barcodes/tags.
func articlePreloads(q *gorm.DB) *gorm.DB {
	return q.
		Preload("Barcodes").
		Preload("ArticleTags.Tag").
		Preload("Precursor.Barcodes").
		Preload("Precursor.ArticleTags.Tag").
		Preload("Precursor.Precursor")
}

func (s *Server) loadArticle(id uint) (*model.Article, error) {
	var a model.Article
	if err := articlePreloads(s.db).First(&a, id).Error; err != nil {
		return nil, err
	}
	return &a, nil
}

func (s *Server) countActiveArticles() int64 {
	var n int64
	s.db.Model(&model.Article{}).Where("active = ?", true).Count(&n)
	return n
}

func (s *Server) listArticles(c *gin.Context) {
	limit := queryInt(c, "limit", 25)
	offset := queryIntPtr(c, "offset")
	active := queryBool(c, "active", true)
	barcode := strings.TrimSpace(c.Query("barcode"))
	precursor := queryBool(c, "precursor", true)
	ancestor := c.Query("ancestor")

	q := s.db.Model(&model.Article{}).Where("article.active = ?", active)
	if barcode != "" {
		q = q.Joins("JOIN barcode b ON b.article_id = article.id").Where("b.barcode = ?", barcode)
	}
	if !precursor {
		q = q.Where("article.precursor_id IS NULL")
	}
	switch ancestor {
	case "true":
		q = q.Where("EXISTS (SELECT 1 FROM article a2 WHERE a2.precursor_id = article.id)")
	case "false":
		q = q.Where("NOT EXISTS (SELECT 1 FROM article a2 WHERE a2.precursor_id = article.id)")
	}

	q = articlePreloads(q).Group("article.id").Order("article.name ASC").Limit(limit)
	if offset != nil {
		q = q.Offset(*offset)
	}

	var articles []model.Article
	if err := q.Find(&articles).Error; err != nil {
		fail(c, err)
		return
	}

	c.JSON(http.StatusOK, gin.H{
		"count":    s.countActiveArticles(),
		"articles": s.serializeArticles(articles, 1),
	})
}

func (s *Server) searchArticles(c *gin.Context) {
	query := c.Query("query")
	limit := queryInt(c, "limit", 25)
	barcode := strings.TrimSpace(c.Query("barcode"))
	tag := strings.TrimSpace(c.Query("tag"))

	q := s.db.Model(&model.Article{}).
		Joins("LEFT JOIN barcode b ON b.article_id = article.id").
		Joins("LEFT JOIN article_tag at ON at.article_id = article.id").
		Joins("LEFT JOIN tag t ON at.tag_id = t.id")

	useQuery := query != ""
	switch {
	case tag != "": // tag overrides barcode (PHP ->where replacement semantics)
		useQuery = false
		q = q.Where("t.tag = ?", tag)
	case barcode != "":
		useQuery = false
		q = q.Where("b.barcode = ?", barcode)
	}
	if useQuery {
		q = q.Where("b.barcode = ? OR t.tag = ? OR article.name LIKE ?", query, query, "%"+query+"%")
	}

	q = articlePreloads(q).Where("article.active = ?", true).
		Group("article.id").Order("article.name").Limit(limit)

	var articles []model.Article
	if err := q.Find(&articles).Error; err != nil {
		fail(c, err)
		return
	}

	c.JSON(http.StatusOK, gin.H{
		"count":    len(articles),
		"articles": s.serializeArticles(articles, 1),
	})
}

func (s *Server) getArticle(c *gin.Context) {
	id, ok := parseUintParam(c, "articleId")
	if !ok {
		fail(c, apierror.ArticleNotFound(c.Param("articleId")))
		return
	}
	depth := queryInt(c, "depth", 1)
	a, err := s.loadArticle(id)
	if errors.Is(err, gorm.ErrRecordNotFound) {
		fail(c, apierror.ArticleNotFound(c.Param("articleId")))
		return
	}
	if err != nil {
		fail(c, err)
		return
	}
	c.JSON(http.StatusOK, gin.H{"article": s.ser.Article(a, depth)})
}

func (s *Server) createArticle(c *gin.Context) {
	article, err := s.articleFromRequest(c)
	if err != nil {
		fail(c, err)
		return
	}
	if err := s.db.Create(article).Error; err != nil {
		fail(c, err)
		return
	}
	a, _ := s.loadArticle(article.ID)
	c.JSON(http.StatusOK, gin.H{"article": s.ser.Article(a, 1)})
}

// articleFromRequest validates and builds an article from the request body,
// mirroring ArticleService::createArticleByRequest.
func (s *Server) articleFromRequest(c *gin.Context) (*model.Article, error) {
	p, err := parseParams(c)
	if err != nil {
		return nil, apierror.ParameterInvalid("body")
	}
	name := p.String("name")
	if name == "" {
		return nil, apierror.ParameterMissing("name")
	}
	amount := p.IntPtr("amount")
	if amount == nil || *amount == 0 {
		return nil, apierror.ParameterMissing("amount")
	}
	return &model.Article{Name: strings.TrimSpace(name), Amount: *amount, Active: true}, nil
}

func (s *Server) updateArticle(c *gin.Context) {
	id, ok := parseUintParam(c, "articleId")
	if !ok {
		fail(c, apierror.ArticleNotFound(c.Param("articleId")))
		return
	}
	var article model.Article
	if err := s.db.First(&article, id).Error; err != nil {
		fail(c, apierror.ArticleNotFound(c.Param("articleId")))
		return
	}
	if !article.Active {
		fail(c, apierror.ArticleInactive(article.Name, int(article.ID)))
		return
	}

	candidate, err := s.articleFromRequest(c)
	if err != nil {
		fail(c, err)
		return
	}

	var refCount int64
	s.db.Model(&model.Transaction{}).Where("article_id = ?", article.ID).Count(&refCount)

	var resultID uint
	if refCount == 0 {
		// Not used yet: update in place.
		article.Name = candidate.Name
		article.Amount = candidate.Amount
		if err := s.db.Save(&article).Error; err != nil {
			fail(c, err)
			return
		}
		resultID = article.ID
	} else {
		// Used: create a new version preserving the old one.
		err := s.db.Transaction(func(tx *gorm.DB) error {
			newArticle := &model.Article{
				Name:        candidate.Name,
				Amount:      candidate.Amount,
				Active:      true,
				UsageCount:  article.UsageCount,
				PrecursorID: &article.ID,
			}
			if err := tx.Create(newArticle).Error; err != nil {
				return err
			}
			if err := tx.Model(&model.Barcode{}).Where("article_id = ?", article.ID).
				Update("article_id", newArticle.ID).Error; err != nil {
				return err
			}
			if err := tx.Model(&model.ArticleTag{}).Where("article_id = ?", article.ID).
				Update("article_id", newArticle.ID).Error; err != nil {
				return err
			}
			article.Active = false
			if err := tx.Save(&article).Error; err != nil {
				return err
			}
			resultID = newArticle.ID
			return nil
		})
		if err != nil {
			fail(c, err)
			return
		}
	}

	a, _ := s.loadArticle(resultID)
	c.JSON(http.StatusOK, gin.H{"article": s.ser.Article(a, 1)})
}

func (s *Server) deleteArticle(c *gin.Context) {
	id, ok := parseUintParam(c, "articleId")
	if !ok {
		fail(c, apierror.ArticleNotFound(c.Param("articleId")))
		return
	}
	var article model.Article
	if err := s.db.First(&article, id).Error; err != nil {
		fail(c, apierror.ArticleNotFound(c.Param("articleId")))
		return
	}

	err := s.db.Transaction(func(tx *gorm.DB) error {
		if err := tx.Where("article_id = ?", article.ID).Delete(&model.Barcode{}).Error; err != nil {
			return err
		}
		article.Active = false
		return tx.Save(&article).Error
	})
	if err != nil {
		fail(c, err)
		return
	}

	a, _ := s.loadArticle(article.ID)
	c.JSON(http.StatusOK, gin.H{"article": s.ser.Article(a, 1)})
}

func (s *Server) serializeArticles(articles []model.Article, depth int) []map[string]any {
	out := make([]map[string]any, 0, len(articles))
	for i := range articles {
		out = append(out, s.ser.Article(&articles[i], depth))
	}
	return out
}
