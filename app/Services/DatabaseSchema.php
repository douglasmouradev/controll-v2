<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class DatabaseSchema
{
	/** @var array<string, bool> */
	private static array $tableCache = [];

	public static function tableExists(PDO $pdo, string $table): bool
	{
		if (isset(self::$tableCache[$table])) {
			return self::$tableCache[$table];
		}

		$stmt = $pdo->query("SHOW TABLES LIKE '" . str_replace("'", "''", $table) . "'");
		self::$tableCache[$table] = (bool) $stmt->rowCount();

		return self::$tableCache[$table];
	}

	public static function columnExists(PDO $pdo, string $table, string $column): bool
	{
		$stmt = $pdo->query(
			"SHOW COLUMNS FROM `" . str_replace('`', '``', $table) . "` LIKE '" . str_replace("'", "''", $column) . "'"
		);

		return (bool) $stmt->rowCount();
	}

	public static function clearCache(): void
	{
		self::$tableCache = [];
	}
}
