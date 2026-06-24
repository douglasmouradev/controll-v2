<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;

final class Database
{
	private static ?PDO $pdo = null;

	public static function pdo(): PDO
	{
		if (self::$pdo instanceof PDO) {
			return self::$pdo;
		}
		$host = getenv('DB_HOST') ?: '127.0.0.1';
		$db   = getenv('DB_NAME') ?: 'helpdesk';
		$user = getenv('DB_USER') ?: 'root';
		$pass = getenv('DB_PASS') ?: '';
		$port = (int) (getenv('DB_PORT') ?: 3306);

		$dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
		$options = [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES => false,
		];
		try {
			self::$pdo = new PDO($dsn, $user, $pass, $options);
			self::configureSessionTimezone(self::$pdo);
			return self::$pdo;
		} catch (PDOException $e) {
			$debug = getenv('APP_DEBUG') ?: getenv('DEBUG_DB');
			$showDetails = PHP_SAPI === 'cli'
				|| ($debug && in_array(strtolower((string) $debug), ['1', 'true', 'yes', 'on'], true));
			$message = $showDetails
				? 'Erro de conexão com o banco: ' . $e->getMessage()
				: 'Erro de conexão com o banco.';
			if (PHP_SAPI === 'cli') {
				fwrite(STDERR, $message . PHP_EOL);
				exit(1);
			}
			http_response_code(500);
			echo $showDetails ? htmlspecialchars($message) : $message;
			exit;
		}
	}

	private static function configureSessionTimezone(PDO $pdo): void
	{
		$tz = trim((string) (getenv('TIMEZONE') ?: 'America/Sao_Paulo'));
		if ($tz === '') {
			$tz = 'America/Sao_Paulo';
		}

		try {
			$stmt = $pdo->prepare('SET time_zone = :tz');
			$stmt->execute([':tz' => $tz]);
		} catch (PDOException $e) {
			try {
				$offset = DateFormatter::mysqlTimezoneOffset();
				$pdo->exec("SET time_zone = '{$offset}'");
			} catch (PDOException $ignored) {
				// Mantém timezone padrão do MySQL se não for possível ajustar.
			}
		}
	}
}


