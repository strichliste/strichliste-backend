package model

import "testing"

func TestTableNames(t *testing.T) {
	cases := []struct {
		name string
		got  string
		want string
	}{
		{"User", User{}.TableName(), "user"},
		{"Article", Article{}.TableName(), "article"},
		{"ArticleTag", ArticleTag{}.TableName(), "article_tag"},
		{"Barcode", Barcode{}.TableName(), "barcode"},
		{"Tag", Tag{}.TableName(), "tag"},
		{"Transaction", Transaction{}.TableName(), "transactions"},
	}
	for _, c := range cases {
		if c.got != c.want {
			t.Errorf("%s.TableName() = %q, want %q", c.name, c.got, c.want)
		}
	}
}

func TestAddBalance(t *testing.T) {
	u := &User{Balance: 100}
	u.AddBalance(50)
	u.AddBalance(-30)
	if u.Balance != 120 {
		t.Errorf("Balance = %d, want 120", u.Balance)
	}
}
