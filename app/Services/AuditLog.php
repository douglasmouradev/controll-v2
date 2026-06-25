<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\Database;
use PDO;

final class AuditLog
{
	public static function record(
		string $action,
		?string $resource = null,
		bool $success = true,
		?int $userId = null
	): void {
		try {
			$pdo = Database::pdo();
			if (!DatabaseSchema::tableExists($pdo, 'access_logs')) {
				return;
			}

			if ($userId === null) {
				$sessionUser = Auth::instance()->user();
				$userId = $sessionUser ? (int) ($sessionUser['id'] ?? 0) : null;
				if ($userId === 0) {
					$userId = null;
				}
			}

			$stmt = $pdo->prepare(
				'INSERT INTO access_logs (user_id, ip_address, user_agent, action, resource, success)
				 VALUES (:user_id, :ip, :ua, :action, :resource, :success)'
			);
			$stmt->execute([
				':user_id' => $userId,
				':ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
				':ua' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
				':action' => substr($action, 0, 100),
				':resource' => $resource !== null ? substr($resource, 0, 255) : null,
				':success' => $success ? 1 : 0,
			]);
		} catch (\Throwable $e) {
			error_log('AuditLog::record: ' . $e->getMessage());
		}
	}

	/** @return array<int, array<string, mixed>> */
	public static function recent(int $limit = 100): array
	{
		$limit = max(1, min($limit, 500));

		try {
			$pdo = Database::pdo();
			if (!DatabaseSchema::tableExists($pdo, 'access_logs')) {
				return [];
			}

			$stmt = $pdo->prepare(
				'SELECT al.id, al.user_id, al.ip_address, al.action, al.resource, al.success, al.created_at, u.name AS user_name
				 FROM access_logs al
				 LEFT JOIN users u ON u.id = al.user_id
				 ORDER BY al.created_at DESC
				 LIMIT ' . $limit
			);
			$stmt->execute();

			return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
		} catch (\Throwable $e) {
			error_log('AuditLog::recent: ' . $e->getMessage());

			return [];
		}
	}

	/** @return array<int, array<string, mixed>> */
	public static function recentByActionPrefix(string $prefix, int $limit = 50): array
	{
		$prefix = trim($prefix);
		if ($prefix === '') {
			return [];
		}

		$limit = max(1, min($limit, 200));

		try {
			$pdo = Database::pdo();
			if (!DatabaseSchema::tableExists($pdo, 'access_logs')) {
				return [];
			}

			$stmt = $pdo->prepare(
				'SELECT al.id, al.user_id, al.ip_address, al.action, al.resource, al.success, al.created_at, u.name AS user_name
				 FROM access_logs al
				 LEFT JOIN users u ON u.id = al.user_id
				 WHERE al.action LIKE :prefix
				 ORDER BY al.created_at DESC
				 LIMIT ' . $limit
			);
			$stmt->execute([':prefix' => $prefix . '%']);

			return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
		} catch (\Throwable $e) {
			error_log('AuditLog::recentByActionPrefix: ' . $e->getMessage());

			return [];
		}
	}
}
