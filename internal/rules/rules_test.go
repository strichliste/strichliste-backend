package rules

import (
	"testing"
	"time"

	"github.com/strichliste/strichliste-backend/internal/apierror"
	"github.com/strichliste/strichliste-backend/internal/model"
	"github.com/strichliste/strichliste-backend/internal/settings"
)

func testSettings() *settings.Settings {
	return settings.New(map[string]any{
		"user":    map[string]any{"stalePeriod": "10 day"},
		"account": map[string]any{"boundary": map[string]any{"upper": 20000, "lower": -20000}},
		"payment": map[string]any{
			"undo":     map[string]any{"enabled": true, "delete": false, "timeout": "5 minute"},
			"boundary": map[string]any{"upper": 15000, "lower": -2000},
		},
	})
}

func TestIsActive(t *testing.T) {
	s := testSettings()
	now := time.Now()
	recent := now.Add(-time.Hour)
	old := now.AddDate(0, 0, -20)

	active := &model.User{Updated: &recent}
	if !IsActive(s, active, now) {
		t.Error("recently updated user should be active")
	}
	inactive := &model.User{Updated: &old}
	if IsActive(s, inactive, now) {
		t.Error("user updated 20 days ago should be inactive")
	}
	never := &model.User{Updated: nil}
	if IsActive(s, never, now) {
		t.Error("user with nil updated should be inactive")
	}

	// No stale period configured => always active.
	noStale := settings.New(map[string]any{})
	if !IsActive(noStale, never, now) {
		t.Error("with no stale period, user should always be active")
	}
}

func TestIsDeletable(t *testing.T) {
	s := testSettings()
	now := time.Now()

	fresh := &model.Transaction{Created: now.Add(-time.Minute)}
	if !IsDeletable(s, fresh, now) {
		t.Error("fresh transaction should be deletable")
	}
	stale := &model.Transaction{Created: now.Add(-10 * time.Minute)}
	if IsDeletable(s, stale, now) {
		t.Error("transaction older than timeout should not be deletable")
	}
	deleted := &model.Transaction{Created: now, Deleted: true}
	if IsDeletable(s, deleted, now) {
		t.Error("already-deleted transaction should not be deletable")
	}

	undoOff := settings.New(map[string]any{"payment": map[string]any{"undo": map[string]any{"enabled": false}}})
	if IsDeletable(undoOff, fresh, now) {
		t.Error("undo disabled => not deletable")
	}
}

func TestCheckTransactionBoundary(t *testing.T) {
	s := testSettings()
	if err := CheckTransactionBoundary(s, 0); err == nil {
		t.Error("zero amount should be invalid")
	}
	if err := CheckTransactionBoundary(s, 100); err != nil {
		t.Errorf("100 within bounds: %v", err)
	}
	if err := CheckTransactionBoundary(s, 20000); err == nil {
		t.Error("20000 exceeds upper boundary 15000")
	} else if e := err.(*apierror.APIError); e.Code != 400 {
		t.Errorf("code = %d, want 400", e.Code)
	}
	if err := CheckTransactionBoundary(s, -5000); err == nil {
		t.Error("-5000 below lower boundary -2000")
	}
}

func TestCheckAccountBalanceBoundary(t *testing.T) {
	s := testSettings()
	if err := CheckAccountBalanceBoundary(s, 1, 0); err != nil {
		t.Errorf("zero balance ok: %v", err)
	}
	if err := CheckAccountBalanceBoundary(s, 1, 25000); err == nil {
		t.Error("25000 exceeds upper account boundary 20000")
	}
	if err := CheckAccountBalanceBoundary(s, 1, -25000); err == nil {
		t.Error("-25000 below lower account boundary -20000")
	}
}
