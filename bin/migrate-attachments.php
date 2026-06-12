<?php
declare(strict_types=1);

/**
 * Move anexos legados de public/uploads/tickets para storage/uploads/tickets
 * e atualiza file_path no banco para o prefixo private:tickets/
 *
 * Uso: php bin/migrate-attachments.php [--dry-run]
 */

require_once __DIR__ . '/bootstrap.php';

$envFile = BASE_PATH . '/.env';
if (!is_file($envFile) || !is_readable($envFile)) {
	fwrite(STDERR, "Arquivo .env não encontrado em {$envFile}\n");
	exit(1);
}

use App\Services\Database;
use App\Services\TicketAttachmentService;

$dryRun = in_array('--dry-run', $argv ?? [], true);

$legacyDir = BASE_PATH . '/public/uploads/tickets';
$storageDir = TicketAttachmentService::storageDir();

if (!is_dir($storageDir) && !$dryRun) {
	mkdir($storageDir, 0755, true);
}

$pdo = Database::pdo();
$stmt = $pdo->query('SELECT id, file_path FROM ticket_attachments ORDER BY id ASC');
$rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

$migrated = 0;
$skipped = 0;
$errors = 0;

foreach ($rows as $row) {
	$id = (int) ($row['id'] ?? 0);
	$filePath = trim((string) ($row['file_path'] ?? ''));
	if ($id <= 0 || $filePath === '') {
		$skipped++;
		continue;
	}

	if (str_starts_with($filePath, 'private:')) {
		$skipped++;
		continue;
	}

	$basename = basename($filePath);
	if ($basename === '' || $basename === '.' || $basename === '..') {
		$errors++;
		echo "ID {$id}: caminho inválido ({$filePath})\n";
		continue;
	}

	$source = null;
	if (str_starts_with($filePath, '/uploads/')) {
		$source = BASE_PATH . '/public' . $filePath;
	} elseif (is_file($legacyDir . '/' . $basename)) {
		$source = $legacyDir . '/' . $basename;
	} elseif (is_file(BASE_PATH . '/public/' . ltrim($filePath, '/'))) {
		$source = BASE_PATH . '/public/' . ltrim($filePath, '/');
	}

	if ($source === null || !is_file($source)) {
		$errors++;
		echo "ID {$id}: arquivo não encontrado ({$filePath})\n";
		continue;
	}

	$target = $storageDir . '/' . $basename;
	$newDbPath = 'private:tickets/' . $basename;

	if ($dryRun) {
		echo "[dry-run] ID {$id}: {$source} -> {$target}\n";
		$migrated++;
		continue;
	}

	if (!is_file($target)) {
		if (!@copy($source, $target)) {
			$errors++;
			echo "ID {$id}: falha ao copiar para storage\n";
			continue;
		}
	}

	$update = $pdo->prepare('UPDATE ticket_attachments SET file_path = :path WHERE id = :id');
	$update->execute([':path' => $newDbPath, ':id' => $id]);
	@unlink($source);
	$migrated++;
	echo "ID {$id}: migrado para {$newDbPath}\n";
}

echo "\nResumo: migrados={$migrated}, ignorados={$skipped}, erros={$errors}" . ($dryRun ? ' (dry-run)' : '') . "\n";
