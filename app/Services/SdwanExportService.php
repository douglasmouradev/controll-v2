<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\SdwanEntry;

final class SdwanExportService
{
	/** @param array<string, mixed> $filters */
	private static function rows(array $filters = []): array
	{
		return SdwanEntry::exportRows($filters);
	}

	private static function sourceLabel(array $row): string
	{
		$source = (string) ($row['entry_source'] ?? 'dashboard');

		return $source === 'public' ? 'Link técnico' : 'Dashboard';
	}

	public static function exportPdf(): void
	{
		$filters = SdwanEntry::filtersFromRequest();
		$rows = self::rows($filters);
		$summary = SdwanEntry::summary($filters);
		$date = DateFormatter::now();
		$filename = 'projeto-sdwan-' . DateFormatter::now('Y-m-d') . '.pdf';

		if (class_exists(\FPDF::class)) {
			$pdf = new \FPDF();
			$pdf->AddPage();
			$pdf->SetFont('Arial', 'B', 16);
			$pdf->Cell(0, 10, self::pdfText('Projeto SDWAN'), 0, 1, 'C');
			$pdf->SetFont('Arial', '', 10);
			$pdf->Cell(0, 8, self::pdfText('Gerado em ' . $date), 0, 1, 'C');
			$pdf->Ln(2);
			$pdf->SetFont('Arial', '', 9);
			$pdf->Cell(0, 6, self::pdfText(sprintf(
				'Registros: %d | XPads previstos: %d | Quantidade localizada: %d | Lojas: %d',
				(int) ($summary['total'] ?? 0),
				(int) ($summary['xpads_previsto'] ?? 0),
				(int) ($summary['quantidade_localizada'] ?? 0),
				(int) ($summary['total_lojas'] ?? 0)
			)), 0, 1);
			$pdf->Ln(4);

			$pdf->SetFont('Arial', 'B', 8);
			$pdf->SetFillColor(37, 99, 235);
			$pdf->SetTextColor(255, 255, 255);
			$pdf->Cell(18, 7, 'Loja', 1, 0, 'C', true);
			$pdf->Cell(22, 7, 'XPads', 1, 0, 'C', true);
			$pdf->Cell(22, 7, 'Localizada', 1, 0, 'C', true);
			$pdf->Cell(22, 7, 'N PDV', 1, 0, 'C', true);
			$pdf->Cell(30, 7, 'N Serie PDV', 1, 0, 'C', true);
			$pdf->Cell(36, 7, 'Cadastro', 1, 1, 'C', true);

			$pdf->SetFont('Arial', '', 8);
			$pdf->SetTextColor(0, 0, 0);
			foreach ($rows as $row) {
				$pdf->Cell(18, 6, self::pdfText(substr((string) ($row['loja'] ?? ''), 0, 10)), 1, 0, 'C');
				$pdf->Cell(22, 6, (string) (int) ($row['xpads_previsto'] ?? 0), 1, 0, 'C');
				$pdf->Cell(22, 6, (string) (int) ($row['quantidade_localizada'] ?? 0), 1, 0, 'C');
				$pdf->Cell(22, 6, self::pdfText(substr((string) ($row['pdv_numero'] ?? ''), 0, 12)), 1, 0, 'C');
				$pdf->Cell(30, 6, self::pdfText(substr((string) ($row['pdv_serie'] ?? ''), 0, 16)), 1, 0, 'C');
				$pdf->Cell(36, 6, self::pdfText(self::formatDate((string) ($row['created_at'] ?? ''))), 1, 1, 'C');
			}

			header('Content-Type: application/pdf');
			header('Content-Disposition: attachment; filename="' . $filename . '"');
			header('Cache-Control: no-cache, no-store, must-revalidate');
			$pdf->Output('D', $filename);
			return;
		}

		header('Content-Type: text/html; charset=UTF-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		echo self::buildHtmlTable($rows, $summary, $date);
	}

	public static function exportXlsx(): void
	{
		$filters = SdwanEntry::filtersFromRequest();
		$rows = self::rows($filters);
		$summary = SdwanEntry::summary($filters);
		$date = DateFormatter::now();
		$filename = 'projeto-sdwan-' . DateFormatter::now('Y-m-d') . '.xlsx';
		$hasSource = SdwanEntry::hasSourceColumns();

		$dataRows = [];
		foreach ($rows as $row) {
			$line = [
				(string) ($row['loja'] ?? ''),
				(string) (int) ($row['xpads_previsto'] ?? 0),
				(string) (int) ($row['quantidade_localizada'] ?? 0),
				(string) ($row['pdv_numero'] ?? ''),
				(string) ($row['pdv_serie'] ?? ''),
				self::formatDate((string) ($row['created_at'] ?? '')),
				(string) ($row['created_by_name'] ?? '-'),
			];
			if ($hasSource) {
				$line[] = self::sourceLabel($row);
			}
			$dataRows[] = $line;
		}

		$headers = ['Loja', 'XPads previstos', 'Quantidade localizada', 'N PDV', 'N Serie PDV', 'Data cadastro', 'Cadastrado por'];
		if ($hasSource) {
			$headers[] = 'Origem';
		}

		SimpleXlsxWriter::download(
			$filename,
			$headers,
			$dataRows,
			[
				'Projeto SDWAN',
				'Gerado em ' . $date,
				sprintf(
					'Registros: %d | XPads previstos: %d | Quantidade localizada: %d | Lojas: %d',
					(int) ($summary['total'] ?? 0),
					(int) ($summary['xpads_previsto'] ?? 0),
					(int) ($summary['quantidade_localizada'] ?? 0),
					(int) ($summary['total_lojas'] ?? 0)
				),
			]
		);
	}

	/** @param array<int, array<string, mixed>> $rows */
	/** @param array{total: int, xpads_previsto: int, quantidade_localizada: int} $summary */
	private static function buildHtmlTable(array $rows, array $summary, string $date): string
	{
		$html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"><style>
			table{border-collapse:collapse;width:100%}
			th,td{border:1px solid #000;padding:5px;text-align:left}
			th{background:#2563eb;color:#fff;font-weight:bold}
		</style></head><body>';
		$html .= '<h2>Projeto SDWAN</h2>';
		$html .= '<p>Gerado em ' . htmlspecialchars($date) . '</p>';
		$html .= '<p>Registros: ' . (int) ($summary['total'] ?? 0)
			. ' | XPads previstos: ' . (int) ($summary['xpads_previsto'] ?? 0)
			. ' | Quantidade localizada: ' . (int) ($summary['quantidade_localizada'] ?? 0) . '</p>';
		$html .= '<table><tr>
			<th>Loja</th><th>XPads previstos</th><th>Quantidade localizada</th>
			<th>N PDV</th><th>N Serie PDV</th><th>Data cadastro</th>
		</tr>';

		foreach ($rows as $row) {
			$html .= '<tr>'
				. '<td>' . htmlspecialchars((string) ($row['loja'] ?? '')) . '</td>'
				. '<td>' . (int) ($row['xpads_previsto'] ?? 0) . '</td>'
				. '<td>' . (int) ($row['quantidade_localizada'] ?? 0) . '</td>'
				. '<td>' . htmlspecialchars((string) ($row['pdv_numero'] ?? '')) . '</td>'
				. '<td>' . htmlspecialchars((string) ($row['pdv_serie'] ?? '')) . '</td>'
				. '<td>' . htmlspecialchars(self::formatDate((string) ($row['created_at'] ?? ''))) . '</td>'
				. '</tr>';
		}

		$html .= '</table></body></html>';

		return $html;
	}

	private static function formatDate(string $value): string
	{
		return DateFormatter::formatDateTime($value);
	}

	private static function pdfText(string $text): string
	{
		if (function_exists('iconv')) {
			$converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
			if ($converted !== false) {
				return $converted;
			}
		}

		return utf8_decode($text) ?: $text;
	}
}
