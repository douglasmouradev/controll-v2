(function () {
	const root = document.getElementById('notifications-root');
	if (!root) return;

	const btn = document.getElementById('btn-notifications');
	const panel = document.getElementById('notifications-panel');
	const list = document.getElementById('notifications-list');
	const badge = document.getElementById('notifications-badge');
	const markAllBtn = document.getElementById('notifications-mark-all');

	function parseLojaFromMessage(message) {
		const match = String(message || '').match(/Loja\s+([A-Z0-9]+)\s+—/);
		return match ? match[1] : '';
	}

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
				const type = String(item.type || '');
				const entryId = item.ticket_id ? String(item.ticket_id) : '';
				const loja = parseLojaFromMessage(item.message || '');
				return `
					<button type="button" class="w-full text-left px-4 py-3 hover:bg-slate-50 ${unreadClass}"
						data-notification-id="${item.id}"
						data-notification-type="${escapeHtml(type)}"
						data-notification-entry="${escapeHtml(entryId)}"
						data-notification-loja="${escapeHtml(loja)}">
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
		const type = target.getAttribute('data-notification-type') || '';
		const entryId = target.getAttribute('data-notification-entry') || '';
		const loja = target.getAttribute('data-notification-loja') || '';

		const form = new FormData();
		form.append('id', id);
		await fetch('/notifications/read', { method: 'POST', body: form });

		if (type === 'sdwan_public_entry' && typeof window.openAcupadTab === 'function') {
			panel?.classList.add('hidden');
			window.openAcupadTab({
				loja,
				entryId: entryId || null,
			});
			loadNotifications();
			return;
		}

		loadNotifications();
	});

	markAllBtn?.addEventListener('click', async () => {
		await fetch('/notifications/read-all', { method: 'POST' });
		loadNotifications();
	});

	loadNotifications();
	startVisibilityAwareInterval(loadNotifications, 60000);
})();
