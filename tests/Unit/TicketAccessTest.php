<?php
declare(strict_types=1);

use App\Services\TicketAccess;
use PHPUnit\Framework\TestCase;

final class TicketAccessTest extends TestCase
{
	public function testNormalizeRole(): void
	{
		$this->assertSame('user', TicketAccess::normalizeRole('usuario'));
		$this->assertSame('user', TicketAccess::normalizeRole('user'));
		$this->assertSame('support', TicketAccess::normalizeRole('suporte'));
		$this->assertSame('support', TicketAccess::normalizeRole('gerente'));
		$this->assertSame('admin', TicketAccess::normalizeRole('admin'));
	}

	public function testIsStaff(): void
	{
		$this->assertTrue(TicketAccess::isStaff('admin'));
		$this->assertTrue(TicketAccess::isStaff('suporte'));
		$this->assertFalse(TicketAccess::isStaff('usuario'));
		$this->assertFalse(TicketAccess::isStaff('user'));
	}
}
