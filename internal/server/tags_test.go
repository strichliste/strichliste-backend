package server

import (
	"fmt"
	"testing"

	"github.com/gin-gonic/gin"
)

func addTag(t *testing.T, engine *gin.Engine, articleID int, tag string) map[string]any {
	t.Helper()
	status, body := doJSON(t, engine, "POST", fmt.Sprintf("/api/article/%d/tag", articleID), fmt.Sprintf(`{"tag":%q}`, tag))
	if status != 200 {
		t.Fatalf("add tag: status %d, body %v", status, body)
	}
	return body
}

func TestAddTagReturnsArticle(t *testing.T) {
	engine, _ := newTestServer(t)
	id := createArticle(t, engine, "Cola", 150)
	body := addTag(t, engine, id, "drinks")
	tags := mustGet(t, body, "article.tags").([]any)
	if len(tags) != 1 || tags[0].(map[string]any)["tag"] != "drinks" {
		t.Errorf("article should embed the new tag, got %v", tags)
	}
}

func TestAddTagEmpty(t *testing.T) {
	engine, _ := newTestServer(t)
	id := createArticle(t, engine, "X", 100)
	code, body := doJSON(t, engine, "POST", fmt.Sprintf("/api/article/%d/tag", id), `{"tag":"   "}`)
	if code != 400 || mustGet(t, body, "error.message") != "Parameter 'tag' is invalid" {
		t.Fatalf("status %d, body %v", code, body)
	}
}

func TestAddTagDuplicateOnArticle(t *testing.T) {
	engine, _ := newTestServer(t)
	id := createArticle(t, engine, "Beer", 250)
	addTag(t, engine, id, "alcohol")
	code, body := doJSON(t, engine, "POST", fmt.Sprintf("/api/article/%d/tag", id), `{"tag":"alcohol"}`)
	if code != 409 || mustGet(t, body, "error.class") != "ArticleTagAlreadyExistsException" {
		t.Fatalf("status %d, body %v", code, body)
	}
}

func TestTagReuseAcrossArticles(t *testing.T) {
	engine, _ := newTestServer(t)
	a1 := createArticle(t, engine, "Wine", 400)
	a2 := createArticle(t, engine, "Whisky", 800)
	addTag(t, engine, a1, "alcohol")
	addTag(t, engine, a2, "alcohol")

	// Only one tag exists, used twice.
	code, body := doJSON(t, engine, "GET", "/api/tag", "")
	if code != 200 {
		t.Fatalf("status %d", code)
	}
	if int(body["count"].(float64)) != 1 {
		t.Fatalf("count = %v, want 1 (reused tag)", body["count"])
	}
	tags := body["tags"].([]any)
	if tags[0].(map[string]any)["usageCount"].(float64) != 2 {
		t.Errorf("usageCount = %v, want 2", tags[0].(map[string]any)["usageCount"])
	}
}

func TestTagSortByUsage(t *testing.T) {
	engine, _ := newTestServer(t)
	a1 := createArticle(t, engine, "A1", 100)
	a2 := createArticle(t, engine, "A2", 100)
	// "popular" used twice, "rare" once.
	addTag(t, engine, a1, "rare")
	addTag(t, engine, a1, "popular")
	addTag(t, engine, a2, "popular")

	_, body := doJSON(t, engine, "GET", "/api/tag", "")
	tags := body["tags"].([]any)
	if tags[0].(map[string]any)["tag"] != "popular" {
		t.Errorf("first tag = %v, want 'popular' (highest usage)", tags[0])
	}
}

func TestListArticleTags(t *testing.T) {
	engine, _ := newTestServer(t)
	id := createArticle(t, engine, "Snack", 120)
	addTag(t, engine, id, "food")

	code, body := doJSON(t, engine, "GET", fmt.Sprintf("/api/article/%d/tag", id), "")
	if code != 200 || int(body["count"].(float64)) != 1 {
		t.Fatalf("status %d, body %v", code, body)
	}
	// standalone tag shape includes usageCount.
	tag := body["tags"].([]any)[0].(map[string]any)
	if _, ok := tag["usageCount"]; !ok {
		t.Error("standalone tag should include usageCount")
	}
}

func TestGetArticleTagNotFound(t *testing.T) {
	engine, _ := newTestServer(t)
	id := createArticle(t, engine, "Item", 100)
	code, body := doJSON(t, engine, "GET", fmt.Sprintf("/api/article/%d/tag/999", id), "")
	if code != 404 || mustGet(t, body, "error.class") != "TagNotFoundException" {
		t.Fatalf("status %d, body %v", code, body)
	}
}

func TestDeleteTagOrphanCleanup(t *testing.T) {
	engine, _ := newTestServer(t)
	a1 := createArticle(t, engine, "Solo", 100)
	a2 := createArticle(t, engine, "Shared", 100)
	addTag(t, engine, a1, "lonely")
	addTag(t, engine, a1, "common")
	addTag(t, engine, a2, "common")

	// Find tag ids.
	_, tagBody := doJSON(t, engine, "GET", "/api/tag", "")
	tagIDs := map[string]int{}
	for _, tg := range tagBody["tags"].([]any) {
		m := tg.(map[string]any)
		tagIDs[m["tag"].(string)] = int(m["id"].(float64))
	}

	// Delete "lonely" from a1 => orphan, tag removed.
	code, _ := doJSON(t, engine, "DELETE", fmt.Sprintf("/api/article/%d/tag/%d", a1, tagIDs["lonely"]), "")
	if code != 200 {
		t.Fatalf("delete lonely status %d", code)
	}
	// Delete "common" from a1 => still used by a2, tag survives.
	code, _ = doJSON(t, engine, "DELETE", fmt.Sprintf("/api/article/%d/tag/%d", a1, tagIDs["common"]), "")
	if code != 200 {
		t.Fatalf("delete common status %d", code)
	}

	_, body := doJSON(t, engine, "GET", "/api/tag", "")
	remaining := map[string]float64{}
	for _, tg := range body["tags"].([]any) {
		m := tg.(map[string]any)
		remaining[m["tag"].(string)] = m["usageCount"].(float64)
	}
	if _, exists := remaining["lonely"]; exists {
		t.Error("'lonely' tag should be deleted as an orphan")
	}
	if remaining["common"] != 1 {
		t.Errorf("'common' should survive with usageCount 1, got %v", remaining["common"])
	}
}
