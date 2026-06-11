<?php
declare(strict_types=1);

namespace App\Services;

final class Auth
{
	private const SESSION_KEY = 'auth_user';
	private static ?self $instance = null;

	public static function instance(): self
	{
		if (!self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function normalizeRole(?string $role): string
	{
		return TicketAccess::normalizeRole((string) $role);
	}

	public function login(array $user): void
	{
		session_regenerate_id(true);
		$_SESSION[self::SESSION_KEY] = [
			'id' => (int) $user['id'],
			'name' => (string) $user['name'],
			'role' => (string) $user['role'],
		];
	}

	public function logout(): void
	{
		unset($_SESSION[self::SESSION_KEY]);
	}

	public function check(): bool
	{
		return isset($_SESSION[self::SESSION_KEY]['id']);
	}

	public function user(): ?array
	{
		return $_SESSION[self::SESSION_KEY] ?? null;
	}

	public function role(): ?string
	{
		$user = $this->user();

		return $user['role'] ?? null;
	}

	public function normalizedRole(): ?string
	{
		$role = $this->role();

		return $role !== null ? self::normalizeRole($role) : null;
	}

	public function isAdmin(): bool
	{
		return $this->normalizedRole() === 'admin';
	}

	public function isSupport(): bool
	{
		return in_array($this->normalizedRole(), ['support', 'admin'], true);
	}

	public function isStaff(): bool
	{
		return TicketAccess::isStaff((string) ($this->role() ?? ''));
	}

	public function isEndUser(): bool
	{
		return $this->normalizedRole() === 'user';
	}

	public function hasAnyRole(array $roles): bool
	{
		$user = $this->user();
		if (!$user) {
			return false;
		}

		$current = self::normalizeRole((string) $user['role']);
		$allowed = array_map(static fn (string $role): string => self::normalizeRole($role), $roles);

		return in_array($current, $allowed, true);
	}
}
