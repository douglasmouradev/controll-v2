<?php
declare(strict_types=1);

namespace App\Services;

final class DateFormatter
{
	public static function appTimezone(): \DateTimeZone
	{
		$tz = trim((string) (getenv('TIMEZONE') ?: 'America/Sao_Paulo'));

		try {
			return new \DateTimeZone($tz !== '' ? $tz : 'America/Sao_Paulo');
		} catch (\Exception $e) {
			return new \DateTimeZone('America/Sao_Paulo');
		}
	}

	public static function mysqlTimezoneOffset(): string
	{
		$now = new \DateTimeImmutable('now', self::appTimezone());

		return $now->format('P');
	}

	public static function formatDateTime(?string $value, string $format = 'd/m/Y H:i'): string
	{
		if ($value === null || trim($value) === '') {
			return '';
		}

		$tz = self::appTimezone();
		$value = trim($value);
		$dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $tz);
		if ($dt === false) {
			try {
				$dt = new \DateTimeImmutable($value, $tz);
			} catch (\Exception $e) {
				return $value;
			}
		}

		return $dt->format($format);
	}

	public static function now(string $format = 'd/m/Y H:i'): string
	{
		return (new \DateTimeImmutable('now', self::appTimezone()))->format($format);
	}
}
