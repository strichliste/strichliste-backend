package server

import (
	"fmt"
	"testing"

	"github.com/gin-gonic/gin"
)

func userBalance(t *testing.T, engine *gin.Engine, id int) int {
	t.Helper()
	_, body := doJSON(t, engine, "GET", fmt.Sprintf("/api/user/%d", id), "")
	return int(mustGet(t, body, "user.balance").(float64))
}

func TestDepositAndDispense(t *testing.T) {
	engine, _ := newTestServer(t)
	id := createUser(t, engine, "Wallet")

	code, body := doJSON(t, engine, "POST", fmt.Sprintf("/api/user/%d/transaction", id), `{"amount":500}`)
	if code != 200 {
		t.Fatalf("deposit status %d, body %v", code, body)
	}
	if mustGet(t, body, "transaction.amount").(float64) != 500 {
		t.Errorf("amount = %v", mustGet(t, body, "transaction.amount"))
	}
	if got := userBalance(t, engine, id); got != 500 {
		t.Errorf("balance after deposit = %d, want 500", got)
	}

	code, _ = doJSON(t, engine, "POST", fmt.Sprintf("/api/user/%d/transaction", id), `{"amount":-200}`)
	if code != 200 {
		t.Fatalf("dispense status %d", code)
	}
	if got := userBalance(t, engine, id); got != 300 {
		t.Errorf("balance after dispense = %d, want 300", got)
	}
}

func TestTransactionBoundaryExceeded(t *testing.T) {
	engine, _ := newTestServer(t)
	id := createUser(t, engine, "Boundary")
	code, body := doJSON(t, engine, "POST", fmt.Sprintf("/api/user/%d/transaction", id), `{"amount":99999}`)
	if code != 400 {
		t.Fatalf("status %d, want 400, body %v", code, body)
	}
	if mustGet(t, body, "error.class") != "TransactionBoundaryException" {
		t.Errorf("class = %v", mustGet(t, body, "error.class"))
	}
}

func TestZeroAmountInvalid(t *testing.T) {
	engine, _ := newTestServer(t)
	id := createUser(t, engine, "Zero")
	code, body := doJSON(t, engine, "POST", fmt.Sprintf("/api/user/%d/transaction", id), `{"amount":0}`)
	if code != 400 || mustGet(t, body, "error.class") != "TransactionInvalidException" {
		t.Fatalf("status %d, body %v", code, body)
	}
}

func TestTransfer(t *testing.T) {
	engine, _ := newTestServer(t)
	sender := createUser(t, engine, "Sender")
	recipient := createUser(t, engine, "Recipient")

	// Sender sends 300 to recipient (amount negative for the sender leg).
	code, body := doJSON(t, engine, "POST",
		fmt.Sprintf("/api/user/%d/transaction", sender),
		fmt.Sprintf(`{"amount":-300,"recipientId":%d}`, recipient))
	if code != 200 {
		t.Fatalf("transfer status %d, body %v", code, body)
	}
	if mustGet(t, body, "transaction.recipient") == nil {
		t.Error("transfer should serialize a recipient")
	}

	if got := userBalance(t, engine, sender); got != -300 {
		t.Errorf("sender balance = %d, want -300", got)
	}
	if got := userBalance(t, engine, recipient); got != 300 {
		t.Errorf("recipient balance = %d, want 300", got)
	}
}

func TestTransferPositiveAmountRejected(t *testing.T) {
	engine, _ := newTestServer(t)
	sender := createUser(t, engine, "S2")
	recipient := createUser(t, engine, "R2")
	code, body := doJSON(t, engine, "POST",
		fmt.Sprintf("/api/user/%d/transaction", sender),
		fmt.Sprintf(`{"amount":300,"recipientId":%d}`, recipient))
	if code != 400 || mustGet(t, body, "error.class") != "TransactionInvalidException" {
		t.Fatalf("status %d, body %v", code, body)
	}
}

func TestRevertTransaction(t *testing.T) {
	engine, _ := newTestServer(t)
	id := createUser(t, engine, "Reverter")

	_, body := doJSON(t, engine, "POST", fmt.Sprintf("/api/user/%d/transaction", id), `{"amount":500}`)
	txID := int(mustGet(t, body, "transaction.id").(float64))

	code, revBody := doJSON(t, engine, "DELETE", fmt.Sprintf("/api/user/%d/transaction/%d", id, txID), "")
	if code != 200 {
		t.Fatalf("revert status %d, body %v", code, revBody)
	}
	if mustGet(t, revBody, "transaction.isDeleted") != true {
		t.Errorf("reverted transaction should be marked deleted")
	}
	if got := userBalance(t, engine, id); got != 0 {
		t.Errorf("balance after revert = %d, want 0", got)
	}

	// Double revert should fail.
	code, revBody = doJSON(t, engine, "DELETE", fmt.Sprintf("/api/user/%d/transaction/%d", id, txID), "")
	if code != 400 || mustGet(t, revBody, "error.class") != "TransactionNotDeletableException" {
		t.Fatalf("double revert status %d, body %v", code, revBody)
	}
}

func TestListTransactions(t *testing.T) {
	engine, _ := newTestServer(t)
	id := createUser(t, engine, "Lister")
	doJSON(t, engine, "POST", fmt.Sprintf("/api/user/%d/transaction", id), `{"amount":100}`)
	doJSON(t, engine, "POST", fmt.Sprintf("/api/user/%d/transaction", id), `{"amount":200}`)

	code, body := doJSON(t, engine, "GET", "/api/transaction", "")
	if code != 200 {
		t.Fatalf("status %d", code)
	}
	if int(body["count"].(float64)) != 2 {
		t.Errorf("count = %v, want 2", body["count"])
	}

	code, body = doJSON(t, engine, "GET", fmt.Sprintf("/api/user/%d/transaction", id), "")
	if code != 200 || int(body["count"].(float64)) != 2 {
		t.Fatalf("user transactions status %d, body %v", code, body)
	}
}
