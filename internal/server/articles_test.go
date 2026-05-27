package server

import (
	"fmt"
	"testing"

	"github.com/gin-gonic/gin"
)

func createArticle(t *testing.T, engine *gin.Engine, name string, amount int) int {
	t.Helper()
	code, body := doJSON(t, engine, "POST", "/api/article", fmt.Sprintf(`{"name":%q,"amount":%d}`, name, amount))
	if code != 200 {
		t.Fatalf("create article %q: status %d, body %v", name, code, body)
	}
	return int(mustGet(t, body, "article.id").(float64))
}

func TestCreateArticle(t *testing.T) {
	engine, _ := newTestServer(t)
	code, body := doJSON(t, engine, "POST", "/api/article", `{"name":"Cola","amount":150}`)
	if code != 200 {
		t.Fatalf("status %d, body %v", code, body)
	}
	if mustGet(t, body, "article.name") != "Cola" || mustGet(t, body, "article.amount").(float64) != 150 {
		t.Errorf("unexpected article %v", body["article"])
	}
	if mustGet(t, body, "article.isActive") != true {
		t.Error("new article should be active")
	}
}

func TestCreateArticleMissingFields(t *testing.T) {
	engine, _ := newTestServer(t)
	code, body := doJSON(t, engine, "POST", "/api/article", `{"amount":150}`)
	if code != 400 || mustGet(t, body, "error.message") != "Parameter 'name' is missing" {
		t.Fatalf("missing name: status %d, body %v", code, body)
	}
	code, body = doJSON(t, engine, "POST", "/api/article", `{"name":"X"}`)
	if code != 400 || mustGet(t, body, "error.message") != "Parameter 'amount' is missing" {
		t.Fatalf("missing amount: status %d, body %v", code, body)
	}
}

func TestGetArticleNotFound(t *testing.T) {
	engine, _ := newTestServer(t)
	code, body := doJSON(t, engine, "GET", "/api/article/999", "")
	if code != 404 || mustGet(t, body, "error.message") != "Article '999' not found" {
		t.Fatalf("status %d, body %v", code, body)
	}
}

func TestListArticlesActiveCount(t *testing.T) {
	engine, _ := newTestServer(t)
	createArticle(t, engine, "Apple", 100)
	createArticle(t, engine, "Banana", 200)

	code, body := doJSON(t, engine, "GET", "/api/article", "")
	if code != 200 {
		t.Fatalf("status %d", code)
	}
	if int(body["count"].(float64)) != 2 {
		t.Errorf("count = %v, want 2", body["count"])
	}
	arts := body["articles"].([]any)
	// ordered by name asc
	if arts[0].(map[string]any)["name"] != "Apple" {
		t.Errorf("first article = %v, want Apple", arts[0])
	}
}

func TestUpdateArticleInPlace(t *testing.T) {
	engine, _ := newTestServer(t)
	id := createArticle(t, engine, "Old", 100)
	code, body := doJSON(t, engine, "POST", fmt.Sprintf("/api/article/%d", id), `{"name":"New","amount":120}`)
	if code != 200 {
		t.Fatalf("status %d, body %v", code, body)
	}
	// unused article updates in place: same id.
	if int(mustGet(t, body, "article.id").(float64)) != id {
		t.Errorf("expected same id for in-place update")
	}
	if mustGet(t, body, "article.name") != "New" {
		t.Errorf("name = %v", mustGet(t, body, "article.name"))
	}
}

func TestUpdateArticleVersioning(t *testing.T) {
	engine, _ := newTestServer(t)
	userID := createUser(t, engine, "Buyer")
	articleID := createArticle(t, engine, "Snack", 100)

	// Purchase to give the article a transaction reference.
	code, _ := doJSON(t, engine, "POST", fmt.Sprintf("/api/user/%d/transaction", userID),
		fmt.Sprintf(`{"articleId":%d}`, articleID))
	if code != 200 {
		t.Fatalf("purchase status %d", code)
	}

	// Update now should create a new version (new id) with precursor set.
	code, body := doJSON(t, engine, "POST", fmt.Sprintf("/api/article/%d", articleID), `{"name":"Snack v2","amount":120}`)
	if code != 200 {
		t.Fatalf("update status %d, body %v", code, body)
	}
	newID := int(mustGet(t, body, "article.id").(float64))
	if newID == articleID {
		t.Error("versioned update should produce a new article id")
	}
	if mustGet(t, body, "article.precursor") == nil {
		t.Error("new version should reference its precursor")
	}
	// usageCount carried forward.
	if mustGet(t, body, "article.usageCount").(float64) != 1 {
		t.Errorf("usageCount = %v, want 1", mustGet(t, body, "article.usageCount"))
	}

	// Old article is now inactive.
	_, oldBody := doJSON(t, engine, "GET", fmt.Sprintf("/api/article/%d", articleID), "")
	if mustGet(t, oldBody, "article.isActive") != false {
		t.Error("old article should be inactive after versioning")
	}
}

func TestDeleteArticleSoft(t *testing.T) {
	engine, _ := newTestServer(t)
	id := createArticle(t, engine, "Doomed", 100)
	code, body := doJSON(t, engine, "DELETE", fmt.Sprintf("/api/article/%d", id), "")
	if code != 200 {
		t.Fatalf("status %d, body %v", code, body)
	}
	if mustGet(t, body, "article.isActive") != false {
		t.Error("deleted article should be inactive")
	}
	// Still retrievable (soft delete).
	code, _ = doJSON(t, engine, "GET", fmt.Sprintf("/api/article/%d", id), "")
	if code != 200 {
		t.Errorf("soft-deleted article should still be retrievable, status %d", code)
	}
}

func TestSearchArticles(t *testing.T) {
	engine, _ := newTestServer(t)
	createArticle(t, engine, "Mango", 100)
	createArticle(t, engine, "Mandarin", 100)
	createArticle(t, engine, "Pear", 100)

	code, body := doJSON(t, engine, "GET", "/api/article/search?query=Man", "")
	if code != 200 {
		t.Fatalf("status %d", code)
	}
	if int(body["count"].(float64)) != 2 {
		t.Errorf("count = %v, want 2", body["count"])
	}
}
