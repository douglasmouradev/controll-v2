<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\SdwanEntry;

final class SdwanEntryService
{
	public static function applyImageUpload(int $entryId, array $post, array $files): void
	{
		if (!SdwanEntry::hasImageColumns()) {
			return;
		}

		$current = SdwanEntry::findRawById($entryId);
		if (!$current) {
			return;
		}

		$removeImage = !empty($post['remove_image']);
		$hasUpload = isset($files['image']) && is_array($files['image'])
			&& (($files['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);

		if (!$hasUpload && !$removeImage) {
			return;
		}

		$data = self::entryPayloadFromRow($current);
		$data = self::mergePostScalars($data, $post);

		if ($removeImage && !$hasUpload) {
			SdwanImageService::deleteImage((string) ($current['image_path'] ?? ''));
			$data['image_path'] = null;
			$data['image_name'] = null;
			$data['image_type'] = null;
			$data['image_size'] = null;
			SdwanEntry::update($entryId, $data);
			return;
		}

		if ($hasUpload) {
			if (!empty($current['image_path'])) {
				SdwanImageService::deleteImage((string) $current['image_path']);
			}
			$imageData = SdwanImageService::saveUploadedFile($files['image'], $entryId);
			if ($imageData !== null) {
				SdwanEntry::update($entryId, array_merge($data, $imageData));
			}
		}
	}

	/** @param array<string, mixed> $data @param array<string, mixed> $post @return array<string, mixed> */
	private static function mergePostScalars(array $data, array $post): array
	{
		if (array_key_exists('quantidade_utilizada', $post)) {
			$data['quantidade_utilizada'] = max(0, (int) $post['quantidade_utilizada']);
		}

		return $data;
	}

	/** @param array<string, mixed> $row */
	private static function entryPayloadFromRow(array $row): array
	{
		return [
			'xpads_previsto' => (int) ($row['xpads_previsto'] ?? 0),
			'quantidade_localizada' => (int) ($row['quantidade_localizada'] ?? 0),
			'quantidade_utilizada' => (int) ($row['quantidade_utilizada'] ?? 0),
			'pdv_numero' => (string) ($row['pdv_numero'] ?? ''),
			'pdv_serie' => (string) ($row['pdv_serie'] ?? ''),
			'serie_antena' => (string) ($row['serie_antena'] ?? ''),
			'serie_acupad' => (string) ($row['serie_acupad'] ?? ''),
			'setor' => (string) ($row['setor'] ?? ''),
			'loja' => (string) ($row['loja'] ?? ''),
			'image_path' => $row['image_path'] ?? null,
			'image_name' => $row['image_name'] ?? null,
			'image_type' => $row['image_type'] ?? null,
			'image_size' => $row['image_size'] ?? null,
		];
	}
}
