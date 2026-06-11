<?php
declare(strict_types=1);

namespace App\Services;

final class PurchasedDailies
{
	private const STORAGE_DIR = '/storage/uploads/purchased_dailies';
	private const POINTER_FILE = '/storage/uploads/purchased_dailies/current_path.txt';

	public static function storageDir(): string
	{
		return BASE_PATH . self::STORAGE_DIR;
	}

	public static function getCurrentFilePath(): string
	{
		$pointer = BASE_PATH . self::POINTER_FILE;
		if (!is_file($pointer) || !is_readable($pointer)) {
			return '';
		}
		$path = trim((string) file_get_contents($pointer));
		if ($path === '' || !is_file($path) || !is_readable($path)) {
			return '';
		}
		return $path;
	}

	public static function setCurrentFilePath(string $path): void
	{
		$pointer = BASE_PATH . self::POINTER_FILE;
		$dir = dirname($pointer);
		if (!is_dir($dir)) {
			@mkdir($dir, 0775, true);
		}
		@file_put_contents($pointer, $path);
	}

	/**
	 * @param array<int, array<int, string>> $rows
	 * @return array{rows: array<int, array<string, mixed>>, summary: array<string, int|string|array<int, string>>}
	 */
	public static function parseRows(array $rows): array
	{
		if (count($rows) < 1) {
			return [
				'rows' => [],
				'summary' => self::emptySummary(),
			];
		}

		$headerRowIndex = self::detectHeaderRowIndex($rows);
		$header = array_map(static fn($v) => self::normalizeHeader((string) $v), $rows[$headerRowIndex]);
		$indexByHeader = [];
		foreach ($header as $idx => $name) {
			if ($name !== '') {
				$indexByHeader[$name] = $idx;
			}
		}
		$detectedHeaders = array_values(array_filter($header, static fn($h) => $h !== ''));

		$dateIdx = self::findColumnIndex($indexByHeader, ['DATA', 'DATE', 'DT'])
			?? self::findColumnIndexLike($indexByHeader, ['DATA', 'DATE', 'DT']);
		$storeIdx = self::findColumnIndex($indexByHeader, ['LOJA', 'UNIDADE', 'SIGLA', 'STORE', 'FILIAL'])
			?? self::findColumnIndexLike($indexByHeader, ['LOJA', 'UNIDADE', 'SIGLA', 'FILIAL', 'STORE']);
		$typeIdx = self::findColumnIndex($indexByHeader, ['TIPO', 'CATEGORIA', 'MODALIDADE', 'CREDITO', 'CRÉDITO'])
			?? self::findColumnIndexLike($indexByHeader, ['TIPO', 'CATEGORIA', 'MODALIDADE', 'CREDITO']);
		$descIdx = self::findColumnIndex($indexByHeader, ['DESCRICAO', 'DESCRIÇÃO', 'OBS', 'OBSERVACAO', 'OBSERVAÇÃO'])
			?? self::findColumnIndexLike($indexByHeader, ['DESCRICAO', 'OBSERVACAO', 'OBS']);
		$activityIdx = self::findColumnIndex($indexByHeader, ['ATIVIDADE', 'ACTIVITY', 'NOME ATIVIDADE', 'NOME DA ATIVIDADE'])
			?? self::findColumnIndexLike($indexByHeader, ['ATIVIDADE', 'ACTIVITY']);

		$sheetActivity = self::detectSheetActivity($rows, $headerRowIndex);
		$qtdIdx = self::resolveQuantityColumnIndex($indexByHeader);

		$parsedRows = [];
		$dailyTotal = 0;
		$projectTotal = 0;

		for ($i = $headerRowIndex + 1; $i < count($rows); $i++) {
			$row = $rows[$i];
			if (!is_array($row)) {
				continue;
			}

			$qtd = $qtdIdx !== null
				? self::parseQuantity((string) ($row[$qtdIdx] ?? ''))
				: self::findQuantityInRow($row, [$dateIdx, $storeIdx, $typeIdx, $descIdx, $activityIdx]);
			if ($qtd <= 0) {
				continue;
			}

			$storeRaw = $storeIdx !== null ? trim((string) ($row[$storeIdx] ?? '')) : '';
			if ($storeRaw === '-' || self::isSummaryLabel($storeRaw)) {
				continue;
			}

			$typeRaw = $typeIdx !== null ? (string) ($row[$typeIdx] ?? '') : '';
			if ($typeRaw === '' && $storeRaw !== '') {
				$typeRaw = $storeRaw;
			}
			$creditType = self::detectCreditType($typeRaw);

			$activityRaw = $activityIdx !== null ? trim((string) ($row[$activityIdx] ?? '')) : '';
			if ($activityRaw === '' || $activityRaw === '-') {
				$activityRaw = $sheetActivity;
			}

			$parsedRows[] = [
				'date' => $dateIdx !== null ? trim((string) ($row[$dateIdx] ?? '')) : '',
				'store' => $storeRaw,
				'activity' => $activityRaw,
				'quantity' => $qtd,
				'type' => $creditType,
				'type_label' => $creditType === 'project_dailies' ? 'Projeto' : 'Diária',
				'description' => $descIdx !== null ? trim((string) ($row[$descIdx] ?? '')) : '',
			];

			if ($creditType === 'project_dailies') {
				$projectTotal += $qtd;
			} else {
				$dailyTotal += $qtd;
			}
		}

		return [
			'rows' => $parsedRows,
			'summary' => [
				'total_rows' => count($parsedRows),
				'daily_purchased' => $dailyTotal,
				'project_purchased' => $projectTotal,
				'total_purchased' => $dailyTotal + $projectTotal,
				'detected_headers' => $detectedHeaders,
				'sheet_activity' => $sheetActivity,
			],
		];
	}

