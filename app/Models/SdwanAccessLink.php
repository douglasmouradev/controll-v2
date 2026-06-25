<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\Database;
use App\Services\DatabaseSchema;
use App\Services\SdwanSettings;
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

	public static function hasSubmissionColumns(): bool
	{
		try {
			return DatabaseSchema::columnExists(Database::pdo(), 'sdwan_access_links', 'submission_count');
		} catch (\Throwable $e) {
			return false;
		}
	}

	/** @param array<string, mixed> $row */
	public static function canAcceptSubmission(array $row): bool
	{
		if (strtotime((string) ($row['expires_at'] ?? '')) <= time()) {
			return false;
		}

		if (!self::hasSubmissionColumns()) {
			return true;
		}

		$max = (int) ($row['max_submissions'] ?? SdwanSettings::linkMaxSubmissions());
		$count = (int) ($row['submission_count'] ?? 0);

		return $count < max(1, $max);
	}

	public static function incrementSubmission(int $linkId): bool
	{
		if (!self::tableReady() || $linkId <= 0 || !self::hasSubmissionColumns()) {
			return true;
		}

		$pdo = Database::pdo();
		$stmt = $pdo->prepare('
			UPDATE sdwan_access_links
			SET submission_count = submission_count + 1
			WHERE id = :id AND expires_at > NOW()
		');

		return $stmt->execute([':id' => $linkId]);
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
		return self::appBaseUrl() . '/acupad/cadastro/' . $code;
	}

	public static function qrCodeUrl(int $linkId): string
	{
		return '/dashboard/sdwan-access-link/qr?id=' . max(0, $linkId);
	}

	public static function fetchQrImage(string $url): ?string
	{
		$endpoint = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . rawurlencode($url);
		$context = stream_context_create([
			'http' => [
				'timeout' => 8,
				'user_agent' => 'ControllIT-ACUPAD/1.0',
			],
		]);
		$image = @file_get_contents($endpoint, false, $context);
		if ($image !== false && $image !== '') {
			return $image;
		}

		if (!function_exists('curl_init')) {
			return null;
		}

		$ch = curl_init($endpoint);
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_TIMEOUT => 8,
			CURLOPT_USERAGENT => 'ControllIT-ACUPAD/1.0',
		]);
		$image = curl_exec($ch);
		$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		return ($image !== false && $status >= 200 && $status < 300) ? (string) $image : null;
	}

	/** @return array<string, mixed>|null */
	public static function findById(int $id): ?array
	{
		if (!self::tableReady() || $id <= 0) {
			return null;
		}

		$pdo = Database::pdo();
		$stmt = $pdo->prepare('SELECT * FROM sdwan_access_links WHERE id = :id LIMIT 1');
		$stmt->execute([':id' => $id]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return $row ?: null;
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

	/** @return array<int, array<string, mixed>> */
	public static function listActive(?int $createdBy = null, int $limit = 20): array
	{
		if (!self::tableReady()) {
			return [];
		}

		$pdo = Database::pdo();
		$sql = 'SELECT * FROM sdwan_access_links WHERE expires_at > NOW()';
		$params = [];
		if ($createdBy !== null && $createdBy > 0) {
			$sql .= ' AND created_by = :created_by';
			$params[':created_by'] = $createdBy;
		}
		$sql .= ' ORDER BY id DESC LIMIT :limit';

		$stmt = $pdo->prepare($sql);
		foreach ($params as $key => $value) {
			$stmt->bindValue($key, $value, PDO::PARAM_INT);
		}
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->execute();

		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

		return array_map([self::class, 'present'], $rows);
	}

	public static function revoke(int $id, ?int $userId = null): bool
	{
		if (!self::tableReady() || $id <= 0) {
			return false;
		}

		$pdo = Database::pdo();
		$sql = 'UPDATE sdwan_access_links SET expires_at = NOW() WHERE id = :id AND expires_at > NOW()';
		$params = [':id' => $id];
		if ($userId !== null && $userId > 0) {
			$sql .= ' AND created_by = :created_by';
			$params[':created_by'] = $userId;
		}

		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);

		return $stmt->rowCount() > 0;
	}

	/** @return array{success: bool, message?: string, link?: array<string, mixed>} */
	public static function generate(?int $createdBy = null): array
	{
		if (!self::tableReady()) {
			return ['success' => false, 'message' => 'Tabela de links ACUPAD não configurada. Execute as migrations.'];
		}

		$pdo = Database::pdo();
		$ttlHours = SdwanSettings::linkTtlHours();
		$expiresAt = (new \DateTimeImmutable('+' . $ttlHours . ' hours'))->format('Y-m-d H:i:s');
		$maxSubmissions = SdwanSettings::linkMaxSubmissions();
		$hasSubmission = self::hasSubmissionColumns();

		for ($attempt = 0; $attempt < 30; $attempt++) {
			$code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
			if (self::findActiveByCode($code) !== null) {
				continue;
			}

			$sql = $hasSubmission
				? 'INSERT INTO sdwan_access_links (code, created_by, expires_at, max_submissions) VALUES (:code, :created_by, :expires_at, :max_submissions)'
				: 'INSERT INTO sdwan_access_links (code, created_by, expires_at) VALUES (:code, :created_by, :expires_at)';
			$stmt = $pdo->prepare($sql);

			try {
				$params = [
					':code' => $code,
					':created_by' => $createdBy,
					':expires_at' => $expiresAt,
				];
				if ($hasSubmission) {
					$params[':max_submissions'] = $maxSubmissions;
				}
				$stmt->execute($params);
			} catch (\PDOException $e) {
				continue;
			}

			$id = (int) $pdo->lastInsertId();
			$row = $pdo->query('SELECT * FROM sdwan_access_links WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);

			return [
				'success' => true,
				'link' => $row ? self::present($row) : [
					'id' => $id,
					'code' => $code,
					'url' => self::buildPublicUrl($code),
					'qr_url' => self::qrCodeUrl($id),
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
		$url = self::buildPublicUrl($code);
		$max = (int) ($row['max_submissions'] ?? SdwanSettings::linkMaxSubmissions());
		$count = (int) ($row['submission_count'] ?? 0);

		return [
			'id' => (int) ($row['id'] ?? 0),
			'code' => $code,
			'url' => $url,
			'qr_url' => self::qrCodeUrl((int) ($row['id'] ?? 0)),
			'expires_at' => $expiresAt,
			'created_at' => (string) ($row['created_at'] ?? ''),
			'submission_count' => $count,
			'max_submissions' => $max,
			'submissions_remaining' => max(0, $max - $count),
		];
	}
}
