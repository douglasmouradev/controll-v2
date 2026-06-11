<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\SystemSetting;
use App\Services\AuditLock;

final class SettingsController extends Controller
{
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

	private function resolveEnabledFlag(): bool
	{
		if (array_key_exists('enabled', $_POST)) {
			$value = strtolower(trim((string) $_POST['enabled']));

			return in_array($value, ['1', 'true', 'yes', 'on'], true);
		}

		return !AuditLock::isMaintenanceEnabled();
	}
}
