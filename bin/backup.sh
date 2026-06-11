#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BACKUP_DIR="${BACKUP_DIR:-$ROOT/storage/backups}"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
ARCHIVE="$BACKUP_DIR/controll_backup_$TIMESTAMP.tar.gz"

mkdir -p "$BACKUP_DIR"

if [ -f "$ROOT/.env" ]; then
  # shellcheck disable=SC1091
  set -a
  source "$ROOT/.env"
  set +a
fi

DB_NAME="${DB_DATABASE:-${DB_NAME:-}}"
DB_USER="${DB_USERNAME:-${DB_USER:-}}"
DB_PASS="${DB_PASSWORD:-${DB_PASS:-}}"
DB_HOST="${DB_HOST:-127.0.0.1}"

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

if [ -n "$DB_NAME" ] && command -v mysqldump >/dev/null 2>&1; then
  mysqldump -h "$DB_HOST" -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} "$DB_NAME" > "$TMP_DIR/database.sql"
fi

tar -czf "$ARCHIVE" \
  --exclude='./vendor' \
  --exclude='./node_modules' \
  --exclude='./storage/logs' \
  --exclude='./storage/backups' \
  -C "$TMP_DIR" . \
  -C "$ROOT" .env public/uploads storage 2>/dev/null || \
tar -czf "$ARCHIVE" -C "$ROOT" public/uploads storage

echo "Backup criado: $ARCHIVE"
