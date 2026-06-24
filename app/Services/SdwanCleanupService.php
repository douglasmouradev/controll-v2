<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\SdwanAccessLink;
use App\Models\SdwanEntry;
use App\Services\Database;
use App\Services\DatabaseSchema;

final class SdwanCleanupService
{
	/** @return array{orphan_images: int, expired_links: int} */
	public static function run(int $linkGraceDays = 30): array
	{
		$result = ['orphan_images' => 0, 'expired_links' => 0];

		if (!SdwanEntry::hasImageColumns()) {
			return $result;
		}

		$storageDir = SdwanImageService::storageDir();
		if (!is_dir($storageDir)) {
			return $result;
		}

		$pdo = Database::pdo();
		$pathsInDb = [];
		if (SdwanEntry::tableReady()) {
			$stmt = $pdo->query('SELECT image_path FROM sdwan_entries WHERE image_path IS NOT NULL AND image_path <> \'\'');
			foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [] as $path) {
				$fs = SdwanImageService::resolveFilesystemPath((string) $path);
				if ($fs !== null) {
					$pathsInDb[basename($fs)] = true;
				}
			}
		}

		foreach (glob($storageDir . '/*') ?: [] as $file) {
			if (!is_file($file)) {
				continue;
			}
			$name = basename($file);
			if (!isset($pathsInDb[$name]) && (time() - filemtime($file)) > 86400) {
				if (@unlink($file)) {
					$result['orphan_images']++;
				}
			}
		}

		if (SdwanAccessLink::tableReady() && DatabaseSchema::tableExists($pdo, 'sdwan_access_links')) {
			$stmt = $pdo->prepare('DELETE FROM sdwan_access_links WHERE expires_at < DATE_SUB(NOW(), INTERVAL :days DAY)');
			$stmt->bindValue(':days', max(1, $linkGraceDays), \PDO::PARAM_INT);
			$stmt->execute();
			$result['expired_links'] = $stmt->rowCount();
		}

		SdwanAudit::record('cleanup', json_encode($result), true);

		return $result;
	}
}
