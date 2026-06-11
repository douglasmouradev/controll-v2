<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Views/helpers/auth.php';

use PHPUnit\Framework\TestCase;

final class AuthViewHelpersTest extends TestCase
{
	public function testViewIsAdmin(): void
	{
		$this->assertTrue(view_is_admin(['role' => 'admin']));
		$this->assertFalse(view_is_admin(['role' => 'support']));
		$this->assertFalse(view_is_admin(['role' => 'usuario']));
	}

	public function testViewIsStaff(): void
	{
		$this->assertTrue(view_is_staff(['role' => 'suporte']));
		$this->assertTrue(view_is_staff(['role' => 'admin']));
		$this->assertFalse(view_is_staff(['role' => 'user']));
	}
}
