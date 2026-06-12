<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('BASE_PATH')) {
	define('BASE_PATH', dirname(__DIR__));
}

$envFile = BASE_PATH . '/.env';
if (is_file($envFile) && is_readable($envFile)) {
	foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
		if (strpos(ltrim($line), '#') === 0) {
			continue;
		}
		$pair = explode('=', $line, 2);
		if (count($pair) !== 2) {
			continue;
		}
		$name = trim($pair[0]);
		$value = trim($pair[1], " \t\"'");
		putenv($name . '=' . $value);
		$_ENV[$name] = $value;
	}
}
