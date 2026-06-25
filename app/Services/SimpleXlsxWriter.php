<?php
declare(strict_types=1);

namespace App\Services;

final class SimpleXlsxWriter
{
	/**
	 * @param array<int, string> $headers
	 * @param array<int, array<int, string|int|float>> $rows
	 * @param array<int, string> $preface
	 */
	public static function download(string $filename, array $headers, array $rows, array $preface = []): void
	{
		if (!class_exists(\ZipArchive::class)) {
			self::downloadCsvFallback($filename, $headers, $rows, $preface);
			return;
		}

		$tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
		if ($tmp === false) {
			self::downloadCsvFallback($filename, $headers, $rows, $preface);
			return;
		}

		$zipPath = $tmp . '.xlsx';
		@rename($tmp, $zipPath);

		$zip = new \ZipArchive();
		if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
			@unlink($zipPath);
			self::downloadCsvFallback($filename, $headers, $rows, $preface);
			return;
		}

		$sheetRows = [];
		foreach ($preface as $line) {
			$sheetRows[] = [(string) $line];
		}
		if ($preface !== []) {
			$sheetRows[] = [''];
		}
		$sheetRows[] = $headers;
		foreach ($rows as $row) {
			$sheetRows[] = array_map(static fn ($value) => (string) $value, $row);
		}

		$zip->addFromString('[Content_Types].xml', self::contentTypesXml());
		$zip->addFromString('_rels/.rels', self::rootRelsXml());
		$zip->addFromString('xl/workbook.xml', self::workbookXml());
		$zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRelsXml());
		$zip->addFromString('xl/styles.xml', self::stylesXml());
		$zip->addFromString('xl/worksheets/sheet1.xml', self::sheetXml($sheetRows));
		$zip->close();

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Cache-Control: no-cache, no-store, must-revalidate');
		header('Content-Length: ' . (string) filesize($zipPath));
		readfile($zipPath);
		@unlink($zipPath);
	}

	/**
	 * @param array<int, string> $headers
	 * @param array<int, array<int, string|int|float>> $rows
	 * @param array<int, string> $preface
	 */
	private static function downloadCsvFallback(string $filename, array $headers, array $rows, array $preface): void
	{
		$csvName = preg_replace('/\.xlsx$/i', '.csv', $filename) ?: ($filename . '.csv');

		header('Content-Type: text/csv; charset=UTF-8');
		header('Content-Disposition: attachment; filename="' . $csvName . '"');
		header('Cache-Control: no-cache, no-store, must-revalidate');

		$output = fopen('php://output', 'w');
		if ($output === false) {
			return;
		}

		fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
		foreach ($preface as $line) {
			fputcsv($output, [(string) $line], ';');
		}
		if ($preface !== []) {
			fputcsv($output, [''], ';');
		}
		fputcsv($output, $headers, ';');
		foreach ($rows as $row) {
			fputcsv($output, array_map(static fn ($value) => (string) $value, $row), ';');
		}
		fclose($output);
	}

	private static function contentTypesXml(): string
	{
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
			. '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
			. '</Types>';
	}

	private static function rootRelsXml(): string
	{
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
			. '</Relationships>';
	}

	private static function workbookXml(): string
	{
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<sheets><sheet name="ACUPAD" sheetId="1" r:id="rId1"/></sheets>'
			. '</workbook>';
	}

	private static function workbookRelsXml(): string
	{
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
			. '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
			. '</Relationships>';
	}

	private static function stylesXml(): string
	{
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
			. '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
			. '<borders count="1"><border/></borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
			. '</styleSheet>';
	}

	/** @param array<int, array<int, string>> $rows */
	private static function sheetXml(array $rows): string
	{
		$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<sheetData>';

		foreach ($rows as $rowIndex => $cells) {
			$rowNumber = $rowIndex + 1;
			$xml .= '<row r="' . $rowNumber . '">';
			foreach ($cells as $colIndex => $value) {
				$cellRef = self::columnName($colIndex) . $rowNumber;
				$xml .= '<c r="' . $cellRef . '" t="inlineStr"><is><t>'
					. self::escapeXml((string) $value)
					. '</t></is></c>';
			}
			$xml .= '</row>';
		}

		$xml .= '</sheetData></worksheet>';

		return $xml;
	}

	private static function columnName(int $index): string
	{
		$name = '';
		$index++;
		while ($index > 0) {
			$remainder = ($index - 1) % 26;
			$name = chr(65 + $remainder) . $name;
			$index = intdiv($index - 1, 26);
		}

		return $name;
	}

	private static function escapeXml(string $value): string
	{
		return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
	}
}
