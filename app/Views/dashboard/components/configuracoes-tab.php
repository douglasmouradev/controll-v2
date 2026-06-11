<?php
/** @var array $user */
use App\Models\SystemSetting;
use App\Services\AuditLock;

if (($user['role'] ?? '') !== 'admin') {
	return;
}

$settings = [
	'maintenance_mode' => AuditLock::isMaintenanceEnabled(),
	'audit_lock_enabled' => AuditLock::isAuditLockEnabled(),
	'audit_available_date' => AuditLock::availableDateRaw(),
	'notification_email' => (string) SystemSetting::get('notification_email', getenv('TICKET_NOTIFICATION_EMAIL') ?: ''),
	'system_name' => (string) SystemSetting::get('system_name', 'Controll IT Help Desk'),
];
?>
<div id="tab-configuracoes" class="tab-content hidden">
	<div class="page-header">
		<div>
			<h2 class="page-title">Configurações do Sistema</h2>
			<p class="page-subtitle">Parâmetros globais — somente administrador</p>
		</div>
	</div>

	<div class="ui-card max-w-2xl">
		<form id="form-system-settings" class="space-y-5 p-6">
			<div>
				<label class="label" for="setting-system-name">Nome do sistema</label>
				<input type="text" id="setting-system-name" name="system_name" class="input" value="<?php echo htmlspecialchars($settings['system_name']); ?>">
			</div>

			<div>
				<label class="label" for="setting-notification-email">E-mail de notificações</label>
				<input type="email" id="setting-notification-email" name="notification_email" class="input" value="<?php echo htmlspecialchars($settings['notification_email']); ?>">
			</div>

			<hr class="border-slate-200">

			<div class="flex items-center justify-between gap-4">
				<div>
					<p class="font-semibold text-slate-900">Modo manutenção</p>
					<p class="text-sm text-slate-500">Bloqueia usuários finais imediatamente.</p>
				</div>
				<label class="inline-flex items-center gap-2 cursor-pointer">
					<input type="checkbox" id="setting-maintenance-mode" name="maintenance_mode" value="1" <?php echo $settings['maintenance_mode'] ? 'checked' : ''; ?> class="rounded border-slate-300">
					<span class="text-sm">Ativo</span>
				</label>
			</div>

			<div class="flex items-center justify-between gap-4">
				<div>
					<p class="font-semibold text-slate-900">Bloqueio por auditoria</p>
					<p class="text-sm text-slate-500">Restringe usuários finais até a data abaixo.</p>
				</div>
				<label class="inline-flex items-center gap-2 cursor-pointer">
					<input type="checkbox" id="setting-audit-lock" name="audit_lock_enabled" value="1" <?php echo $settings['audit_lock_enabled'] ? 'checked' : ''; ?> class="rounded border-slate-300">
					<span class="text-sm">Ativo</span>
				</label>
			</div>

			<div>
				<label class="label" for="setting-audit-date">Data de liberação (auditoria)</label>
				<input type="date" id="setting-audit-date" name="audit_available_date" class="input" value="<?php echo htmlspecialchars($settings['audit_available_date']); ?>">
			</div>

			<div class="pt-2">
				<button type="submit" class="btn btn-primary">Salvar configurações</button>
			</div>
		</form>
	</div>
</div>
