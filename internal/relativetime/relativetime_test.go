package relativetime

import (
	"testing"
	"time"
)

func TestSub(t *testing.T) {
	base := time.Date(2026, 5, 27, 12, 0, 0, 0, time.UTC)
	cases := []struct {
		spec string
		want time.Time
	}{
		{"10 day", base.AddDate(0, 0, -10)},
		{"5 minute", base.Add(-5 * time.Minute)},
		{"1 hour", base.Add(-time.Hour)},
		{"2 weeks", base.AddDate(0, 0, -14)},
		{"1 month", base.AddDate(0, -1, 0)},
		{"1 year", base.AddDate(-1, 0, 0)},
		{"30 seconds", base.Add(-30 * time.Second)},
	}
	for _, c := range cases {
		got, err := Sub(base, c.spec)
		if err != nil {
			t.Errorf("Sub(%q) error: %v", c.spec, err)
			continue
		}
		if !got.Equal(c.want) {
			t.Errorf("Sub(%q) = %v, want %v", c.spec, got, c.want)
		}
	}
}

func TestSubInvalid(t *testing.T) {
	base := time.Now()
	for _, spec := range []string{"", "abc", "10", "ten days", "5 lightyears"} {
		if _, err := Sub(base, spec); err == nil {
			t.Errorf("Sub(%q) expected error", spec)
		}
	}
}
