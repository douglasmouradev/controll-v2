<?php
declare(strict_types=1);

use App\Services\AuditLock;
use App\Services\TicketAccess;
use PHPUnit\Framework\TestCase;

final class AuditLockTest extends TestCase
{
	public function testIsEndUser(): void
	{
		$this->assertTrue(AuditLock::isEndUser(['role' => 'user']));
		$this->assertTrue(AuditLock::isEndUser(['role' => 'usuario']));
		$this->assertFalse(AuditLock::isEndUser(['role' => 'admin']));
		$this->assertFalse(AuditLock::isEndUser(['role' => 'support']));
		$this->assertFalse(AuditLock::isEndUser(null));
	}

	public function testAllowedPaths(): void
	{
		$this->assertTrue(AuditLock::isAllowedPath('/auditoria'));
		$this->assertTrue(AuditLock::isAllowedPath('/logout'));
		$this->assertFalse(AuditLock::isAllowedPath('/'));
	}

	public function testBlockMessageDefault(): void
	{
		if (AuditLock::isActive()) {
			$this->assertNotSame('Sistema temporariamente indisponível.', AuditLock::blockMessage());
			return;
		}
		$this->assertSame('Sistema temporariamente indisponível.', AuditLock::blockMessage());
	}
}
