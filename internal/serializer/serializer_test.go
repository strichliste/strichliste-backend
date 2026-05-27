package serializer

import (
	"testing"
	"time"

	"github.com/strichliste/strichliste-backend/internal/model"
	"github.com/strichliste/strichliste-backend/internal/settings"
)

func newSer() *Serializer {
	return New(settings.New(map[string]any{
		"user":    map[string]any{"stalePeriod": "10 day"},
		"payment": map[string]any{"undo": map[string]any{"enabled": true}},
	}))
}

func TestArticlePrecursorDepth(t *testing.T) {
	s := newSer()
	created := time.Now()
	precursor := &model.Article{ID: 1, Name: "v1", Amount: 100, Active: false, Created: created}
	article := &model.Article{ID: 2, Name: "v2", Amount: 150, Active: true, Precursor: precursor, Created: created}

	// depth 1: precursor present, its own precursor null.
	out := s.Article(article, 1)
	pre, ok := out["precursor"].(map[string]any)
	if !ok {
		t.Fatalf("depth 1 should include precursor, got %v", out["precursor"])
	}
	if pre["precursor"] != nil {
		t.Errorf("nested precursor should be nil at depth 0")
	}

	// depth 0: no precursor.
	out0 := s.Article(article, 0)
	if out0["precursor"] != nil {
		t.Errorf("depth 0 precursor should be nil, got %v", out0["precursor"])
	}
}

func TestArticleEmbeddedTagShape(t *testing.T) {
	s := newSer()
	now := time.Now()
	tag := &model.Tag{ID: 7, Tag: "drinks", Created: now.Add(-time.Hour)}
	article := &model.Article{
		ID: 3, Name: "Cola", Amount: 200, Active: true, Created: now,
		ArticleTags: []model.ArticleTag{{ID: 99, Tag: tag, Created: now}},
	}
	out := s.Article(article, 1)
	tags := out["tags"].([]map[string]any)
	if len(tags) != 1 {
		t.Fatalf("expected 1 tag, got %d", len(tags))
	}
	// Embedded id is the Tag id (7), not the join-row id (99).
	if tags[0]["id"] != uint(7) {
		t.Errorf("embedded tag id = %v, want 7 (tag id)", tags[0]["id"])
	}
	if tags[0]["tag"] != "drinks" {
		t.Errorf("embedded tag = %v", tags[0]["tag"])
	}
	if _, hasUsage := tags[0]["usageCount"]; hasUsage {
		t.Error("embedded tag must not include usageCount")
	}
}

func TestArticleEmptyArrays(t *testing.T) {
	s := newSer()
	out := s.Article(&model.Article{ID: 1, Name: "x", Created: time.Now()}, 1)
	if _, ok := out["barcodes"].([]map[string]any); !ok {
		t.Errorf("barcodes should be an array, got %T", out["barcodes"])
	}
	if _, ok := out["tags"].([]map[string]any); !ok {
		t.Errorf("tags should be an array, got %T", out["tags"])
	}
}
