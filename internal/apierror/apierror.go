// Package apierror defines the API error type and the error envelope that
// matches the PHP ApiExceptionSubscriber output:
//
//	{ "error": { "class": "...", "code": <int>, "message": "..." } }
//
// Class names are the Go-equivalent identifiers of the PHP exception classes
// (an accepted, documented divergence from byte-for-byte PHP FQCNs).
package apierror

import "fmt"

// APIError is an error that carries an HTTP status code, a class identifier,
// and a human-readable message, serialized via the standard error envelope.
type APIError struct {
	Class   string `json:"class"`
	Code    int    `json:"code"`
	Message string `json:"message"`
}

func (e *APIError) Error() string { return e.Message }

func newErr(class string, code int, format string, args ...any) *APIError {
	return &APIError{Class: class, Code: code, Message: fmt.Sprintf(format, args...)}
}

// --- User ---

func UserNotFound(identifier string) *APIError {
	return newErr("UserNotFoundException", 404, "User '%s' not found", identifier)
}

func UserAlreadyExists(name string) *APIError {
	return newErr("UserAlreadyExistsException", 409, "User '%s' already exists", name)
}

// --- Parameters ---

func ParameterMissing(param string) *APIError {
	return newErr("ParameterMissingException", 400, "Parameter '%s' is missing", param)
}

func ParameterInvalid(param string) *APIError {
	return newErr("ParameterInvalidException", 400, "Parameter '%s' is invalid", param)
}

// --- Transactions ---

func TransactionInvalid(message string) *APIError {
	if message == "" {
		message = "Transaction invalid"
	}
	return &APIError{Class: "TransactionInvalidException", Code: 400, Message: message}
}

func TransactionBoundaryUpper(amount, boundary int) *APIError {
	return newErr("TransactionBoundaryException", 400,
		"Transaction amount '%d' exceeds upper transaction boundary '%d'", amount, boundary)
}

func TransactionBoundaryLower(amount, boundary int) *APIError {
	return newErr("TransactionBoundaryException", 400,
		"Transaction amount '%d' is below lower transaction boundary '%d'", amount, boundary)
}

func AccountBalanceBoundaryUpper(amount, boundary, userID int) *APIError {
	return newErr("AccountBalanceBoundaryException", 400,
		"Transaction amount '%d' exceeds upper account balance boundary '%d' for user '%d'", amount, boundary, userID)
}

func AccountBalanceBoundaryLower(amount, boundary, userID int) *APIError {
	return newErr("AccountBalanceBoundaryException", 400,
		"Transaction amount '%d' is below lower account balance boundary '%d' for user '%d'", amount, boundary, userID)
}

func TransactionNotFound(transactionID int) *APIError {
	return newErr("TransactionNotFoundException", 404, "Transaction '%d' not found", transactionID)
}

func TransactionNotDeletable(transactionID int) *APIError {
	// Note: PHP retains the misspelling "deleteable".
	return newErr("TransactionNotDeletableException", 400, "Transaction '%d' is not deleteable", transactionID)
}

// --- Articles ---

func ArticleNotFound(articleID string) *APIError {
	return newErr("ArticleNotFoundException", 404, "Article '%s' not found", articleID)
}

func ArticleInactive(name string, id int) *APIError {
	return newErr("ArticleInactiveException", 400, "Article '%s' (%d) is inactive", name, id)
}

// --- Barcodes ---

func BarcodeNotFound(barcodeID int) *APIError {
	return newErr("BarcodeNotFoundException", 404, "Barcode ID '%d' not found.", barcodeID)
}

func ArticleBarcodeAlreadyExists(name string, id int, barcode string) *APIError {
	return newErr("ArticleBarcodeAlreadyExistsException", 409,
		"Active article '%s' (%d) with barcode '%s' already exists.", name, id, barcode)
}

// --- Tags ---

func TagNotFound(tagID int) *APIError {
	return newErr("TagNotFoundException", 404, "Tag ID '%d' not found.", tagID)
}

func ArticleTagAlreadyExists(name string, id int, tag string) *APIError {
	return newErr("ArticleTagAlreadyExistsException", 409,
		"Article '%s' (%d) already has tag '%s'", name, id, tag)
}
