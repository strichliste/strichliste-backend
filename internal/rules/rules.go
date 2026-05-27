// Package rules holds the pure business rules shared by handlers and
// serializers: user activity, transaction deletability, and boundary checks.
package rules

import (
	"time"

	"github.com/strichliste/strichliste-backend/internal/apierror"
	"github.com/strichliste/strichliste-backend/internal/model"
	"github.com/strichliste/strichliste-backend/internal/relativetime"
	"github.com/strichliste/strichliste-backend/internal/settings"
)

// StaleDateTime returns the cutoff before which a user is considered inactive,
// derived from the user.stalePeriod setting. The bool is false when no stale
// period is configured (in which case every user is active).
func StaleDateTime(s *settings.Settings, now time.Time) (time.Time, bool) {
	period := s.GetString("user.stalePeriod", "")
	if period == "" {
		return time.Time{}, false
	}
	cutoff, err := relativetime.Sub(now, period)
	if err != nil {
		return time.Time{}, false
	}
	return cutoff, true
}

// IsActive reports whether the user counts as active at the given time.
func IsActive(s *settings.Settings, u *model.User, now time.Time) bool {
	cutoff, ok := StaleDateTime(s, now)
	if !ok {
		return true
	}
	return u.Updated != nil && !u.Updated.Before(cutoff)
}

// IsDeletable reports whether a transaction may still be reverted.
func IsDeletable(s *settings.Settings, t *model.Transaction, now time.Time) bool {
	if t.Deleted {
		return false
	}
	if !s.GetBool("payment.undo.enabled", false) {
		return false
	}
	timeout := s.GetString("payment.undo.timeout", "")
	if timeout != "" {
		cutoff, err := relativetime.Sub(now, timeout)
		if err == nil && t.Created.Before(cutoff) {
			return false
		}
	}
	return true
}

// CheckTransactionBoundary validates a transaction amount against the
// payment.boundary settings. A zero amount is always invalid.
func CheckTransactionBoundary(s *settings.Settings, amount int) error {
	if amount == 0 {
		return apierror.TransactionInvalid("")
	}
	if upper, ok := s.GetIntOrNil("payment.boundary.upper"); ok && amount > upper {
		return apierror.TransactionBoundaryUpper(amount, upper)
	}
	if lower, ok := s.GetIntOrNil("payment.boundary.lower"); ok && amount < lower {
		return apierror.TransactionBoundaryLower(amount, lower)
	}
	return nil
}

// CheckAccountBalanceBoundary validates a user balance against the
// account.boundary settings.
func CheckAccountBalanceBoundary(s *settings.Settings, userID, balance int) error {
	if upper, ok := s.GetIntOrNil("account.boundary.upper"); ok && balance > upper {
		return apierror.AccountBalanceBoundaryUpper(balance, upper, userID)
	}
	if lower, ok := s.GetIntOrNil("account.boundary.lower"); ok && balance < lower {
		return apierror.AccountBalanceBoundaryLower(balance, lower, userID)
	}
	return nil
}
