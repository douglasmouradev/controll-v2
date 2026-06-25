<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\Database;
use App\Services\DatabaseSchema;
use App\Services\DateFormatter;
use App\Services\SdwanImageService;
use App\Services\StoreAddressService;
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

	public static function hasSourceColumns(): bool
	{
		try {
			return DatabaseSchema::columnExists(Database::pdo(), 'sdwan_entries', 'entry_source');
		} catch (\Throwable $e) {
			return false;
		}
	}

	/** @param array<string, mixed> $filters @return array<int, array<string, mixed>> */
	public static function storePanel(array $filters = []): array
	{
		if (!self::tableReady()) {
			return [];
		}

		$filter = self::buildFilterWhere($filters);
		$pdo = Database::pdo();
		$stmt = $pdo->prepare('
			SELECT
				e.loja,
				COUNT(*) AS registros,
				COALESCE(SUM(e.xpads_previsto), 0) AS xpads_previsto,
				COALESCE(SUM(e.quantidade_localizada), 0) AS quantidade_localizada
			FROM sdwan_entries e
			WHERE ' . $filter['where'] . '
			GROUP BY e.loja
			ORDER BY e.loja ASC
		');
		$stmt->execute($filter['params']);
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

		return array_map(static function (array $row): array {
			$previsto = (int) ($row['xpads_previsto'] ?? 0);
			$localizada = (int) ($row['quantidade_localizada'] ?? 0);
			$percent = $previsto > 0 ? min(100, (int) round(($localizada / $previsto) * 100)) : ($localizada > 0 ? 100 : 0);

			return [
				'loja' => (string) ($row['loja'] ?? ''),
				'registros' => (int) ($row['registros'] ?? 0),
				'xpads_previsto' => $previsto,
				'quantidade_localizada' => $localizada,
				'pendente' => max(0, $previsto - $localizada),
				'percent' => $percent,
			];
		}, $rows);
	}

	/** @return array{loja: string, pdv: string, source: string, date_from: string, date_to: string, page: int, per_page: int} */
	public static function filtersFromRequest(): array
	{
		return [
			'loja' => strtoupper(trim((string) ($_GET['loja'] ?? $_POST['loja_filter'] ?? ''))),
			'pdv' => trim((string) ($_GET['pdv'] ?? $_POST['pdv_filter'] ?? '')),
			'source' => trim((string) ($_GET['source'] ?? $_POST['source_filter'] ?? '')),
			'date_from' => trim((string) ($_GET['date_from'] ?? $_POST['date_from_filter'] ?? '')),
			'date_to' => trim((string) ($_GET['date_to'] ?? $_POST['date_to_filter'] ?? '')),
			'page' => max(1, (int) ($_GET['page'] ?? 1)),
			'per_page' => min(100, max(10, (int) ($_GET['per_page'] ?? 25))),
		];
	}

	/** @param array<string, mixed> $filters @return array{where: string, params: array<string, mixed>} */
	private static function buildFilterWhere(array $filters): array
	{
		$conditions = ['1 = 1'];
		$params = [];

		if (($filters['loja'] ?? '') !== '') {
			$conditions[] = 'e.loja LIKE :loja';
			$params[':loja'] = '%' . (string) $filters['loja'] . '%';
		}
		if (($filters['pdv'] ?? '') !== '') {
			$conditions[] = '(e.pdv_numero LIKE :pdv OR e.pdv_serie LIKE :pdv)';
			$params[':pdv'] = '%' . (string) $filters['pdv'] . '%';
		}
		if (($filters['source'] ?? '') !== '' && self::hasSourceColumns()) {
			$conditions[] = 'e.entry_source = :source';
			$params[':source'] = (string) $filters['source'];
		}
		if (($filters['date_from'] ?? '') !== '') {
			$conditions[] = 'DATE(e.created_at) >= :date_from';
			$params[':date_from'] = (string) $filters['date_from'];
		}
		if (($filters['date_to'] ?? '') !== '') {
			$conditions[] = 'DATE(e.created_at) <= :date_to';
			$params[':date_to'] = (string) $filters['date_to'];
		}

		return [
			'where' => implode(' AND ', $conditions),
			'params' => $params,
		];
	}

	/** @param array<string, mixed> $row */
	public static function enrichRow(array $row): array
	{
		$row['has_image'] = self::hasImageColumns() && !empty($row['image_path']);
		$row['image_url'] = $row['has_image'] ? SdwanImageService::imageUrl((int) ($row['id'] ?? 0)) : null;
		unset($row['image_path']);

		$source = (string) ($row['entry_source'] ?? 'dashboard');
		$row['entry_source'] = $source;
		$row['source_label'] = $source === 'public' ? 'Link técnico' : 'Dashboard';
		$row['created_at_formatted'] = DateFormatter::formatDateTime((string) ($row['created_at'] ?? ''));
		$row['created_by_name'] = (string) ($row['created_by_name'] ?? '-');
		$row['warning_localizada'] = (int) ($row['quantidade_localizada'] ?? 0) > (int) ($row['xpads_previsto'] ?? 0);

		return $row;
	}

	/** @return array<int, array<string, mixed>> */
	public static function listAll(int $limit = 500): array
	{
		$result = self::listFiltered(['page' => 1, 'per_page' => $limit]);

		return $result['entries'];
	}

	/**
	 * @param array<string, mixed> $filters
	 * @return array{entries: array<int, array<string, mixed>>, pagination: array<string, int>}
	 */
	public static function listFiltered(array $filters): array
	{
		if (!self::tableReady()) {
			return ['entries' => [], 'pagination' => self::emptyPagination($filters)];
		}

		$page = max(1, (int) ($filters['page'] ?? 1));
		$perPage = min(100, max(10, (int) ($filters['per_page'] ?? 25)));
		$offset = ($page - 1) * $perPage;
		$filter = self::buildFilterWhere($filters);
		$pdo = Database::pdo();

		$countStmt = $pdo->prepare('SELECT COUNT(*) FROM sdwan_entries e WHERE ' . $filter['where']);
		$countStmt->execute($filter['params']);
		$total = (int) $countStmt->fetchColumn();

		$stmt = $pdo->prepare('
			SELECT e.*, u.name AS created_by_name
			FROM sdwan_entries e
			LEFT JOIN users u ON u.id = e.created_by
			WHERE ' . $filter['where'] . '
			ORDER BY e.created_at DESC, e.id DESC
			LIMIT :limit OFFSET :offset
		');
		foreach ($filter['params'] as $key => $value) {
			$stmt->bindValue($key, $value);
		}
		$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
		$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
		$stmt->execute();

		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

		return [
			'entries' => array_map([self::class, 'enrichRow'], $rows),
			'pagination' => [
				'page' => $page,
				'per_page' => $perPage,
				'total' => $total,
				'total_pages' => max(1, (int) ceil($total / $perPage)),
			],
		];
	}

	/** @param array<string, mixed> $filters @return array<string, int> */
	private static function emptyPagination(array $filters): array
	{
		$perPage = min(100, max(10, (int) ($filters['per_page'] ?? 25)));

		return ['page' => 1, 'per_page' => $perPage, 'total' => 0, 'total_pages' => 1];
	}

	/** @param array<string, mixed> $filters @return array{total: int, xpads_previsto: int, quantidade_localizada: int, total_lojas: int} */
	public static function summary(array $filters = []): array
	{
		if (!self::tableReady()) {
			return ['total' => 0, 'xpads_previsto' => 0, 'quantidade_localizada' => 0, 'total_lojas' => 0];
		}

		$filter = self::buildFilterWhere($filters);
		$pdo = Database::pdo();
		$stmt = $pdo->prepare('
			SELECT
				COUNT(*) AS total,
				COALESCE(SUM(e.xpads_previsto), 0) AS xpads_previsto,
				COALESCE(SUM(e.quantidade_localizada), 0) AS quantidade_localizada,
				COUNT(DISTINCT e.loja) AS total_lojas
			FROM sdwan_entries e
			WHERE ' . $filter['where']
		);
		$stmt->execute($filter['params']);
		$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

		return [
			'total' => (int) ($row['total'] ?? 0),
			'xpads_previsto' => (int) ($row['xpads_previsto'] ?? 0),
			'quantidade_localizada' => (int) ($row['quantidade_localizada'] ?? 0),
			'total_lojas' => (int) ($row['total_lojas'] ?? 0),
		];
	}

	/** @param array<string, mixed> $filters @return array{labels: array<int, string>, data: array<int, int>, metric: string} */
	public static function pieChartByStore(array $filters = [], int $maxSlices = 10): array
	{
		if (!self::tableReady()) {
			return ['labels' => [], 'data' => [], 'metric' => 'quantidade_localizada'];
		}

		$filter = self::buildFilterWhere($filters);
		$pdo = Database::pdo();
		$stmt = $pdo->prepare('
			SELECT
				e.loja,
				COALESCE(SUM(e.quantidade_localizada), 0) AS total
			FROM sdwan_entries e
			WHERE ' . $filter['where'] . '
			GROUP BY e.loja
			HAVING total > 0
			ORDER BY total DESC, e.loja ASC
		');
		$stmt->execute($filter['params']);
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

		if ($rows === []) {
			return ['labels' => [], 'data' => [], 'metric' => 'quantidade_localizada'];
		}

		$labels = [];
		$data = [];
		$others = 0;

		foreach ($rows as $index => $row) {
			$value = (int) ($row['total'] ?? 0);
			if ($index < $maxSlices) {
				$labels[] = (string) ($row['loja'] ?? '');
				$data[] = $value;
			} else {
				$others += $value;
			}
		}

		if ($others > 0) {
			$labels[] = 'Outras';
			$data[] = $others;
		}

		return ['labels' => $labels, 'data' => $data, 'metric' => 'quantidade_localizada'];
	}

	/** @param array<string, mixed> $filters */
	public static function progressChart(array $filters = []): array
	{
		$summary = self::summary($filters);

		return [
			'labels' => ['Acupad previstos', 'Quantidade localizada'],
			'data' => [
				(int) ($summary['xpads_previsto'] ?? 0),
				(int) ($summary['quantidade_localizada'] ?? 0),
			],
		];
	}

	/** @param array<string, mixed> $filters */
	public static function chartPayload(array $filters = []): array
	{
		$chart = self::pieChartByStore($filters);

		return [
			'success' => true,
			'labels' => $chart['labels'],
			'data' => $chart['data'],
			'metric' => $chart['metric'],
			'progress' => self::progressChart($filters),
			'summary' => self::summary($filters),
		];
	}

	public static function findById(int $id): ?array
	{
		if (!self::tableReady() || $id <= 0) {
			return null;
		}

		$pdo = Database::pdo();
		$stmt = $pdo->prepare('
			SELECT e.*, u.name AS created_by_name
			FROM sdwan_entries e
			LEFT JOIN users u ON u.id = e.created_by
			WHERE e.id = :id
			LIMIT 1
		');
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

	public static function existsPdvInStore(string $loja, string $pdvNumero, ?int $excludeId = null): bool
	{
		if (!self::tableReady() || trim($pdvNumero) === '') {
			return false;
		}

		$pdo = Database::pdo();
		$sql = 'SELECT id FROM sdwan_entries WHERE loja = :loja AND pdv_numero = :pdv_numero';
		$params = [
			':loja' => strtoupper(trim($loja)),
			':pdv_numero' => trim($pdvNumero),
		];
		if ($excludeId !== null && $excludeId > 0) {
			$sql .= ' AND id <> :exclude_id';
			$params[':exclude_id'] = $excludeId;
		}
		$sql .= ' LIMIT 1';

		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);

		return (bool) $stmt->fetchColumn();
	}

	/** @param array<string, mixed> $meta */
	public static function create(array $data, ?int $createdBy = null, array $meta = []): int
	{
		if (!self::tableReady()) {
			throw new \RuntimeException('Tabela ACUPAD não configurada. Execute as migrations.');
		}

		$pdo = Database::pdo();
		$hasImage = self::hasImageColumns();
		$hasSource = self::hasSourceColumns();

		$columns = ['xpads_previsto', 'quantidade_localizada', 'pdv_numero', 'pdv_serie', 'loja', 'created_by'];
		$placeholders = [':xpads_previsto', ':quantidade_localizada', ':pdv_numero', ':pdv_serie', ':loja', ':created_by'];

		if ($hasImage) {
			array_push($columns, 'image_path', 'image_name', 'image_type', 'image_size');
			array_push($placeholders, ':image_path', ':image_name', ':image_type', ':image_size');
		}
		if ($hasSource) {
			array_push($columns, 'entry_source', 'access_link_id');
			array_push($placeholders, ':entry_source', ':access_link_id');
		}

		$sql = 'INSERT INTO sdwan_entries (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';

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
		if ($hasSource) {
			$params[':entry_source'] = (string) ($meta['entry_source'] ?? 'dashboard');
			$params[':access_link_id'] = isset($meta['access_link_id']) ? (int) $meta['access_link_id'] : null;
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
			? 'UPDATE sdwan_entries SET xpads_previsto = :xpads_previsto, quantidade_localizada = :quantidade_localizada,
				pdv_numero = :pdv_numero, pdv_serie = :pdv_serie, loja = :loja,
				image_path = :image_path, image_name = :image_name, image_type = :image_type, image_size = :image_size
				WHERE id = :id'
			: 'UPDATE sdwan_entries SET xpads_previsto = :xpads_previsto, quantidade_localizada = :quantidade_localizada,
				pdv_numero = :pdv_numero, pdv_serie = :pdv_serie, loja = :loja WHERE id = :id';

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

	/** @return array{success: bool, message?: string, data?: array<string, mixed>, warning?: string} */
	public static function validateInput(array $input, ?int $excludeId = null): array
	{
		$loja = strtoupper(trim((string) ($input['loja'] ?? '')));
		if ($loja === '') {
			return ['success' => false, 'message' => 'Informe a loja'];
		}

		if (!StoreAddressService::isValidSigla($loja)) {
			return ['success' => false, 'message' => 'Sigla de loja inválida. Use uma sigla da planilha de lojas.'];
		}

		$xpadsPrevisto = max(0, (int) ($input['xpads_previsto'] ?? 0));
		$quantidadeLocalizada = max(0, (int) ($input['quantidade_localizada'] ?? 0));
		$pdvNumero = trim((string) ($input['pdv_numero'] ?? ''));
		$pdvSerie = trim((string) ($input['pdv_serie'] ?? ''));

		if ($pdvNumero !== '' && self::existsPdvInStore($loja, $pdvNumero, $excludeId)) {
			return ['success' => false, 'message' => 'Este Nº PDV já está cadastrado para a loja ' . $loja];
		}

		$result = [
			'success' => true,
			'data' => [
				'xpads_previsto' => $xpadsPrevisto,
				'quantidade_localizada' => $quantidadeLocalizada,
				'pdv_numero' => $pdvNumero,
				'pdv_serie' => $pdvSerie,
				'loja' => $loja,
			],
		];

		if ($quantidadeLocalizada > $xpadsPrevisto && $xpadsPrevisto > 0) {
			$result['warning'] = 'A quantidade localizada é maior que a quantidade prevista de Acupad.';
		}

		return $result;
	}

	/** @param array<string, mixed> $filters @return array<int, array<string, mixed>> */
	public static function exportRows(array $filters = []): array
	{
		if (!self::tableReady()) {
			return [];
		}

		$filter = self::buildFilterWhere($filters);
		$pdo = Database::pdo();
		$sourceSelect = self::hasSourceColumns() ? ', e.entry_source' : '';
		$imageSelect = self::hasImageColumns() ? ', e.id, e.image_path' : ', e.id';
		$stmt = $pdo->prepare('
			SELECT e.loja, e.xpads_previsto, e.quantidade_localizada, e.pdv_numero, e.pdv_serie, e.created_at,
				u.name AS created_by_name' . $sourceSelect . $imageSelect . '
			FROM sdwan_entries e
			LEFT JOIN users u ON u.id = e.created_by
			WHERE ' . $filter['where'] . '
			ORDER BY e.created_at DESC, e.id DESC
		');
		$stmt->execute($filter['params']);

		return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
	}
}
