<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\SdwanEntry;

final class SdwanImportService
{
	/** @return array{success: bool, message?: string, imported?: int, skipped?: int, errors?: array<int, string>, valid?: int, total_rows?: int} */
	public static function importCsvFile(array $file, ?int $createdBy = null): array
	{
		$parsed = self::parseCsvFile($file);
		if (!($parsed['success'] ?? false)) {
			return $parsed;
		}

		$imported = 0;
		$skipped = 0;
		$errors = $parsed['errors'] ?? [];

		foreach ($parsed['rows'] ?? [] as $item) {
			$lineNo = (int) ($item['line'] ?? 0);
			if (!($item['valid'] ?? false)) {
				$skipped++;
				continue;
			}

			try {
				SdwanEntry::create($item['data'], $createdBy, ['entry_source' => 'dashboard']);
				$imported++;
			} catch (\Throwable $e) {
				$errors[] = 'Linha ' . $lineNo . ': ' . $e->getMessage();
				$skipped++;
			}
		}

		SdwanAudit::record('import', 'csv:' . $imported . '_rows', $imported > 0);

		$message = sprintf('%d registro(s) importado(s), %d ignorado(s).', $imported, $skipped);
		if ($errors !== []) {
			$message .= ' Veja o relatório de erros abaixo.';
		}

		return [
			'success' => true,
			'message' => $message,
			'imported' => $imported,
			'skipped' => $skipped,
			'valid' => (int) ($parsed['valid'] ?? 0),
			'total_rows' => (int) ($parsed['total_rows'] ?? 0),
			'errors' => array_slice($errors, 0, 100),
		];
	}

	/** @return array{success: bool, message?: string, valid?: int, invalid?: int, total_rows?: int, errors?: array<int, string>, preview?: array<int, array<string, mixed>>} */
	public static function previewCsvFile(array $file): array
	{
		$parsed = self::parseCsvFile($file);
		if (!($parsed['success'] ?? false)) {
			return $parsed;
		}

		$valid = 0;
		$invalid = 0;
		$preview = [];

		foreach ($parsed['rows'] ?? [] as $item) {
			if ($item['valid'] ?? false) {
				$valid++;
				if (count($preview) < 5) {
					$preview[] = $item['data'];
				}
			} else {
				$invalid++;
			}
		}

		return [
			'success' => true,
			'message' => sprintf(
				'%d linha(s) válida(s), %d com problema(s), de %d linha(s) de dados.',
				$valid,
				$invalid,
				(int) ($parsed['total_rows'] ?? 0)
			),
			'valid' => $valid,
			'invalid' => $invalid,
			'total_rows' => (int) ($parsed['total_rows'] ?? 0),
			'errors' => array_slice($parsed['errors'] ?? [], 0, 100),
			'preview' => $preview,
		];
	}

	/**
	 * @return array{
	 *   success: bool,
	 *   message?: string,
	 *   total_rows?: int,
	 *   valid?: int,
	 *   errors?: array<int, string>,
	 *   rows?: array<int, array{line: int, valid: bool, data?: array<string, mixed>}>
	 * }
	 */
	private static function parseCsvFile(array $file): array
	{
		if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
			return ['success' => false, 'message' => 'Arquivo não enviado'];
		}

		$tmp = (string) ($file['tmp_name'] ?? '');
		if ($tmp === '' || !is_uploaded_file($tmp)) {
			return ['success' => false, 'message' => 'Arquivo temporário inválido'];
		}

		$name = (string) ($file['name'] ?? '');
		$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
		if (!in_array($ext, ['csv', 'txt'], true)) {
			return ['success' => false, 'message' => 'Envie um arquivo CSV com colunas: loja, xpads_previsto, quantidade_localizada, pdv_numero, pdv_serie'];
		}

		$handle = fopen($tmp, 'rb');
		if ($handle === false) {
			return ['success' => false, 'message' => 'Não foi possível ler o arquivo'];
		}

		$rows = [];
		$errors = [];
		$lineNo = 0;
		$headerMap = null;
		$dataRows = 0;
		$valid = 0;

		while (($row = fgetcsv($handle, 0, ';')) !== false) {
			$lineNo++;
			if ($row === [null] || $row === false) {
				continue;
			}
			if (count($row) === 1 && str_contains((string) $row[0], ',')) {
				$row = str_getcsv((string) $row[0], ',');
			}

			$normalized = array_map(static fn ($v) => trim((string) $v), $row);
			if ($headerMap === null) {
				$headerMap = self::mapHeaders($normalized);
				if ($headerMap === null) {
					fclose($handle);
					return ['success' => false, 'message' => 'Cabeçalho inválido. Use: loja, xpads_previsto, quantidade_localizada, pdv_numero, pdv_serie'];
				}
				continue;
			}

			$dataRows++;
			$data = self::rowToData($normalized, $headerMap);
			if ($data === null) {
				$errors[] = 'Linha ' . $lineNo . ': loja vazia ou linha em branco';
				$rows[] = ['line' => $lineNo, 'valid' => false];
				continue;
			}

			$validation = SdwanEntry::validateInput($data);
			if (!$validation['success']) {
				$errors[] = 'Linha ' . $lineNo . ': ' . ($validation['message'] ?? 'inválida');
				$rows[] = ['line' => $lineNo, 'valid' => false];
				continue;
			}

			$valid++;
			$rows[] = [
				'line' => $lineNo,
				'valid' => true,
				'data' => $validation['data'],
			];
		}

		fclose($handle);

		if ($dataRows === 0) {
			return ['success' => false, 'message' => 'O arquivo não contém linhas de dados.'];
		}

		return [
			'success' => true,
			'total_rows' => $dataRows,
			'valid' => $valid,
			'errors' => $errors,
			'rows' => $rows,
		];
	}

	/** @param array<int, string> $cells @return array<string, int>|null */
	private static function mapHeaders(array $cells): ?array
	{
		$map = [];
		foreach ($cells as $index => $cell) {
			$key = strtolower(preg_replace('/[^a-z0-9_]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $cell) ?: $cell) ?? '');
			if (in_array($key, ['loja', 'xpads_previsto', 'xpadsprevisto', 'quantidade_localizada', 'quantidadelocalizada', 'pdv_numero', 'pdvnumero', 'pdv_serie', 'pdvserie'], true)) {
				$canonical = match ($key) {
					'xpadsprevisto' => 'xpads_previsto',
					'quantidadelocalizada' => 'quantidade_localizada',
					'pdvnumero' => 'pdv_numero',
					'pdvserie' => 'pdv_serie',
					default => $key,
				};
				$map[$canonical] = $index;
			}
		}

		return isset($map['loja']) ? $map : null;
	}

	/** @param array<int, string> $cells @param array<string, int> $headerMap @return array<string, mixed>|null */
	private static function rowToData(array $cells, array $headerMap): ?array
	{
		$loja = strtoupper(trim($cells[$headerMap['loja']] ?? ''));
		if ($loja === '') {
			return null;
		}

		return [
			'loja' => $loja,
			'xpads_previsto' => (int) ($cells[$headerMap['xpads_previsto'] ?? -1] ?? 0),
			'quantidade_localizada' => (int) ($cells[$headerMap['quantidade_localizada'] ?? -1] ?? 0),
			'pdv_numero' => trim($cells[$headerMap['pdv_numero'] ?? -1] ?? ''),
			'pdv_serie' => trim($cells[$headerMap['pdv_serie'] ?? -1] ?? ''),
		];
	}
}
