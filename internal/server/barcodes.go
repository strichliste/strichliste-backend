package server

import (
	"net/http"
	"strings"

	"github.com/gin-gonic/gin"
	"github.com/strichliste/strichliste-backend/internal/apierror"
	"github.com/strichliste/strichliste-backend/internal/model"
)

func (s *Server) registerBarcodes(api *gin.RouterGroup) {
	api.GET("/barcode", s.listBarcodes)
	api.GET("/article/:articleId/barcode", s.listArticleBarcodes)
	api.GET("/article/:articleId/barcode/:barcodeId", s.getArticleBarcode)
	api.POST("/article/:articleId/barcode", s.addArticleBarcode)
	api.DELETE("/article/:articleId/barcode/:barcodeId", s.deleteArticleBarcode)
}

func (s *Server) serializeBarcodes(barcodes []model.Barcode) []map[string]any {
	out := make([]map[string]any, 0, len(barcodes))
	for i := range barcodes {
		out = append(out, s.ser.Barcode(&barcodes[i]))
	}
	return out
}

func (s *Server) listBarcodes(c *gin.Context) {
	var barcodes []model.Barcode
	if err := s.db.Order("created DESC").Find(&barcodes).Error; err != nil {
		fail(c, err)
		return
	}
	c.JSON(http.StatusOK, gin.H{"count": len(barcodes), "barcodes": s.serializeBarcodes(barcodes)})
}

func (s *Server) listArticleBarcodes(c *gin.Context) {
	articleID, ok := parseUintParam(c, "articleId")
	if !ok || !s.articleExists(articleID) {
		fail(c, apierror.ArticleNotFound(c.Param("articleId")))
		return
	}
	var barcodes []model.Barcode
	if err := s.db.Where("article_id = ?", articleID).Find(&barcodes).Error; err != nil {
		fail(c, err)
		return
	}
	c.JSON(http.StatusOK, gin.H{"count": len(barcodes), "barcodes": s.serializeBarcodes(barcodes)})
}

func (s *Server) getArticleBarcode(c *gin.Context) {
	articleID, ok := parseUintParam(c, "articleId")
	if !ok || !s.articleExists(articleID) {
		fail(c, apierror.ArticleNotFound(c.Param("articleId")))
		return
	}
	barcodeID, _ := parseUintParam(c, "barcodeId")
	var barcode model.Barcode
	if err := s.db.First(&barcode, barcodeID).Error; err != nil || barcode.ArticleID != articleID {
		fail(c, apierror.BarcodeNotFound(int(barcodeID)))
		return
	}
	c.JSON(http.StatusOK, gin.H{"barcode": s.ser.Barcode(&barcode)})
}

func (s *Server) addArticleBarcode(c *gin.Context) {
	p, err := parseParams(c)
	if err != nil {
		fail(c, apierror.ParameterInvalid("body"))
		return
	}
	code := strings.TrimSpace(p.String("barcode"))
	if code == "" {
		fail(c, apierror.ParameterInvalid("barcode"))
		return
	}

	articleID, ok := parseUintParam(c, "articleId")
	if !ok {
		fail(c, apierror.ArticleNotFound(c.Param("articleId")))
		return
	}
	var article model.Article
	if err := s.db.First(&article, articleID).Error; err != nil {
		fail(c, apierror.ArticleNotFound(c.Param("articleId")))
		return
	}
	if !article.Active {
		fail(c, apierror.ArticleInactive(article.Name, int(article.ID)))
		return
	}

	var existing model.Barcode
	if err := s.db.Preload("Article").Where("barcode = ?", code).First(&existing).Error; err == nil {
		name := ""
		aid := 0
		if existing.Article != nil {
			name = existing.Article.Name
			aid = int(existing.Article.ID)
		}
		fail(c, apierror.ArticleBarcodeAlreadyExists(name, aid, existing.Barcode))
		return
	}

	newBarcode := model.Barcode{Barcode: code, ArticleID: article.ID}
	if err := s.db.Create(&newBarcode).Error; err != nil {
		fail(c, err)
		return
	}

	a, _ := s.loadArticle(article.ID)
	c.JSON(http.StatusOK, gin.H{"article": s.ser.Article(a, 1)})
}

func (s *Server) deleteArticleBarcode(c *gin.Context) {
	articleID, ok := parseUintParam(c, "articleId")
	if !ok {
		fail(c, apierror.ArticleNotFound(c.Param("articleId")))
		return
	}
	var article model.Article
	if err := s.db.First(&article, articleID).Error; err != nil {
		fail(c, apierror.ArticleNotFound(c.Param("articleId")))
		return
	}

	barcodeID, _ := parseUintParam(c, "barcodeId")
	var barcode model.Barcode
	if err := s.db.First(&barcode, barcodeID).Error; err != nil || barcode.ArticleID != articleID {
		fail(c, apierror.BarcodeNotFound(int(barcodeID)))
		return
	}

	if err := s.db.Delete(&model.Barcode{}, barcode.ID).Error; err != nil {
		fail(c, err)
		return
	}

	a, _ := s.loadArticle(article.ID)
	c.JSON(http.StatusOK, gin.H{"article": s.ser.Article(a, 1)})
}

func (s *Server) articleExists(id uint) bool {
	var count int64
	s.db.Model(&model.Article{}).Where("id = ?", id).Count(&count)
	return count > 0
}
