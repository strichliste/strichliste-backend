package config

import "testing"

func TestLoadRequiresDatabaseURL(t *testing.T) {
	t.Setenv("DATABASE_URL", "")
	if _, err := Load(); err == nil {
		t.Fatal("expected error when DATABASE_URL is unset")
	}
}

func TestLoadDefaults(t *testing.T) {
	t.Setenv("DATABASE_URL", "postgres://localhost/db")
	t.Setenv("LISTEN_ADDR", "")
	t.Setenv("STRICHLISTE_SETTINGS_FILE", "")
	cfg, err := Load()
	if err != nil {
		t.Fatal(err)
	}
	if cfg.ListenAddr != ":8080" {
		t.Errorf("ListenAddr = %q, want :8080", cfg.ListenAddr)
	}
	if cfg.SettingsFile != "config/strichliste.yaml" {
		t.Errorf("SettingsFile = %q, want config/strichliste.yaml", cfg.SettingsFile)
	}
}

func TestLoadOverrides(t *testing.T) {
	t.Setenv("DATABASE_URL", "postgres://localhost/db")
	t.Setenv("LISTEN_ADDR", ":9999")
	t.Setenv("STRICHLISTE_SETTINGS_FILE", "/etc/strichliste.yaml")
	cfg, err := Load()
	if err != nil {
		t.Fatal(err)
	}
	if cfg.ListenAddr != ":9999" || cfg.SettingsFile != "/etc/strichliste.yaml" {
		t.Errorf("overrides not applied: %+v", cfg)
	}
}
