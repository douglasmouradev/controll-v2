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

	public static function exportPdf(): void
	{
		$filters = SdwanEntry::filtersFromRequest();
		$rows = self::rows($filters);
		$summary = SdwanEntry::summary($filters);
		$date = DateFormatter::now();
		$filename = 'projeto-acupad-' . DateFormatter::now('Y-m-d') . '.pdf';

		if (class_exists(\FPDF::class)) {
			$pdf = new \FPDF('L', 'mm', 'A4');
			$pdf->AddPage();
			$pdf->SetFont('Arial', 'B', 16);
			$pdf->Cell(0, 10, self::pdfText('Projeto ACUPAD'), 0, 1, 'C');
			$pdf->SetFont('Arial', '', 10);
			$pdf->Cell(0, 8, self::pdfText('Gerado em ' . $date), 0, 1, 'C');
			$pdf->Ln(2);
			$pdf->SetFont('Arial', '', 9);
			$pdf->Cell(0, 6, self::pdfText(sprintf(
				'Registros: %d | Acupad previstos: %d | Quantidade localizada: %d | Quantidade utilizada: %d | Lojas: %d',
				(int) ($summary['total'] ?? 0),
				(int) ($summary['xpads_previsto'] ?? 0),
				(int) ($summary['quantidade_localizada'] ?? 0),
				(int) ($summary['quantidade_utilizada'] ?? 0),
				(int) ($summary['total_lojas'] ?? 0)
			)), 0, 1);
			$pdf->Ln(3);

			$pdf->SetFont('Arial', 'B', 6.5);
			$pdf->SetFillColor(37, 99, 235);
			$pdf->SetTextColor(255, 255, 255);
			$pdf->Cell(24, 7, self::pdfText('Acupad previstos'), 1, 0, 'C', true);
			$pdf->Cell(24, 7, self::pdfText('Qtd. localizada'), 1, 0, 'C', true);
			$pdf->Cell(24, 7, self::pdfText('Qtd. utilizada'), 1, 0, 'C', true);
			$pdf->Cell(22, 7, self::pdfText('N PDV'), 1, 0, 'C', true);
			$pdf->Cell(30, 7, self::pdfText('N Serie PDV'), 1, 0, 'C', true);
			$pdf->Cell(32, 7, self::pdfText('Serie antena'), 1, 0, 'C', true);
			$pdf->Cell(32, 7, self::pdfText('Serie Acupad'), 1, 0, 'C', true);
			$pdf->Cell(28, 7, self::pdfText('Setor'), 1, 0, 'C', true);
			$pdf->Cell(20, 7, 'Loja', 1, 1, 'C', true);

			$pdf->SetFont('Arial', '', 6.5);
			$pdf->SetTextColor(0, 0, 0);
			foreach ($rows as $row) {
				$rowHeight = 6.0;

				$pdf->Cell(24, $rowHeight, (string) (int) ($row['xpads_previsto'] ?? 0), 1, 0, 'C');
				$pdf->Cell(24, $rowHeight, (string) (int) ($row['quantidade_localizada'] ?? 0), 1, 0, 'C');
				$pdf->Cell(24, $rowHeight, (string) (int) ($row['quantidade_utilizada'] ?? 0), 1, 0, 'C');
				$pdf->Cell(22, $rowHeight, self::pdfText(substr((string) ($row['pdv_numero'] ?? ''), 0, 12)), 1, 0, 'C');
				$pdf->Cell(30, $rowHeight, self::pdfText(substr((string) ($row['pdv_serie'] ?? ''), 0, 18)), 1, 0, 'C');
				$pdf->Cell(32, $rowHeight, self::pdfText(substr((string) ($row['serie_antena'] ?? ''), 0, 20)), 1, 0, 'C');
				$pdf->Cell(32, $rowHeight, self::pdfText(substr((string) ($row['serie_acupad'] ?? ''), 0, 20)), 1, 0, 'C');
				$pdf->Cell(28, $rowHeight, self::pdfText(substr((string) ($row['setor'] ?? ''), 0, 16)), 1, 0, 'C');
				$pdf->Cell(20, $rowHeight, self::pdfText(substr((string) ($row['loja'] ?? ''), 0, 10)), 1, 1, 'C');

				if ($pdf->GetY() > 180) {
					$pdf->AddPage();
					$pdf->SetFont('Arial', 'B', 6.5);
					$pdf->SetFillColor(37, 99, 235);
					$pdf->SetTextColor(255, 255, 255);
					$pdf->Cell(24, 7, self::pdfText('Acupad previstos'), 1, 0, 'C', true);
					$pdf->Cell(24, 7, self::pdfText('Qtd. localizada'), 1, 0, 'C', true);
					$pdf->Cell(24, 7, self::pdfText('Qtd. utilizada'), 1, 0, 'C', true);
					$pdf->Cell(22, 7, self::pdfText('N PDV'), 1, 0, 'C', true);
					$pdf->Cell(30, 7, self::pdfText('N Serie PDV'), 1, 0, 'C', true);
					$pdf->Cell(32, 7, self::pdfText('Serie antena'), 1, 0, 'C', true);
					$pdf->Cell(32, 7, self::pdfText('Serie Acupad'), 1, 0, 'C', true);
					$pdf->Cell(28, 7, self::pdfText('Setor'), 1, 0, 'C', true);
					$pdf->Cell(20, 7, 'Loja', 1, 1, 'C', true);
					$pdf->SetFont('Arial', '', 6.5);
					$pdf->SetTextColor(0, 0, 0);
				}
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
		$filename = 'projeto-acupad-' . DateFormatter::now('Y-m-d') . '.xlsx';

		$dataRows = [];
		foreach ($rows as $row) {
			$line = [
				(string) (int) ($row['xpads_previsto'] ?? 0),
				(string) (int) ($row['quantidade_localizada'] ?? 0),
				(string) (int) ($row['quantidade_utilizada'] ?? 0),
				(string) ($row['pdv_numero'] ?? ''),
				(string) ($row['pdv_serie'] ?? ''),
				(string) ($row['serie_antena'] ?? ''),
				(string) ($row['serie_acupad'] ?? ''),
				(string) ($row['setor'] ?? ''),
				(string) ($row['loja'] ?? ''),
			];
			$dataRows[] = $line;
		}

		$headers = [
			'Acupad previstos',
			'Quantidade localizada',
			'Quantidade utilizada',
			'N PDV',
			'N Serie PDV',
			'Série antena',
			'Série Acupad',
			'Setor',
			'Loja',
		];

		SimpleXlsxWriter::download(
			$filename,
			$headers,
			$dataRows,
			[
				'Projeto ACUPAD',
				'Gerado em ' . $date,
				sprintf(
					'Registros: %d | Acupad previstos: %d | Quantidade localizada: %d | Quantidade utilizada: %d | Lojas: %d',
					(int) ($summary['total'] ?? 0),
					(int) ($summary['xpads_previsto'] ?? 0),
					(int) ($summary['quantidade_localizada'] ?? 0),
					(int) ($summary['quantidade_utilizada'] ?? 0),
					(int) ($summary['total_lojas'] ?? 0)
				),
			]
		);
	}

	/** @param array<int, array<string, mixed>> $rows */
	/** @param array{total: int, xpads_previsto: int, quantidade_localizada: int, quantidade_utilizada: int, total_lojas: int} $summary */
	private static function buildHtmlTable(array $rows, array $summary, string $date): string
	{
		$html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"><style>
			table{border-collapse:collapse;width:100%}
			th,td{border:1px solid #000;padding:5px;text-align:left}
			th{background:#2563eb;color:#fff;font-weight:bold}
		</style></head><body>';
		$html .= '<h2>Projeto ACUPAD</h2>';
		$html .= '<p>Gerado em ' . htmlspecialchars($date) . '</p>';
		$html .= '<p>Registros: ' . (int) ($summary['total'] ?? 0)
			. ' | Acupad previstos: ' . (int) ($summary['xpads_previsto'] ?? 0)
			. ' | Quantidade localizada: ' . (int) ($summary['quantidade_localizada'] ?? 0)
			. ' | Quantidade utilizada: ' . (int) ($summary['quantidade_utilizada'] ?? 0)
			. ' | Lojas: ' . (int) ($summary['total_lojas'] ?? 0) . '</p>';
		$html .= '<table><tr>
			<th>Acupad previstos</th><th>Quantidade localizada</th><th>Quantidade utilizada</th>
			<th>N PDV</th><th>N Serie PDV</th><th>Série antena</th><th>Série Acupad</th><th>Setor</th><th>Loja</th></tr>';

		foreach ($rows as $row) {
			$html .= '<tr>'
				. '<td>' . (int) ($row['xpads_previsto'] ?? 0) . '</td>'
				. '<td>' . (int) ($row['quantidade_localizada'] ?? 0) . '</td>'
				. '<td>' . (int) ($row['quantidade_utilizada'] ?? 0) . '</td>'
				. '<td>' . htmlspecialchars((string) ($row['pdv_numero'] ?? '')) . '</td>'
				. '<td>' . htmlspecialchars((string) ($row['pdv_serie'] ?? '')) . '</td>'
				. '<td>' . htmlspecialchars((string) ($row['serie_antena'] ?? '')) . '</td>'
				. '<td>' . htmlspecialchars((string) ($row['serie_acupad'] ?? '')) . '</td>'
				. '<td>' . htmlspecialchars((string) ($row['setor'] ?? '')) . '</td>'
				. '<td>' . htmlspecialchars((string) ($row['loja'] ?? '')) . '</td>'
				. '</tr>';
		}

		$html .= '</table></body></html>';

		return $html;
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
