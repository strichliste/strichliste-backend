package server

import (
	"net/http"
	"sort"
	"strings"

	"github.com/gin-gonic/gin"
	"github.com/strichliste/strichliste-backend/internal/apierror"
	"github.com/strichliste/strichliste-backend/internal/model"
	"gorm.io/gorm"
)

func (s *Server) registerTags(api *gin.RouterGroup) {
	api.GET("/tag", s.listTags)
	api.GET("/article/:articleId/tag", s.listArticleTags)
	api.GET("/article/:articleId/tag/:tagId", s.getArticleTag)
	api.POST("/article/:articleId/tag", s.addArticleTag)
	api.DELETE("/article/:articleId/tag/:tagId", s.deleteArticleTag)
}

func (s *Server) serializeTags(tags []model.Tag) []map[string]any {
	out := make([]map[string]any, 0, len(tags))
	for i := range tags {
		out = append(out, s.ser.Tag(&tags[i]))
	}
	return out
}

func (s *Server) listTags(c *gin.Context) {
	var tags []model.Tag
	if err := s.db.Preload("ArticleTags").Find(&tags).Error; err != nil {
		fail(c, err)
		return
	}
	// Sort by usageCount DESC, then created DESC.
	sort.SliceStable(tags, func(i, j int) bool {
		ui, uj := len(tags[i].ArticleTags), len(tags[j].ArticleTags)
		if ui != uj {
			return ui > uj
		}
		return tags[i].Created.After(tags[j].Created)
	})
	c.JSON(http.StatusOK, gin.H{"count": len(tags), "tags": s.serializeTags(tags)})
}

func (s *Server) listArticleTags(c *gin.Context) {
	articleID, ok := parseUintParam(c, "articleId")
	if !ok || !s.articleExists(articleID) {
		fail(c, apierror.ArticleNotFound(c.Param("articleId")))
		return
	}

	var articleTags []model.ArticleTag
	if err := s.db.Preload("Tag.ArticleTags").Where("article_id = ?", articleID).Find(&articleTags).Error; err != nil {
		fail(c, err)
		return
	}

	out := make([]map[string]any, 0, len(articleTags))
	for i := range articleTags {
		if articleTags[i].Tag != nil {
			out = append(out, s.ser.Tag(articleTags[i].Tag))
		}
	}
	c.JSON(http.StatusOK, gin.H{"count": len(out), "tags": out})
}

func (s *Server) getArticleTag(c *gin.Context) {
	articleID, ok := parseUintParam(c, "articleId")
	if !ok || !s.articleExists(articleID) {
		fail(c, apierror.ArticleNotFound(c.Param("articleId")))
		return
	}
	tagID, _ := parseUintParam(c, "tagId")

	var articleTag model.ArticleTag
	err := s.db.Preload("Tag.ArticleTags").
		Where("article_id = ? AND tag_id = ?", articleID, tagID).First(&articleTag).Error
	if err != nil || articleTag.Tag == nil {
		fail(c, apierror.TagNotFound(int(tagID)))
		return
	}
	c.JSON(http.StatusOK, gin.H{"tag": s.ser.Tag(articleTag.Tag)})
}

func (s *Server) addArticleTag(c *gin.Context) {
	p, err := parseParams(c)
	if err != nil {
		fail(c, apierror.ParameterInvalid("body"))
		return
	}
	tagText := strings.TrimSpace(p.String("tag"))
	if tagText == "" {
		fail(c, apierror.ParameterInvalid("tag"))
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

	// Resolve the tag: reuse an existing one or create a new one.
	var tag model.Tag
	existing := s.db.Where("tag = ?", tagText).First(&tag).Error == nil
	if existing {
		var link int64
		s.db.Model(&model.ArticleTag{}).Where("article_id = ? AND tag_id = ?", article.ID, tag.ID).Count(&link)
		if link > 0 {
			fail(c, apierror.ArticleTagAlreadyExists(article.Name, int(article.ID), tag.Tag))
			return
		}
	} else {
		tag = model.Tag{Tag: tagText}
		if err := s.db.Create(&tag).Error; err != nil {
			fail(c, err)
			return
		}
	}

	link := model.ArticleTag{ArticleID: article.ID, TagID: tag.ID}
	if err := s.db.Create(&link).Error; err != nil {
		fail(c, err)
		return
	}

	a, _ := s.loadArticle(article.ID)
	c.JSON(http.StatusOK, gin.H{"article": s.ser.Article(a, 1)})
}

func (s *Server) deleteArticleTag(c *gin.Context) {
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
	tagID, _ := parseUintParam(c, "tagId")

	var articleTag model.ArticleTag
	if err := s.db.Where("article_id = ? AND tag_id = ?", articleID, tagID).First(&articleTag).Error; err != nil {
		fail(c, apierror.TagNotFound(int(tagID)))
		return
	}

	err := s.db.Transaction(func(tx *gorm.DB) error {
		if err := tx.Delete(&model.ArticleTag{}, articleTag.ID).Error; err != nil {
			return err
		}
		// Orphan cleanup: if this was the tag's only usage, delete the tag.
		var usage int64
		if err := tx.Model(&model.ArticleTag{}).Where("tag_id = ?", tagID).Count(&usage).Error; err != nil {
			return err
		}
		if usage == 0 {
			if err := tx.Delete(&model.Tag{}, tagID).Error; err != nil {
				return err
			}
		}
		return nil
	})
	if err != nil {
		fail(c, err)
		return
	}

	a, _ := s.loadArticle(article.ID)
	c.JSON(http.StatusOK, gin.H{"article": s.ser.Article(a, 1)})
}
