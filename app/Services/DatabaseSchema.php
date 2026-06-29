<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class DatabaseSchema
{
	/** @var array<string, bool> */
	private static array $tableCache = [];

	/** @var array<string, bool> */
	private static array $columnCache = [];

	public static function tableExists(PDO $pdo, string $table): bool
	{
		if (isset(self::$tableCache[$table])) {
			return self::$tableCache[$table];
		}

		$stmt = $pdo->prepare(
			'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
			 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
		);
		$stmt->execute([$table]);
		self::$tableCache[$table] = ((int) $stmt->fetchColumn()) > 0;

		return self::$tableCache[$table];
	}

	public static function columnExists(PDO $pdo, string $table, string $column): bool
	{
		$key = $table . '.' . $column;
		if (isset(self::$columnCache[$key])) {
			return self::$columnCache[$key];
		}

		$stmt = $pdo->prepare(
			'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
		);
		$stmt->execute([$table, $column]);
		self::$columnCache[$key] = ((int) $stmt->fetchColumn()) > 0;

		return self::$columnCache[$key];
	}

	public static function clearCache(): void
	{
		self::$tableCache = [];
		self::$columnCache = [];
	}
}
