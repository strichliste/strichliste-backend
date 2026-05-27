package server

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"os"
	"strings"
	"testing"

	"github.com/gin-gonic/gin"
	"github.com/strichliste/strichliste-backend/internal/config"
	"github.com/strichliste/strichliste-backend/internal/db"
	"github.com/strichliste/strichliste-backend/internal/settings"
	"gorm.io/gorm"
)

// testSettings mirrors the defaults in config/strichliste.yaml that the business
// logic depends on.
func testSettings() *settings.Settings {
	return settings.New(map[string]any{
		"article": map[string]any{"enabled": true, "autoOpen": false},
		"user":    map[string]any{"stalePeriod": "10 day"},
		"account": map[string]any{"boundary": map[string]any{"upper": 20000, "lower": -20000}},
		"payment": map[string]any{
			"undo":     map[string]any{"enabled": true, "delete": false, "timeout": "5 minute"},
			"boundary": map[string]any{"upper": 15000, "lower": -2000},
		},
	})
}

// newTestServer connects to TEST_DATABASE_URL, migrates, truncates all tables,
// and returns a ready engine. It skips the test if no DB is configured.
func newTestServer(t *testing.T) (*gin.Engine, *gorm.DB) {
	t.Helper()
	url := os.Getenv("TEST_DATABASE_URL")
	if url == "" {
		t.Skip("TEST_DATABASE_URL not set; skipping integration test")
	}
	gdb, err := db.Open(url)
	if err != nil {
		t.Fatalf("open db: %v", err)
	}
	if err := db.Migrate(gdb); err != nil {
		t.Fatalf("migrate: %v", err)
	}
	truncateAll(t, gdb)

	srv := New(gdb, testSettings(), &config.Config{})
	return srv.Engine(), gdb
}

func truncateAll(t *testing.T, gdb *gorm.DB) {
	t.Helper()
	// Order-independent truncate with cascade; quote "user" (reserved word).
	if err := gdb.Exec(`TRUNCATE TABLE transactions, article_tag, barcode, tag, article, "user" RESTART IDENTITY CASCADE`).Error; err != nil {
		t.Fatalf("truncate: %v", err)
	}
}

// doJSON performs a request and decodes the JSON response body.
func doJSON(t *testing.T, engine *gin.Engine, method, path, body string) (int, map[string]any) {
	t.Helper()
	var reqBody *strings.Reader
	if body != "" {
		reqBody = strings.NewReader(body)
	} else {
		reqBody = strings.NewReader("")
	}
	req := httptest.NewRequest(method, path, reqBody)
	if body != "" {
		req.Header.Set("Content-Type", "application/json")
	}
	rec := httptest.NewRecorder()
	engine.ServeHTTP(rec, req)

	var out map[string]any
	if rec.Body.Len() > 0 {
		if err := json.Unmarshal(rec.Body.Bytes(), &out); err != nil {
			// Non-JSON body (e.g. index route); return raw under "_raw".
			out = map[string]any{"_raw": rec.Body.String()}
		}
	}
	return rec.Code, out
}

func rawRequest(engine *gin.Engine, method, path string) *httptest.ResponseRecorder {
	req := httptest.NewRequest(method, path, nil)
	rec := httptest.NewRecorder()
	engine.ServeHTTP(rec, req)
	return rec
}

// mustGet fetches a nested value via dotted path for assertions.
func mustGet(t *testing.T, m map[string]any, path string) any {
	t.Helper()
	parts := strings.Split(path, ".")
	var cur any = m
	for _, p := range parts {
		mp, ok := cur.(map[string]any)
		if !ok {
			t.Fatalf("path %q: %q is not an object", path, p)
		}
		cur = mp[p]
	}
	return cur
}

var _ = http.StatusOK
