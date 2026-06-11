<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class DatabaseSchema
{
	public static function tableExists(PDO $pdo, string $table): bool
	{
		$stmt = $pdo->query("SHOW TABLES LIKE '" . str_replace("'", "''", $table) . "'");

		return (bool) $stmt->rowCount();
	}

	public static function columnExists(PDO $pdo, string $table, string $column): bool
	{
		$stmt = $pdo->query(
			"SHOW COLUMNS FROM `" . str_replace('`', '``', $table) . "` LIKE '" . str_replace("'", "''", $column) . "'"
		);

		return (bool) $stmt->rowCount();
	}
}
