<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\SystemSetting;
use App\Services\AuditLock;
use App\Services\AuditLog;
use App\Services\Cache;

final class SettingsController extends Controller
{
	public function index(): void
	{
		$this->requireAuth(['admin']);

		$this->json([
			'success' => true,
			'settings' => self::currentSettings(),
		]);
	}

	public function update(): void
	{
		$this->requireAuth(['admin']);

		$updates = [];

		if (array_key_exists('maintenance_mode', $_POST)) {
			$enabled = $this->isTruthy($_POST['maintenance_mode']);
			SystemSetting::set('maintenance_mode', $enabled ? '1' : '0', 'Modo manutenção — bloqueia acesso de usuários finais');
			$updates[] = 'maintenance_mode';
			AuditLog::record($enabled ? 'maintenance_on' : 'maintenance_off', 'system_settings');
		}

		if (array_key_exists('audit_lock_enabled', $_POST)) {
			$enabled = $this->isTruthy($_POST['audit_lock_enabled']);
			SystemSetting::set('audit_lock_enabled', $enabled ? '1' : '0', 'Bloqueio por auditoria até data configurada');
			$updates[] = 'audit_lock_enabled';
		}

		if (isset($_POST['audit_available_date'])) {
			$date = trim((string) $_POST['audit_available_date']);
			$dt = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
			if ($dt) {
				SystemSetting::set('audit_available_date', $dt->format('Y-m-d'), 'Data de liberação do acesso para usuários finais');
				$updates[] = 'audit_available_date';
			}
		}

		if (isset($_POST['notification_email'])) {
			$email = trim((string) $_POST['notification_email']);
			if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL)) {
				SystemSetting::set('notification_email', $email, 'E-mail para notificações de chamados');
				$updates[] = 'notification_email';
			}
		}

		if (isset($_POST['system_name'])) {
			$name = trim((string) $_POST['system_name']);
			if ($name !== '') {
				SystemSetting::set('system_name', $name, 'Nome do sistema');
				$updates[] = 'system_name';
			}
		}

		if ($updates === []) {
			$this->json(['success' => false, 'message' => 'Nenhuma configuração enviada.'], 422);
			return;
		}

		AuditLog::record('settings_update', implode(',', $updates));
		Cache::flush();

		$this->json([
			'success' => true,
			'message' => 'Configurações salvas com sucesso.',
			'settings' => self::currentSettings(),
		]);
	}

	public function maintenanceStatus(): void
	{
		$this->requireAuth(['admin']);

		$this->json([
			'success' => true,
			'enabled' => AuditLock::isMaintenanceEnabled(),
			'blocking' => AuditLock::isActive(),
			'reason' => AuditLock::lockReason(),
		]);
	}

	public function maintenanceToggle(): void
	{
		$this->requireAuth(['admin']);

		$enabled = $this->resolveEnabledFlag();
		$ok = SystemSetting::set(
			'maintenance_mode',
			$enabled ? '1' : '0',
			'Modo manutenção — bloqueia acesso de usuários finais'
		);

		if (!$ok) {
			$this->json(['success' => false, 'message' => 'Não foi possível salvar a configuração.'], 500);
			return;
		}

		AuditLog::record($enabled ? 'maintenance_on' : 'maintenance_off', 'system_settings');
		Cache::flush();

		$this->json([
			'success' => true,
			'enabled' => $enabled,
			'blocking' => AuditLock::isActive(),
			'reason' => AuditLock::lockReason(),
			'message' => $enabled
				? 'Modo manutenção ativado. Usuários finais não poderão acessar o sistema.'
				: 'Modo manutenção desativado.',
		]);
	}

	/** @return array<string, mixed> */
	private static function currentSettings(): array
	{
		return [
			'maintenance_mode' => AuditLock::isMaintenanceEnabled(),
			'audit_lock_enabled' => AuditLock::isAuditLockEnabled(),
			'audit_available_date' => AuditLock::availableDateRaw(),
			'audit_available_date_formatted' => AuditLock::availableDateFormatted(),
			'notification_email' => (string) SystemSetting::get('notification_email', getenv('TICKET_NOTIFICATION_EMAIL') ?: ''),
			'system_name' => (string) SystemSetting::get('system_name', 'Controll IT Help Desk'),
			'blocking' => AuditLock::isActive(),
			'lock_reason' => AuditLock::lockReason(),
		];
	}

	private function resolveEnabledFlag(): bool
	{
		if (array_key_exists('enabled', $_POST)) {
			return $this->isTruthy($_POST['enabled']);
		}

		return !AuditLock::isMaintenanceEnabled();
	}

	private function isTruthy(mixed $value): bool
	{
		return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
	}
}
