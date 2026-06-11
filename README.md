# Controll IT Help Desk (PHP MVC)

Aplicação de CRM/Help Desk em PHP 8+, MySQL, CSS próprio e Vanilla JS, com sessões PHP e ACL por perfis.

## Requisitos

- PHP 8.1+
- MySQL 5.7+ ou 8+
- Composer

## Instalação local

```bash
composer install
npm install && npm run build:css
cp .env.example .env
# Configure DB_* no .env
composer migrate
php -S 127.0.0.1:8000 -t public public/index.php
```

Abra: `http://127.0.0.1:8000/login`

## Deploy (VPS)

```bash
cd /www/wwwroot/cea.controllit.com.br
./deploy.sh
```

O `deploy.sh` executa `git pull`, `composer dump-autoload` e **migrations automáticas** (`php bin/migrate.php`).

**Erro `could not find driver` na VPS (aaPanel):** o PHP da linha de comando não tem `pdo_mysql`. No painel: **App Store → PHP → Extensões → pdo_mysql**. Depois rode:

```bash
PHP_BIN=/www/server/php/81/bin/php bash deploy.sh
# ou só as migrations:
/www/server/php/81/bin/php bin/migrate.php
```

(Ajuste `81` para a versão PHP do site.)

## Migrations

Arquivos SQL em `database/migrations/`. Para aplicar manualmente:

```bash
php bin/migrate.php
# ou
composer migrate
```

## Testes

```bash
composer install
vendor/bin/phpunit
```

## Perfis (ACL)

| Perfil na sessão | Acesso |
|------------------|--------|
| `user` / `usuario` | Próprios chamados |
| `support` / `suporte` | Todos os chamados, usuários, relatórios |
| `admin` | Acesso total + configurações do sistema |

## Configurações do sistema (admin)

Menu **Configurações** no sidebar:

- Modo manutenção (bloqueia usuários finais)
- Bloqueio por auditoria + data de liberação
- E-mail de notificações
- Nome do sistema

Também disponível pelo menu **Administração** → Modo manutenção (toggle rápido).

## Endpoints úteis

| Rota | Descrição |
|------|-----------|
| `GET /health` | Saúde da aplicação (monitoramento) |
| `GET /settings` | Configurações (admin, JSON) |
| `POST /settings/update` | Salvar configurações (admin) |
| `GET /notifications` | Notificações in-app do usuário |
| `GET /security/two-factor` | Status 2FA (admin/suporte) |

Documentação OpenAPI: `docs/openapi.yaml`

## Fila de e-mails

Com a migration `002_email_queue.sql`, os e-mails de chamados entram na fila. Processe via cron:

```bash
*/5 * * * * php /caminho/bin/process-mail-queue.php >> /caminho/storage/logs/mail-queue.log 2>&1
```

Use `MAIL_SYNC=1` no `.env` para envio síncrono (sem fila).

## Backup (VPS)

```bash
bash bin/backup.sh
```

## 2FA (admin/suporte)

Menu **Segurança** no sidebar: ative TOTP (Google Authenticator, Authy, etc.).

## Variáveis de ambiente (.env)

| Variável | Descrição |
|----------|-----------|
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | Banco de dados |
| `APP_URL` | URL pública do sistema |
| `TICKET_NOTIFICATION_EMAIL` | E-mail padrão de notificações |
| `MAIL_HOST`, `MAIL_PORT`, ... | SMTP (opcional) |
| `APP_SETUP_ENABLED=1` | Habilita `/setup.php` (somente dev) |

## Estrutura principal

```
app/
  Controllers/   # HTTP
  Models/        # Dados
  Services/      # Regras de negócio (TicketService, Cache, AuditLog...)
  Views/         # Templates PHP
public/assets/
  css/app.css    # Estilos principais
  css/tw.css     # Tailwind compilado (npm run build:css)
  js/vendor/     # Chart.js (local)
  js/dashboard/  # utils, charts, tickets, notifications, security
bin/migrate.php  # Runner de migrations
database/
  schema.sql     # Schema inicial
  migrations/    # Migrations incrementais
tests/Unit/      # PHPUnit
```

## Segurança

- CSRF em requisições POST
- Rate limit no login
- Sessão com cookies HttpOnly / SameSite
- Headers de segurança (CSP, X-Frame-Options, etc.)
- Logs de ações admin em `access_logs`
- `/setup.php` bloqueado sem `APP_SETUP_ENABLED=1`

## Schema do banco

Fonte oficial para novas instalações: `database/schema.sql`  
Alterações incrementais: `database/migrations/`
