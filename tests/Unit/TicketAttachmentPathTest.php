<?php
declare(strict_types=1);

use App\Services\TicketAttachmentService;
use PHPUnit\Framework\TestCase;

final class TicketAttachmentPathTest extends TestCase
{
	public function testResolvePrivateStoragePath(): void
	{
		if (!defined('BASE_PATH')) {
			define('BASE_PATH', dirname(__DIR__, 2));
		}

		$dir = TicketAttachmentService::storageDir();
		if (!is_dir($dir)) {
			mkdir($dir, 0775, true);
		}

		$fileName = 'test_' . uniqid('', true) . '.txt';
		$fullPath = $dir . '/' . $fileName;
		file_put_contents($fullPath, 'ok');

		$resolved = TicketAttachmentService::resolveFilesystemPath('private:tickets/' . $fileName);
		$this->assertSame($fullPath, $resolved);

		@unlink($fullPath);
	}

	public function testResolveLegacyPublicPath(): void
	{
		if (!defined('BASE_PATH')) {
			define('BASE_PATH', dirname(__DIR__, 2));
		}

		$legacyDir = BASE_PATH . '/public/uploads/tickets';
		if (!is_dir($legacyDir)) {
			mkdir($legacyDir, 0775, true);
		}

		$fileName = 'legacy_' . uniqid('', true) . '.txt';
		$fullPath = $legacyDir . '/' . $fileName;
		file_put_contents($fullPath, 'legacy');

		$resolved = TicketAttachmentService::resolveFilesystemPath('/uploads/tickets/' . $fileName);
		$this->assertSame($fullPath, $resolved);

		@unlink($fullPath);
	}
}
