<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\TicketAttachment;

final class TicketAttachmentService
{
	public static function handleUpload(int $ticketId, string $filesKey, ?array $user = null): void
	{
		if (empty($_FILES[$filesKey]) || !is_array($_FILES[$filesKey]['name'])) {
			return;
		}

		$user = $user ?? Auth::instance()->user();
		$files = $_FILES[$filesKey];
		$uploadDir = BASE_PATH . '/public/uploads/tickets/';
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
			$filePath = $uploadDir . $newFileName;
			if (!@move_uploaded_file($fileTmp, $filePath)) {
				continue;
			}

			try {
				TicketAttachment::create([
					'ticket_id' => $ticketId,
					'file_path' => '/uploads/tickets/' . $newFileName,
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
}
