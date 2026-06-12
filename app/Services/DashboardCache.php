<?php
declare(strict_types=1);

namespace App\Services;

final class DashboardCache
{
	private static function versionFile(): string
	{
		$base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);

		return $base . '/storage/cache/dashboard_stats.version';
	}

	public static function statsVersion(): int
	{
		$file = self::versionFile();
		if (!is_file($file)) {
			return 1;
		}

		return max(1, (int) @file_get_contents($file));
	}

	public static function invalidateStats(): void
	{
		$dir = dirname(self::versionFile());
		if (!is_dir($dir)) {
			@mkdir($dir, 0775, true);
		}
		@file_put_contents(self::versionFile(), (string) time(), LOCK_EX);
	}

	public static function statsKey(string $prefix, array $user): string
	{
		return $prefix . ':'
			. (int) ($user['id'] ?? 0) . ':'
			. TicketAccess::normalizeRole((string) ($user['role'] ?? ''))
			. ':v' . self::statsVersion();
	}
}
