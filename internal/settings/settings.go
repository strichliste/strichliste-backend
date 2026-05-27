// Package settings loads the strichliste settings tree (config/strichliste.yaml)
// and exposes dot-path lookups equivalent to the PHP SettingsService.
package settings

import (
	"fmt"
	"os"
	"strings"

	"gopkg.in/yaml.v3"
)

// Settings holds the strichliste settings tree (the parameters.strichliste
// subtree of the YAML file).
type Settings struct {
	tree map[string]any
}

// Load reads the YAML file and extracts the parameters.strichliste subtree.
func Load(path string) (*Settings, error) {
	raw, err := os.ReadFile(path)
	if err != nil {
		return nil, fmt.Errorf("reading settings file %q: %w", path, err)
	}

	var doc map[string]any
	if err := yaml.Unmarshal(raw, &doc); err != nil {
		return nil, fmt.Errorf("parsing settings file %q: %w", path, err)
	}

	tree := extractStrichliste(doc)
	if tree == nil {
		return nil, fmt.Errorf("settings file %q has no parameters.strichliste tree", path)
	}

	return &Settings{tree: tree}, nil
}

// New builds Settings directly from a tree (useful for tests).
func New(tree map[string]any) *Settings {
	return &Settings{tree: tree}
}

func extractStrichliste(doc map[string]any) map[string]any {
	params, ok := doc["parameters"].(map[string]any)
	if !ok {
		return nil
	}
	tree, ok := params["strichliste"].(map[string]any)
	if !ok {
		return nil
	}
	return tree
}

// All returns the full settings tree (served at GET /api/settings).
func (s *Settings) All() map[string]any {
	return s.tree
}

// Get looks up a dot-separated path (e.g. "payment.undo.enabled") and returns
// the value and whether it was found.
func (s *Settings) Get(path string) (any, bool) {
	parts := strings.Split(path, ".")
	var cur any = s.tree
	for _, part := range parts {
		m, ok := cur.(map[string]any)
		if !ok {
			return nil, false
		}
		v, ok := m[part]
		if !ok {
			return nil, false
		}
		cur = v
	}
	return cur, true
}

// GetString returns the string at path, or def if missing/wrong type.
func (s *Settings) GetString(path, def string) string {
	if v, ok := s.Get(path); ok {
		if str, ok := v.(string); ok {
			return str
		}
	}
	return def
}

// GetBool returns the bool at path, or def if missing/wrong type.
func (s *Settings) GetBool(path string, def bool) bool {
	if v, ok := s.Get(path); ok {
		if b, ok := v.(bool); ok {
			return b
		}
	}
	return def
}

// GetIntOrNil returns the int at path. The second return is false when the
// value is missing (mirrors the PHP `getOrDefault(path, false)` boundary
// pattern where an absent boundary disables the check).
func (s *Settings) GetIntOrNil(path string) (int, bool) {
	v, ok := s.Get(path)
	if !ok {
		return 0, false
	}
	switch n := v.(type) {
	case int:
		return n, true
	case int64:
		return int(n), true
	case float64:
		return int(n), true
	}
	return 0, false
}
