package server

import (
	"fmt"
	"testing"

	"github.com/gin-gonic/gin"
)

func addBarcode(t *testing.T, engine *gin.Engine, articleID int, code string) map[string]any {
	t.Helper()
	status, body := doJSON(t, engine, "POST", fmt.Sprintf("/api/article/%d/barcode", articleID), fmt.Sprintf(`{"barcode":%q}`, code))
	if status != 200 {
		t.Fatalf("add barcode: status %d, body %v", status, body)
	}
	return body
}

func TestAddBarcodeReturnsArticle(t *testing.T) {
	engine, _ := newTestServer(t)
	id := createArticle(t, engine, "Beer", 250)
	body := addBarcode(t, engine, id, "111222")

	// Response is the article (not the barcode), with the barcode embedded.
	barcodes := mustGet(t, body, "article.barcodes").([]any)
	if len(barcodes) != 1 || barcodes[0].(map[string]any)["barcode"] != "111222" {
		t.Errorf("article should embed the new barcode, got %v", barcodes)
	}
}

func TestAddBarcodeEmpty(t *testing.T) {
	engine, _ := newTestServer(t)
	id := createArticle(t, engine, "Water", 50)
	code, body := doJSON(t, engine, "POST", fmt.Sprintf("/api/article/%d/barcode", id), `{"barcode":"  "}`)
	if code != 400 || mustGet(t, body, "error.message") != "Parameter 'barcode' is invalid" {
		t.Fatalf("status %d, body %v", code, body)
	}
}

func TestAddBarcodeDuplicate(t *testing.T) {
	engine, _ := newTestServer(t)
	id := createArticle(t, engine, "Juice", 120)
	addBarcode(t, engine, id, "555")
	code, body := doJSON(t, engine, "POST", fmt.Sprintf("/api/article/%d/barcode", id), `{"barcode":"555"}`)
	if code != 409 || mustGet(t, body, "error.class") != "ArticleBarcodeAlreadyExistsException" {
		t.Fatalf("status %d, body %v", code, body)
	}
}

func TestListAndGetBarcode(t *testing.T) {
	engine, _ := newTestServer(t)
	id := createArticle(t, engine, "Coffee", 300)
	body := addBarcode(t, engine, id, "777")
	barcodeID := int(mustGet(t, body, "article.barcodes").([]any)[0].(map[string]any)["id"].(float64))

	// List for article.
	code, listBody := doJSON(t, engine, "GET", fmt.Sprintf("/api/article/%d/barcode", id), "")
	if code != 200 || int(listBody["count"].(float64)) != 1 {
		t.Fatalf("list status %d, body %v", code, listBody)
	}

	// Get single barcode.
	code, getBody := doJSON(t, engine, "GET", fmt.Sprintf("/api/article/%d/barcode/%d", id, barcodeID), "")
	if code != 200 || mustGet(t, getBody, "barcode.barcode") != "777" {
		t.Fatalf("get status %d, body %v", code, getBody)
	}

	// Global barcode list.
	code, allBody := doJSON(t, engine, "GET", "/api/barcode", "")
	if code != 200 || int(allBody["count"].(float64)) != 1 {
		t.Fatalf("global list status %d, body %v", code, allBody)
	}
}

func TestGetBarcodeArticleMismatch(t *testing.T) {
	engine, _ := newTestServer(t)
	id1 := createArticle(t, engine, "A1", 100)
	id2 := createArticle(t, engine, "A2", 100)
	body := addBarcode(t, engine, id1, "888")
	barcodeID := int(mustGet(t, body, "article.barcodes").([]any)[0].(map[string]any)["id"].(float64))

	// Requesting the barcode under the wrong article => 404.
	code, getBody := doJSON(t, engine, "GET", fmt.Sprintf("/api/article/%d/barcode/%d", id2, barcodeID), "")
	if code != 404 || mustGet(t, getBody, "error.class") != "BarcodeNotFoundException" {
		t.Fatalf("status %d, body %v", code, getBody)
	}
}

func TestDeleteBarcode(t *testing.T) {
	engine, _ := newTestServer(t)
	id := createArticle(t, engine, "Tea", 90)
	body := addBarcode(t, engine, id, "999")
	barcodeID := int(mustGet(t, body, "article.barcodes").([]any)[0].(map[string]any)["id"].(float64))

	code, delBody := doJSON(t, engine, "DELETE", fmt.Sprintf("/api/article/%d/barcode/%d", id, barcodeID), "")
	if code != 200 {
		t.Fatalf("delete status %d, body %v", code, delBody)
	}
	// Article returned, now without barcodes.
	if len(mustGet(t, delBody, "article.barcodes").([]any)) != 0 {
		t.Error("barcode should be removed from article")
	}
}
