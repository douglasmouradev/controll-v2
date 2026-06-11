<?php
declare(strict_types=1);

namespace App\Services;

final class Mail
{
	public static function queue(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool
	{
		if (EmailQueue::isAvailable() && getenv('MAIL_SYNC') !== '1') {
			return EmailQueue::enqueue($to, $subject, $htmlBody, $textBody);
		}

		return self::send($to, $subject, $htmlBody, $textBody);
	}

	public static function send(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool
	{
		$to = trim($to);
		if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
			self::log('invalid_recipient', $to, $subject, false, 'E-mail de destino inválido');
			return false;
		}

		$from = trim((string) (getenv('MAIL_FROM') ?: 'noreply@controllit.com.br'));
		$fromName = trim((string) (getenv('MAIL_FROM_NAME') ?: 'Controll IT Help Desk'));
		$textBody = $textBody ?? strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));

		$smtpHost = trim((string) (getenv('MAIL_HOST') ?: ''));
		$sent = $smtpHost !== ''
			? self::sendViaSmtp($to, $subject, $htmlBody, $textBody, $from, $fromName)
			: self::sendViaPhpMail($to, $subject, $htmlBody, $textBody, $from, $fromName);

		self::log($smtpHost !== '' ? 'smtp' : 'mail', $to, $subject, $sent);
		return $sent;
	}

	private static function sendViaPhpMail(
		string $to,
		string $subject,
		string $htmlBody,
		string $textBody,
		string $from,
		string $fromName
	): bool {
		$boundary = '=_' . bin2hex(random_bytes(8));
		$headers = [
			'MIME-Version: 1.0',
			'From: ' . self::encodeAddress($fromName, $from),
			'Reply-To: ' . $from,
			'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
			'X-Mailer: ControllIT-HelpDesk',
		];
		$body = self::buildMultipartBody($boundary, $textBody, $htmlBody);
		$subjectEncoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';

		try {
			return @mail($to, $subjectEncoded, $body, implode("\r\n", $headers));
		} catch (\Throwable $e) {
			self::log('mail', $to, $subject, false, $e->getMessage());
			return false;
		}
	}

	private static function sendViaSmtp(
		string $to,
		string $subject,
		string $htmlBody,
		string $textBody,
		string $from,
		string $fromName
	): bool {
		$host = trim((string) getenv('MAIL_HOST'));
		$port = (int) (getenv('MAIL_PORT') ?: 587);
		$username = trim((string) (getenv('MAIL_USERNAME') ?: ''));
		$password = (string) (getenv('MAIL_PASSWORD') ?: '');
		$encryption = strtolower(trim((string) (getenv('MAIL_ENCRYPTION') ?: 'tls')));

		$remote = $encryption === 'ssl' ? "ssl://{$host}:{$port}" : "{$host}:{$port}";
		$socket = @stream_socket_client($remote, $errno, $errstr, 30);
		if (!$socket) {
			self::log('smtp', $to, $subject, false, "Conexão falhou: {$errstr} ({$errno})");
			return false;
		}

		stream_set_timeout($socket, 30);

		try {
			self::expect($socket, [220]);
			self::command($socket, 'EHLO ' . (gethostname() ?: 'localhost'), [250]);

			if ($encryption === 'tls') {
				self::command($socket, 'STARTTLS', [220]);
				if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
					throw new \RuntimeException('Falha ao iniciar TLS');
				}
				self::command($socket, 'EHLO ' . (gethostname() ?: 'localhost'), [250]);
			}

			if ($username !== '') {
				self::command($socket, 'AUTH LOGIN', [334]);
				self::command($socket, base64_encode($username), [334]);
				self::command($socket, base64_encode($password), [235]);
			}

			self::command($socket, 'MAIL FROM:<' . $from . '>', [250]);
			self::command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
			self::command($socket, 'DATA', [354]);

			$boundary = '=_' . bin2hex(random_bytes(8));
			$message = 'From: ' . self::encodeAddress($fromName, $from) . "\r\n";
			$message .= 'To: <' . $to . ">\r\n";
			$message .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
			$message .= "MIME-Version: 1.0\r\n";
			$message .= 'Content-Type: multipart/alternative; boundary="' . $boundary . "\"\r\n";
			$message .= "X-Mailer: ControllIT-HelpDesk\r\n\r\n";
			$message .= self::buildMultipartBody($boundary, $textBody, $htmlBody);
			$message .= "\r\n.\r\n";

			fwrite($socket, $message);
			self::expect($socket, [250]);
			self::command($socket, 'QUIT', [221]);
			fclose($socket);
			return true;
		} catch (\Throwable $e) {
			fclose($socket);
			self::log('smtp', $to, $subject, false, $e->getMessage());
			return false;
		}
	}

	private static function buildMultipartBody(string $boundary, string $textBody, string $htmlBody): string
	{
		$body = "--{$boundary}\r\n";
		$body .= "Content-Type: text/plain; charset=UTF-8\r\n";
		$body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
		$body .= $textBody . "\r\n\r\n";
		$body .= "--{$boundary}\r\n";
		$body .= "Content-Type: text/html; charset=UTF-8\r\n";
		$body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
		$body .= $htmlBody . "\r\n\r\n";
		$body .= "--{$boundary}--";
		return $body;
	}

	private static function command($socket, string $command, array $expectedCodes): void
	{
		fwrite($socket, $command . "\r\n");
		self::expect($socket, $expectedCodes);
	}

	private static function expect($socket, array $expectedCodes): void
	{
		$response = '';
		while (($line = fgets($socket, 515)) !== false) {
			$response .= $line;
			if (isset($line[3]) && $line[3] === ' ') {
				break;
			}
		}
		$code = (int) substr($response, 0, 3);
		if (!in_array($code, $expectedCodes, true)) {
			throw new \RuntimeException('SMTP inesperado: ' . trim($response));
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

	private static function log(
		string $transport,
		string $to,
		string $subject,
		bool $success,
		?string $error = null
	): void {
		try {
			$logDir = defined('BASE_PATH') ? BASE_PATH . '/storage/logs' : dirname(__DIR__, 2) . '/storage/logs';
			if (!is_dir($logDir)) {
				@mkdir($logDir, 0775, true);
			}
			$entry = [
				'time' => date('c'),
				'transport' => $transport,
				'to' => $to,
				'subject' => $subject,
				'success' => $success,
				'error' => $error,
			];
			@file_put_contents(
				$logDir . '/mail.log',
				json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL,
				FILE_APPEND
			);
		} catch (\Throwable $e) {
		}
	}
}
