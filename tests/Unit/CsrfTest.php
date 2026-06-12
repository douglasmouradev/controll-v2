<?php
declare(strict_types=1);

use App\Services\Csrf;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
	protected function setUp(): void
	{
		if (session_status() !== PHP_SESSION_ACTIVE) {
			session_start();
		}
		unset($_SESSION['_csrf_token']);
	}

	public function testTokenIsGeneratedAndVerified(): void
	{
		$token = Csrf::token();
		$this->assertNotSame('', $token);
		$this->assertTrue(Csrf::verify($token));
	}

	public function testVerifyRejectsInvalidToken(): void
	{
		Csrf::token();
		$this->assertFalse(Csrf::verify('token-invalido'));
		$this->assertFalse(Csrf::verify(null));
	}
}
