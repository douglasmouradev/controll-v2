<?php
declare(strict_types=1);

namespace App\Services;

final class InventoryService
{
	private const POINTER_FILE = '/storage/uploads/inventory/current_path.txt';
	private const UPLOAD_DIR = '/storage/uploads/inventory';

	public static function getGlobalPath(): string
	{
		$pointerPath = BASE_PATH . '/storage/uploads/inventory/current_path.txt';
		if (!is_file($pointerPath) || !is_readable($pointerPath)) {
			return '';
		}
		$path = trim((string) file_get_contents($pointerPath));
		if ($path === '' || !is_file($path) || !is_readable($path)) {
			return '';
		}
		return $path;
	}

	public static function setGlobalPath(string $path): void
	{
		$pointerPath = BASE_PATH . '/storage/uploads/inventory/current_path.txt';
		@file_put_contents($pointerPath, $path);
	}


	public static function resolvePath(string $sessionPath = ''): string
	{
		$path = self::getGlobalPath();
		if ($path === '' && $sessionPath !== '') {
			$path = $sessionPath;
		}
		if ($path === '') {
			$path = trim((string) (getenv('INVENTORY_XLSX_PATH') ?: ''));
		}
		if ($path === '' || !is_file($path) || !is_readable($path)) {
			return '';
		}
		return $path;
	}

	public static function storageDir(): string
	{
		return BASE_PATH . self::UPLOAD_DIR;
	}

	public static function saveUploadedFile(array $file): string
	{
		$originalName = (string) ($file['name'] ?? 'planilha.xlsx');
		$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
		$allowed = ['xlsx', 'xlsm', 'xltx', 'xltm'];
		if (!in_array($ext, $allowed, true)) {
			throw new \InvalidArgumentException('Formato inválido. Envie um arquivo XLSX.');
		}

		$storageDir = self::storageDir();
		if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
			throw new \RuntimeException('Não foi possível preparar a pasta de upload');
		}

