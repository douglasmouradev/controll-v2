<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\InventoryService;
use PHPUnit\Framework\TestCase;

final class InventoryServiceTest extends TestCase
{
	public function testResolvePathReturnsEmptyWhenMissing(): void
	{
		$previous = getenv('INVENTORY_XLSX_PATH');
		putenv('INVENTORY_XLSX_PATH');

		$this->assertSame('', InventoryService::resolvePath(''));

		if ($previous !== false) {
			putenv('INVENTORY_XLSX_PATH=' . $previous);
		}
	}

	public function testBuildStatsPayloadWithEmptyRows(): void
	{
		$payload = InventoryService::buildStatsPayload([], [], '/tmp/test.xlsx');
		$this->assertTrue($payload['success']);
		$this->assertSame(['Sem dados'], $payload['labels']);
	}
}
