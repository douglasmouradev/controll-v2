<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\Database;
use PDO;

final class TicketAttachment
{
	public static function create(array $data): int
	{
		self::ensureTableExists();
		$sql = 'INSERT INTO ticket_attachments (ticket_id, file_path, file_name, file_type, file_size, uploaded_by)
				VALUES (:ticket_id, :file_path, :file_name, :file_type, :file_size, :uploaded_by)';
		$stmt = Database::pdo()->prepare($sql);
		$stmt->execute([
			':ticket_id' => (int) $data['ticket_id'],
			':file_path' => $data['file_path'],
			':file_name' => $data['file_name'],
			':file_type' => $data['file_type'],
			':file_size' => (int) $data['file_size'],
			':uploaded_by' => (int) $data['uploaded_by'],
		]);
		return (int) Database::pdo()->lastInsertId();
	}

	public static function findByTicket(int $ticketId): array
	{
		self::ensureTableExists();
		$sql = 'SELECT a.*, u.name AS uploaded_by_name
				FROM ticket_attachments a
				LEFT JOIN users u ON u.id = a.uploaded_by
				WHERE a.ticket_id = :ticket_id
				ORDER BY a.created_at DESC';
		$stmt = Database::pdo()->prepare($sql);
		$stmt->execute([':ticket_id' => $ticketId]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public static function find(int $id): ?array
	{
		self::ensureTableExists();
		$sql = 'SELECT * FROM ticket_attachments WHERE id = :id LIMIT 1';
		$stmt = Database::pdo()->prepare($sql);
		$stmt->execute([':id' => $id]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ?: null;
	}

	public static function delete(int $id): bool
	{
		self::ensureTableExists();
		$sql = 'SELECT file_path FROM ticket_attachments WHERE id = :id';
		$stmt = Database::pdo()->prepare($sql);
		$stmt->execute([':id' => $id]);
		$attachment = $stmt->fetch(PDO::FETCH_ASSOC);
		
		if ($attachment && !empty($attachment['file_path'])) {
			$webPath = (string) $attachment['file_path'];
			$basePath = dirname(__DIR__, 2) . '/public';
			$fsPath = $webPath[0] === '/' ? $basePath . $webPath : $basePath . '/' . $webPath;
			if (is_file($fsPath)) {
				@unlink($fsPath);
			}
		}
		
		$sql = 'DELETE FROM ticket_attachments WHERE id = :id';
		$stmt = Database::pdo()->prepare($sql);
		return $stmt->execute([':id' => $id]);
	}

	private static function ensureTableExists(): void
	{
		static $checked = false;
		if ($checked) {
			return;
		}
		$checked = true;

		// Cria tabela de anexos caso não exista no ambiente atual.
		$sql = "CREATE TABLE IF NOT EXISTS ticket_attachments (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			ticket_id INT UNSIGNED NOT NULL,
			file_path VARCHAR(255) NOT NULL,
			file_name VARCHAR(255) NOT NULL,
			file_type VARCHAR(100) NULL,
			file_size INT UNSIGNED NULL,
			uploaded_by INT UNSIGNED NULL,
			created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			INDEX idx_ticket_attachments_ticket_id (ticket_id),
			INDEX idx_ticket_attachments_uploaded_by (uploaded_by)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

		try {
			Database::pdo()->exec($sql);
		} catch (\Throwable $e) {
			// Não interrompe o fluxo aqui; o erro original da operação será tratado acima.
		}
	}
}


