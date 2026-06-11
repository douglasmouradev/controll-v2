<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\User;

final class TwoFactor
{
	private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
	private const PERIOD = 30;
	private const DIGITS = 6;

	public static function isStaffRole(string $role): bool
	{
		return in_array(TicketAccess::normalizeRole($role), ['admin', 'support'], true);
	}

	public static function isEnabledForUser(?array $user): bool
	{
		if (!$user) {
			return false;
		}

		return self::isStaffRole((string) ($user['role'] ?? ''))
			&& !empty($user['two_factor_enabled'])
			&& trim((string) ($user['two_factor_secret'] ?? '')) !== '';
	}

	public static function generateSecret(int $length = 16): string
	{
		$secret = '';
		$max = strlen(self::BASE32_ALPHABET) - 1;
		for ($i = 0; $i < $length; $i++) {
			$secret .= self::BASE32_ALPHABET[random_int(0, $max)];
		}

		return $secret;
	}

	public static function getOtpAuthUri(string $secret, string $email, string $issuer = 'Controll IT'): string
	{
		$label = rawurlencode($issuer . ':' . $email);
		$params = http_build_query([
			'secret' => $secret,
			'issuer' => $issuer,
			'algorithm' => 'SHA1',
			'digits' => self::DIGITS,
			'period' => self::PERIOD,
		]);

		return 'otpauth://totp/' . $label . '?' . $params;
	}

	public static function verify(string $secret, string $code, int $window = 1): bool
	{
		$code = preg_replace('/\D+/', '', $code) ?? '';
		if (strlen($code) !== self::DIGITS) {
			return false;
		}

		$secret = strtoupper(preg_replace('/\s+/', '', $secret) ?? '');
		$timeSlice = (int) floor(time() / self::PERIOD);

		for ($i = -$window; $i <= $window; $i++) {
			if (hash_equals(self::codeForSlice($secret, $timeSlice + $i), $code)) {
				return true;
			}
		}

		return false;
	}

	public static function enableForUser(int $userId, string $secret, string $code): bool
	{
		if (!self::verify($secret, $code)) {
			return false;
		}

		return User::updateTwoFactor($userId, $secret, true);
	}

	public static function disableForUser(int $userId, string $code): bool
	{
		$user = User::findById($userId);
		if (!$user || empty($user['two_factor_secret'])) {
			return false;
		}
		if (!self::verify((string) $user['two_factor_secret'], $code)) {
			return false;
		}

		return User::updateTwoFactor($userId, null, false);
	}

	private static function codeForSlice(string $secret, int $timeSlice): string
	{
		$key = self::base32Decode($secret);
		$time = pack('N*', 0, $timeSlice);
		$hash = hash_hmac('sha1', $time, $key, true);
		$offset = ord($hash[19]) & 0x0f;
		$binary = (
			((ord($hash[$offset]) & 0x7f) << 24)
			| ((ord($hash[$offset + 1]) & 0xff) << 16)
			| ((ord($hash[$offset + 2]) & 0xff) << 8)
			| (ord($hash[$offset + 3]) & 0xff)
		);

		return str_pad((string) ($binary % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
	}

	private static function base32Decode(string $secret): string
	{
		$secret = strtoupper($secret);
		$buffer = 0;
		$bitsLeft = 0;
		$output = '';

		for ($i = 0, $len = strlen($secret); $i < $len; $i++) {
			$pos = strpos(self::BASE32_ALPHABET, $secret[$i]);
			if ($pos === false) {
				continue;
			}
			$buffer = ($buffer << 5) | $pos;
			$bitsLeft += 5;
			if ($bitsLeft >= 8) {
				$bitsLeft -= 8;
				$output .= chr(($buffer >> $bitsLeft) & 0xff);
			}
		}

		return $output;
	}
}
