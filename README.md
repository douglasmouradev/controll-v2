# Controll IT Help Desk (PHP MVC)

Aplicação de CRM/Help Desk em PHP 8+, MySQL, Tailwind (CDN) e Vanilla JS, com sessões PHP e ACL por perfis.

## Requisitos
- PHP 8.1+
- MySQL 5.7+ ou MySQL 8+
- Composer

## Estrutura
```
help-desk-controll/
├── app/
│   ├── Controllers/
│   ├── Core/
│   ├── Models/
│   ├── Services/
│   └── Views/
├── config/
│   └── routes.php
├── database/
│   └── schema.sql          # Único schema do banco
├── public/                 # Document root
│   ├── index.php
│   ├── favicon.svg
│   └── logo-controll-it.svg
└── composer.json
```

## Instalação
1) Autoload do Composer
```bash
composer dump-autoload
```

2) Banco de dados (MySQL)
- Crie um banco (ex.: `helpdesk`)
- Importe o schema:
```bash
mysql -u SEU_USUARIO -p -h 127.0.0.1 -P 3306 helpdesk < database/schema.sql
```

3) Configuração (variáveis de ambiente)
- Defina no servidor/ambiente:
  - `DB_HOST` (ex.: 127.0.0.1)
  - `DB_PORT` (ex.: 3306)
  - `DB_NAME` (ex.: helpdesk)
  - `DB_USER` (ex.: root)
  - `DB_PASS` (senha)

4) Servir a aplicação
```bash
php -S 127.0.0.1:8000 -t public
```
Abra: `http://127.0.0.1:8000/login`

## Perfis e ACL
- superadmin, admin, suporte, gerente, usuario
- Usuário comum vê apenas seus próprios chamados
- Suporte/Admin podem atribuir e alterar status; acesso a Usuários/Relatórios (API espelhada será adicionada)

## Funcionalidades implementadas
- Autenticação com sessões (`password_hash`/`password_verify`)
- Dashboard com tabela de chamados, filtros rápidos e visual TDESK
- Abertura de chamado: retorna JSON `{ success, message, id }` e mostra toast
- Modal “Ver” com resumo; botões de status e “Atribuir p/ mim” atualizam tabela instantaneamente (AJAX)
- ViaCEP para preenchimento automático de endereço por CEP

## Próximos passos
- CRUD completo de usuários/chamados via REST API
- Relatórios em PDF/XLSX/CSV com cabeçalho “Relatório de Chamados – Gerado em dd/mm/aaaa hh:mm”
- Gráfico de diárias com Chart.js

## Licença
MIT