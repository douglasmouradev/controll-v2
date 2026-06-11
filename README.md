# Controll IT Help Desk (PHP MVC)

Aplicação de CRM/Help Desk em PHP 8+, MySQL, CSS próprio e Vanilla JS, com sessões PHP e ACL por perfis.

## Requisitos

- PHP 8.1+
- MySQL 5.7+ ou 8+
- Composer

## Instalação local

```bash
composer install
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
  Services/      # Regras de negócio
  Views/         # Templates PHP
bin/migrate.php  # Runner de migrations
database/
  schema.sql     # Schema inicial
  migrations/    # Migrations incrementais
public/          # Document root
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
