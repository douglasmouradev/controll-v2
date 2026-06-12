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

	public static function handleUpload(int $ticketId, string $filesKey, ?array $user = null): void
	{
		if (empty($_FILES[$filesKey]) || !is_array($_FILES[$filesKey]['name'])) {
			return;
		}

		$user = $user ?? Auth::instance()->user();
		$files = $_FILES[$filesKey];
		$uploadDir = self::storageDir();
		if (!is_dir($uploadDir)) {
			@mkdir($uploadDir, 0755, true);
		}

		$maxFiles = 20;
		$maxSize = 40 * 1024 * 1024;
		$fileCount = count($files['name']);
		$processed = 0;

		for ($i = 0; $i < $fileCount && $processed < $maxFiles; $i++) {
			if ($files['error'][$i] !== UPLOAD_ERR_OK) {
				continue;
			}

			$fileName = (string) $files['name'][$i];
			$fileTmp = (string) $files['tmp_name'][$i];
			$fileType = (string) ($files['type'][$i] ?? '');
			$fileSize = (int) ($files['size'][$i] ?? 0);
			if ($fileSize <= 0 || $fileSize > $maxSize) {
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
					continue;
				}
			}

			$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
			$imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
			$isImage = strpos($fileType, 'image/') === 0 || in_array($ext, $imageExts, true);
			$isPdf = $fileType === 'application/pdf' || $ext === 'pdf';
			if (!$isImage && !$isPdf) {
				continue;
			}

			if ($ext === '') {
				$ext = $isPdf ? 'pdf' : 'bin';
			}

			$resolvedType = $fileType !== '' ? $fileType : ($isPdf ? 'application/pdf' : 'image/*');
			$newFileName = 'ticket_' . $ticketId . '_' . time() . '_' . $i . '.' . $ext;
			$filePath = $uploadDir . '/' . $newFileName;
			if (!@move_uploaded_file($fileTmp, $filePath)) {
				continue;
			}

			try {
				TicketAttachment::create([
					'ticket_id' => $ticketId,
					'file_path' => self::PRIVATE_PREFIX . 'tickets/' . $newFileName,
					'file_name' => $fileName,
					'file_type' => $resolvedType,
					'file_size' => $fileSize,
					'uploaded_by' => (int) ($user['id'] ?? 0),
				]);
				$processed++;
			} catch (\Throwable $e) {
				@unlink($filePath);
			}
		}
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
