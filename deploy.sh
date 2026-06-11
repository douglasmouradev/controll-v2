#!/bin/bash
set -euo pipefail

APP_DIR="${APP_DIR:-/www/wwwroot/cea.controllit.com.br}"

echo "==> Deploy em ${APP_DIR}"
cd "${APP_DIR}"

git config --global --add safe.directory "${APP_DIR}" 2>/dev/null || true

if [ -f .env ]; then
	cp .env ".env.backup.$(date +%Y%m%d%H%M%S)"
	echo "==> Backup do .env criado"
fi

chattr -i .user.ini public/.user.ini 2>/dev/null || true

git fetch origin
git checkout -f main 2>/dev/null || git checkout -f -B main origin/main
git pull origin main

if command -v composer >/dev/null 2>&1; then
	COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --no-interaction
fi

if [ -f bin/migrate.php ]; then
	php bin/migrate.php || echo "==> Aviso: migrations falharam (verifique o banco)"
fi

chown -R www:www storage public/uploads 2>/dev/null || true
chmod -R 775 storage public/uploads 2>/dev/null || true

echo "==> Deploy concluído: $(git log -1 --oneline)"
