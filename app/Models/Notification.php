<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\Database;
use App\Services\DatabaseSchema;
use PDO;

final class Notification
{
	public static function create(
		int $userId,
		string $type,
		string $title,
		string $message,
		?int $ticketId = null,
		string $priority = 'normal'
	): bool {
		if (!self::tableExists()) {
			return false;
		}

		$stmt = Database::pdo()->prepare(
			'INSERT INTO notifications (user_id, type, title, message, ticket_id, priority, is_read)
			 VALUES (:user_id, :type, :title, :message, :ticket_id, :priority, 0)'
		);

		return $stmt->execute([
			':user_id' => $userId,
			':type' => $type,
			':title' => $title,
			':message' => $message,
			':ticket_id' => $ticketId,
			':priority' => $priority,
		]);
	}

	public static function unreadCount(int $userId): int
	{
		if (!self::tableExists()) {
			return 0;
		}

		$stmt = Database::pdo()->prepare(
			'SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0'
		);
		$stmt->execute([':user_id' => $userId]);

		return (int) $stmt->fetchColumn();
	}

	/** @return list<array<string, mixed>> */
	public static function recent(int $userId, int $limit = 25): array
	{
		if (!self::tableExists()) {
			return [];
		}

		$limit = max(1, min($limit, 100));
		$stmt = Database::pdo()->prepare(
			'SELECT id, type, title, message, ticket_id, priority, is_read, created_at
			 FROM notifications
			 WHERE user_id = :user_id
			 ORDER BY created_at DESC
			 LIMIT ' . $limit
		);
		$stmt->execute([':user_id' => $userId]);
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

		return $rows ?: [];
	}

	public static function markRead(int $id, int $userId): bool
	{
		if (!self::tableExists()) {
			return false;
		}

		$stmt = Database::pdo()->prepare(
			'UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = :id AND user_id = :user_id'
		);

		return $stmt->execute([':id' => $id, ':user_id' => $userId]);
	}

	public static function markAllRead(int $userId): bool
	{
		if (!self::tableExists()) {
			return false;
		}

		$stmt = Database::pdo()->prepare(
			'UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = :user_id AND is_read = 0'
		);

		return $stmt->execute([':user_id' => $userId]);
	}

	private static function tableExists(): bool
	{
		try {
			return DatabaseSchema::tableExists(Database::pdo(), 'notifications');
		} catch (\Throwable $e) {
			return false;
		}
	}
}
