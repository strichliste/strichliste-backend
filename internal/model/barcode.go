package model

import (
	"time"

	"gorm.io/gorm"
)

// Barcode is a scannable code attached to an article.
type Barcode struct {
	ID        uint      `gorm:"primaryKey"`
	Barcode   string    `gorm:"type:varchar(32);not null"`
	ArticleID uint      `gorm:"not null"`
	Article   *Article  `gorm:"foreignKey:ArticleID"`
	Created   time.Time `gorm:"not null"`
}

// TableName matches the Doctrine table name.
func (Barcode) TableName() string { return "barcode" }

// BeforeCreate sets the created timestamp if unset.
func (b *Barcode) BeforeCreate(*gorm.DB) error {
	if b.Created.IsZero() {
		b.Created = time.Now()
	}
	return nil
}
