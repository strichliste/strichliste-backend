package model

import (
	"time"

	"gorm.io/gorm"
)

// Tag is a label that can be attached to articles via ArticleTag rows.
type Tag struct {
	ID          uint         `gorm:"primaryKey"`
	Tag         string       `gorm:"type:varchar(255);not null;default:''"`
	ArticleTags []ArticleTag `gorm:"foreignKey:TagID"`
	Created     time.Time    `gorm:"not null"`
}

// TableName matches the Doctrine table name.
func (Tag) TableName() string { return "tag" }

// BeforeCreate sets the created timestamp if unset.
func (t *Tag) BeforeCreate(*gorm.DB) error {
	if t.Created.IsZero() {
		t.Created = time.Now()
	}
	return nil
}
