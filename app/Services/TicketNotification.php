<?php
declare(strict_types=1);

namespace App\Services;

final class TicketNotification
{
	private const DEFAULT_RECIPIENT = 'Grupotitanium@titaniumtelecom.com.br';

	public static function notifyTicketOpened(int $ticketId, array $ticketData, array $openedByUser): void
	{
		$recipient = trim((string) (getenv('TICKET_NOTIFICATION_EMAIL') ?: self::DEFAULT_RECIPIENT));
		if ($recipient === '') {
			return;
		}

		$openedByName = (string) ($openedByUser['name'] ?? 'Usuário');
		$openedByEmail = (string) ($openedByUser['email'] ?? '');
		$title = (string) ($ticketData['title'] ?? '');
		$category = (string) ($ticketData['category'] ?? '');
		$priority = (string) ($ticketData['priority'] ?? '');
		$status = (string) ($ticketData['status'] ?? 'Aberto');
		$requesterName = (string) ($ticketData['name'] ?? '');
		$unit = (string) ($ticketData['unit'] ?? '');
		$cep = (string) ($ticketData['cep'] ?? '');
		$address = (string) ($ticketData['address'] ?? '');
		$addressNumber = (string) ($ticketData['address_number'] ?? '');
		$city = (string) ($ticketData['city'] ?? '');
		$uf = (string) ($ticketData['uf'] ?? '');
		$description = (string) ($ticketData['description'] ?? '');
		$qtd = (int) ($ticketData['qtd'] ?? 1);
		$fullAddress = trim($address . ($addressNumber !== '' ? ', ' . $addressNumber : ''));
		$location = trim($fullAddress . ($city !== '' ? ' - ' . $city : '') . ($uf !== '' ? '/' . $uf : '') . ($cep !== '' ? ' (CEP: ' . $cep . ')' : ''));

		$appUrl = self::appUrl();
		$dashboardLink = $appUrl !== '' ? $appUrl . '/' : '';

		$subject = sprintf('[Controll IT] Novo chamado #%d - %s', $ticketId, $title);

		$rows = [
			'Chamado' => '#' . $ticketId,
			'Título' => $title,
			'Categoria' => $category,
			'Prioridade' => $priority,
			'Status' => $status,
			'Quantidade' => (string) $qtd,
			'Solicitante' => $requesterName,
			'Unidade' => $unit,
			'Endereço' => $location !== '' ? $location : '-',
			'Aberto por' => $openedByName . ($openedByEmail !== '' ? ' (' . $openedByEmail . ')' : ''),
			'Data/Hora' => date('d/m/Y H:i:s'),
		];

		$htmlRows = '';
		foreach ($rows as $label => $value) {
			$htmlRows .= '<tr>'
				. '<td style="padding:8px 12px;border:1px solid #e5e7eb;background:#f9fafb;font-weight:600;width:160px;">'
				. htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
				. '</td>'
				. '<td style="padding:8px 12px;border:1px solid #e5e7eb;">'
				. htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
				. '</td></tr>';
		}

		$htmlBody = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;color:#111827;line-height:1.5;">'
			. '<div style="max-width:640px;margin:0 auto;padding:24px;">'
			. '<h2 style="margin:0 0 8px;color:#1d4ed8;">Novo chamado aberto</h2>'
			. '<p style="margin:0 0 16px;">Um novo chamado foi registrado no sistema Controll IT Help Desk.</p>'
			. '<table style="width:100%;border-collapse:collapse;margin-bottom:20px;">' . $htmlRows . '</table>'
			. '<h3 style="margin:0 0 8px;font-size:16px;">Descrição</h3>'
			. '<div style="padding:12px;border:1px solid #e5e7eb;border-radius:8px;background:#f9fafb;white-space:pre-wrap;">'
			. nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8'))
			. '</div>';

		if ($dashboardLink !== '') {
			$htmlBody .= '<p style="margin:20px 0 0;">'
				. '<a href="' . htmlspecialchars($dashboardLink, ENT_QUOTES, 'UTF-8') . '" '
				. 'style="display:inline-block;padding:10px 16px;background:#1d4ed8;color:#fff;text-decoration:none;border-radius:8px;">'
				. 'Acessar o sistema</a></p>';
		}

		$htmlBody .= '</div></body></html>';

		$textLines = ["Novo chamado aberto no Controll IT Help Desk", ""];
		foreach ($rows as $label => $value) {
			$textLines[] = $label . ': ' . $value;
		}
		$textLines[] = '';
		$textLines[] = 'Descrição:';
		$textLines[] = $description;
		if ($dashboardLink !== '') {
			$textLines[] = '';
			$textLines[] = 'Acessar: ' . $dashboardLink;
		}

		$sent = Mail::send($recipient, $subject, $htmlBody, implode("\n", $textLines));
		if (!$sent) {
			error_log(sprintf('Falha ao enviar notificação do chamado #%d para %s', $ticketId, $recipient));
		}
	}

	private static function appUrl(): string
	{
		$configured = trim((string) (getenv('APP_URL') ?: ''));
		if ($configured !== '') {
			return rtrim($configured, '/');
		}

		$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
		if ($host === '') {
			return '';
		}

		$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
		return $scheme . '://' . $host;
	}
}
