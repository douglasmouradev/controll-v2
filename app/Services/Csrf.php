<?php
declare(strict_types=1);

namespace App\Services;

final class Csrf
{
	private const SESSION_KEY = '_csrf_token';

	public static function token(): string
	{
		if (empty($_SESSION[self::SESSION_KEY])) {
			$_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
		}
		return (string) $_SESSION[self::SESSION_KEY];
	}

	public static function verify(?string $token): bool
	{
		if ($token === null || $token === '') {
			return false;
		}
		$expected = (string) ($_SESSION[self::SESSION_KEY] ?? '');
		return $expected !== '' && hash_equals($expected, $token);
	}

	public static function field(): string
	{
		return '<input type="hidden" name="csrf_token" value="'
			. htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
	}
}
