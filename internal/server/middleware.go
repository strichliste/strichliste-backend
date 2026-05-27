package server

import (
	"net/http"

	"github.com/gin-gonic/gin"
)

// cacheControl mirrors the PHP ApiResponseSubscriber: every response is marked
// non-cacheable.
func cacheControl() gin.HandlerFunc {
	return func(c *gin.Context) {
		c.Header("Cache-Control", "no-cache, max-age=0, must-revalidate, no-store")
		c.Next()
	}
}

// cors mirrors the nelmio_cors config for the /api path group: permissive
// origin/headers, a fixed method set, and a 1-hour preflight cache.
func cors() gin.HandlerFunc {
	return func(c *gin.Context) {
		c.Header("Access-Control-Allow-Origin", "*")
		c.Header("Access-Control-Allow-Headers", "*")
		c.Header("Access-Control-Allow-Methods", "POST, PUT, GET, DELETE")
		c.Header("Access-Control-Max-Age", "3600")

		if c.Request.Method == http.MethodOptions {
			c.AbortWithStatus(http.StatusNoContent)
			return
		}
		c.Next()
	}
}
