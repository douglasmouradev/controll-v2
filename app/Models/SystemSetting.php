<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\Database;
use PDO;

final class SystemSetting
{
	public static function get(string $key, ?string $default = null): ?string
	{
		try {
			$stmt = Database::pdo()->prepare(
				'SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1'
			);
			$stmt->execute([$key]);
			$row = $stmt->fetch(PDO::FETCH_ASSOC);

			return $row ? (string) $row['setting_value'] : $default;
		} catch (\Throwable) {
			return $default;
		}
	}

	public static function set(string $key, string $value, ?string $description = null): bool
	{
		try {
			$pdo = Database::pdo();
			$stmt = $pdo->prepare(
				'INSERT INTO system_settings (setting_key, setting_value, description)
				 VALUES (?, ?, ?)
				 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP'
			);
			$stmt->execute([$key, $value, $description ?? '']);

			return true;
		} catch (\Throwable) {
			return false;
		}
	}

	public static function isEnabled(string $key): bool
	{
		$value = strtolower((string) self::get($key, '0'));

		return in_array($value, ['1', 'true', 'yes', 'on'], true);
	}
}
