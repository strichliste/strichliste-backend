package server

import (
	"net/http"
	"os"
	"path/filepath"
	"strings"

	"github.com/gin-gonic/gin"
)

// index serves the frontend index.html if a webroot is configured and the file
// exists; otherwise it reports that the front-end is missing, matching the PHP
// IndexController for an API-only deployment.
func (s *Server) index(c *gin.Context) {
	if s.cfg.Webroot != "" {
		if content, err := os.ReadFile(filepath.Join(s.cfg.Webroot, "index.html")); err == nil {
			c.Data(http.StatusOK, "text/html; charset=utf-8", content)
			return
		}
	}
	c.String(http.StatusOK, "Front-End is missing!")
}

// serveStatic serves static frontend assets from the configured webroot. Real
// files are served directly; any other (non-API) path falls back to index.html
// so client-side SPA routing works. Registered as the NoRoute handler only when
// a webroot is configured.
func (s *Server) serveStatic(c *gin.Context) {
	reqPath := c.Request.URL.Path

	// Never hijack API routes — return the standard not-found envelope.
	if strings.HasPrefix(reqPath, "/api") {
		c.JSON(http.StatusNotFound, gin.H{"error": gin.H{
			"class":   "NotFoundException",
			"code":    http.StatusNotFound,
			"message": "Not Found",
		}})
		return
	}

	webroot, err := filepath.Abs(s.cfg.Webroot)
	if err != nil {
		c.String(http.StatusOK, "Front-End is missing!")
		return
	}

	// Resolve the requested file, guarding against path traversal.
	target := filepath.Join(webroot, filepath.Clean("/"+reqPath))
	if rel, err := filepath.Rel(webroot, target); err == nil && !strings.HasPrefix(rel, "..") {
		if info, err := os.Stat(target); err == nil && !info.IsDir() {
			c.File(target)
			return
		}
	}

	// SPA fallback: serve index.html for unknown client-side routes.
	indexPath := filepath.Join(webroot, "index.html")
	if _, err := os.Stat(indexPath); err == nil {
		c.File(indexPath)
		return
	}
	c.String(http.StatusOK, "Front-End is missing!")
}
