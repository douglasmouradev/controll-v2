<?php
declare(strict_types=1);

namespace App\Services;

final class TicketAccess
{
	public static function normalizeRole(string $role): string
	{
		$role = strtolower(trim($role));

		return match ($role) {
			'usuario' => 'user',
			'suporte', 'gerente' => 'support',
			default => $role,
		};
	}

	public static function isStaff(string $role): bool
	{
		$role = self::normalizeRole($role);

		return in_array($role, ['admin', 'support'], true);
	}

	public static function canAccess(array $authUser, array $ticket): bool
	{
		if (self::isStaff((string) ($authUser['role'] ?? ''))) {
			return true;
		}

		return (int) ($ticket['user_id'] ?? 0) === (int) ($authUser['id'] ?? 0);
	}
}
