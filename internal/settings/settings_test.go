package settings

import (
	"os"
	"path/filepath"
	"testing"
)

const sampleYAML = `parameters:
    strichliste:
        article:
            enabled: true
            autoOpen: false
        user:
            stalePeriod: '10 day'
        payment:
            undo:
                enabled: true
                timeout: '5 minute'
            boundary:
                upper: 15000
                lower: -2000
            deposit:
                steps:
                    - 50
                    - 100
`

func loadSample(t *testing.T) *Settings {
	t.Helper()
	dir := t.TempDir()
	path := filepath.Join(dir, "strichliste.yaml")
	if err := os.WriteFile(path, []byte(sampleYAML), 0o644); err != nil {
		t.Fatal(err)
	}
	s, err := Load(path)
	if err != nil {
		t.Fatalf("Load: %v", err)
	}
	return s
}

func TestLoadExtractsTree(t *testing.T) {
	s := loadSample(t)
	if _, ok := s.All()["article"]; !ok {
		t.Fatalf("expected 'article' key in tree, got %v", s.All())
	}
}

func TestGetBool(t *testing.T) {
	s := loadSample(t)
	if !s.GetBool("payment.undo.enabled", false) {
		t.Error("payment.undo.enabled should be true")
	}
	if s.GetBool("article.autoOpen", true) {
		t.Error("article.autoOpen should be false")
	}
	if !s.GetBool("missing.key", true) {
		t.Error("missing key should return default")
	}
}

func TestGetString(t *testing.T) {
	s := loadSample(t)
	if got := s.GetString("user.stalePeriod", ""); got != "10 day" {
		t.Errorf("stalePeriod = %q, want '10 day'", got)
	}
	if got := s.GetString("payment.undo.timeout", ""); got != "5 minute" {
		t.Errorf("timeout = %q, want '5 minute'", got)
	}
}

func TestGetIntOrNil(t *testing.T) {
	s := loadSample(t)
	if v, ok := s.GetIntOrNil("payment.boundary.upper"); !ok || v != 15000 {
		t.Errorf("boundary.upper = %d,%v want 15000,true", v, ok)
	}
	if v, ok := s.GetIntOrNil("payment.boundary.lower"); !ok || v != -2000 {
		t.Errorf("boundary.lower = %d,%v want -2000,true", v, ok)
	}
	if _, ok := s.GetIntOrNil("payment.boundary.missing"); ok {
		t.Error("missing boundary should report not-found")
	}
}

func TestNestedTypePreserved(t *testing.T) {
	s := loadSample(t)
	steps, ok := s.Get("payment.deposit.steps")
	if !ok {
		t.Fatal("steps not found")
	}
	list, ok := steps.([]any)
	if !ok || len(list) != 2 {
		t.Fatalf("steps = %v (%T), want 2-element list", steps, steps)
	}
}

func TestLoadMissingFile(t *testing.T) {
	if _, err := Load("/nonexistent/strichliste.yaml"); err == nil {
		t.Error("expected error for missing file")
	}
}
