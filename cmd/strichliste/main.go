// Command strichliste is the single-binary backend for the strichliste tally sheet.
package main

import (
	"log"

	"github.com/strichliste/strichliste-backend/internal/config"
	"github.com/strichliste/strichliste-backend/internal/db"
	"github.com/strichliste/strichliste-backend/internal/server"
	"github.com/strichliste/strichliste-backend/internal/settings"
)

func main() {
	cfg, err := config.Load()
	if err != nil {
		log.Fatalf("config: %v", err)
	}

	s, err := settings.Load(cfg.SettingsFile)
	if err != nil {
		log.Fatalf("settings: %v", err)
	}

	gdb, err := db.Open(cfg.DatabaseURL)
	if err != nil {
		log.Fatalf("database: %v", err)
	}

	if err := db.Migrate(gdb); err != nil {
		log.Fatalf("migrate: %v", err)
	}

	srv := server.New(gdb, s, cfg)
	log.Printf("strichliste listening on %s", cfg.ListenAddr)
	if err := srv.Engine().Run(cfg.ListenAddr); err != nil {
		log.Fatalf("server: %v", err)
	}
}
