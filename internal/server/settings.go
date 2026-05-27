package server

import (
	"net/http"

	"github.com/gin-gonic/gin"
)

func (s *Server) registerSettings(api *gin.RouterGroup) {
	api.GET("/settings", s.listSettings)
}

// listSettings returns the full settings tree wrapped as {"settings": {...}}.
func (s *Server) listSettings(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{"settings": s.settings.All()})
}
