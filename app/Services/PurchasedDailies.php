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
	 * @return array{rows: array<int, array<string, mixed>>, summary: array<string, int|string>}
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

		$dateIdx = self::findColumnIndex($indexByHeader, ['DATA', 'DATE', 'DT']);
		$storeIdx = self::findColumnIndex($indexByHeader, ['LOJA', 'UNIDADE', 'SIGLA', 'STORE', 'FILIAL']);
		$qtdIdx = self::findColumnIndex($indexByHeader, ['QTD', 'QTDE', 'QUANTIDADE', 'DIARIAS', 'DIÁRIAS', 'DIARIA', 'DIÁRIA']);
		$typeIdx = self::findColumnIndex($indexByHeader, ['TIPO', 'CATEGORIA', 'MODALIDADE', 'CREDITO', 'CRÉDITO']);
		$descIdx = self::findColumnIndex($indexByHeader, ['DESCRICAO', 'DESCRIÇÃO', 'OBS', 'OBSERVACAO', 'OBSERVAÇÃO']);

		if ($qtdIdx === null && count($indexByHeader) >= 2) {
			$qtdIdx = max(array_values($indexByHeader));
		}

		$parsedRows = [];
		$dailyTotal = 0;
		$projectTotal = 0;

		for ($i = $headerRowIndex + 1; $i < count($rows); $i++) {
			$row = $rows[$i];
			if (!is_array($row)) {
				continue;
			}

			$qtd = $qtdIdx !== null ? self::parseQuantity((string) ($row[$qtdIdx] ?? '')) : 0;
			if ($qtd <= 0) {
				continue;
			}

			$typeRaw = $typeIdx !== null ? (string) ($row[$typeIdx] ?? '') : '';
			$creditType = self::detectCreditType($typeRaw);

			$parsedRows[] = [
				'date' => $dateIdx !== null ? trim((string) ($row[$dateIdx] ?? '')) : '',
				'store' => $storeIdx !== null ? trim((string) ($row[$storeIdx] ?? '')) : '',
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
			],
		];
	}

	private static function emptySummary(): array
	{
		return [
			'total_rows' => 0,
			'daily_purchased' => 0,
			'project_purchased' => 0,
			'total_purchased' => 0,
		];
	}

	/**
	 * @param array<int, array<int, string>> $rows
	 */
	private static function detectHeaderRowIndex(array $rows): int
	{
		$keywords = ['DATA', 'LOJA', 'UNIDADE', 'SIGLA', 'QTD', 'QUANTIDADE', 'DIARIAS', 'DIÁRIAS', 'TIPO'];
		$bestIndex = 0;
		$bestScore = -1;
		$limit = min(count($rows), 10);
		for ($i = 0; $i < $limit; $i++) {
			$normalized = array_map(static fn($v) => self::normalizeHeader((string) $v), $rows[$i]);
			$score = 0;
			foreach ($normalized as $cell) {
				if (in_array($cell, $keywords, true)) {
					$score++;
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

	private static function parseQuantity(string $value): int
	{
		$value = trim($value);
		if ($value === '') {
			return 0;
		}
		$value = str_replace(['.', ' '], '', $value);
		$value = str_replace(',', '.', $value);
		if (!is_numeric($value)) {
			return 0;
		}
		return max(0, (int) round((float) $value));
	}

	private static function detectCreditType(string $typeRaw): string
	{
		$type = self::normalizeHeader($typeRaw);
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
