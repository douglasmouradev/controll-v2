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

	public function login(array $user): void
	{
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

	public function hasAnyRole(array $roles): bool
	{
		$user = $this->user();
		if (!$user) {
			return false;
		}
		return in_array($user['role'], $roles, true);
	}
}


