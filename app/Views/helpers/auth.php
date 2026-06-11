<?php
declare(strict_types=1);

use App\Services\Auth;
use App\Services\TicketAccess;

function view_user_role(?array $user): string
{
	return TicketAccess::normalizeRole((string) ($user['role'] ?? ''));
}

function view_is_admin(?array $user): bool
{
	return view_user_role($user) === 'admin';
}

function view_is_staff(?array $user): bool
{
	return TicketAccess::isStaff((string) ($user['role'] ?? ''));
}

function view_is_support_or_admin(?array $user): bool
{
	$role = view_user_role($user);

	return in_array($role, ['support', 'admin'], true);
}
