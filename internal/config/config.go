// Package config loads runtime configuration from environment variables.
package config

import (
	"fmt"
	"os"
)

// Config holds the runtime configuration for the server.
type Config struct {
	// DatabaseURL is a PostgreSQL connection URL (postgres://user:pass@host:port/db?sslmode=...).
	DatabaseURL string
	// ListenAddr is the address the HTTP server binds to (e.g. ":8080").
	ListenAddr string
	// SettingsFile is the path to the strichliste.yaml settings file.
	SettingsFile string
	// Webroot is an optional directory containing index.html served at "/".
	Webroot string
}

// Load reads configuration from the environment, applying defaults. It returns
// an error if a required value (DATABASE_URL) is missing.
func Load() (*Config, error) {
	dbURL := os.Getenv("DATABASE_URL")
	if dbURL == "" {
		return nil, fmt.Errorf("DATABASE_URL is required")
	}

	return &Config{
		DatabaseURL:  dbURL,
		ListenAddr:   getEnv("LISTEN_ADDR", ":8080"),
		SettingsFile: getEnv("STRICHLISTE_SETTINGS_FILE", "config/strichliste.yaml"),
		Webroot:      os.Getenv("WEBROOT"),
	}, nil
}

func getEnv(key, fallback string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return fallback
}
