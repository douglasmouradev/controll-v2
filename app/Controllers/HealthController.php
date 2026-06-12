<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\Database;
use App\Services\DatabaseSchema;
use App\Services\EmailQueue;

final class HealthController extends Controller
{
	public function index(): void
	{
		$checks = [
			'database' => 'ok',
			'storage' => 'ok',
			'email_queue' => 'n/a',
		];
		$ok = true;

		try {
			Database::pdo()->query('SELECT 1');
		} catch (\Throwable) {
			$checks['database'] = 'error';
			$ok = false;
		}

		$storageDir = defined('BASE_PATH') ? BASE_PATH . '/storage' : dirname(__DIR__, 2) . '/storage';
		if (!is_dir($storageDir) || !is_writable($storageDir)) {
			$checks['storage'] = 'error';
			$ok = false;
		}

		if (EmailQueue::isAvailable()) {
			try {
				$stmt = Database::pdo()->query(
					"SELECT COUNT(*) FROM email_queue WHERE status = 'pending' AND attempts < 5"
				);
				$pending = (int) ($stmt->fetchColumn() ?: 0);
				$checks['email_queue'] = $pending > 100 ? 'backlog' : 'ok';
				if ($pending > 500) {
					$ok = false;
				}
			} catch (\Throwable) {
				$checks['email_queue'] = 'error';
			}
		}

		$migrationsDir = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/database/migrations';
		$pendingMigrations = 0;
		if (is_dir($migrationsDir)) {
			$files = glob($migrationsDir . '/*.sql') ?: [];
			try {
				$pdo = Database::pdo();
				if (DatabaseSchema::tableExists($pdo, 'schema_migrations')) {
					$stmt = $pdo->query('SELECT migration FROM schema_migrations');
					$applied = $stmt ? array_column($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [], 'migration') : [];
					foreach ($files as $file) {
						if (!in_array(basename($file), $applied, true)) {
							$pendingMigrations++;
						}
					}
				} else {
					$pendingMigrations = count($files);
				}
			} catch (\Throwable) {
				$pendingMigrations = -1;
			}
		}

		$this->json([
			'status' => $ok ? 'ok' : 'degraded',
			'php' => PHP_VERSION,
			'time' => date('c'),
			'checks' => $checks,
			'pending_migrations' => max(0, $pendingMigrations),
		], $ok ? 200 : 503);
	}
}
