document.addEventListener('DOMContentLoaded', function () {
	if (!document.getElementById('f-closed-apply') && document.querySelectorAll('.btn-view-closed').length === 0) {
		return;
	}

	async function loadClosedTicketAttachments(ticketId) {
		try {
			const res = await fetch('/tickets/attachments?id=' + ticketId);
			const data = await res.json();
			const container = document.getElementById('attachments-container');
			if (!container) return;

			if (data.success && Array.isArray(data.attachments) && data.attachments.length > 0) {
				let html = '<div class="mt-4"><strong class="text-sm text-gray-700">Anexos:</strong><div class="grid grid-cols-3 gap-3 mt-2">';
				data.attachments.forEach(att => {
					const type = att.file_type || '';
					const name = att.file_name || '';
					const fileUrl = att.download_url || att.file_path || '';
					const ext = String(name).toLowerCase().split('.').pop();
					const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
					const isImage = type.startsWith('image/') || imageExts.includes(ext);
					const isPdf = type === 'application/pdf' || ext === 'pdf';
					if (isImage) {
						html += `
							<div class="relative">
								<img src="${escapeHtml(fileUrl)}" class="w-full h-24 object-cover rounded border cursor-pointer" onclick="window.open('${escapeHtml(fileUrl)}', '_blank')">
								<span class="text-xs text-gray-500 block mt-1 truncate">${escapeHtml(name)}</span>
							</div>
						`;
					} else if (isPdf) {
						html += `
							<div class="flex flex-col items-start justify-start p-2 border rounded bg-gray-50 cursor-pointer hover:bg-gray-100" onclick="window.open('${escapeHtml(fileUrl)}', '_blank')">
								<div class="flex items-center gap-2">
									<span class="inline-flex items-center justify-center px-2 py-1 text-xs font-semibold rounded bg-red-100 text-red-700 border border-red-200">PDF</span>
									<span class="text-xs text-gray-700 truncate max-w-[8rem]">${escapeHtml(name)}</span>
								</div>
							</div>
						`;
					}
				});
				html += '</div></div>';
				container.innerHTML = html;
			} else {
				container.innerHTML = '';
			}
		} catch (error) {
			console.error('Erro ao carregar anexos:', error);
		}
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
			modalBody.innerHTML = ticketDetailHtml(t);
			modal.showModal();
			loadClosedTicketAttachments(id);
		});
	});
});
