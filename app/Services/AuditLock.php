<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\SystemSetting;

final class AuditLock
{
	public const AVAILABLE_DATE = '2026-06-15';
	public const MAINTENANCE_SETTING_KEY = 'maintenance_mode';
	public const AUDIT_LOCK_KEY = 'audit_lock_enabled';
	public const AUDIT_DATE_KEY = 'audit_available_date';

	public static function isMaintenanceEnabled(): bool
	{
		return SystemSetting::isEnabled(self::MAINTENANCE_SETTING_KEY);
	}

	public static function isAuditLockEnabled(): bool
	{
		$value = SystemSetting::get(self::AUDIT_LOCK_KEY);
		if ($value === null) {
			return true;
		}

		return SystemSetting::isEnabled(self::AUDIT_LOCK_KEY);
	}

	public static function availableDateRaw(): string
	{
		$date = trim((string) SystemSetting::get(self::AUDIT_DATE_KEY, self::AVAILABLE_DATE));
		$dt = \DateTimeImmutable::createFromFormat('Y-m-d', $date);

		return $dt ? $dt->format('Y-m-d') : self::AVAILABLE_DATE;
	}

	public static function isDateLockActive(): bool
	{
		if (!self::isAuditLockEnabled()) {
			return false;
		}

		return date('Y-m-d') < self::availableDateRaw();
	}

	public static function isActive(): bool
	{
		return self::isDateLockActive() || self::isMaintenanceEnabled();
	}

	public static function lockReason(): string
	{
		if (self::isMaintenanceEnabled()) {
			return 'maintenance';
		}
		if (self::isDateLockActive()) {
			return 'audit';
		}

		return 'none';
	}

	public static function blockMessage(): string
	{
		return match (self::lockReason()) {
			'maintenance' => 'Sistema em manutenção. Tente novamente mais tarde.',
			'audit' => 'Sistema em auditoria. Disponível em ' . self::availableDateFormatted() . '.',
			default => 'Sistema temporariamente indisponível.',
		};
	}

	public static function isEndUser(?array $user): bool
	{
		if (!$user) {
			return false;
		}

		return TicketAccess::normalizeRole((string) ($user['role'] ?? '')) === 'user';
	}

	public static function shouldBlock(?array $user): bool
	{
		return self::isActive() && self::isEndUser($user);
	}

	public static function availableDateFormatted(): string
	{
		$dt = \DateTimeImmutable::createFromFormat('Y-m-d', self::availableDateRaw());
		if (!$dt) {
			return '15/06/2026';
		}

		return $dt->format('d/m/Y');
	}

	/** @return string[] */
	public static function allowedPaths(): array
	{
		return ['/auditoria', '/logout'];
	}

	public static function isAllowedPath(string $path): bool
	{
		$path = rtrim($path, '/') ?: '/';

		return in_array($path, self::allowedPaths(), true);
	}
}