	/**
	 * Lê atividade definida no topo da planilha (ex.: célula ATIVIDADE + valor ao lado).
	 *
	 * @param array<int, array<int, string>> $rows
	 */
	private static function detectSheetActivity(array $rows, int $headerRowIndex): string
	{
		$limit = min($headerRowIndex, 20);
		for ($i = 0; $i < $limit; $i++) {
			$row = $rows[$i];
			if (!is_array($row)) {
				continue;
			}
			foreach ($row as $idx => $cell) {
				$key = self::normalizeHeaderKey((string) $cell);
				if ($key !== 'ATIVIDADE' && !str_contains($key, 'ATIVIDADE')) {
					continue;
				}
				for ($j = $idx + 1; $j < count($row); $j++) {
					$value = trim((string) ($row[$j] ?? ''));
					if ($value !== '' && $value !== '-' && self::normalizeHeaderKey($value) !== 'ATIVIDADE') {
						return $value;
					}
				}
			}
		}
		return '';
	}

	/**
	 * @param array<string, int> $indexByHeader
	 */
	private static function resolveQuantityColumnIndex(array $indexByHeader): ?int
	{
		$exactCandidates = [
			'DIARIAS COMPRADAS',
			'DIÁRIAS COMPRADAS',
			'DIARIAS ADQUIRIDAS',
			'DIÁRIAS ADQUIRIDAS',
			'COMPRADAS',
			'QTD COMPRADA',
			'QTDE COMPRADA',
			'QUANTIDADE COMPRADA',
			'QTD',
			'QTDE',
			'QUANTIDADE',
		];
		$idx = self::findColumnIndex($indexByHeader, $exactCandidates);
		if ($idx !== null) {
			return $idx;
		}

		$idx = self::findColumnIndexLike($indexByHeader, [
			'DIARIASCOMPRADAS',
			'DIARIASADQUIRIDAS',
			'COMPRADAS',
			'QTDCOMPRADA',
			'QUANTIDADECOMPRADA',
		]);
		if ($idx !== null) {
			return $idx;
		}

		$idx = self::findColumnIndexLike($indexByHeader, ['QUANTIDADE', 'QTD', 'QTDE']);
		if ($idx !== null) {
			return $idx;
		}

		$idx = self::findColumnIndexLike($indexByHeader, ['DIARIAS', 'DIARIA'], ['CONSUMID', 'CONSUMIDA', 'UTILIZAD']);
		if ($idx !== null) {
			return $idx;
		}

		return self::findColumnIndexLike($indexByHeader, ['PREVISTO'], ['EXECUTADO', 'REALIZADO']);
	}

	/**
	 * @param array<int, string> $row
	 * @param array<int, int|null> $skipIndexes
	 */
	private static function findQuantityInRow(array $row, array $skipIndexes): int
	{
		$skip = array_values(array_filter($skipIndexes, static fn($v) => $v !== null));
		$best = 0;
		foreach ($row as $idx => $cell) {
			if (in_array($idx, $skip, true)) {
				continue;
			}
			$qtd = self::parseQuantity((string) $cell);
			if ($qtd > $best) {
				$best = $qtd;
			}
		}
		return $best;
	}

	private static function isSummaryLabel(string $value): bool
	{
		$key = self::normalizeHeaderKey($value);
		if ($key === '') {
			return false;
		}
		$labels = ['TOTAL', 'TOTAIS', 'SUBTOTAL', 'RESUMO', 'GERAL'];
		foreach ($labels as $label) {
			if ($key === $label || str_contains($key, $label)) {
				return true;
			}
		}
		return false;
	}

