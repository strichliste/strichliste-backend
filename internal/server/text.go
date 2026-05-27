package server

import (
	"regexp"
	"strings"
	"unicode"
)

// controlChars matches the ASCII control range stripped by the PHP user
// name sanitizer (\x00-\x1F and \x7F).
var controlChars = regexp.MustCompile(`[\x00-\x1f\x7f]`)

// sanitizeName trims whitespace and strips control characters, mirroring the
// PHP createUser/updateUser sanitization.
func sanitizeName(name string) string {
	return controlChars.ReplaceAllString(strings.TrimSpace(name), "")
}

// emailRegex is a pragmatic approximation of PHP FILTER_VALIDATE_EMAIL.
var emailRegex = regexp.MustCompile(`^[^@\s]+@[^@\s]+\.[^@\s]+$`)

func validEmail(email string) bool {
	return emailRegex.MatchString(email)
}

// natCaseLess reports whether a sorts before b using case-insensitive natural
// ordering (digit runs compared numerically), matching PHP strnatcasecmp.
func natCaseLess(a, b string) bool {
	return natCompare(strings.ToLower(a), strings.ToLower(b)) < 0
}

func natCompare(a, b string) int {
	ar, br := []rune(a), []rune(b)
	i, j := 0, 0
	for i < len(ar) && j < len(br) {
		if unicode.IsDigit(ar[i]) && unicode.IsDigit(br[j]) {
			// Compare two digit runs numerically.
			si, sj := i, j
			for i < len(ar) && unicode.IsDigit(ar[i]) {
				i++
			}
			for j < len(br) && unicode.IsDigit(br[j]) {
				j++
			}
			na := strings.TrimLeft(string(ar[si:i]), "0")
			nb := strings.TrimLeft(string(br[sj:j]), "0")
			if len(na) != len(nb) {
				if len(na) < len(nb) {
					return -1
				}
				return 1
			}
			if c := strings.Compare(na, nb); c != 0 {
				return c
			}
			continue
		}
		if ar[i] != br[j] {
			if ar[i] < br[j] {
				return -1
			}
			return 1
		}
		i++
		j++
	}
	switch {
	case len(ar)-i < len(br)-j:
		return -1
	case len(ar)-i > len(br)-j:
		return 1
	default:
		return 0
	}
}
