<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\Database;

final class HealthController extends Controller
{
	public function index(): void
	{
		$checks = ['database' => 'ok'];
		$ok = true;

		try {
			Database::pdo()->query('SELECT 1');
		} catch (\Throwable) {
			$checks['database'] = 'error';
			$ok = false;
		}

		$this->json([
			'status' => $ok ? 'ok' : 'degraded',
			'php' => PHP_VERSION,
			'time' => date('c'),
			'checks' => $checks,
		], $ok ? 200 : 503);
	}
}
