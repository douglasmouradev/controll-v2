(function () {
	const statusEl = document.getElementById('two-factor-status');
	if (!statusEl) return;

	const setupBox = document.getElementById('two-factor-setup');
	const disableBox = document.getElementById('two-factor-disable');
	const startBtn = document.getElementById('two-factor-start-setup');
	const confirmBtn = document.getElementById('two-factor-confirm');
	const disableBtn = document.getElementById('two-factor-disable-btn');

	async function refreshStatus() {
		const res = await fetch('/security/two-factor');
		const data = await res.json();
		if (!data.success) {
			statusEl.textContent = data.message || 'Não foi possível carregar o 2FA.';
			return;
		}

		if (data.enabled) {
			statusEl.innerHTML = '<span class="text-emerald-700 font-medium">2FA ativo</span> — login exige código do autenticador.';
			setupBox?.classList.add('hidden');
			disableBox?.classList.remove('hidden');
			startBtn?.classList.add('hidden');
		} else {
			statusEl.textContent = '2FA desativado. Recomendado para contas de admin e suporte.';
			disableBox?.classList.add('hidden');
			startBtn?.classList.remove('hidden');
		}
	}

	startBtn?.addEventListener('click', async () => {
		const res = await fetch('/security/two-factor/setup', { method: 'POST' });
		const data = await res.json();
		if (!data.success) {
			showToast(data.message || 'Erro ao iniciar configuração');
			return;
		}
		document.getElementById('two-factor-secret').textContent = data.secret || '';
		document.getElementById('two-factor-uri').textContent = data.otpauth_uri || '';
		setupBox?.classList.remove('hidden');
		startBtn?.classList.add('hidden');
	});

	confirmBtn?.addEventListener('click', async () => {
		const code = document.getElementById('two-factor-code')?.value?.trim() || '';
		const form = new FormData();
		form.append('code', code);
		const res = await fetch('/security/two-factor/confirm', { method: 'POST', body: form });
		const data = await res.json();
		showToast(data.message || (data.success ? '2FA ativado' : 'Erro'));
		if (data.success) {
			setupBox?.classList.add('hidden');
			refreshStatus();
		}
	});

	disableBtn?.addEventListener('click', async () => {
		const code = document.getElementById('two-factor-disable-code')?.value?.trim() || '';
		const form = new FormData();
		form.append('code', code);
		const res = await fetch('/security/two-factor/disable', { method: 'POST', body: form });
		const data = await res.json();
		showToast(data.message || (data.success ? '2FA desativado' : 'Erro'));
		if (data.success) {
			disableBox?.classList.add('hidden');
			refreshStatus();
		}
	});

	refreshStatus();
})();
