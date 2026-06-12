<?php
declare(strict_types=1);

namespace App\Services;

final class XlsxReaderService
{
	public function readRows(string $xlsxPath, ?array $preferredHeaders = null): array
	{
		if (!class_exists(\ZipArchive::class)) {
			throw new \RuntimeException('Extensão ZipArchive não está disponível no PHP.');
		}

		$zip = new \ZipArchive();
		if ($zip->open($xlsxPath) !== true) {
			throw new \RuntimeException('Não foi possível abrir o arquivo XLSX.');
		}

		$sharedStrings = [];
		$sharedXml = $zip->getFromName('xl/sharedStrings.xml');
		if (is_string($sharedXml) && $sharedXml !== '') {
			$sharedDoc = @simplexml_load_string($sharedXml);
			if ($sharedDoc !== false) {
				$siNodes = $sharedDoc->xpath('/*[local-name()="sst"]/*[local-name()="si"]');
				if (!is_array($siNodes)) {
					$siNodes = [];
				}
				foreach ($siNodes as $si) {
					$text = '';
					$tNodes = $si->xpath('./*[local-name()="t"]');
					if (is_array($tNodes) && count($tNodes) > 0) {
						$text = (string) ($tNodes[0] ?? '');
					} else {
						$rNodes = $si->xpath('./*[local-name()="r"]');
						if (!is_array($rNodes)) {
							$rNodes = [];
						}
						foreach ($rNodes as $run) {
							$runTNodes = $run->xpath('./*[local-name()="t"]');
							if (is_array($runTNodes) && count($runTNodes) > 0) {
								$text .= (string) ($runTNodes[0] ?? '');
							}
						}
					}
					$sharedStrings[] = $text;
				}
			}
		}

		$sheetXml = null;
		$sheetCandidates = [];
		$workbookXml = $zip->getFromName('xl/workbook.xml');
		$relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
		if (is_string($workbookXml) && $workbookXml !== '' && is_string($relsXml) && $relsXml !== '') {
			$workbookDoc = @simplexml_load_string($workbookXml);
			$relsDoc = @simplexml_load_string($relsXml);
			if ($workbookDoc !== false && $relsDoc !== false) {
				$workbookDoc->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
				$relsDoc->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');
				$sheetNodes = $workbookDoc->xpath('//x:sheets/x:sheet');
				$relNodes = $relsDoc->xpath('//r:Relationship');
				$relById = [];
				if (is_array($relNodes)) {
					foreach ($relNodes as $relNode) {
						$rid = (string) ($relNode['Id'] ?? '');
						$target = (string) ($relNode['Target'] ?? '');
						if ($rid !== '' && $target !== '') {
							$relById[$rid] = $target;
						}
					}
				}
				if (is_array($sheetNodes) && count($sheetNodes) > 0) {
					foreach ($sheetNodes as $sheetNode) {
						$sheetName = (string) ($sheetNode['name'] ?? '');
						$rid = (string) ($sheetNode->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] ?? '');
						if ($rid === '' || !isset($relById[$rid])) {
							continue;
						}
						$target = (string) $relById[$rid];
						$sheetRawXml = null;
						foreach ($this->xlsxResolveEntryCandidates($target) as $sheetPathCandidate) {
							$candidateXml = $zip->getFromName($sheetPathCandidate);
							if (is_string($candidateXml) && $candidateXml !== '') {
								$sheetRawXml = $candidateXml;
								break;
							}
						}
						if (!is_string($sheetRawXml) || $sheetRawXml === '') {
							continue;
						}
						$sheetCandidates[] = [
							'name' => $sheetName,
							'xml' => $sheetRawXml,
						];
					}
				}
			}
		}
		if (count($sheetCandidates) === 0) {
			$fallbackXml = $zip->getFromName('xl/worksheets/sheet1.xml');
			if (is_string($fallbackXml) && $fallbackXml !== '') {
				$sheetCandidates[] = [
					'name' => 'sheet1',
					'xml' => $fallbackXml,
				];
			}
		}
		if (count($sheetCandidates) === 0) {
			// Fallback robusto: listar qualquer aba física existente no ZIP.
			$sheetCandidates = $this->xlsxCollectWorksheetCandidates($zip);
		}
		if (count($sheetCandidates) > 0) {
			// Escolher automaticamente a aba com maior aderência ao cabeçalho esperado
			// para evitar "importação vazia" quando a primeira aba for Dashboard/Resumo.
			$requiredHeaders = $preferredHeaders ?? ['DATA', 'LOJA', 'SETUP', 'ROLLOUT', 'HEXAPADS', 'DEFEITO', 'SUPORTE'];
			$normalizeSheetHeader = static function (string $text): string {
				$text = strtoupper(trim($text));
				$from = ['Á', 'À', 'Â', 'Ã', 'Ä', 'É', 'È', 'Ê', 'Ë', 'Í', 'Ì', 'Î', 'Ï', 'Ó', 'Ò', 'Ô', 'Õ', 'Ö', 'Ú', 'Ù', 'Û', 'Ü', 'Ç'];
				$to =   ['A', 'A', 'A', 'A', 'A', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'C'];
				$text = str_replace($from, $to, $text);
				return preg_replace('/[^A-Z0-9]/', '', $text) ?? $text;
			};
			$bestScore = -1;
			foreach ($sheetCandidates as $candidate) {
				$rows = $this->extractRowsFromSheetXml((string) $candidate['xml'], $sharedStrings);
				if (count($rows) === 0) {
					continue;
				}
				$headerLimit = min(count($rows), 8);
				$bestRowScore = -1;
				for ($headerRowIdx = 0; $headerRowIdx < $headerLimit; $headerRowIdx++) {
					$headerKeys = array_map(
						static fn($v) => $normalizeSheetHeader((string) $v),
						$rows[$headerRowIdx]
					);
					$headerKeys = array_values(array_filter($headerKeys, static fn($h) => $h !== ''));
					$rowScore = 0;
					foreach ($requiredHeaders as $requiredHeader) {
						$needle = $normalizeSheetHeader($requiredHeader);
						if ($needle === '') {
							continue;
						}
						foreach ($headerKeys as $headerKey) {
							if ($headerKey === $needle || str_contains($headerKey, $needle) || str_contains($needle, $headerKey)) {
								$rowScore++;
								break;
							}
						}
					}
					if ($rowScore > $bestRowScore) {
						$bestRowScore = $rowScore;
					}
				}
				if ($bestRowScore > $bestScore) {
					$bestScore = $bestRowScore;
					$sheetXml = (string) $candidate['xml'];
				}
			}
		}
		if (!is_string($sheetXml) || $sheetXml === '') {
			$entries = [];
			$entryTotal = (int) $zip->numFiles;
			for ($i = 0; $i < min($entryTotal, 30); $i++) {
				$name = (string) $zip->getNameIndex($i);
				if ($name !== '') {
					$entries[] = $name;
				}
			}
			$hasWorkbook = is_string($workbookXml) && $workbookXml !== '';
			$hasRels = is_string($relsXml) && $relsXml !== '';
			$zip->close();
			throw new \RuntimeException(
				'Aba principal da planilha não foi encontrada. ' .
				'debug: entries=' . $entryTotal .
				', workbook=' . ($hasWorkbook ? 'ok' : 'nao') .
				', rels=' . ($hasRels ? 'ok' : 'nao') .
				', candidatos=' . count($sheetCandidates) .
				', amostra=[' . implode(', ', $entries) . ']'
			);
		}
		$zip->close();
		return $this->extractRowsFromSheetXml($sheetXml, $sharedStrings);
	}

	/**
	 * @return array<int, array<int, string>>
	 */
	private function extractRowsFromSheetXml(string $sheetXml, array $sharedStrings): array
	{
		$sheetDoc = @simplexml_load_string($sheetXml);
		if ($sheetDoc === false) {
			throw new \RuntimeException('Falha ao interpretar XML da planilha.');
		}

		$rows = [];
		$rowNodes = $sheetDoc->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]');
		if (!is_array($rowNodes)) {
			return [];
		}

		foreach ($rowNodes as $rowNode) {
			$row = [];
			$cells = $rowNode->xpath('./*[local-name()="c"]');
			if (!is_array($cells)) {
				continue;
			}

			$fallbackColIndex = 0;
			foreach ($cells as $cell) {
				$ref = (string) ($cell['r'] ?? '');
				$colIndex = $this->xlsxColumnIndexFromRef($ref);
				if ($colIndex < 0) {
					// Alguns geradores não incluem referência (r="A1") em todas as células.
					// Nesses casos, usar sequência posicional na linha.
					$colIndex = $fallbackColIndex;
				}
				$value = $this->xlsxExtractCellValue($cell, $sharedStrings);
				if ($colIndex >= 0) {
					$row[$colIndex] = $value;
					$fallbackColIndex = $colIndex + 1;
				}
			}

			if (!empty($row)) {
				ksort($row);
				$rows[] = $row;
			}
		}

		return $rows;
	}

	private function normalizeSpreadsheetDateToYmd($raw): ?string
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

	private function xlsxExtractCellValue(\SimpleXMLElement $cell, array $sharedStrings): string
	{
		$attrs = $cell->attributes();
		$type = (string) ($attrs['t'] ?? '');

		if ($type === 'inlineStr') {
			$text = '';
			$isNodes = $cell->xpath('./*[local-name()="is"]');
			if (is_array($isNodes) && count($isNodes) > 0) {
				$inline = $isNodes[0];
				$tNodes = $inline->xpath('./*[local-name()="t"]');
				if (is_array($tNodes) && count($tNodes) > 0) {
					$text = (string) ($tNodes[0] ?? '');
				} else {
					$rNodes = $inline->xpath('./*[local-name()="r"]');
					if (!is_array($rNodes)) {
						$rNodes = [];
					}
					foreach ($rNodes as $run) {
						$runTNodes = $run->xpath('./*[local-name()="t"]');
						if (is_array($runTNodes) && count($runTNodes) > 0) {
							$text .= (string) ($runTNodes[0] ?? '');
						}
					}
				}
			}
			return $text;
		}

		$vNodes = $cell->xpath('./*[local-name()="v"]');
		if (!is_array($vNodes) || count($vNodes) === 0) {
			return '';
		}
		$raw = (string) ($vNodes[0] ?? '');
		if ($type === 's') {
			$sharedIndex = (int) $raw;
			return (string) ($sharedStrings[$sharedIndex] ?? '');
		}
		if ($type === 'b') {
			return $raw === '1' ? '1' : '0';
		}
		return $raw;
	}

	private function xlsxColumnIndexFromRef(string $ref): int
	{
		if ($ref === '') {
			return -1;
		}
		if (!preg_match('/^([A-Z]+)/i', $ref, $m)) {
			return -1;
		}
		$letters = strtoupper($m[1]);
		$index = 0;
		$len = strlen($letters);
		for ($i = 0; $i < $len; $i++) {
			$index = ($index * 26) + (ord($letters[$i]) - 64);
		}
		return $index - 1;
	}

	/**
	 * Normaliza caminhos de entries de XLSX vindos de workbook rels.
	 *
	 * @return array<int, string>
	 */
	private function xlsxResolveEntryCandidates(string $target): array
	{
		$value = trim($target);
		if ($value === '') {
			return [];
		}

		$value = str_replace('\\', '/', $value);
		$value = ltrim($value, '/');
		$normalized = preg_replace('#/\./#', '/', $value) ?? $value;
		while (strpos($normalized, '../') === 0) {
			$normalized = substr($normalized, 3);
		}

		$candidates = [$normalized];
		if (strpos($normalized, 'xl/') !== 0) {
			$candidates[] = 'xl/' . ltrim($normalized, '/');
		}
		if (strpos($normalized, 'worksheets/') === 0) {
			$candidates[] = 'xl/' . $normalized;
		}

		return array_values(array_unique(array_filter($candidates, static fn($v) => $v !== '')));
	}

	/**
	 * @return array<int, array{name:string, xml:string}>
	 */
	private function xlsxCollectWorksheetCandidates(\ZipArchive $zip): array
	{
		$candidates = [];
		for ($i = 0; $i < $zip->numFiles; $i++) {
			$entryName = (string) $zip->getNameIndex($i);
			if ($entryName === '') {
				continue;
			}
			if (!preg_match('#^xl/worksheets/[^/]+\.xml$#i', $entryName)) {
				continue;
			}
			$xml = $zip->getFromIndex($i);
			if (!is_string($xml) || $xml === '') {
				continue;
			}
			$candidates[] = [
				'name' => basename($entryName, '.xml'),
				'xml' => $xml,
			];
		}
		return $candidates;
	}

}
