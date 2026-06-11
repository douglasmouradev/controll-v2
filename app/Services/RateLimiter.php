<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class RateLimiter
{
	public static function tooManyAttempts(string $identifier, int $maxAttempts = 5, int $decayMinutes = 15): bool
	{
		try {
			$pdo = Database::pdo();
			if (!self::tableExists($pdo)) {
				return false;
			}
			$cutoff = date('Y-m-d H:i:s', time() - ($decayMinutes * 60));
			$stmt = $pdo->prepare(
				'SELECT COUNT(*) FROM rate_limits WHERE identifier = :id AND created_at > :cutoff'
			);
			$stmt->execute([':id' => $identifier, ':cutoff' => $cutoff]);
			return (int) $stmt->fetchColumn() >= $maxAttempts;
		} catch (\Throwable $e) {
			error_log('RateLimiter::tooManyAttempts: ' . $e->getMessage());
			return false;
		}
	}

	public static function hit(string $identifier, string $ipAddress): void
	{
		try {
			$pdo = Database::pdo();
			if (!self::tableExists($pdo)) {
				return;
			}
			$stmt = $pdo->prepare(
				'INSERT INTO rate_limits (identifier, ip_address) VALUES (:id, :ip)'
			);
			$stmt->execute([':id' => $identifier, ':ip' => $ipAddress]);
		} catch (\Throwable $e) {
			error_log('RateLimiter::hit: ' . $e->getMessage());
		}
	}

	public static function clear(string $identifier): void
	{
		try {
			$pdo = Database::pdo();
			if (!self::tableExists($pdo)) {
				return;
			}
			$stmt = $pdo->prepare('DELETE FROM rate_limits WHERE identifier = :id');
			$stmt->execute([':id' => $identifier]);
		} catch (\Throwable $e) {
			error_log('RateLimiter::clear: ' . $e->getMessage());
		}
	}

	private static function tableExists(PDO $pdo): bool
	{
		static $exists = null;
		if ($exists !== null) {
			return $exists;
		}
		$stmt = $pdo->query("SHOW TABLES LIKE 'rate_limits'");
		$exists = $stmt !== false && $stmt->rowCount() > 0;
		return $exists;
	}
}
