// Package serializer renders entities into the JSON shapes the PHP API produces.
package serializer

import (
	"time"

	"github.com/strichliste/strichliste-backend/internal/model"
	"github.com/strichliste/strichliste-backend/internal/rules"
	"github.com/strichliste/strichliste-backend/internal/settings"
)

// dateLayout matches the PHP "Y-m-d H:i:s" format.
const dateLayout = "2006-01-02 15:04:05"

// Serializer renders entities, consulting settings for computed fields.
type Serializer struct {
	settings *settings.Settings
	now      func() time.Time
}

// New builds a Serializer.
func New(s *settings.Settings) *Serializer {
	return &Serializer{settings: s, now: time.Now}
}

func formatTime(t time.Time) string { return t.Format(dateLayout) }

func formatTimePtr(t *time.Time) any {
	if t == nil {
		return nil
	}
	return t.Format(dateLayout)
}

func strPtr(s *string) any {
	if s == nil {
		return nil
	}
	return *s
}

func intPtr(i *int) any {
	if i == nil {
		return nil
	}
	return *i
}

// User renders a user object.
func (s *Serializer) User(u *model.User) map[string]any {
	if u == nil {
		return nil
	}
	return map[string]any{
		"id":         u.ID,
		"name":       u.Name,
		"email":      strPtr(u.Email),
		"balance":    u.Balance,
		"isActive":   rules.IsActive(s.settings, u, s.now()),
		"isDisabled": u.Disabled,
		"created":    formatTime(u.Created),
		"updated":    formatTimePtr(u.Updated),
	}
}

// Barcode renders a barcode object.
func (s *Serializer) Barcode(b *model.Barcode) map[string]any {
	return map[string]any{
		"id":      b.ID,
		"barcode": b.Barcode,
		"created": formatTime(b.Created),
	}
}

// articleTag renders the embedded tag shape inside an article (Tag id + the
// join row's created timestamp; no usageCount).
func (s *Serializer) articleTag(at *model.ArticleTag) map[string]any {
	out := map[string]any{
		"id":      uint(0),
		"tag":     "",
		"created": formatTime(at.Created),
	}
	if at.Tag != nil {
		out["id"] = at.Tag.ID
		out["tag"] = at.Tag.Tag
	}
	return out
}

// Article renders an article object. depth controls precursor recursion.
func (s *Serializer) Article(a *model.Article, depth int) map[string]any {
	if a == nil {
		return nil
	}

	barcodes := make([]map[string]any, 0, len(a.Barcodes))
	for i := range a.Barcodes {
		barcodes = append(barcodes, s.Barcode(&a.Barcodes[i]))
	}

	tags := make([]map[string]any, 0, len(a.ArticleTags))
	for i := range a.ArticleTags {
		tags = append(tags, s.articleTag(&a.ArticleTags[i]))
	}

	var precursor any
	if depth > 0 && a.Precursor != nil {
		precursor = s.Article(a.Precursor, depth-1)
	}

	return map[string]any{
		"id":         a.ID,
		"name":       a.Name,
		"barcodes":   barcodes,
		"tags":       tags,
		"amount":     a.Amount,
		"isActive":   a.Active,
		"usageCount": a.UsageCount,
		"precursor":  precursor,
		"created":    formatTime(a.Created),
	}
}

// Tag renders the standalone tag object used by the /tag endpoints. usageCount
// is the number of article_tag rows referencing the tag (len of the preloaded
// ArticleTags association).
func (s *Serializer) Tag(t *model.Tag) map[string]any {
	return map[string]any{
		"id":         t.ID,
		"tag":        t.Tag,
		"usageCount": len(t.ArticleTags),
		"created":    formatTime(t.Created),
	}
}

// Transaction renders a transaction object.
func (s *Serializer) Transaction(t *model.Transaction) map[string]any {
	var article any
	if t.Article != nil {
		article = s.Article(t.Article, 1)
	}

	var sender, recipient any
	if t.SenderTransaction != nil && t.SenderTransaction.User != nil {
		sender = s.User(t.SenderTransaction.User)
	}
	if t.RecipientTransaction != nil && t.RecipientTransaction.User != nil {
		recipient = s.User(t.RecipientTransaction.User)
	}

	return map[string]any{
		"id":          t.ID,
		"user":        s.User(t.User),
		"quantity":    intPtr(t.Quantity),
		"article":     article,
		"sender":      sender,
		"recipient":   recipient,
		"comment":     strPtr(t.Comment),
		"amount":      t.Amount,
		"isDeleted":   t.Deleted,
		"isDeletable": rules.IsDeletable(s.settings, t, s.now()),
		"created":     formatTime(t.Created),
	}
}
