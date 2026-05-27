package server

import (
	"encoding/json"
	"io"
	"strconv"
	"strings"

	"github.com/gin-gonic/gin"
)

// params reads request parameters from the JSON body (preferred, mirroring the
// PHP BeforeActionSubscriber which merges the JSON body into the request bag)
// with a fallback to form values.
type params struct {
	body map[string]any
	c    *gin.Context
}

// parseParams reads and parses the request body once.
func parseParams(c *gin.Context) (*params, error) {
	p := &params{c: c}
	ct := c.GetHeader("Content-Type")
	if strings.Contains(ct, "application/json") {
		raw, err := io.ReadAll(c.Request.Body)
		if err != nil {
			return nil, err
		}
		if len(raw) > 0 {
			if err := json.Unmarshal(raw, &p.body); err != nil {
				return nil, err
			}
		}
	}
	return p, nil
}

func (p *params) raw(key string) (any, bool) {
	if p.body != nil {
		if v, ok := p.body[key]; ok {
			return v, true
		}
	}
	if v, ok := p.c.GetPostForm(key); ok {
		return v, true
	}
	return nil, false
}

// String returns a string parameter, or "" if absent/null.
func (p *params) String(key string) string {
	v, ok := p.raw(key)
	if !ok || v == nil {
		return ""
	}
	if s, ok := v.(string); ok {
		return s
	}
	return ""
}

// StringPtr returns a pointer to the string parameter, or nil if absent/null.
func (p *params) StringPtr(key string) *string {
	v, ok := p.raw(key)
	if !ok || v == nil {
		return nil
	}
	if s, ok := v.(string); ok {
		return &s
	}
	return nil
}

// IntPtr returns a pointer to the int parameter, or nil if absent/null/unparsable.
func (p *params) IntPtr(key string) *int {
	v, ok := p.raw(key)
	if !ok || v == nil {
		return nil
	}
	switch n := v.(type) {
	case float64:
		i := int(n)
		return &i
	case int:
		return &n
	case string:
		if i, err := strconv.Atoi(strings.TrimSpace(n)); err == nil {
			return &i
		}
	}
	return nil
}

// BoolPtr returns a pointer to the bool parameter, or nil if absent/null.
func (p *params) BoolPtr(key string) *bool {
	v, ok := p.raw(key)
	if !ok || v == nil {
		return nil
	}
	switch b := v.(type) {
	case bool:
		return &b
	case string:
		switch strings.ToLower(b) {
		case "1", "true", "on", "yes":
			t := true
			return &t
		case "0", "false", "off", "no", "":
			f := false
			return &f
		}
	}
	return nil
}

// queryInt returns a query-string int with a default.
func queryInt(c *gin.Context, key string, def int) int {
	if v := c.Query(key); v != "" {
		if i, err := strconv.Atoi(v); err == nil {
			return i
		}
	}
	return def
}

// queryIntPtr returns a query-string int pointer, nil if absent/unparsable.
func queryIntPtr(c *gin.Context, key string) *int {
	if v := c.Query(key); v != "" {
		if i, err := strconv.Atoi(v); err == nil {
			return &i
		}
	}
	return nil
}

// queryBool parses a query value with PHP-getBoolean truthiness, using def when
// the parameter is absent.
func queryBool(c *gin.Context, key string, def bool) bool {
	v, ok := c.GetQuery(key)
	if !ok {
		return def
	}
	switch strings.ToLower(v) {
	case "1", "true", "on", "yes":
		return true
	default:
		return false
	}
}
