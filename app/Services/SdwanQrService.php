<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\SdwanAccessLink;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

final class SdwanQrService
{
	/** @return array{body: string, content_type: string}|null */
	public static function render(string $text): ?array
	{
		$text = trim($text);
		if ($text === '') {
			return null;
		}

		if (class_exists(QRCode::class)) {
			try {
				$options = new QROptions([
					'outputType' => QRCode::OUTPUT_MARKUP_SVG,
					'eccLevel' => QRCode::ECC_L,
					'scale' => 5,
					'imageBase64' => false,
					'addQuietzone' => true,
				]);
				$svg = (new QRCode($options))->render($text);
				if (is_string($svg) && $svg !== '') {
					return ['body' => $svg, 'content_type' => 'image/svg+xml'];
				}
			} catch (\Throwable $e) {
				error_log('SdwanQrService local SVG: ' . $e->getMessage());
			}

			if (extension_loaded('gd')) {
				try {
					$options = new QROptions([
						'outputType' => QRCode::OUTPUT_IMAGE_PNG,
						'eccLevel' => QRCode::ECC_L,
						'scale' => 6,
					]);
					$png = (new QRCode($options))->render($text);
					if (is_string($png) && $png !== '') {
						return ['body' => $png, 'content_type' => 'image/png'];
					}
				} catch (\Throwable $e) {
					error_log('SdwanQrService local PNG: ' . $e->getMessage());
				}
			}
		}

		$remote = SdwanAccessLink::fetchQrImage($text);

		return $remote !== null
			? ['body' => $remote, 'content_type' => 'image/png']
			: null;
	}
}
