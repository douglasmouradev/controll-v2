<?php
declare(strict_types=1);

// Setup rápido (apenas desenvolvimento local)
// Para ativar: APP_SETUP_ENABLED=1 no .env e acesse /setup.php

// Carrega variáveis do arquivo .env (se existir) antes do gate de APP_DEBUG
$basePath = dirname(__DIR__);
$envFile = $basePath . '/.env';
if (is_file($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(ltrim($line), '#') === 0) { continue; }
        $pair = explode('=', $line, 2);
        if (count($pair) !== 2) { continue; }
        $name = trim($pair[0]);
        $value = trim($pair[1]);
        if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
            $value = trim($value, "\"'");
        }
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

// Bloqueado em produção — requer APP_SETUP_ENABLED=1 explicitamente
$setupEnabled = getenv('APP_SETUP_ENABLED');
if (
	!$setupEnabled
	|| !in_array(strtolower((string) $setupEnabled), ['1', 'true', 'yes', 'on'], true)
) {
	http_response_code(403);
	echo 'Setup desabilitado.';
	exit;
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

function env(string $k, string $default = ''): string {
	$v = getenv($k);
	return $v === false ? $default : (string) $v;
}

$host = env('DB_HOST', '127.0.0.1');
$port = (int) env('DB_PORT', '3306');
$db   = env('DB_NAME', 'helpdesk');
$user = env('DB_USER', 'root');
$pass = env('DB_PASS', '');

// Timezone (opcional)
$tz = getenv('TIMEZONE') ?: 'America/Sao_Paulo';
if ($tz) { date_default_timezone_set((string) $tz); }

echo "<pre>";
echo "Iniciando setup...\n";

try {
	// Conecta sem banco para criar database se necessário
	$dsnNoDb = "mysql:host={$host};port={$port};charset=utf8mb4";
	$pdo = new PDO($dsnNoDb, $user, $pass, [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES => false,
	]);
	$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
	echo "Banco garantido: {$db}\n";
} catch (Throwable $e) {
	echo "Falha ao criar banco: " . $e->getMessage() . "\n";
	exit;
}

try {
	// Conecta no banco e aplica schema mínimo
	$dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
	$dbh = new PDO($dsn, $user, $pass, [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES => false,
	]);

	// Executa um schema mínimo compatível com o banco normalizado
	$schema = <<<SQL
DROP TABLE IF EXISTS ticket_attachments;
DROP TABLE IF EXISTS tickets;
DROP TABLE IF EXISTS ticket_statuses;
DROP TABLE IF EXISTS ticket_priorities;
DROP TABLE IF EXISTS ticket_categories;
DROP TABLE IF EXISTS users;
CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  username VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  user_type ENUM('user', 'support', 'admin') NOT NULL DEFAULT 'user',
  active TINYINT(1) NOT NULL DEFAULT 1,
  credits INT UNSIGNED NOT NULL DEFAULT 0,
  daily_credits INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_email (email),
  INDEX idx_username (username),
  INDEX idx_user_type (user_type),
  INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE ticket_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL UNIQUE,
  description TEXT NULL,
  color VARCHAR(7) NULL,
  icon VARCHAR(50) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE ticket_priorities (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(50) NOT NULL UNIQUE,
  level INT NOT NULL,
  color VARCHAR(7) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_name (name),
  INDEX idx_level (level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE ticket_statuses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(50) NOT NULL UNIQUE,
  slug VARCHAR(50) NOT NULL UNIQUE,
  color VARCHAR(7) NULL,
  is_final TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_name (name),
  INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE tickets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(200) NOT NULL,
  description TEXT NOT NULL,
  category_id INT UNSIGNED NULL,
  priority_id INT UNSIGNED NULL,
  status_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NOT NULL,
  assigned_to INT UNSIGNED NULL,
  department_id INT UNSIGNED NULL,
  rating INT NULL,
  name VARCHAR(120) NULL,
  registration VARCHAR(60) NULL,
  unit VARCHAR(120) NULL,
  cep VARCHAR(12) NULL,
  address VARCHAR(255) NULL,
  city VARCHAR(120) NULL,
  uf VARCHAR(5) NULL,
  internal_order VARCHAR(120) NULL,
  invoice VARCHAR(120) NULL,
  external_ticket VARCHAR(120) NULL,
  logo_path VARCHAR(255) NULL,
  support_response TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  closed_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY idx_user (user_id),
  KEY idx_assigned (assigned_to),
  KEY idx_category (category_id),
  KEY idx_priority (priority_id),
  KEY idx_status (status_id),
  CONSTRAINT fk_tickets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_tickets_assigned FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_tickets_category FOREIGN KEY (category_id) REFERENCES ticket_categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_tickets_priority FOREIGN KEY (priority_id) REFERENCES ticket_priorities(id) ON DELETE SET NULL,
  CONSTRAINT fk_tickets_status FOREIGN KEY (status_id) REFERENCES ticket_statuses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE ticket_attachments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id INT UNSIGNED NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_type VARCHAR(100) NULL,
  file_size INT UNSIGNED NULL,
  uploaded_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ticket (ticket_id),
  CONSTRAINT fk_attachments_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  CONSTRAINT fk_attachments_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO ticket_categories (name, color) VALUES ('Geral', '#9b59b6');
INSERT INTO ticket_priorities (name, level, color) VALUES ('Baixa', 1, '#10b981'), ('Média', 2, '#f59e0b'), ('Alta', 3, '#ef4444'), ('Crítica', 4, '#dc2626');
INSERT INTO ticket_statuses (name, slug, color, is_final) VALUES ('Aberto', 'aberto', '#f59e0b', 0), ('Em Andamento', 'em_andamento', '#3b82f6', 0), ('Fechado', 'fechado', '#10b981', 1);
SQL;
	$dbh->exec($schema);
	echo "Schema aplicado com sucesso.\n";

	// Garante usuários padrão: admin, support e user
	$users = [
		['Admin',   'admin@local', 'admin',     'admin123',   'admin'],
		['Suporte', 'suporte@local', 'suporte', 'suporte123', 'support'],
		['Usuário', 'usuario@local', 'usuario', 'usuario123', 'user'],
	];
	$stmt = $dbh->prepare(
		'INSERT INTO users (name,email,username,password_hash,user_type,active)
		 VALUES (:name,:email,:username,:password_hash,:user_type,:active)
		 ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), user_type = VALUES(user_type)'
	);
	foreach ($users as [$nameU, $emailU, $usernameU, $plain, $userTypeU]) {
		$stmt->execute([
			':name' => $nameU,
			':email' => $emailU,
			':username' => $usernameU,
			':password_hash' => password_hash($plain, PASSWORD_BCRYPT),
			':user_type' => $userTypeU,
			':active' => 1,
		]);
	}
	echo "Usuários padrão criados/atualizados (admin, support, user).\n";
} catch (Throwable $e) {
	echo "Falha ao aplicar schema: " . $e->getMessage() . "\n";
	exit;
}

echo "Finalizado. Você já pode acessar /login\n";
echo "</pre>";