	private static function emptySummary(): array
	{
		return [
			'total_rows' => 0,
			'daily_purchased' => 0,
			'project_purchased' => 0,
			'total_purchased' => 0,
			'detected_headers' => [],
		];
	}

	/**
	 * @param array<int, array<int, string>> $rows
	 */
	private static function detectHeaderRowIndex(array $rows): int
	{
		$keywords = [
			'DATA', 'LOJA', 'UNIDADE', 'SIGLA', 'QTD', 'QUANTIDADE', 'DIARIAS', 'DIARIA',
			'COMPRADAS', 'PREVISTO', 'TIPO', 'COMPRA', 'ATIVIDADE',
		];
		$bestIndex = 0;
		$bestScore = -1;
		$limit = min(count($rows), 15);
		for ($i = 0; $i < $limit; $i++) {
			$normalized = array_map(static fn($v) => self::normalizeHeaderKey((string) $v), $rows[$i]);
			$score = 0;
			foreach ($normalized as $cell) {
				if ($cell === '') {
					continue;
				}
				foreach ($keywords as $keyword) {
					$key = self::normalizeHeaderKey($keyword);
					if ($cell === $key || str_contains($cell, $key) || str_contains($key, $cell)) {
						$score++;
						break;
					}
				}
			}
			if ($score > $bestScore) {
				$bestScore = $score;
				$bestIndex = $i;
			}
		}
		return $bestIndex;
	}

	private static function normalizeHeader(string $value): string
	{
		$value = trim($value);
		if ($value === '') {
			return '';
		}
		$value = mb_strtoupper($value, 'UTF-8');
		$value = str_replace(['Á', 'À', 'Ã', 'Â', 'É', 'Ê', 'Í', 'Ó', 'Ô', 'Õ', 'Ú', 'Ç'], ['A', 'A', 'A', 'A', 'E', 'E', 'I', 'O', 'O', 'O', 'U', 'C'], $value);
		return $value;
	}

	private static function normalizeHeaderKey(string $value): string
	{
		$value = self::normalizeHeader($value);
		$value = preg_replace('/[^A-Z0-9]/', '', $value) ?? $value;
		return $value;
	}

	/**
	 * @param array<string, int> $indexByHeader
	 * @param array<int, string> $candidates
	 */
	private static function findColumnIndex(array $indexByHeader, array $candidates): ?int
	{
		foreach ($candidates as $candidate) {
			if (isset($indexByHeader[$candidate])) {
				return $indexByHeader[$candidate];
			}
		}
		return null;
	}

	/**
	 * @param array<string, int> $indexByHeader
	 * @param array<int, string> $needles
	 * @param array<int, string> $excludeNeedles
	 */
	private static function findColumnIndexLike(array $indexByHeader, array $needles, array $excludeNeedles = []): ?int
	{
		$normalized = [];
		foreach ($indexByHeader as $name => $idx) {
			$normalized[self::normalizeHeaderKey($name)] = (int) $idx;
		}

		foreach ($needles as $needle) {
			$needleKey = self::normalizeHeaderKey($needle);
			if ($needleKey === '') {
				continue;
			}
			foreach ($normalized as $key => $idx) {
				if (!str_contains($key, $needleKey) && $key !== $needleKey) {
					continue;
				}
				$excluded = false;
				foreach ($excludeNeedles as $exclude) {
					$excludeKey = self::normalizeHeaderKey($exclude);
					if ($excludeKey !== '' && str_contains($key, $excludeKey)) {
						$excluded = true;
						break;
					}
				}
				if (!$excluded) {
					return $idx;
				}
			}
		}

		return null;
	}

	private static function parseQuantity(string $value): int
	{
		$value = trim($value);
		if ($value === '' || $value === '-') {
			return 0;
		}
		if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{2,4}$/', $value) === 1) {
			return 0;
		}
		$value = str_replace(['.', ' '], '', $value);
		$value = str_replace(',', '.', $value);
		if (!is_numeric($value)) {
			return 0;
		}
		$float = (float) $value;
		if ($float > 0 && $float < 1) {
			return 0;
		}
		if ($float >= 40000 && $float <= 60000) {
			return 0;
		}
		return max(0, (int) round($float));
	}

	private static function detectCreditType(string $typeRaw): string
	{
		$type = self::normalizeHeaderKey($typeRaw);
		if ($type === '') {
			return 'daily';
		}
		if (str_contains($type, 'PROJ')) {
			return 'project_dailies';
		}
		if (str_contains($type, 'TICKET')) {
			return 'ticket';
		}
		return 'daily';
	}
}
