<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.1+-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP"/>
  <img src="https://img.shields.io/badge/Tailwind-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white" alt="Tailwind"/>
  <img src="https://img.shields.io/badge/Help_Desk-0ea5e9?style=flat-square" alt="Help Desk"/>
</p>

<h1 align="center">Controll IT Help Desk</h1>

<p align="center">
  <strong>Central de atendimentos</strong> com filas, perfis de acesso (ACL), dashboard com filtros e integração ViaCEP.
</p>

<p align="center">
  <a href="https://cea.controllit.com.br"><strong>Ver em produção</strong></a> ·
  <a href="https://portifolio-douglas-moura.vercel.app">Portfólio</a> ·
  <a href="https://github.com/douglasmouradev/controll-v2">Repositório</a>
</p>

---

# Controll IT Help Desk (PHP MVC)

Aplicação de CRM/Help Desk em PHP 8+, MySQL, Tailwind (CDN) e Vanilla JS, com sessões PHP e ACL por perfis.

## Destaques

- Dashboard com filtros rápidos e visual TDESK
- Modal “Ver” com resumo e atualizações via **AJAX**
- Preenchimento automático de endereço por **ViaCEP**
- Perfis: superadmin, admin, suporte, gerente e usuário

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