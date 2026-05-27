// Package relativetime parses PHP DateInterval::createFromDateString-style
// relative date strings (e.g. "10 day", "5 minute", "1 week 2 days") and
// subtracts them from a base time, matching the PHP settings semantics.
package relativetime

import (
	"fmt"
	"strconv"
	"strings"
	"time"
)

// Sub subtracts the relative period described by spec from base. For example
// Sub(now, "10 day") returns now-10*24h. Month/year units are calendar-aware.
func Sub(base time.Time, spec string) (time.Time, error) {
	fields := strings.Fields(strings.ToLower(strings.TrimSpace(spec)))
	if len(fields) == 0 || len(fields)%2 != 0 {
		return base, fmt.Errorf("invalid relative time spec %q", spec)
	}

	result := base
	for i := 0; i < len(fields); i += 2 {
		n, err := strconv.Atoi(fields[i])
		if err != nil {
			return base, fmt.Errorf("invalid magnitude %q in %q", fields[i], spec)
		}
		unit := strings.TrimSuffix(fields[i+1], "s") // normalize plural
		switch unit {
		case "second", "sec":
			result = result.Add(-time.Duration(n) * time.Second)
		case "minute", "min":
			result = result.Add(-time.Duration(n) * time.Minute)
		case "hour":
			result = result.Add(-time.Duration(n) * time.Hour)
		case "day":
			result = result.AddDate(0, 0, -n)
		case "week":
			result = result.AddDate(0, 0, -7*n)
		case "month":
			result = result.AddDate(0, -n, 0)
		case "year":
			result = result.AddDate(-n, 0, 0)
		default:
			return base, fmt.Errorf("unknown unit %q in %q", fields[i+1], spec)
		}
	}
	return result, nil
}
