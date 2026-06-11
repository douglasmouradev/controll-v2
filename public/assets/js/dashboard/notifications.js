(function () {
	const root = document.getElementById('notifications-root');
	if (!root) return;

	const btn = document.getElementById('btn-notifications');
	const panel = document.getElementById('notifications-panel');
	const list = document.getElementById('notifications-list');
	const badge = document.getElementById('notifications-badge');
	const markAllBtn = document.getElementById('notifications-mark-all');

	async function loadNotifications() {
		try {
			const res = await fetch('/notifications');
			const data = await res.json();
			if (!data.success) return;

			const unread = Number(data.unread || 0);
			if (unread > 0) {
				badge.textContent = unread > 99 ? '99+' : String(unread);
				badge.classList.remove('hidden');
			} else {
				badge.classList.add('hidden');
			}

			const items = Array.isArray(data.notifications) ? data.notifications : [];
			if (items.length === 0) {
				list.innerHTML = '<p class="px-4 py-6 text-sm text-slate-500 text-center">Nenhuma notificação.</p>';
				return;
			}

			list.innerHTML = items.map((item) => {
				const unreadClass = item.is_read === '0' || item.is_read === 0 || item.is_read === false
					? 'bg-blue-50/60'
					: '';
				const date = item.created_at ? new Date(item.created_at).toLocaleString('pt-BR') : '';
				return `
					<button type="button" class="w-full text-left px-4 py-3 hover:bg-slate-50 ${unreadClass}" data-notification-id="${item.id}">
						<p class="text-sm font-medium text-slate-800">${escapeHtml(item.title || '')}</p>
						<p class="text-xs text-slate-600 mt-1">${escapeHtml(item.message || '')}</p>
						<p class="text-[11px] text-slate-400 mt-1">${escapeHtml(date)}</p>
					</button>
				`;
			}).join('');
		} catch (e) {
			console.error('Erro ao carregar notificações', e);
		}
	}

	btn?.addEventListener('click', () => {
		panel.classList.toggle('hidden');
		if (!panel.classList.contains('hidden')) {
			loadNotifications();
		}
	});

	document.addEventListener('click', (e) => {
		if (!root.contains(e.target)) {
			panel?.classList.add('hidden');
		}
	});

	list?.addEventListener('click', async (e) => {
		const target = e.target.closest('[data-notification-id]');
		if (!target) return;
		const id = target.getAttribute('data-notification-id');
		const form = new FormData();
		form.append('id', id);
		await fetch('/notifications/read', { method: 'POST', body: form });
		loadNotifications();
	});

	markAllBtn?.addEventListener('click', async () => {
		await fetch('/notifications/read-all', { method: 'POST' });
		loadNotifications();
	});

	loadNotifications();
	setInterval(loadNotifications, 60000);
})();
