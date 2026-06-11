<?php
declare(strict_types=1);

namespace App\Services;

final class Mail
{
	public static function send(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool
	{
		$to = trim($to);
		if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
			return false;
		}

		$from = trim((string) (getenv('MAIL_FROM') ?: 'noreply@controllit.com.br'));
		$fromName = trim((string) (getenv('MAIL_FROM_NAME') ?: 'Controll IT Help Desk'));

		$boundary = '=_' . bin2hex(random_bytes(8));
		$textBody = $textBody ?? strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));

		$headers = [
			'MIME-Version: 1.0',
			'From: ' . self::encodeAddress($fromName, $from),
			'Reply-To: ' . $from,
			'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
			'X-Mailer: ControllIT-HelpDesk',
		];

		$body = "--{$boundary}\r\n";
		$body .= "Content-Type: text/plain; charset=UTF-8\r\n";
		$body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
		$body .= $textBody . "\r\n\r\n";
		$body .= "--{$boundary}\r\n";
		$body .= "Content-Type: text/html; charset=UTF-8\r\n";
		$body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
		$body .= $htmlBody . "\r\n\r\n";
		$body .= "--{$boundary}--";

		$subjectEncoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';

		try {
			return @mail($to, $subjectEncoded, $body, implode("\r\n", $headers));
		} catch (\Throwable $e) {
			error_log('Erro ao enviar e-mail: ' . $e->getMessage());
			return false;
		}
	}

	private static function encodeAddress(string $name, string $email): string
	{
		$name = trim($name);
		if ($name === '') {
			return $email;
		}
		return '=?UTF-8?B?' . base64_encode($name) . '?= <' . $email . '>';
	}
}
