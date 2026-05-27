package server

import (
	"net/http"
	"os"
	"path/filepath"

	"github.com/gin-gonic/gin"
)

// index serves the frontend index.html if a webroot is configured and the file
// exists; otherwise it reports that the front-end is missing, matching the PHP
// IndexController for an API-only deployment.
func (s *Server) index(c *gin.Context) {
	if s.cfg.Webroot != "" {
		indexPath := filepath.Join(s.cfg.Webroot, "index.html")
		if content, err := os.ReadFile(indexPath); err == nil {
			c.Data(http.StatusOK, "text/html; charset=utf-8", content)
			return
		}
	}
	c.String(http.StatusOK, "Front-End is missing!")
}
