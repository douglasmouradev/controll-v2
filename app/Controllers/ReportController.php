<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Ticket;
use App\Services\Auth;

final class ReportController extends Controller
{
	public function pdf(): void
	{
		$this->requireAuth(['support', 'admin']);
		$user = Auth::instance()->user();
		$tickets = Ticket::listForUser($user, []);

		$date = date('d/m/Y H:i');
		
		// Gerar PDF usando FPDF (leve, sem dependências)
		try {
			if (class_exists('FPDF')) {
				$this->generatePdfWithFpdf($tickets, $date);
			} else {
				// Fallback: HTML que pode ser salvo como PDF
				$html = $this->generateReportHtml($tickets, $date);
				header('Content-Type: application/pdf');
				header('Content-Disposition: attachment; filename="relatorio-chamados-' . date('Y-m-d') . '.pdf"');
				echo $html;
			}
		} catch (\Throwable $e) {
			error_log('Erro ao gerar PDF: ' . $e->getMessage());
			header('Content-Type: application/json');
			header('HTTP/1.1 500 Internal Server Error');
			echo json_encode(['error' => 'Erro ao gerar PDF']);
		}
		exit;
	}

	private function generatePdfWithFpdf(array $tickets, string $date): void
	{
		$pdf = new \FPDF();
		$pdf->AddPage();
		$pdf->SetFont('Arial', 'B', 16);
		$pdf->Cell(0, 10, 'Relatorio de Chamados', 0, 1, 'C');
		$pdf->SetFont('Arial', '', 10);
		$pdf->Cell(0, 10, 'Gerado em ' . $date, 0, 1, 'C');
		$pdf->Ln(5);
		
		$pdf->SetFont('Arial', 'B', 9);
		$pdf->SetFillColor(37, 99, 235);
		$pdf->SetTextColor(255, 255, 255);
		$pdf->Cell(15, 7, 'ID', 1, 0, 'C', true);
		$pdf->Cell(35, 7, 'Titulo', 1, 0, 'L', true);
		$pdf->Cell(20, 7, 'Prior.', 1, 0, 'C', true);
		$pdf->Cell(25, 7, 'Categ.', 1, 0, 'L', true);
		$pdf->Cell(30, 7, 'Nome', 1, 0, 'L', true);
		$pdf->Cell(20, 7, 'Status', 1, 1, 'C', true);
		
		$pdf->SetFont('Arial', '', 8);
		$pdf->SetTextColor(0, 0, 0);
		foreach ($tickets as $t) {
			$pdf->Cell(15, 6, (string)$t['id'], 1, 0, 'C');
			$pdf->Cell(35, 6, substr($t['title'], 0, 18), 1, 0, 'L');
			$pdf->Cell(20, 6, substr((string)($t['priority'] ?? ''), 0, 10), 1, 0, 'C');
			$pdf->Cell(25, 6, substr((string)($t['category'] ?? ''), 0, 12), 1, 0, 'L');
			$pdf->Cell(30, 6, substr((string)($t['name'] ?? ''), 0, 14), 1, 0, 'L');
			$pdf->Cell(20, 6, substr((string)($t['status'] ?? ''), 0, 10), 1, 1, 'C');
		}
		
		header('Content-Type: application/pdf');
		header('Content-Disposition: attachment; filename="relatorio-chamados-' . date('Y-m-d') . '.pdf"');
		header('Cache-Control: no-cache, no-store, must-revalidate');
		header('Pragma: no-cache');
		header('Expires: 0');
		$pdf->Output('D', 'relatorio-chamados-' . date('Y-m-d') . '.pdf');
	}

	public function xlsx(): void
	{
		$this->requireAuth(['support', 'admin']);
		$user = Auth::instance()->user();
		$tickets = Ticket::listForUser($user, []);

		$date = date('d/m/Y H:i');
		
		// Gerar XLSX usando HTML (Excel aceita como XLSX)
		try {
			header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
			header('Content-Disposition: attachment; filename="relatorio-chamados-' . date('Y-m-d') . '.xlsx"');
			header('Cache-Control: no-cache, no-store, must-revalidate');
			header('Pragma: no-cache');
			header('Expires: 0');
			$this->generateXlsxAsHtml($tickets, $date);
		} catch (\Throwable $e) {
			error_log('Erro ao gerar XLSX: ' . $e->getMessage());
			header('Content-Type: application/json');
			header('HTTP/1.1 500 Internal Server Error');
			echo json_encode(['error' => 'Erro ao gerar XLSX']);
		}
		exit;
	}

