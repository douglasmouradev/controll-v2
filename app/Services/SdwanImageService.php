<?php
declare(strict_types=1);

namespace App\Services;

final class SdwanImageService
{
	private const PRIVATE_PREFIX = 'private:sdwan/';

	public static function storageDir(): string
	{
		return BASE_PATH . '/storage/uploads/sdwan';
	}

	/** @return array{image_path: string, image_name: string, image_type: string, image_size: int}|null */
	public static function saveUploadedFile(array $file, int $entryId): ?array
	{
		if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
			return null;
		}

		$fileName = (string) ($file['name'] ?? '');
		$fileTmp = (string) ($file['tmp_name'] ?? '');
		$fileType = (string) ($file['type'] ?? '');
		$fileSize = (int) ($file['size'] ?? 0);
		if ($fileTmp === '' || $fileSize <= 0 || $fileSize > 10 * 1024 * 1024) {
			throw new \InvalidArgumentException('Imagem inválida ou maior que 10 MB');
		}

		if (function_exists('finfo_open')) {
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$detectedMime = $finfo ? (string) finfo_file($finfo, $fileTmp) : '';
			if ($finfo) {
				finfo_close($finfo);
			}
			$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
			if ($detectedMime !== '' && !in_array($detectedMime, $allowedMimes, true)) {
				throw new \InvalidArgumentException('Envie apenas imagens (JPG, PNG, GIF ou WEBP)');
			}
			if ($detectedMime !== '') {
				$fileType = $detectedMime;
			}
		}

		$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
		$imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
		if (!in_array($ext, $imageExts, true)) {
			throw new \InvalidArgumentException('Envie apenas imagens (JPG, PNG, GIF ou WEBP)');
		}

		$uploadDir = self::storageDir();
		if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true)) {
			throw new \RuntimeException('Não foi possível preparar a pasta de upload');
		}

		$newFileName = 'sdwan_' . $entryId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
		$targetPath = $uploadDir . '/' . $newFileName;
		if (!is_uploaded_file($fileTmp) || !@move_uploaded_file($fileTmp, $targetPath)) {
			throw new \RuntimeException('Falha ao salvar a imagem');
		}

		return [
			'image_path' => self::PRIVATE_PREFIX . $newFileName,
			'image_name' => $fileName !== '' ? $fileName : $newFileName,
			'image_type' => $fileType !== '' ? $fileType : 'image/*',
			'image_size' => $fileSize,
		];
	}

	public static function resolveFilesystemPath(?string $imagePath): ?string
	{
		$imagePath = trim((string) $imagePath);
		if ($imagePath === '') {
			return null;
		}

		if (str_starts_with($imagePath, self::PRIVATE_PREFIX)) {
			$relative = substr($imagePath, strlen(self::PRIVATE_PREFIX));
			$fsPath = self::storageDir() . '/' . ltrim($relative, '/');

			return is_file($fsPath) && is_readable($fsPath) ? $fsPath : null;
		}

		return null;
	}

	public static function deleteImage(?string $imagePath): void
	{
		$fsPath = self::resolveFilesystemPath($imagePath);
		if ($fsPath !== null) {
			@unlink($fsPath);
		}
	}

	public static function imageUrl(int $entryId): string
	{
		return '/dashboard/sdwan-entries/image?id=' . $entryId;
	}
}
