<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\StoreAddressService;
use PHPUnit\Framework\TestCase;

final class SdwanValidationTest extends TestCase
{
	public function testStoreAddressServiceRejectsUnknownSiglaWhenListEmpty(): void
	{
		$this->assertFalse(StoreAddressService::isValidSigla('ZZZ'));
	}

	public function testStoreAddressApiPayloadShape(): void
	{
		$payload = StoreAddressService::apiPayload();
		$this->assertTrue($payload['success']);
		$this->assertIsArray($payload['data']);
	}
}
