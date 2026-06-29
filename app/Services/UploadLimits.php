<?php
declare(strict_types=1);

namespace App\Services;

final class UploadLimits
{
	public const MAX_FILE_BYTES = 40 * 1024 * 1024;
	public const MAX_FILES = 20;

	public static function iniBytes(string $directive): int
	{
		$raw = ini_get($directive);
		if (!is_string($raw) || $raw === '') {
			return 0;
		}
		$raw = trim($raw);
		if ($raw === '-1') {
			return PHP_INT_MAX;
		}
		$unit = strtolower(substr($raw, -1));
		$value = (float) $raw;
		return match ($unit) {
			'g' => (int) round($value * 1024 * 1024 * 1024),
			'm' => (int) round($value * 1024 * 1024),
			'k' => (int) round($value * 1024),
			default => (int) round($value),
		};
	}

	public static function formatBytes(int $bytes): string
	{
		if ($bytes >= 1024 * 1024) {
			return rtrim(rtrim(number_format($bytes / (1024 * 1024), 1, '.', ''), '0'), '.') . ' MB';
		}
		if ($bytes >= 1024) {
			return rtrim(rtrim(number_format($bytes / 1024, 1, '.', ''), '0'), '.') . ' KB';
		}

		return $bytes . ' B';
	}

	public static function postBodyTooLargeMessage(): ?string
	{
		if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
			return null;
		}

		$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
		if ($contentLength <= 0) {
			return null;
		}

		$postMax = self::iniBytes('post_max_size');
		if ($postMax > 0 && $contentLength > $postMax) {
			return 'O envio total excede o limite do servidor (' . self::formatBytes($postMax) . '). Reduza o tamanho dos arquivos ou envie menos anexos por vez.';
		}

		$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
		if (
			str_contains($contentType, 'multipart/form-data')
			&& $contentLength > 0
			&& empty($_POST)
			&& empty($_FILES)
		) {
			$limit = $postMax > 0 ? self::formatBytes($postMax) : 'configurado no servidor';

			return 'O servidor rejeitou o envio por exceder o limite de ' . $limit . '. Tente arquivos menores ou contate o administrador.';
		}

		return null;
	}

	public static function uploadErrorMessage(int $code, string $fileName = ''): ?string
	{
		$label = $fileName !== '' ? (' (' . $fileName . ')') : '';
		$uploadMax = self::formatBytes(self::iniBytes('upload_max_filesize'));

		return match ($code) {
			UPLOAD_ERR_INI_SIZE => 'Arquivo' . $label . ' excede o limite do PHP (' . $uploadMax . ').',
			UPLOAD_ERR_FORM_SIZE => 'Arquivo' . $label . ' excede o limite permitido pelo formulário.',
			UPLOAD_ERR_PARTIAL => 'Upload incompleto do arquivo' . $label . '. Tente novamente.',
			UPLOAD_ERR_NO_FILE => null,
			UPLOAD_ERR_NO_TMP_DIR => 'Servidor sem pasta temporária para upload.',
			UPLOAD_ERR_CANT_WRITE => 'Servidor não conseguiu gravar o arquivo' . $label . '.',
			UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão do PHP.',
			default => $code !== UPLOAD_ERR_OK ? ('Falha no upload do arquivo' . $label . ' (código ' . $code . ').') : null,
		};
	}
}
