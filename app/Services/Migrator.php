<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class Migrator
{
	public static function run(): array
	{
		$pdo = Database::pdo();
		self::ensureMigrationsTable($pdo);

		$dir = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/database/migrations';
		if (!is_dir($dir)) {
			return ['applied' => [], 'skipped' => [], 'errors' => []];
		}

		$files = glob($dir . '/*.sql') ?: [];
		sort($files, SORT_STRING);

		$applied = [];
		$skipped = [];
		$errors = [];

		foreach ($files as $file) {
			$name = basename($file);
			if (self::wasApplied($pdo, $name)) {
				$skipped[] = $name;
				continue;
			}

			$sql = (string) file_get_contents($file);
			if (trim($sql) === '') {
				$skipped[] = $name;
				continue;
			}

			try {
				$pdo->exec($sql);
				$stmt = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (?)');
				$stmt->execute([$name]);
				$applied[] = $name;
			} catch (\Throwable $e) {
				$errors[$name] = $e->getMessage();
			}
		}

		if ($applied !== []) {
			DatabaseSchema::clearCache();
		}

		return ['applied' => $applied, 'skipped' => $skipped, 'errors' => $errors];
	}

	private static function ensureMigrationsTable(PDO $pdo): void
	{
		$pdo->exec(
			'CREATE TABLE IF NOT EXISTS schema_migrations (
				id INT AUTO_INCREMENT PRIMARY KEY,
				migration VARCHAR(255) NOT NULL UNIQUE,
				applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
		);
	}

	private static function wasApplied(PDO $pdo, string $name): bool
	{
		$stmt = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE migration = ? LIMIT 1');
		$stmt->execute([$name]);

		return (bool) $stmt->fetchColumn();
	}
}
