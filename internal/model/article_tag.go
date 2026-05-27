package model

import (
	"time"

	"gorm.io/gorm"
)

// ArticleTag is the join row linking an article to a tag (many-to-many).
type ArticleTag struct {
	ID        uint      `gorm:"primaryKey"`
	ArticleID uint      `gorm:"not null"`
	Article   *Article  `gorm:"foreignKey:ArticleID"`
	TagID     uint      `gorm:"not null"`
	Tag       *Tag      `gorm:"foreignKey:TagID"`
	Created   time.Time `gorm:"not null"`
}

// TableName matches the Doctrine table name.
func (ArticleTag) TableName() string { return "article_tag" }

// BeforeCreate sets the created timestamp if unset.
func (at *ArticleTag) BeforeCreate(*gorm.DB) error {
	if at.Created.IsZero() {
		at.Created = time.Now()
	}
	return nil
}
