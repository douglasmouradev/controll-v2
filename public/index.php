<?php
declare(strict_types=1);

// Limpar qualquer output anterior e iniciar buffer
if (ob_get_level() > 0) {
	ob_clean();
}
ob_start();

session_set_cookie_params([
	'lifetime' => 0,
	'path' => '/',
	'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
	'httponly' => true,
	'samesite' => 'Lax',
]);
session_start();

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header(
	"Content-Security-Policy: default-src 'self'; "
	. "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.tailwindcss.com; "
	. "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.tailwindcss.com; "
	. "font-src 'self' https://fonts.gstatic.com; "
	. "img-src 'self' data:; "
	. "connect-src 'self' https://viacep.com.br https://cdn.jsdelivr.net"
);

// Composer autoload (for App\ namespace)
require_once dirname(__DIR__) . '/vendor/autoload.php';

\App\Services\Csrf::token();

// Basic constants
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');

$envFile = BASE_PATH . '/.env';
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
$tz = getenv('TIMEZONE') ?: 'America/Sao_Paulo';
if ($tz) { date_default_timezone_set((string) $tz); }
$dbg = getenv('APP_DEBUG') ?: getenv('DEBUG_MODE') ?: '0';
if (!defined('APP_DEBUG')) {
	define('APP_DEBUG', in_array(strtolower((string) $dbg), ['1','true','yes','on'], true));
}
if (APP_DEBUG || in_array(strtolower((string)(getenv('SHOW_ERRORS') ?: '0')), ['1','true','yes','on'], true)) {
	ini_set('display_errors', '1');
	error_reporting(E_ALL);
} else {
	ini_set('display_errors', '0');
}

// Load routes
$routesFile = CONFIG_PATH . '/routes.php';
if (!file_exists($routesFile)) {
	http_response_code(500);
	echo 'Routes file not found.';
	exit;
}
$routes = require $routesFile;

// Simple Router
$router = new App\Core\Router($routes);
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $requestPath);


