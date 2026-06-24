<?php
declare(strict_types=1);

namespace App\Services;

final class SdwanImageService
{
	private const PRIVATE_PREFIX = 'private:sdwan/';
	private const MAX_DIMENSION = 1920;
	private const JPEG_QUALITY = 82;

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

		$optimized = self::optimizeImageFile($targetPath);
		if ($optimized !== null) {
			$newFileName = $optimized['filename'];
			$fileType = $optimized['mime'];
			$fileSize = $optimized['size'];
		}

		return [
			'image_path' => self::PRIVATE_PREFIX . $newFileName,
			'image_name' => $fileName !== '' ? $fileName : $newFileName,
			'image_type' => $fileType !== '' ? $fileType : 'image/jpeg',
			'image_size' => $fileSize,
		];
	}

	/**
	 * Redimensiona e comprime a imagem salva no disco.
	 *
	 * @return array{filename: string, mime: string, size: int}|null
	 */
	private static function optimizeImageFile(string $path): ?array
	{
		if (!extension_loaded('gd')) {
			return null;
		}

		$info = @getimagesize($path);
		if ($info === false) {
			return null;
		}

		$width = (int) ($info[0] ?? 0);
		$height = (int) ($info[1] ?? 0);
		$type = (int) ($info[2] ?? 0);
		if ($width <= 0 || $height <= 0) {
			return null;
		}

		if ($type === IMAGETYPE_GIF) {
			return null;
		}

		$image = self::createGdImage($path, $type);
		if ($image === null) {
			return null;
		}

		$orientation = 1;
		if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
			$exif = @exif_read_data($path);
			if (is_array($exif) && isset($exif['Orientation'])) {
				$orientation = (int) $exif['Orientation'];
			}
		}

		$image = self::applyExifOrientation($image, $orientation);
		$width = imagesx($image);
		$height = imagesy($image);

		if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
			$ratio = min(self::MAX_DIMENSION / $width, self::MAX_DIMENSION / $height);
			$newWidth = max(1, (int) round($width * $ratio));
			$newHeight = max(1, (int) round($height * $ratio));
			$resized = imagecreatetruecolor($newWidth, $newHeight);
			if ($resized === false) {
				imagedestroy($image);
				return null;
			}
			imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
			imagedestroy($image);
			$image = $resized;
			$width = $newWidth;
			$height = $newHeight;
		}

		if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP) {
			$flattened = imagecreatetruecolor($width, $height);
			if ($flattened === false) {
				imagedestroy($image);
				return null;
			}
			$white = imagecolorallocate($flattened, 255, 255, 255);
			imagefill($flattened, 0, 0, $white);
			imagecopy($flattened, $image, 0, 0, 0, 0, $width, $height);
			imagedestroy($image);
			$image = $flattened;
		}

		$optimizedPath = preg_replace('/\.[^.]+$/', '', $path) . '.jpg';
		if ($optimizedPath === $path) {
			$optimizedPath .= '.jpg';
		}

		if (!imagejpeg($image, $optimizedPath, self::JPEG_QUALITY)) {
			imagedestroy($image);
			return null;
		}
		imagedestroy($image);

		if ($optimizedPath !== $path && is_file($path)) {
			@unlink($path);
		}

		if (!is_file($optimizedPath)) {
			return null;
		}

		return [
			'filename' => basename($optimizedPath),
			'mime' => 'image/jpeg',
			'size' => (int) filesize($optimizedPath),
		];
	}

	/** @return \GdImage|resource|null */
	private static function createGdImage(string $path, int $type)
	{
		$image = match ($type) {
			IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
			IMAGETYPE_PNG => @imagecreatefrompng($path),
			IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
			default => false,
		};

		return $image !== false ? $image : null;
	}

	/** @param \GdImage|resource $image @return \GdImage|resource */
	private static function applyExifOrientation($image, int $orientation)
	{
		return match ($orientation) {
			2 => self::flipGdImage($image, true, false),
			3 => imagerotate($image, 180, 0) ?: $image,
			4 => self::flipGdImage($image, false, true),
			5 => self::rotateAndFlip($image, 90, true, false),
			6 => imagerotate($image, -90, 0) ?: $image,
			7 => self::rotateAndFlip($image, -90, true, false),
			8 => imagerotate($image, 90, 0) ?: $image,
			default => $image,
		};
	}

	/** @param \GdImage|resource $image @return \GdImage|resource */
	private static function rotateAndFlip($image, int $angle, bool $flipHorizontal, bool $flipVertical)
	{
		$rotated = imagerotate($image, $angle, 0);
		if ($rotated !== false) {
			imagedestroy($image);
			$image = $rotated;
		}

		return self::flipGdImage($image, $flipHorizontal, $flipVertical);
	}

	/** @param \GdImage|resource $image @return \GdImage|resource */
	private static function flipGdImage($image, bool $horizontal, bool $vertical)
	{
		if (function_exists('imageflip')) {
			if ($horizontal && $vertical) {
				imageflip($image, IMG_FLIP_BOTH);
			} elseif ($horizontal) {
				imageflip($image, IMG_FLIP_HORIZONTAL);
			} elseif ($vertical) {
				imageflip($image, IMG_FLIP_VERTICAL);
			}
		}

		return $image;
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
