document.addEventListener('DOMContentLoaded', function () {
	if (!document.getElementById('f-closed-apply') && document.querySelectorAll('.btn-view-closed').length === 0) {
		return;
	}

	document.getElementById('f-closed-apply')?.addEventListener('click', () => {
		const params = new URLSearchParams();
		const id = document.getElementById('f-closed-id')?.value || '';
		const period = document.getElementById('f-closed-period')?.value || '';
		const u = document.getElementById('f-closed-user')?.value || '';
		if (id) params.set('closed_id', id);
		if (period) params.set('closed_period', period);
		if (u) params.set('closed_user', u);
		params.set('closed_page', '1');
		params.set('tab', 'chamados-fechados');
		location.href = '/?' + params.toString();
	});

	document.querySelectorAll('.btn-view-closed').forEach(btn => {
		btn.addEventListener('click', async (e) => {
			const tr = e.target.closest('tr');
			const id = tr?.dataset?.id;
			if (!id) return;
			const res = await fetch('/tickets/view?id=' + id);
			const data = await res.json();
			if (!data.success) {
				showToast('Erro ao carregar chamado');
				return;
			}
			const t = data.ticket;
			const modalBody = document.getElementById('ticket-modal-body');
			const modal = document.getElementById('ticket-modal');
			if (!modalBody || !modal) return;
			modal.dataset.ticketId = String(id);
			modalBody.innerHTML = ticketDetailHtml(t);
			modal.showModal();
			if (typeof loadAttachments === 'function') {
				loadAttachments(id);
			}
		});
	});
});
