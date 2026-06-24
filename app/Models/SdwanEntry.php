<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\Database;
use App\Services\DatabaseSchema;
use App\Services\SdwanImageService;
use PDO;

final class SdwanEntry
{
	public static function tableReady(): bool
	{
		try {
			return DatabaseSchema::tableExists(Database::pdo(), 'sdwan_entries');
		} catch (\Throwable $e) {
			error_log('Erro ao verificar tabela sdwan_entries: ' . $e->getMessage());
			return false;
		}
	}

	public static function hasImageColumns(): bool
	{
		try {
			return DatabaseSchema::columnExists(Database::pdo(), 'sdwan_entries', 'image_path');
		} catch (\Throwable $e) {
			return false;
		}
	}

	/** @param array<string, mixed> $row */
	public static function enrichRow(array $row): array
	{
		$row['has_image'] = self::hasImageColumns() && !empty($row['image_path']);
		$row['image_url'] = $row['has_image'] ? SdwanImageService::imageUrl((int) ($row['id'] ?? 0)) : null;
		unset($row['image_path']);

		return $row;
	}

	/** @return array<int, array<string, mixed>> */
	public static function listAll(int $limit = 500): array
	{
		if (!self::tableReady()) {
			return [];
		}

		$pdo = Database::pdo();
		$stmt = $pdo->prepare('
			SELECT e.*, u.name AS created_by_name
			FROM sdwan_entries e
			LEFT JOIN users u ON u.id = e.created_by
			ORDER BY e.created_at DESC, e.id DESC
			LIMIT :limit
		');
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->execute();

		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

		return array_map([self::class, 'enrichRow'], $rows);
	}

	/** @return array{total: int, xpads_previsto: int, quantidade_localizada: int} */
	public static function summary(): array
	{
		if (!self::tableReady()) {
			return ['total' => 0, 'xpads_previsto' => 0, 'quantidade_localizada' => 0];
		}

		$pdo = Database::pdo();
		$row = $pdo->query('
			SELECT
				COUNT(*) AS total,
				COALESCE(SUM(xpads_previsto), 0) AS xpads_previsto,
				COALESCE(SUM(quantidade_localizada), 0) AS quantidade_localizada
			FROM sdwan_entries
		')->fetch(PDO::FETCH_ASSOC) ?: [];

		return [
			'total' => (int) ($row['total'] ?? 0),
			'xpads_previsto' => (int) ($row['xpads_previsto'] ?? 0),
			'quantidade_localizada' => (int) ($row['quantidade_localizada'] ?? 0),
		];
	}

	public static function findById(int $id): ?array
	{
		if (!self::tableReady() || $id <= 0) {
			return null;
		}

		$pdo = Database::pdo();
		$stmt = $pdo->prepare('SELECT * FROM sdwan_entries WHERE id = :id LIMIT 1');
		$stmt->execute([':id' => $id]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return $row ? self::enrichRow($row) : null;
	}

	public static function findRawById(int $id): ?array
	{
		if (!self::tableReady() || $id <= 0) {
			return null;
		}

		$pdo = Database::pdo();
		$stmt = $pdo->prepare('SELECT * FROM sdwan_entries WHERE id = :id LIMIT 1');
		$stmt->execute([':id' => $id]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return $row ?: null;
	}

	public static function create(array $data, ?int $createdBy = null): int
	{
		if (!self::tableReady()) {
			throw new \RuntimeException('Tabela SDWAN não configurada. Execute as migrations.');
		}

		$pdo = Database::pdo();
		$hasImage = self::hasImageColumns();
		$sql = $hasImage
			? 'INSERT INTO sdwan_entries
				(xpads_previsto, quantidade_localizada, pdv_numero, pdv_serie, loja, image_path, image_name, image_type, image_size, created_by)
				VALUES
				(:xpads_previsto, :quantidade_localizada, :pdv_numero, :pdv_serie, :loja, :image_path, :image_name, :image_type, :image_size, :created_by)'
			: 'INSERT INTO sdwan_entries
				(xpads_previsto, quantidade_localizada, pdv_numero, pdv_serie, loja, created_by)
				VALUES
				(:xpads_previsto, :quantidade_localizada, :pdv_numero, :pdv_serie, :loja, :created_by)';

		$params = [
			':xpads_previsto' => (int) ($data['xpads_previsto'] ?? 0),
			':quantidade_localizada' => (int) ($data['quantidade_localizada'] ?? 0),
			':pdv_numero' => (string) ($data['pdv_numero'] ?? ''),
			':pdv_serie' => (string) ($data['pdv_serie'] ?? ''),
			':loja' => (string) ($data['loja'] ?? ''),
			':created_by' => $createdBy,
		];
		if ($hasImage) {
			$params[':image_path'] = $data['image_path'] ?? null;
			$params[':image_name'] = $data['image_name'] ?? null;
			$params[':image_type'] = $data['image_type'] ?? null;
			$params[':image_size'] = isset($data['image_size']) ? (int) $data['image_size'] : null;
		}

		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);

		return (int) $pdo->lastInsertId();
	}

	public static function update(int $id, array $data): bool
	{
		if (!self::tableReady() || $id <= 0) {
			return false;
		}

		$pdo = Database::pdo();
		$hasImage = self::hasImageColumns();
		$sql = $hasImage
			? 'UPDATE sdwan_entries
				SET
					xpads_previsto = :xpads_previsto,
					quantidade_localizada = :quantidade_localizada,
					pdv_numero = :pdv_numero,
					pdv_serie = :pdv_serie,
					loja = :loja,
					image_path = :image_path,
					image_name = :image_name,
					image_type = :image_type,
					image_size = :image_size
				WHERE id = :id'
			: 'UPDATE sdwan_entries
				SET
					xpads_previsto = :xpads_previsto,
					quantidade_localizada = :quantidade_localizada,
					pdv_numero = :pdv_numero,
					pdv_serie = :pdv_serie,
					loja = :loja
				WHERE id = :id';

		$params = [
			':id' => $id,
			':xpads_previsto' => (int) ($data['xpads_previsto'] ?? 0),
			':quantidade_localizada' => (int) ($data['quantidade_localizada'] ?? 0),
			':pdv_numero' => (string) ($data['pdv_numero'] ?? ''),
			':pdv_serie' => (string) ($data['pdv_serie'] ?? ''),
			':loja' => (string) ($data['loja'] ?? ''),
		];
		if ($hasImage) {
			$params[':image_path'] = $data['image_path'] ?? null;
			$params[':image_name'] = $data['image_name'] ?? null;
			$params[':image_type'] = $data['image_type'] ?? null;
			$params[':image_size'] = isset($data['image_size']) ? (int) $data['image_size'] : null;
		}

		$stmt = $pdo->prepare($sql);

		return $stmt->execute($params);
	}

	public static function delete(int $id): bool
	{
		if (!self::tableReady() || $id <= 0) {
			return false;
		}

		$entry = self::findRawById($id);
		if ($entry && self::hasImageColumns() && !empty($entry['image_path'])) {
			SdwanImageService::deleteImage((string) $entry['image_path']);
		}

		$pdo = Database::pdo();
		$stmt = $pdo->prepare('DELETE FROM sdwan_entries WHERE id = :id');

		return $stmt->execute([':id' => $id]);
	}

	/** @return array{success: bool, message?: string, data?: array<string, mixed>} */
	public static function validateInput(array $input): array
	{
		$loja = strtoupper(trim((string) ($input['loja'] ?? '')));
		if ($loja === '') {
			return ['success' => false, 'message' => 'Informe a loja'];
		}

		$xpadsPrevisto = max(0, (int) ($input['xpads_previsto'] ?? 0));
		$quantidadeLocalizada = max(0, (int) ($input['quantidade_localizada'] ?? 0));
		$pdvNumero = trim((string) ($input['pdv_numero'] ?? ''));
		$pdvSerie = trim((string) ($input['pdv_serie'] ?? ''));

		return [
			'success' => true,
			'data' => [
				'xpads_previsto' => $xpadsPrevisto,
				'quantidade_localizada' => $quantidadeLocalizada,
				'pdv_numero' => $pdvNumero,
				'pdv_serie' => $pdvSerie,
				'loja' => $loja,
			],
		];
	}
}
