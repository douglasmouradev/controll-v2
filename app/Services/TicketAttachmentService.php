<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\TicketAttachment;

final class TicketAttachmentService
{
	private const PRIVATE_PREFIX = 'private:';

	public static function storageDir(): string
	{
		return BASE_PATH . '/storage/uploads/tickets';
	}

	public static function resolveFilesKey(string $preferredKey = 'images'): ?string
	{
		$candidates = [$preferredKey, $preferredKey . '[]', 'attachments', 'attachments[]'];
		foreach ($candidates as $key) {
			if (empty($_FILES[$key])) {
				continue;
			}
			if (is_array($_FILES[$key]['name'] ?? null)) {
				return $key;
			}
			if (is_string($_FILES[$key]['name'] ?? null) && ($_FILES[$key]['name'] ?? '') !== '') {
				$_FILES[$key] = [
					'name' => [$_FILES[$key]['name']],
					'type' => [$_FILES[$key]['type'] ?? ''],
					'tmp_name' => [$_FILES[$key]['tmp_name'] ?? ''],
					'error' => [$_FILES[$key]['error'] ?? UPLOAD_ERR_NO_FILE],
					'size' => [$_FILES[$key]['size'] ?? 0],
				];
				return $key;
			}
		}

		return null;
	}

	public static function handleUpload(int $ticketId, string $filesKey, ?array $user = null): array
	{
		$errors = [];
		$postError = UploadLimits::postBodyTooLargeMessage();
		if ($postError !== null) {
			return ['count' => 0, 'errors' => [$postError]];
		}

		if (empty($_FILES[$filesKey]) || !is_array($_FILES[$filesKey]['name'] ?? null)) {
			return ['count' => 0, 'errors' => $errors];
		}

		$user = $user ?? Auth::instance()->user();
		$userId = (int) ($user['id'] ?? 0);
		$files = $_FILES[$filesKey];
		$uploadDir = self::storageDir();
		if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true)) {
			error_log('TicketAttachmentService: não foi possível criar diretório ' . $uploadDir);
			return ['count' => 0, 'errors' => ['Não foi possível preparar o diretório de anexos.']];
		}
		if (!is_writable($uploadDir)) {
			error_log('TicketAttachmentService: diretório sem permissão de escrita ' . $uploadDir);
			return ['count' => 0, 'errors' => ['Diretório de anexos sem permissão de escrita.']];
		}

		$maxFiles = UploadLimits::MAX_FILES;
		$maxSize = UploadLimits::MAX_FILE_BYTES;
		$fileCount = count($files['name']);
		$processed = 0;

		for ($i = 0; $i < $fileCount && $processed < $maxFiles; $i++) {
			$fileName = (string) ($files['name'][$i] ?? '');
			$uploadError = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
			if ($uploadError !== UPLOAD_ERR_OK) {
				$message = UploadLimits::uploadErrorMessage($uploadError, $fileName);
				if ($message !== null) {
					$errors[] = $message;
				}
				error_log('TicketAttachmentService: erro no upload #' . $i . ' código ' . $uploadError);
				continue;
			}

			$fileTmp = (string) $files['tmp_name'][$i];
			$fileType = (string) ($files['type'][$i] ?? '');
			$fileSize = (int) ($files['size'][$i] ?? 0);
			if ($fileSize <= 0) {
				$errors[] = 'Arquivo inválido ou vazio' . ($fileName !== '' ? ' (' . $fileName . ')' : '') . '.';
				continue;
			}
			if ($fileSize > $maxSize) {
				$errors[] = 'Arquivo muito grande (máx. ' . UploadLimits::formatBytes($maxSize) . '): ' . $fileName . '.';
				continue;
			}

			if (function_exists('finfo_open')) {
				$finfo = finfo_open(FILEINFO_MIME_TYPE);
				$detectedMime = $finfo ? (string) finfo_file($finfo, $fileTmp) : '';
				if ($finfo) {
					finfo_close($finfo);
				}
				$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
				if ($detectedMime !== '' && !in_array($detectedMime, $allowedMimes, true)) {
					$errors[] = 'Tipo de arquivo não permitido: ' . $fileName . '.';
					error_log('TicketAttachmentService: MIME não permitido ' . $detectedMime . ' (' . $fileName . ')');
					continue;
				}
			}

			$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
			$imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
			$isImage = strpos($fileType, 'image/') === 0 || in_array($ext, $imageExts, true);
			$isPdf = $fileType === 'application/pdf' || $ext === 'pdf';
			if (!$isImage && !$isPdf) {
				$errors[] = 'Tipo de arquivo não permitido: ' . $fileName . '.';
				continue;
			}

			if ($ext === '') {
				$ext = $isPdf ? 'pdf' : 'jpg';
			}

			$resolvedType = $fileType !== '' ? $fileType : ($isPdf ? 'application/pdf' : 'image/jpeg');
			$newFileName = 'ticket_' . $ticketId . '_' . time() . '_' . $i . '.' . $ext;
			$filePath = $uploadDir . '/' . $newFileName;
			if (!@move_uploaded_file($fileTmp, $filePath)) {
				$errors[] = 'Falha ao salvar o arquivo' . ($fileName !== '' ? ' (' . $fileName . ')' : '') . '.';
				error_log('TicketAttachmentService: falha ao mover arquivo para ' . $filePath);
				continue;
			}

			try {
				TicketAttachment::create([
					'ticket_id' => $ticketId,
					'file_path' => self::PRIVATE_PREFIX . 'tickets/' . $newFileName,
					'file_name' => $fileName,
					'file_type' => $resolvedType,
					'file_size' => $fileSize,
					'uploaded_by' => $userId > 0 ? $userId : null,
				]);
				$processed++;
			} catch (\Throwable $e) {
				@unlink($filePath);
				$errors[] = 'Falha ao registrar anexo' . ($fileName !== '' ? ' (' . $fileName . ')' : '') . '.';
				error_log('TicketAttachmentService: falha ao gravar anexo no banco: ' . $e->getMessage());
			}
		}

		return ['count' => $processed, 'errors' => $errors];
	}

	public static function resolveFilesystemPath(string $filePath): ?string
	{
		$filePath = trim($filePath);
		if ($filePath === '') {
			return null;
		}

		if (str_starts_with($filePath, self::PRIVATE_PREFIX)) {
			$relative = ltrim(substr($filePath, strlen(self::PRIVATE_PREFIX)), '/');
			$fsPath = BASE_PATH . '/storage/uploads/' . $relative;

			return is_file($fsPath) && is_readable($fsPath) ? $fsPath : null;
		}

		if (str_starts_with($filePath, '/uploads/')) {
			$fsPath = BASE_PATH . '/public' . $filePath;

			return is_file($fsPath) && is_readable($fsPath) ? $fsPath : null;
		}

		$legacy = BASE_PATH . '/public/' . ltrim($filePath, '/');

		return is_file($legacy) && is_readable($legacy) ? $legacy : null;
	}

	public static function deleteFilesystem(string $filePath): void
	{
		$fsPath = self::resolveFilesystemPath($filePath);
		if ($fsPath !== null) {
			@unlink($fsPath);
		}
	}
}
