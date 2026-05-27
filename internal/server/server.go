// Package server wires the Gin engine, middleware, and HTTP handlers.
package server

import (
	"net/http"

	"github.com/gin-gonic/gin"
	"github.com/strichliste/strichliste-backend/internal/apierror"
	"github.com/strichliste/strichliste-backend/internal/config"
	"github.com/strichliste/strichliste-backend/internal/serializer"
	"github.com/strichliste/strichliste-backend/internal/settings"
	"gorm.io/gorm"
)

// Server holds the dependencies shared by all handlers.
type Server struct {
	db       *gorm.DB
	settings *settings.Settings
	cfg      *config.Config
	ser      *serializer.Serializer
}

// New builds a Server and its configured Gin engine.
func New(db *gorm.DB, s *settings.Settings, cfg *config.Config) *Server {
	return &Server{db: db, settings: s, cfg: cfg, ser: serializer.New(s)}
}

// Engine constructs the Gin engine with middleware and routes registered.
func (s *Server) Engine() *gin.Engine {
	gin.SetMode(gin.ReleaseMode)
	r := gin.New()
	r.Use(gin.Recovery())
	r.Use(cacheControl())
	r.Use(cors())

	r.GET("/", s.index)

	api := r.Group("/api")
	s.registerSettings(api)
	s.registerUsers(api)
	s.registerTransactions(api)
	s.registerArticles(api)
	s.registerBarcodes(api)
	s.registerTags(api)
	s.registerMetrics(api)

	return r
}

// fail writes an error using the standard envelope. APIErrors map to their
// status; anything else becomes a 500.
func fail(c *gin.Context, err error) {
	if apiErr, ok := err.(*apierror.APIError); ok {
		c.JSON(apiErr.Code, gin.H{"error": apiErr})
		c.Abort()
		return
	}
	c.JSON(http.StatusInternalServerError, gin.H{"error": gin.H{
		"class":   "InternalServerError",
		"code":    http.StatusInternalServerError,
		"message": err.Error(),
	}})
	c.Abort()
}
