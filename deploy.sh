#!/bin/bash
set -euo pipefail

APP_DIR="${APP_DIR:-/www/wwwroot/cea.controllit.com.br}"

resolve_php_bin() {
	if [ -n "${PHP_BIN:-}" ] && [ -x "${PHP_BIN}" ]; then
		echo "${PHP_BIN}"
		return
	fi
	local candidate
	for candidate in \
		/www/server/php/83/bin/php \
		/www/server/php/82/bin/php \
		/www/server/php/81/bin/php \
		/www/server/php/80/bin/php \
		/www/server/php/74/bin/php \
		"$(command -v php 2>/dev/null || true)"; do
		[ -n "${candidate}" ] || continue
		[ -x "${candidate}" ] || continue
		if "${candidate}" -m 2>/dev/null | grep -qi pdo_mysql; then
			echo "${candidate}"
			return
		fi
	done
	echo "php"
}

PHP_BIN="$(resolve_php_bin)"

echo "==> Deploy em ${APP_DIR}"
echo "==> PHP: ${PHP_BIN} ($(${PHP_BIN} -v 2>/dev/null | head -1 || echo 'versão desconhecida'))"
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

if command -v composer >/dev/null 2>&1 && [ -f composer.json ]; then
	echo "==> Instalando dependências PHP (composer install)"
	COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
fi

if command -v npm >/dev/null 2>&1 && [ -f package.json ]; then
	echo "==> Compilando Tailwind CSS"
	npm ci 2>/dev/null || npm install 2>/dev/null || true
	npm run build:css || echo "==> Aviso: build CSS falhou"
fi

if [ -f bin/migrate.php ]; then
	if ! "${PHP_BIN}" -m 2>/dev/null | grep -qi pdo_mysql; then
		echo "==> Aviso: PDO MySQL ausente no PHP CLI. Ative pdo_mysql no aaPanel ou defina PHP_BIN."
		echo "==> Exemplo: PHP_BIN=/www/server/php/81/bin/php bash deploy.sh"
	else
		echo "==> Executando migrations"
		"${PHP_BIN}" bin/migrate.php
	fi
fi

chmod +x bin/backup.sh bin/process-mail-queue.php deploy.sh 2>/dev/null || true
echo "==> Fila de e-mail: */5 * * * * ${PHP_BIN} ${APP_DIR}/bin/process-mail-queue.php"

chown -R www:www storage public/uploads 2>/dev/null || true
chmod -R 775 storage public/uploads 2>/dev/null || true

echo "==> Deploy concluído: $(git log -1 --oneline)"
