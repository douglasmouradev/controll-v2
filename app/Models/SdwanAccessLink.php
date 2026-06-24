<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\Database;
use App\Services\DatabaseSchema;
use PDO;

final class SdwanAccessLink
{
	public static function tableReady(): bool
	{
		try {
			return DatabaseSchema::tableExists(Database::pdo(), 'sdwan_access_links');
		} catch (\Throwable $e) {
			return false;
		}
	}

	public static function appBaseUrl(): string
	{
		$base = rtrim((string) (getenv('APP_URL') ?: ''), '/');
		if ($base !== '') {
			return $base;
		}

		$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
		$host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

		return $scheme . '://' . $host;
	}

	public static function buildPublicUrl(string $code): string
	{
		return self::appBaseUrl() . '/sdwan/cadastro/' . $code;
	}

	/** @return array<string, mixed>|null */
	public static function findActiveByCode(string $code): ?array
	{
		if (!self::tableReady()) {
			return null;
		}

		$code = self::normalizeCode($code);
		if ($code === '') {
			return null;
		}

		$pdo = Database::pdo();
		$stmt = $pdo->prepare('
			SELECT *
			FROM sdwan_access_links
			WHERE code = :code AND expires_at > NOW()
			ORDER BY id DESC
			LIMIT 1
		');
		$stmt->execute([':code' => $code]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return $row ?: null;
	}

	/** @return array<string, mixed>|null */
	public static function getLatestActiveForUser(int $userId): ?array
	{
		if (!self::tableReady() || $userId <= 0) {
			return null;
		}

		$pdo = Database::pdo();
		$stmt = $pdo->prepare('
			SELECT *
			FROM sdwan_access_links
			WHERE created_by = :created_by AND expires_at > NOW()
			ORDER BY id DESC
			LIMIT 1
		');
		$stmt->execute([':created_by' => $userId]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return $row ? self::present($row) : null;
	}

	/** @return array{success: bool, message?: string, link?: array<string, mixed>} */
	public static function generate(?int $createdBy = null): array
	{
		if (!self::tableReady()) {
			return ['success' => false, 'message' => 'Tabela de links SDWAN não configurada. Execute as migrations.'];
		}

		$pdo = Database::pdo();
		$expiresAt = (new \DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s');

		for ($attempt = 0; $attempt < 30; $attempt++) {
			$code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
			if (self::findActiveByCode($code) !== null) {
				continue;
			}

			$stmt = $pdo->prepare('
				INSERT INTO sdwan_access_links (code, created_by, expires_at)
				VALUES (:code, :created_by, :expires_at)
			');

			try {
				$stmt->execute([
					':code' => $code,
					':created_by' => $createdBy,
					':expires_at' => $expiresAt,
				]);
			} catch (\PDOException $e) {
				continue;
			}

			$id = (int) $pdo->lastInsertId();
			$row = $pdo->query('SELECT * FROM sdwan_access_links WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);

			return [
				'success' => true,
				'link' => $row ? self::present($row) : [
					'code' => $code,
					'url' => self::buildPublicUrl($code),
					'expires_at' => $expiresAt,
				],
			];
		}

		return ['success' => false, 'message' => 'Não foi possível gerar um código único. Tente novamente.'];
	}

	public static function normalizeCode(string $code): string
	{
		$code = preg_replace('/\D+/', '', $code) ?? '';
		if (strlen($code) !== 4) {
			return '';
		}

		return $code;
	}

	/** @param array<string, mixed> $row */
	public static function present(array $row): array
	{
		$code = self::normalizeCode((string) ($row['code'] ?? ''));
		$expiresAt = (string) ($row['expires_at'] ?? '');

		return [
			'id' => (int) ($row['id'] ?? 0),
			'code' => $code,
			'url' => self::buildPublicUrl($code),
			'expires_at' => $expiresAt,
			'created_at' => (string) ($row['created_at'] ?? ''),
		];
	}
}
