#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$result = \App\Services\Migrator::run();

foreach ($result['applied'] as $migration) {
	echo "[OK] Applied: {$migration}\n";
}
foreach ($result['skipped'] as $migration) {
	echo "[SKIP] {$migration}\n";
}
foreach ($result['errors'] as $migration => $error) {
	echo "[ERR] {$migration}: {$error}\n";
}

exit(empty($result['errors']) ? 0 : 1);
