// Package model defines the GORM entities mirroring the PHP/Doctrine schema.
package model

import (
	"time"

	"gorm.io/gorm"
)

// User is an account on the tally sheet.
type User struct {
	ID       uint       `gorm:"primaryKey"`
	Name     string     `gorm:"type:varchar(64);uniqueIndex;not null"`
	Email    *string    `gorm:"type:varchar(255)"`
	Balance  int        `gorm:"not null;default:0"`
	Disabled bool       `gorm:"not null;default:false;index:disabled_updated"`
	Created  time.Time  `gorm:"not null"`
	Updated  *time.Time `gorm:"index:disabled_updated"`
}

// TableName matches the Doctrine table name (a reserved word, quoted by GORM).
func (User) TableName() string { return "user" }

// BeforeCreate sets created and updated to now (mirrors the PrePersist hook:
// both timestamps are populated on insert, so new users are immediately active).
func (u *User) BeforeCreate(*gorm.DB) error {
	now := time.Now()
	if u.Created.IsZero() {
		u.Created = now
	}
	if u.Updated == nil {
		u.Updated = &now
	}
	return nil
}

// BeforeUpdate bumps updated to now (mirrors the PreUpdate hook), so any change
// — including a balance change from a transaction — reactivates the user.
func (u *User) BeforeUpdate(*gorm.DB) error {
	now := time.Now()
	u.Updated = &now
	return nil
}

// AddBalance adjusts the balance by the (signed) amount in cents.
func (u *User) AddBalance(amount int) { u.Balance += amount }
