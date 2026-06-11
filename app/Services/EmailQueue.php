<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\DatabaseSchema;
use PDO;

final class EmailQueue
{
	private const MAX_ATTEMPTS = 5;

	public static function isAvailable(): bool
	{
		try {
			$pdo = Database::pdo();

			return DatabaseSchema::tableExists($pdo, 'email_queue');
		} catch (\Throwable $e) {
			return false;
		}
	}

	public static function enqueue(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool
	{
		if (!self::isAvailable()) {
			return Mail::send($to, $subject, $htmlBody, $textBody);
		}

		$to = trim($to);
		if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
			return false;
		}

		$stmt = Database::pdo()->prepare(
			'INSERT INTO email_queue (recipient, subject, html_body, text_body, status, attempts)
			 VALUES (:recipient, :subject, :html_body, :text_body, :status, 0)'
		);

		return $stmt->execute([
			':recipient' => $to,
			':subject' => $subject,
			':html_body' => $htmlBody,
			':text_body' => $textBody,
			':status' => 'pending',
		]);
	}

	/** @return array{sent: int, failed: int, skipped: int} */
	public static function process(int $limit = 25): array
	{
		$result = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
		if (!self::isAvailable()) {
			return $result;
		}

		$pdo = Database::pdo();
		$stmt = $pdo->prepare(
			'SELECT id, recipient, subject, html_body, text_body, attempts
			 FROM email_queue
			 WHERE status = :status AND attempts < :max_attempts
			 ORDER BY id ASC
			 LIMIT ' . (int) max(1, min($limit, 100))
		);
		$stmt->execute([':status' => 'pending', ':max_attempts' => self::MAX_ATTEMPTS]);
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

		foreach ($rows as $row) {
			$id = (int) $row['id'];
			$attempts = (int) $row['attempts'] + 1;
			$sent = Mail::send(
				(string) $row['recipient'],
				(string) $row['subject'],
				(string) $row['html_body'],
				$row['text_body'] !== null ? (string) $row['text_body'] : null
			);

			if ($sent) {
				$update = $pdo->prepare(
					'UPDATE email_queue SET status = :status, attempts = :attempts, sent_at = NOW(), last_error = NULL WHERE id = :id'
				);
				$update->execute([':status' => 'sent', ':attempts' => $attempts, ':id' => $id]);
				$result['sent']++;
				continue;
			}

			$status = $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending';
			$update = $pdo->prepare(
				'UPDATE email_queue SET status = :status, attempts = :attempts, last_error = :error WHERE id = :id'
			);
			$update->execute([
				':status' => $status,
				':attempts' => $attempts,
				':error' => 'Falha no envio',
				':id' => $id,
			]);
			$result[$status === 'failed' ? 'failed' : 'skipped']++;
		}

		return $result;
	}
}
