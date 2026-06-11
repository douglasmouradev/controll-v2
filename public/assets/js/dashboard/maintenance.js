(function () {
	function updateMaintenanceUI(enabled) {
		const btn = document.getElementById('btn-toggle-maintenance');
		const label = document.getElementById('maintenance-status-label');
		const badge = document.getElementById('maintenance-topbar-badge');

		if (btn) {
			btn.dataset.enabled = enabled ? '1' : '0';
		}
		if (label) {
			label.textContent = enabled ? 'Ativo' : 'Inativo';
		}
		if (badge) {
			if (enabled) {
				badge.className = 'hidden sm:inline-flex items-center gap-1.5 rounded-full bg-amber-100 text-amber-900 border border-amber-200 px-3 py-1 text-xs font-semibold';
				badge.textContent = 'Manutenção ativa';
			} else {
				badge.className = 'hidden';
				badge.textContent = '';
			}
		}
	}

	async function toggleMaintenanceMode() {
		const btn = document.getElementById('btn-toggle-maintenance');
		if (!btn) return;

		const currentlyEnabled = btn.dataset.enabled === '1';
		const nextEnabled = !currentlyEnabled;
		const actionLabel = nextEnabled ? 'ATIVAR' : 'DESATIVAR';

		if (!confirm('Deseja ' + actionLabel + ' o modo manutenção?\n\nUsuários finais ' + (nextEnabled ? 'não poderão' : 'voltarão a') + ' acessar o sistema.')) {
			return;
		}

		const fd = new FormData();
		fd.set('enabled', nextEnabled ? '1' : '0');

		try {
			const res = await fetch('/settings/maintenance', {
				method: 'POST',
				body: fd,
				headers: { 'X-Requested-With': 'XMLHttpRequest' }
			});
			const data = await res.json();
			if (data.success) {
				updateMaintenanceUI(!!data.enabled);
				if (typeof showToast === 'function') {
					showToast(data.message || 'Configuração atualizada');
				}
			} else if (typeof showToast === 'function') {
				showToast(data.message || 'Erro ao alterar modo manutenção');
			}
		} catch (error) {
			console.error('Erro ao alterar modo manutenção:', error);
			if (typeof showToast === 'function') {
				showToast('Erro ao conectar com o servidor');
			}
		}
	}

	window.DashboardMaintenance = {
		updateMaintenanceUI: updateMaintenanceUI,
		toggleMaintenanceMode: toggleMaintenanceMode,
	};
})();