		$targetPath = $storageDir . '/inventory_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.xlsx';
		$tmpPath = (string) ($file['tmp_name'] ?? '');
		if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
			throw new \InvalidArgumentException('Arquivo temporário inválido');
		}
		if (!move_uploaded_file($tmpPath, $targetPath)) {
			throw new \RuntimeException('Não foi possível salvar a planilha');
		}

		self::setGlobalPath($targetPath);
		return $targetPath;
	}

	/**
	 * @param array<int, array<int, string>> $rows
	 * @param array<string, mixed> $query
	 * @return array<string, mixed>
	 */
	public static function buildStatsPayload(array $rows, array $query, string $xlsxPath): array
	{
		if (count($rows) < 2) {
			return [
				'success' => true,
				'labels' => ['Sem dados'],
				'data' => [0],
				'total_items' => 0,
				'source' => $xlsxPath,
			];
		}

		$header = array_map(static fn($v) => strtoupper(trim((string) $v)), $rows[0]);
		$targetColumns = ['SETUP', 'ROLLOUT', 'HEXAPADS', 'DEFEITO', 'SUPORTE_INSTALADO', 'SUPORTE_PENDENTE'];
		$counts = array_fill_keys($targetColumns, 0);
		$availableStores = [];
		$filteredStores = [];
		$locationsByCategory = [];
		$sampleRows = [];
		$summaryMetrics = [
			'stores' => 0,
			'previsto' => 0,
			'executado' => 0,
			'diarias_consumidas' => 0,
			'setup' => 0,
			'rollout' => 0,
			'formatacao' => 0,
			'troca_memoria' => 0,
			'pdvs' => 0,
			'ocorrencias' => 0,
			'suporte_instalado' => 0,
			'suporte_pendente' => 0,
		];

		$indexByHeader = [];
		foreach ($header as $idx => $name) {
			if ($name !== '') {
				$indexByHeader[$name] = $idx;
			}
		}
		$normalizeHeaderKey = static function (string $text): string {
			$text = strtoupper(trim($text));
			$from = ['Á', 'À', 'Â', 'Ã', 'Ä', 'É', 'È', 'Ê', 'Ë', 'Í', 'Ì', 'Î', 'Ï', 'Ó', 'Ò', 'Ô', 'Õ', 'Ö', 'Ú', 'Ù', 'Û', 'Ü', 'Ç'];
			$to =   ['A', 'A', 'A', 'A', 'A', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'C'];
			$text = str_replace($from, $to, $text);
			$text = preg_replace('/[^A-Z0-9]/', '', $text) ?? $text;
			return $text;
		};
		$normalizedHeaderIndex = [];
		foreach ($indexByHeader as $name => $idx) {
			$normalizedHeaderIndex[$normalizeHeaderKey((string) $name)] = (int) $idx;
		}
		$findHeaderIdxLike = static function (array $normalizedMap, string $needle) use ($normalizeHeaderKey): ?int {
			$needleNorm = $normalizeHeaderKey($needle);
			foreach ($normalizedMap as $key => $idx) {
				if ($key === $needleNorm || strpos($key, $needleNorm) !== false) {
					return (int) $idx;
				}
			}
			return null;
		};
		$ocorrenciasHeaderIdx = $indexByHeader['OCORRENCIAS'] ?? ($indexByHeader['OCORRÊNCIAS'] ?? null);
		$pdvHeaderIdx = $indexByHeader['HEXAPADS']
			?? ($indexByHeader['XPAD'] ?? ($indexByHeader['XPADS'] ?? null));
		$suporteHeaderIdx = $indexByHeader['SUPORTE'] ?? $findHeaderIdxLike($normalizedHeaderIndex, 'SUPORTE');
		$suporteInstaladoHeaderIdx = $indexByHeader['SUPORTE INSTALADO']
			?? ($indexByHeader['SUPORTE INSTALADO '] ?? $findHeaderIdxLike($normalizedHeaderIndex, 'SUPORTEINSTALADO'));
		$suportePendenteHeaderIdx = $indexByHeader['SUPORTE PENDENTE']
			?? ($indexByHeader['SUPORTE PENDENTE '] ?? $findHeaderIdxLike($normalizedHeaderIndex, 'SUPORTEPENDENTE'));

		$storeFilter = strtoupper(trim((string) ($query['store'] ?? '')));
		$supportStatusFilter = strtolower(trim((string) ($query['support_status'] ?? '')));
		$startDateFilter = trim((string) ($query['start_date'] ?? ''));
		$endDateFilter = trim((string) ($query['end_date'] ?? ''));
		$startDateTs = $startDateFilter !== '' ? strtotime($startDateFilter . ' 00:00:00') : null;
		$endDateTs = $endDateFilter !== '' ? strtotime($endDateFilter . ' 23:59:59') : null;

		$dataRows = array_slice($rows, 1);
		foreach ($dataRows as $row) {
			$storeValue = '';
			if (isset($indexByHeader['LOJA'])) {
				$storeRaw = $row[$indexByHeader['LOJA']] ?? '';
				$storeValue = strtoupper(trim((string) $storeRaw));
				if ($storeValue !== '' && $storeValue !== '-') {
					$availableStores[$storeValue] = true;
				}
			}
			if ($storeFilter !== '' && $storeValue !== $storeFilter) {
				continue;
			}

			if (isset($indexByHeader['DATA'])) {
				$dateRaw = $row[$indexByHeader['DATA']] ?? '';
				$dateYmd = self::normalizeSpreadsheetDateToYmd($dateRaw);
				if ($dateYmd !== null) {
					$dateTs = strtotime($dateYmd . ' 12:00:00');
					if ($startDateTs !== null && $dateTs !== false && $dateTs < $startDateTs) {
						continue;
					}
					if ($endDateTs !== null && $dateTs !== false && $dateTs > $endDateTs) {
						continue;
					}
				}
			}

			$rowSupportStatus = '';
			if ($suporteInstaladoHeaderIdx !== null || $suportePendenteHeaderIdx !== null) {
				$installedValue = 0.0;
				$pendingValue = 0.0;
				if ($suporteInstaladoHeaderIdx !== null) {
					$instRaw = $row[$suporteInstaladoHeaderIdx] ?? null;
					$instNorm = strtoupper(trim((string) $instRaw));
					if ($instNorm !== '' && $instNorm !== '-' && $instNorm !== '0') {
						$installedValue = is_numeric($instRaw) ? max(0, (float) $instRaw) : 1.0;
					}
				}
				if ($suportePendenteHeaderIdx !== null) {
					$pendRaw = $row[$suportePendenteHeaderIdx] ?? null;
					$pendNorm = strtoupper(trim((string) $pendRaw));
					if ($pendNorm !== '' && $pendNorm !== '-' && $pendNorm !== '0') {
						$pendingValue = is_numeric($pendRaw) ? max(0, (float) $pendRaw) : 1.0;
					}
				}
				if ($pendingValue > 0) {
					$rowSupportStatus = 'pending';
				} elseif ($installedValue > 0) {
					$rowSupportStatus = 'installed';
				}
			} else {
				$suporteIdx = $suporteHeaderIdx;
				if ($suporteIdx !== null) {
					$suporteRaw = $row[$suporteIdx] ?? null;
					$suporteNormalized = strtoupper(trim((string) $suporteRaw));
					$rowSupportStatus = ($suporteNormalized === '' || $suporteNormalized === '-' || $suporteNormalized === '0')
						? 'pending'
						: 'installed';
				}
			}
			if (($supportStatusFilter === 'pending' || $supportStatusFilter === 'installed') && $rowSupportStatus !== $supportStatusFilter) {
				continue;
			}

			if ($storeValue !== '' && $storeValue !== '-') {
				$filteredStores[$storeValue] = true;
			}

			$previstoIdx = $indexByHeader['PREVISTO'] ?? null;
			if ($previstoIdx !== null) {
				$previstoRaw = $row[$previstoIdx] ?? null;
				$previstoNorm = strtoupper(trim((string) $previstoRaw));
				if ($previstoNorm !== '' && $previstoNorm !== '-' && is_numeric($previstoRaw)) {
					$summaryMetrics['previsto'] += max(0, (float) $previstoRaw);
				}
			}

			$executadoIdx = $indexByHeader['EXECUTADO'] ?? null;
			if ($executadoIdx !== null) {
				$executadoRaw = $row[$executadoIdx] ?? null;
				$executadoNorm = strtoupper(trim((string) $executadoRaw));
				if ($executadoNorm !== '' && $executadoNorm !== '-' && is_numeric($executadoRaw)) {
					$summaryMetrics['executado'] += max(0, (float) $executadoRaw);
				}
			}

			$diariasIdx = $indexByHeader['DIARIAS CONSUMIDAS'] ?? null;
			if ($diariasIdx !== null) {
				$diariasRaw = $row[$diariasIdx] ?? null;
				$diariasNorm = strtoupper(trim((string) $diariasRaw));
				if ($diariasNorm !== '' && $diariasNorm !== '-' && is_numeric($diariasRaw)) {
					$summaryMetrics['diarias_consumidas'] += max(0, (float) $diariasRaw);
				}
			}

			$setupIdx = $indexByHeader['SETUP'] ?? null;
			if ($setupIdx !== null) {
				$setupRaw = $row[$setupIdx] ?? null;
				$setupNormalized = strtoupper(trim((string) $setupRaw));
				if ($setupNormalized !== '' && $setupNormalized !== '-' && $setupNormalized !== '0') {
					$summaryMetrics['setup']++;
					// Regra de negócio: setup e formatação representam o mesmo conjunto.
					$summaryMetrics['formatacao']++;
				}
			}
			$rolloutIdx = $indexByHeader['ROLLOUT'] ?? null;
			if ($rolloutIdx !== null) {
				$rolloutRaw = $row[$rolloutIdx] ?? null;
				$rolloutNormalized = strtoupper(trim((string) $rolloutRaw));
				if ($rolloutNormalized !== '' && $rolloutNormalized !== '-' && $rolloutNormalized !== '0') {
					$summaryMetrics['rollout']++;
				}
			}

			$evidenciasIdx = $indexByHeader['EVIDENCIAS'] ?? null;
			if ($evidenciasIdx !== null) {
				$evidencias = strtoupper(trim((string) ($row[$evidenciasIdx] ?? '')));
				if ($evidencias !== '' && $evidencias !== '-') {
					if (strpos($evidencias, 'MEMOR') !== false) {
						$summaryMetrics['troca_memoria']++;
					}
				}
			}

			$pdvIdx = $pdvHeaderIdx;
			if ($pdvIdx !== null) {
				$pdvRaw = $row[$pdvIdx] ?? null;
				$pdvNormalized = strtoupper(trim((string) $pdvRaw));
				if ($pdvNormalized !== '' && $pdvNormalized !== '-' && $pdvNormalized !== '0') {
					if (is_numeric($pdvRaw)) {
						$summaryMetrics['pdvs'] += max(0, (int) ((float) $pdvRaw));
					} else {
						$summaryMetrics['pdvs']++;
					}
				}
			}

			$ocorrenciasIdx = $indexByHeader['OCORRENCIAS'] ?? ($indexByHeader['OCORRÊNCIAS'] ?? ($indexByHeader['DEFEITO'] ?? null));
			if ($ocorrenciasIdx !== null) {
				$ocorrenciasRaw = $row[$ocorrenciasIdx] ?? null;
				$ocorrenciasNormalized = strtoupper(trim((string) $ocorrenciasRaw));
				if ($ocorrenciasNormalized !== '' && $ocorrenciasNormalized !== '-' && $ocorrenciasNormalized !== '0') {
					// Ocorrências = quantidade de linhas com ocorrência registrada.
					$summaryMetrics['ocorrencias']++;
				}
			}
			// Preferir colunas dedicadas de suporte quando existirem (I/J).
			if ($suporteInstaladoHeaderIdx !== null || $suportePendenteHeaderIdx !== null) {
				if ($suporteInstaladoHeaderIdx !== null) {
					$instRaw = $row[$suporteInstaladoHeaderIdx] ?? null;
					$instNorm = strtoupper(trim((string) $instRaw));
					if ($instNorm !== '' && $instNorm !== '-' && $instNorm !== '0') {
						$summaryMetrics['suporte_instalado'] += is_numeric($instRaw)
							? max(0, (float) $instRaw)
							: 1;
					}
				}
				if ($suportePendenteHeaderIdx !== null) {
					$pendRaw = $row[$suportePendenteHeaderIdx] ?? null;
					$pendNorm = strtoupper(trim((string) $pendRaw));
					if ($pendNorm !== '' && $pendNorm !== '-' && $pendNorm !== '0') {
						$summaryMetrics['suporte_pendente'] += is_numeric($pendRaw)
							? max(0, (float) $pendRaw)
							: 1;
					}
				}
			} else {
				$suporteIdx = $suporteHeaderIdx;
				if ($suporteIdx !== null) {
					$suporteRaw = $row[$suporteIdx] ?? null;
					$suporteNormalized = strtoupper(trim((string) $suporteRaw));
					if ($suporteNormalized === '' || $suporteNormalized === '-' || $suporteNormalized === '0') {
						$summaryMetrics['suporte_pendente']++;
					} else {
						$isInstalled = is_numeric($suporteRaw)
							? ((float) $suporteRaw) > 0
							: true;
						if ($isInstalled) {
							$summaryMetrics['suporte_instalado']++;
						} else {
							$summaryMetrics['suporte_pendente']++;
						}
					}
				}
			}

			foreach ($targetColumns as $columnName) {
				$colIdx = $indexByHeader[$columnName] ?? null;
				// A categoria "DEFEITO" no gráfico é exibida como "OCORRÊNCIAS";
				// portanto deve ler preferencialmente da coluna de ocorrências.
				if ($columnName === 'DEFEITO' && $ocorrenciasHeaderIdx !== null) {
					$colIdx = $ocorrenciasHeaderIdx;
				}
				// A categoria de PDV pode vir como HEXAPADS, XPAD ou XPADS na planilha.
				if ($columnName === 'HEXAPADS' && $pdvHeaderIdx !== null) {
					$colIdx = $pdvHeaderIdx;
				}
				if ($columnName === 'SUPORTE_INSTALADO' || $columnName === 'SUPORTE_PENDENTE') {
					$storeKey = $storeValue !== '' && $storeValue !== '-' ? $storeValue : 'SEM LOJA';
					if (!isset($locationsByCategory[$columnName])) {
						$locationsByCategory[$columnName] = [];
					}

					$supportValue = 0.0;
					if ($columnName === 'SUPORTE_INSTALADO') {
						if ($suporteInstaladoHeaderIdx !== null) {
							$raw = $row[$suporteInstaladoHeaderIdx] ?? null;
							$norm = strtoupper(trim((string) $raw));
							if ($norm !== '' && $norm !== '-' && $norm !== '0') {
								$supportValue = is_numeric($raw) ? max(0, (float) $raw) : 1.0;
							}
						} elseif ($suporteHeaderIdx !== null) {
							$raw = $row[$suporteHeaderIdx] ?? null;
							$norm = strtoupper(trim((string) $raw));
							if ($norm !== '' && $norm !== '-' && $norm !== '0') {
								$supportValue = is_numeric($raw) ? max(0, (float) $raw) : 1.0;
							}
						}
					} else {
						if ($suportePendenteHeaderIdx !== null) {
							$raw = $row[$suportePendenteHeaderIdx] ?? null;
							$norm = strtoupper(trim((string) $raw));
							if ($norm !== '' && $norm !== '-' && $norm !== '0') {
								$supportValue = is_numeric($raw) ? max(0, (float) $raw) : 1.0;
							}
						} elseif ($suporteHeaderIdx !== null) {
							$raw = $row[$suporteHeaderIdx] ?? null;
							$norm = strtoupper(trim((string) $raw));
							if ($norm === '' || $norm === '-' || $norm === '0') {
								$supportValue = 1.0;
							}
						}
					}

					if ($supportValue > 0) {
						$counts[$columnName] += $supportValue;
						$locationsByCategory[$columnName][$storeKey] = ($locationsByCategory[$columnName][$storeKey] ?? 0) + $supportValue;
					}
					continue;
				}
				if ($colIdx === null) {
					continue;
				}
				$value = $row[$colIdx] ?? null;
				$normalized = strtoupper(trim((string) $value));
				if ($normalized === '' || $normalized === '-' || $normalized === '0') {
					continue;
				}
				$storeKey = $storeValue !== '' && $storeValue !== '-' ? $storeValue : 'SEM LOJA';
				if (!isset($locationsByCategory[$columnName])) {
					$locationsByCategory[$columnName] = [];
				}

				$isOcorrenciasCategory = ($columnName === 'DEFEITO' && $ocorrenciasHeaderIdx !== null);
				if ($isOcorrenciasCategory) {
					$counts[$columnName] += 1;
					$locationsByCategory[$columnName][$storeKey] = ($locationsByCategory[$columnName][$storeKey] ?? 0) + 1;
				} elseif (is_numeric($value)) {
					$increment = max(0, (float) $value);
					$counts[$columnName] += $increment;
					$locationsByCategory[$columnName][$storeKey] = ($locationsByCategory[$columnName][$storeKey] ?? 0) + $increment;
				} else {
					$counts[$columnName] += 1;
					$locationsByCategory[$columnName][$storeKey] = ($locationsByCategory[$columnName][$storeKey] ?? 0) + 1;
				}
			}

			if (count($sampleRows) < 5) {
				$sample = [];
				foreach ($targetColumns as $columnName) {
					$colIdx = $indexByHeader[$columnName] ?? null;
					$sample[$columnName] = $colIdx !== null ? (string) ($row[$colIdx] ?? '') : '';
				}
				$sample['LOJA'] = isset($indexByHeader['LOJA']) ? (string) ($row[$indexByHeader['LOJA']] ?? '') : '';
				$sample['DATA'] = isset($indexByHeader['DATA']) ? (string) ($row[$indexByHeader['DATA']] ?? '') : '';
				$sampleRows[] = $sample;
			}
		}

		$labels = [];
		$data = [];
		foreach ($counts as $label => $total) {
			$labels[] = $label;
			$data[] = (float) $total;
		}

		$totalItems = array_sum($data);
		$summaryMetrics['stores'] = count($filteredStores);
		$locationsPayload = [];
		foreach ($locationsByCategory as $category => $storeTotals) {
			arsort($storeTotals);
			$locationsPayload[$category] = [];
			foreach ($storeTotals as $store => $total) {
				$locationsPayload[$category][] = [
					'store' => (string) $store,
					'total' => (float) $total,
				];
			}
		}
		if ($totalItems <= 0) {
			$labels = ['Sem dados'];
			$data = [0];
		}

		$payload = [
			'success' => true,
			'labels' => $labels,
			'data' => $data,
			'total_items' => $totalItems,
			'summary_metrics' => $summaryMetrics,
			'locations_by_category' => $locationsPayload,
			'stores' => array_keys($availableStores),
			'source' => basename($xlsxPath),
		];

		if (defined('APP_DEBUG') && APP_DEBUG) {
			$payload['debug'] = [
				'rows_total' => count($rows),
				'rows_data' => count($dataRows),
				'headers_detected' => array_values(array_filter($header, static fn($h) => $h !== '')),
				'target_columns_found' => array_values(array_intersect($targetColumns, array_keys($indexByHeader))),
				'target_columns_missing' => array_values(array_diff($targetColumns, array_keys($indexByHeader))),
				'sample_rows' => $sampleRows,
				'counts' => $counts,
				'filters' => [
					'store' => $storeFilter,
					'support_status' => $supportStatusFilter,
					'start_date' => $startDateFilter,
					'end_date' => $endDateFilter,
				],
			];
		}

		return $payload;
	}

	private static function normalizeSpreadsheetDateToYmd($raw): ?string
	{
		$value = trim((string) $raw);
		if ($value === '' || $value === '-') {
			return null;
		}

		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
			return $value;
		}

		if (is_numeric($value)) {
			$serial = (float) $value;
			if ($serial > 20000) {
				$unix = (int) round(($serial - 25569) * 86400);
				return gmdate('Y-m-d', $unix);
			}
		}

		$ts = strtotime($value);
		if ($ts === false) {
			return null;
		}
		return date('Y-m-d', $ts);
	}

}
