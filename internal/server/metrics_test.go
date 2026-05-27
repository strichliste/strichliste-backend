package server

import (
	"fmt"
	"testing"
)

func TestGlobalMetrics(t *testing.T) {
	engine, _ := newTestServer(t)
	u1 := createUser(t, engine, "M1")
	createUser(t, engine, "M2")
	doJSON(t, engine, "POST", fmt.Sprintf("/api/user/%d/transaction", u1), `{"amount":500}`)
	doJSON(t, engine, "POST", fmt.Sprintf("/api/user/%d/transaction", u1), `{"amount":-200}`)

	code, body := doJSON(t, engine, "GET", "/api/metrics", "")
	if code != 200 {
		t.Fatalf("status %d, body %v", code, body)
	}
	if body["balance"].(float64) != 300 {
		t.Errorf("balance = %v, want 300", body["balance"])
	}
	if body["transactionCount"].(float64) != 2 {
		t.Errorf("transactionCount = %v, want 2", body["transactionCount"])
	}
	if body["userCount"].(float64) != 2 {
		t.Errorf("userCount = %v, want 2", body["userCount"])
	}
	if _, ok := body["days"].([]any); !ok {
		t.Errorf("days should be an array")
	}
}

func TestMetricsDaysQuirk(t *testing.T) {
	engine, _ := newTestServer(t)
	u := createUser(t, engine, "DayUser")
	doJSON(t, engine, "POST", fmt.Sprintf("/api/user/%d/transaction", u), `{"amount":100}`)

	_, body := doJSON(t, engine, "GET", "/api/metrics?days=7", "")
	days := body["days"].([]any)
	// Most recent day is first; today has a transaction => charged is an object.
	today := days[0].(map[string]any)
	if _, ok := today["charged"].(map[string]any); !ok {
		t.Errorf("active day 'charged' should be an object, got %T", today["charged"])
	}
	// An earlier empty day keeps the scalar zero.
	empty := days[len(days)-1].(map[string]any)
	if empty["charged"] != float64(0) {
		t.Errorf("empty day 'charged' should be scalar 0, got %v (%T)", empty["charged"], empty["charged"])
	}
}

func TestUserMetrics(t *testing.T) {
	engine, _ := newTestServer(t)
	buyer := createUser(t, engine, "Buyer2")
	articleID := createArticle(t, engine, "Cookie", 100)

	// Two purchases of the article.
	doJSON(t, engine, "POST", fmt.Sprintf("/api/user/%d/transaction", buyer), fmt.Sprintf(`{"articleId":%d,"quantity":1}`, articleID))
	doJSON(t, engine, "POST", fmt.Sprintf("/api/user/%d/transaction", buyer), fmt.Sprintf(`{"articleId":%d,"quantity":1}`, articleID))

	code, body := doJSON(t, engine, "GET", fmt.Sprintf("/api/user/%d/metrics", buyer), "")
	if code != 200 {
		t.Fatalf("status %d, body %v", code, body)
	}
	if body["balance"].(float64) != -200 {
		t.Errorf("balance = %v, want -200", body["balance"])
	}
	arts := body["articles"].([]any)
	if len(arts) != 1 {
		t.Fatalf("expected 1 aggregated article, got %d", len(arts))
	}
	first := arts[0].(map[string]any)
	if first["count"].(float64) != 2 {
		t.Errorf("article count = %v, want 2", first["count"])
	}
	if first["amount"].(float64) != 200 {
		t.Errorf("article amount = %v, want 200 (sign-flipped)", first["amount"])
	}
	if mustGet(t, body, "transactions.count").(float64) != 2 {
		t.Errorf("transactions.count = %v, want 2", mustGet(t, body, "transactions.count"))
	}
}

func TestUserMetricsTransfers(t *testing.T) {
	engine, _ := newTestServer(t)
	sender := createUser(t, engine, "TSender")
	recipient := createUser(t, engine, "TRecipient")

	doJSON(t, engine, "POST", fmt.Sprintf("/api/user/%d/transaction", sender),
		fmt.Sprintf(`{"amount":-300,"recipientId":%d}`, recipient))

	_, sBody := doJSON(t, engine, "GET", fmt.Sprintf("/api/user/%d/metrics", sender), "")
	if mustGet(t, sBody, "transactions.outgoing.count").(float64) != 1 {
		t.Errorf("sender outgoing count = %v, want 1", mustGet(t, sBody, "transactions.outgoing.count"))
	}

	_, rBody := doJSON(t, engine, "GET", fmt.Sprintf("/api/user/%d/metrics", recipient), "")
	if mustGet(t, rBody, "transactions.incoming.count").(float64) != 1 {
		t.Errorf("recipient incoming count = %v, want 1", mustGet(t, rBody, "transactions.incoming.count"))
	}
}

func TestUserMetricsNotFound(t *testing.T) {
	engine, _ := newTestServer(t)
	code, body := doJSON(t, engine, "GET", "/api/user/999/metrics", "")
	if code != 404 || mustGet(t, body, "error.class") != "UserNotFoundException" {
		t.Fatalf("status %d, body %v", code, body)
	}
}
