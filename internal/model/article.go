package model

import (
	"time"

	"gorm.io/gorm"
)

// Article is a purchasable item. Articles are versioned: editing a used article
// creates a new row whose Precursor points at the old (now inactive) one.
type Article struct {
	ID          uint   `gorm:"primaryKey"`
	Name        string `gorm:"type:varchar(255);not null"`
	Amount      int    `gorm:"not null"`
	Active      bool   `gorm:"not null;default:true"`
	UsageCount  int    `gorm:"not null;default:0"`
	PrecursorID *uint
	Precursor   *Article  `gorm:"foreignKey:PrecursorID"`
	Barcodes    []Barcode `gorm:"foreignKey:ArticleID"`
	ArticleTags []ArticleTag `gorm:"foreignKey:ArticleID"`
	Created     time.Time `gorm:"not null"`
}

// TableName matches the Doctrine table name.
func (Article) TableName() string { return "article" }

// BeforeCreate sets the created timestamp if unset.
func (a *Article) BeforeCreate(*gorm.DB) error {
	if a.Created.IsZero() {
		a.Created = time.Now()
	}
	return nil
}
