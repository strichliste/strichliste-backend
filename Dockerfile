# --- build stage ---
FROM golang:1.26-alpine AS builder

WORKDIR /src

# Download dependencies first for better layer caching.
COPY go.mod go.sum ./
RUN go mod download

COPY . .
RUN CGO_ENABLED=0 GOOS=linux go build -ldflags="-s -w" -o /out/strichliste ./cmd/strichliste

# --- runtime stage ---
FROM alpine:3.21

RUN apk add --no-cache ca-certificates

WORKDIR /app
COPY --from=builder /out/strichliste /app/strichliste
COPY config/strichliste.yaml /app/config/strichliste.yaml

ENV LISTEN_ADDR=:8080
EXPOSE 8080

ENTRYPOINT ["/app/strichliste"]
