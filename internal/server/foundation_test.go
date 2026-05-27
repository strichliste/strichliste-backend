package server

import (
	"testing"
)

func TestSettingsEndpoint(t *testing.T) {
	engine, _ := newTestServer(t)

	code, body := doJSON(t, engine, "GET", "/api/settings", "")
	if code != 200 {
		t.Fatalf("status = %d, want 200", code)
	}
	if _, ok := body["settings"].(map[string]any); !ok {
		t.Fatalf("response missing settings object: %v", body)
	}
	if v := mustGet(t, body, "settings.payment.boundary.upper"); v.(float64) != 15000 {
		t.Errorf("boundary.upper = %v, want 15000", v)
	}
}

func TestSettingsCacheAndCorsHeaders(t *testing.T) {
	engine, _ := newTestServer(t)

	rec := rawRequest(engine, "GET", "/api/settings")
	if got := rec.Header().Get("Cache-Control"); got != "no-cache, max-age=0, must-revalidate, no-store" {
		t.Errorf("Cache-Control = %q", got)
	}
	if got := rec.Header().Get("Access-Control-Allow-Origin"); got != "*" {
		t.Errorf("Allow-Origin = %q", got)
	}
	if got := rec.Header().Get("Access-Control-Allow-Methods"); got != "POST, PUT, GET, DELETE" {
		t.Errorf("Allow-Methods = %q", got)
	}
}

func TestCorsPreflight(t *testing.T) {
	engine, _ := newTestServer(t)
	rec := rawRequest(engine, "OPTIONS", "/api/settings")
	if rec.Code != 204 {
		t.Errorf("OPTIONS status = %d, want 204", rec.Code)
	}
}

func TestIndexFrontEndMissing(t *testing.T) {
	engine, _ := newTestServer(t)
	rec := rawRequest(engine, "GET", "/")
	if rec.Code != 200 {
		t.Fatalf("status = %d, want 200", rec.Code)
	}
	if rec.Body.String() != "Front-End is missing!" {
		t.Errorf("body = %q, want 'Front-End is missing!'", rec.Body.String())
	}
}

func TestAutoMigrateCreatesTables(t *testing.T) {
	_, gdb := newTestServer(t)
	for _, tbl := range []string{"user", "article", "article_tag", "barcode", "tag", "transactions"} {
		if !gdb.Migrator().HasTable(tbl) {
			t.Errorf("table %q was not created", tbl)
		}
	}
}
