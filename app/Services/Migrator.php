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
			return ['applied' => [], 'skipped' => [], 'errors' => [], 'ensured' => []];
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
				self::execSql($pdo, $sql);
				$stmt = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (?)');
				$stmt->execute([$name]);
				$applied[] = $name;
			} catch (\Throwable $e) {
				$errors[$name] = $e->getMessage();
			}
		}

		$ensured = [];
		try {
			$ensured = self::ensureSdwanColumns($pdo);
		} catch (\Throwable $e) {
			$errors['ensure_sdwan_columns'] = $e->getMessage();
		}

		if ($applied !== [] || $ensured !== []) {
			DatabaseSchema::clearCache();
		}

		return ['applied' => $applied, 'skipped' => $skipped, 'errors' => $errors, 'ensured' => $ensured];
	}

	private static function execSql(PDO $pdo, string $sql): void
	{
		$driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
		if (stripos($driver, 'mysql') === false) {
			$pdo->exec($sql);
			return;
		}

		$previousMulti = null;
		try {
			$previousMulti = $pdo->getAttribute(PDO::MYSQL_ATTR_MULTI_STATEMENTS);
			$pdo->setAttribute(PDO::MYSQL_ATTR_MULTI_STATEMENTS, true);
		} catch (\Throwable $e) {
			$pdo->exec($sql);
			return;
		}

		try {
			$pdo->exec($sql);
			try {
				while ($pdo->nextRowset()) {
				}
			} catch (\Throwable $e) {
				// Sem mais result sets.
			}
		} finally {
			if ($previousMulti !== null) {
				$pdo->setAttribute(PDO::MYSQL_ATTR_MULTI_STATEMENTS, (bool) $previousMulti);
			}
		}
	}

	/** @return list<string> */
	private static function ensureSdwanColumns(PDO $pdo): array
	{
		if (!DatabaseSchema::tableExists($pdo, 'sdwan_entries')) {
			return [];
		}

		$added = [];
		$specs = [
			['quantidade_utilizada', 'INT UNSIGNED NOT NULL DEFAULT 0', 'quantidade_localizada'],
			['serie_antena', "VARCHAR(60) NOT NULL DEFAULT ''", 'pdv_serie'],
			['serie_acupad', "VARCHAR(60) NOT NULL DEFAULT ''", 'serie_antena'],
			['setor', "VARCHAR(120) NOT NULL DEFAULT ''", 'serie_acupad'],
		];

		foreach ($specs as [$column, $definition, $after]) {
			if (DatabaseSchema::columnExists($pdo, 'sdwan_entries', $column)) {
				continue;
			}

			$sql = sprintf(
				'ALTER TABLE sdwan_entries ADD COLUMN `%s` %s AFTER `%s`',
				str_replace('`', '``', $column),
				$definition,
				str_replace('`', '``', $after)
			);
			$pdo->exec($sql);
			DatabaseSchema::clearCache();
			$added[] = 'sdwan_entries.' . $column;
		}

		return $added;
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
