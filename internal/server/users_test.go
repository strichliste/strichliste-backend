package server

import (
	"fmt"
	"testing"

	"github.com/gin-gonic/gin"
)

// createUser is a test helper that creates a user and returns its id.
func createUser(t *testing.T, engine *gin.Engine, name string) int {
	t.Helper()
	code, body := doJSON(t, engine, "POST", "/api/user", fmt.Sprintf(`{"name":%q}`, name))
	if code != 200 {
		t.Fatalf("create user %q: status %d, body %v", name, code, body)
	}
	return int(mustGet(t, body, "user.id").(float64))
}

func TestCreateUser(t *testing.T) {
	engine, _ := newTestServer(t)
	code, body := doJSON(t, engine, "POST", "/api/user", `{"name":"Alice","email":"alice@example.com"}`)
	if code != 200 {
		t.Fatalf("status %d, body %v", code, body)
	}
	if mustGet(t, body, "user.name") != "Alice" {
		t.Errorf("name = %v", mustGet(t, body, "user.name"))
	}
	if mustGet(t, body, "user.email") != "alice@example.com" {
		t.Errorf("email = %v", mustGet(t, body, "user.email"))
	}
	if mustGet(t, body, "user.balance").(float64) != 0 {
		t.Errorf("balance should be 0")
	}
	if mustGet(t, body, "user.isActive") != true {
		t.Errorf("new user should be active")
	}
	if mustGet(t, body, "user.isDisabled") != false {
		t.Errorf("new user should not be disabled")
	}
}

func TestCreateUserDuplicate(t *testing.T) {
	engine, _ := newTestServer(t)
	createUser(t, engine, "Bob")
	code, body := doJSON(t, engine, "POST", "/api/user", `{"name":"Bob"}`)
	if code != 409 {
		t.Fatalf("status %d, want 409, body %v", code, body)
	}
	if mustGet(t, body, "error.message") != "User 'Bob' already exists" {
		t.Errorf("message = %v", mustGet(t, body, "error.message"))
	}
}

func TestCreateUserMissingName(t *testing.T) {
	engine, _ := newTestServer(t)
	code, body := doJSON(t, engine, "POST", "/api/user", `{}`)
	if code != 400 || mustGet(t, body, "error.message") != "Parameter 'name' is missing" {
		t.Fatalf("status %d, body %v", code, body)
	}
}

func TestCreateUserInvalidEmail(t *testing.T) {
	engine, _ := newTestServer(t)
	code, body := doJSON(t, engine, "POST", "/api/user", `{"name":"Carol","email":"not-an-email"}`)
	if code != 400 || mustGet(t, body, "error.message") != "Parameter 'email' is invalid" {
		t.Fatalf("status %d, body %v", code, body)
	}
}

func TestGetUserByIdAndName(t *testing.T) {
	engine, _ := newTestServer(t)
	id := createUser(t, engine, "Dave")

	code, body := doJSON(t, engine, "GET", fmt.Sprintf("/api/user/%d", id), "")
	if code != 200 || int(mustGet(t, body, "user.id").(float64)) != id {
		t.Fatalf("by id: status %d, body %v", code, body)
	}

	code, body = doJSON(t, engine, "GET", "/api/user/Dave", "")
	if code != 200 || mustGet(t, body, "user.name") != "Dave" {
		t.Fatalf("by name: status %d, body %v", code, body)
	}
}

func TestGetUserNotFound(t *testing.T) {
	engine, _ := newTestServer(t)
	code, body := doJSON(t, engine, "GET", "/api/user/999", "")
	if code != 404 || mustGet(t, body, "error.message") != "User '999' not found" {
		t.Fatalf("status %d, body %v", code, body)
	}
}

func TestListUsersSortedAndExcludesDisabled(t *testing.T) {
	engine, _ := newTestServer(t)
	createUser(t, engine, "Charlie")
	createUser(t, engine, "alice")
	bobID := createUser(t, engine, "Bob")

	// Disable Bob.
	doJSON(t, engine, "POST", fmt.Sprintf("/api/user/%d", bobID), `{"isDisabled":true}`)

	code, body := doJSON(t, engine, "GET", "/api/user", "")
	if code != 200 {
		t.Fatalf("status %d", code)
	}
	users := body["users"].([]any)
	var names []string
	for _, u := range users {
		names = append(names, u.(map[string]any)["name"].(string))
	}
	// Disabled Bob excluded; natural case-insensitive order: alice, Charlie.
	if len(names) != 2 || names[0] != "alice" || names[1] != "Charlie" {
		t.Errorf("names = %v, want [alice Charlie]", names)
	}
}

func TestSearchUsers(t *testing.T) {
	engine, _ := newTestServer(t)
	createUser(t, engine, "Alpha")
	createUser(t, engine, "Alphabet")
	createUser(t, engine, "Beta")

	code, body := doJSON(t, engine, "GET", "/api/user/search?query=Alph", "")
	if code != 200 {
		t.Fatalf("status %d", code)
	}
	if int(body["count"].(float64)) != 2 {
		t.Errorf("count = %v, want 2", body["count"])
	}
}

func TestUpdateUserRename(t *testing.T) {
	engine, _ := newTestServer(t)
	id := createUser(t, engine, "OldName")
	code, body := doJSON(t, engine, "POST", fmt.Sprintf("/api/user/%d", id), `{"name":"NewName"}`)
	if code != 200 || mustGet(t, body, "user.name") != "NewName" {
		t.Fatalf("status %d, body %v", code, body)
	}
}
