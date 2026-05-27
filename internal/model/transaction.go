package model

import (
	"time"

	"gorm.io/gorm"
)

// Transaction is a single balance movement on a user account. Transfers create
// two linked rows (sender + recipient) referencing each other.
type Transaction struct {
	ID                     uint `gorm:"primaryKey"`
	UserID                 *uint
	User                   *User `gorm:"foreignKey:UserID"`
	Quantity               *int
	ArticleID              *uint
	Article                *Article `gorm:"foreignKey:ArticleID"`
	RecipientTransactionID *uint
	RecipientTransaction   *Transaction `gorm:"foreignKey:RecipientTransactionID"`
	SenderTransactionID    *uint
	SenderTransaction      *Transaction `gorm:"foreignKey:SenderTransactionID"`
	Comment                *string      `gorm:"type:varchar(255)"`
	Amount                 int          `gorm:"not null"`
	Deleted                bool         `gorm:"not null;default:false"`
	Created                time.Time    `gorm:"not null"`
}

// TableName matches the Doctrine table name.
func (Transaction) TableName() string { return "transactions" }

// BeforeCreate sets the created timestamp if unset.
func (t *Transaction) BeforeCreate(*gorm.DB) error {
	if t.Created.IsZero() {
		t.Created = time.Now()
	}
	return nil
}