	private function generateXlsxAsHtml(array $tickets, string $date): void
	{
		echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
<head>
	<meta charset="UTF-8">
	<style>
		table { border-collapse: collapse; width: 100%; }
		th, td { border: 1px solid #000; padding: 5px; text-align: left; }
		th { background-color: #2563eb; color: white; font-weight: bold; }
	</style>
</head>
<body>
<table>
	<tr>
		<th>ID</th>
		<th>Titulo</th>
		<th>Prioridade</th>
		<th>Categoria</th>
		<th>Nome</th>
		<th>Matricula</th>
		<th>Unidade</th>
		<th>CEP</th>
		<th>Endereco</th>
		<th>Cidade/UF</th>
		<th>Status</th>
		<th>Data</th>
	</tr>';
		
		foreach ($tickets as $t) {
			echo '<tr>
		<td>' . htmlspecialchars((string)$t['id']) . '</td>
		<td>' . htmlspecialchars($t['title']) . '</td>
		<td>' . htmlspecialchars((string)($t['priority'] ?? '')) . '</td>
		<td>' . htmlspecialchars((string)($t['category'] ?? '')) . '</td>
		<td>' . htmlspecialchars((string)($t['name'] ?? '')) . '</td>
		<td>' . htmlspecialchars((string)($t['registration'] ?? '')) . '</td>
		<td>' . htmlspecialchars((string)($t['unit'] ?? '')) . '</td>
		<td>' . htmlspecialchars((string)($t['cep'] ?? '')) . '</td>
		<td>' . htmlspecialchars((string)($t['address'] ?? '')) . '</td>
		<td>' . htmlspecialchars(((string)($t['city'] ?? '')) . '/' . ((string)($t['uf'] ?? ''))) . '</td>
		<td>' . htmlspecialchars((string)($t['status'] ?? '')) . '</td>
		<td>' . date('d/m/Y H:i', strtotime($t['created_at'])) . '</td>
	</tr>';
		}
		
		echo '</table>
</body>
</html>';
	}

	private function generateXlsxAsXml(array $tickets, string $date): void
	{
		// Gerar como CSV com separador PONTO-E-VÍRGULA (Excel aceita)
		$output = fopen('php://output', 'w');
		
		// BOM para UTF-8
		fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
		
		// Cabeçalho
		fputcsv($output, [
			'ID', 'Titulo', 'Prioridade', 'Categoria', 'Nome', 'Matricula', 
			'Unidade', 'CEP', 'Endereco', 'Cidade/UF', 'Status', 'Data'
		], ";");
		
		// Dados
		foreach ($tickets as $t) {
			fputcsv($output, [
				$t['id'],
				$t['title'],
				(string)($t['priority'] ?? ''),
				(string)($t['category'] ?? ''),
				(string)($t['name'] ?? ''),
				(string)($t['registration'] ?? ''),
				(string)($t['unit'] ?? ''),
				(string)($t['cep'] ?? ''),
				(string)($t['address'] ?? ''),
				((string)($t['city'] ?? '')) . '/' . ((string)($t['uf'] ?? '')),
				(string)($t['status'] ?? ''),
				date('d/m/Y H:i', strtotime($t['created_at']))
			], ";");
		}
		
		fclose($output);
	}

	private function generateXlsxWithPhpSpreadsheet(array $tickets, string $date): void
	{
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('Chamados');

		// Cabeçalho
		$headers = ['ID', 'Título', 'Prioridade', 'Categoria', 'Nome', 'Matrícula', 'Unidade', 'CEP', 'Endereço', 'Cidade/UF', 'Status', 'Data'];
		$sheet->fromArray([$headers], null, 'A1');

		// Dados
		$row = 2;
		foreach ($tickets as $t) {
			$sheet->fromArray([[
				$t['id'],
				$t['title'],
				$t['priority'] ?? '',
				$t['category'] ?? '',
				$t['name'] ?? '',
				$t['registration'] ?? '',
				$t['unit'] ?? '',
				$t['cep'] ?? '',
				$t['address'] ?? '',
				(($t['city'] ?? '') . '/' . ($t['uf'] ?? '')),
				$t['status'] ?? '',
				date('d/m/Y H:i', strtotime($t['created_at']))
			]], null, 'A' . $row);
			$row++;
		}

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment; filename="relatorio-chamados-' . date('Y-m-d') . '.xlsx"');
		header('Cache-Control: no-cache, no-store, must-revalidate');
		header('Pragma: no-cache');
		header('Expires: 0');

		$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
		$writer->save('php://output');
	}

	public function csv(): void
	{
		$this->requireAuth(['support', 'admin']);
		$user = Auth::instance()->user();
		$tickets = Ticket::listForUser($user, []);

		$date = date('d/m/Y H:i');
		
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="relatorio-chamados-' . date('Y-m-d') . '.csv"');
		
		$this->generateCsv($tickets, $date);
		exit;
	}

	private function generateReportHtml(array $tickets, string $date): string
	{
		$html = '<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>Relatório de Chamados</title>
	<style>
		body { font-family: Arial, sans-serif; margin: 20px; }
		.header { text-align: center; margin-bottom: 30px; }
		.header h1 { color: #1e40af; margin: 0; }
		.header p { color: #666; margin: 5px 0; }
		table { width: 100%; border-collapse: collapse; margin-top: 20px; }
		th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
		th { background-color: #2563eb; color: white; }
		tr:nth-child(even) { background-color: #f9fafb; }
	</style>
</head>
<body>
	<div class="header">
		<h1>Relatório de Chamados</h1>
		<p>Gerado em ' . htmlspecialchars($date) . '</p>
	</div>
	<table>
		<thead>
			<tr>
				<th>ID</th>
				<th>Título</th>
				<th>Prioridade</th>
				<th>Categoria</th>
				<th>Nome</th>
				<th>Status</th>
				<th>Data</th>
			</tr>
		</thead>
		<tbody>';

		foreach ($tickets as $t) {
			$html .= '<tr>
				<td>' . (int) $t['id'] . '</td>
				<td>' . htmlspecialchars($t['title']) . '</td>
				<td>' . htmlspecialchars((string)($t['priority'] ?? '')) . '</td>
				<td>' . htmlspecialchars((string)($t['category'] ?? '')) . '</td>
				<td>' . htmlspecialchars((string)($t['name'] ?? '')) . '</td>
				<td>' . htmlspecialchars((string)($t['status'] ?? '')) . '</td>
				<td>' . date('d/m/Y', strtotime($t['created_at'])) . '</td>
			</tr>';
		}

		$html .= '</tbody>
	</table>
</body>
</html>';

		return $html;
	}

	private function generateCsv(array $tickets, string $date): void
	{
		$output = fopen('php://output', 'w');
		
		// BOM para UTF-8 (Excel)
		fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
		
		// Cabeçalho
		fputcsv($output, ['Relatório de Chamados – Gerado em ' . $date], ';');
		fputcsv($output, [], ';');
		
		// Cabeçalhos das colunas
		fputcsv($output, [
			'ID', 'Título', 'Prioridade', 'Categoria', 'Nome', 'Matrícula', 
			'Unidade', 'CEP', 'Endereço', 'Cidade/UF', 'Status', 'Atribuído a', 'Data'
		], ';');
		
		// Dados
		foreach ($tickets as $t) {
			fputcsv($output, [
				$t['id'],
				$t['title'],
				(string)($t['priority'] ?? ''),
				(string)($t['category'] ?? ''),
				(string)($t['name'] ?? ''),
				(string)($t['registration'] ?? ''),
				(string)($t['unit'] ?? ''),
				(string)($t['cep'] ?? ''),
				(string)($t['address'] ?? ''),
				((string)($t['city'] ?? '')) . '/' . ((string)($t['uf'] ?? '')),
				(string)($t['status'] ?? ''),
				$t['assigned_name'] ?? '-',
				date('d/m/Y H:i', strtotime($t['created_at']))
			], ';');
		}
		
		fclose($output);
	}
}

