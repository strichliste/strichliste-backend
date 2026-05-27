// Package db opens the PostgreSQL connection and runs schema auto-migration.
package db

import (
	"fmt"
	"log"
	"os"
	"time"

	"github.com/strichliste/strichliste-backend/internal/model"
	"gorm.io/driver/postgres"
	"gorm.io/gorm"
	"gorm.io/gorm/logger"
)

// AllModels lists every entity managed by auto-migrate.
var AllModels = []any{
	&model.User{},
	&model.Article{},
	&model.Tag{},
	&model.ArticleTag{},
	&model.Barcode{},
	&model.Transaction{},
}

// Open connects to PostgreSQL using the given connection URL.
func Open(databaseURL string) (*gorm.DB, error) {
	gdb, err := gorm.Open(postgres.Open(databaseURL), &gorm.Config{
		Logger: logger.New(log.New(os.Stderr, "\r\n", log.LstdFlags), logger.Config{
			SlowThreshold:             200 * time.Millisecond,
			LogLevel:                  logger.Warn,
			IgnoreRecordNotFoundError: true,
		}),
	})
	if err != nil {
		return nil, fmt.Errorf("connecting to database: %w", err)
	}
	return gdb, nil
}

// Migrate creates/updates the schema for all entities. It is idempotent.
func Migrate(gdb *gorm.DB) error {
	if err := gdb.AutoMigrate(AllModels...); err != nil {
		return fmt.Errorf("auto-migrate: %w", err)
	}
	return nil
}
