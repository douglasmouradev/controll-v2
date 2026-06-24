document.addEventListener('DOMContentLoaded', function () {
	const form = document.getElementById('sdwan-entry-form');
	const tableBody = document.getElementById('sdwan-table-body');
	if (!form || !tableBody) return;

	const formTitle = document.getElementById('sdwan-form-title');
	const entryIdInput = document.getElementById('sdwan-entry-id');
	const cancelBtn = document.getElementById('sdwan-form-cancel');
	const submitBtn = document.getElementById('sdwan-form-submit');
	const totalRowsEl = document.getElementById('sdwan-total-rows');
	const totalXpadsEl = document.getElementById('sdwan-total-xpads');
	const totalLocalizadaEl = document.getElementById('sdwan-total-localizada');
	let sdwanEntries = [];

	function getCsrfToken() {
		return form.querySelector('input[name="csrf_token"]')?.value || '';
	}

	function resetForm() {
		form.reset();
		entryIdInput.value = '';
		formTitle.textContent = 'Novo registro';
		submitBtn.textContent = 'Salvar registro';
		cancelBtn.classList.add('hidden');
	}

	function fillForm(entry) {
		entryIdInput.value = String(entry.id || '');
		document.getElementById('sdwan-xpads-previsto').value = String(entry.xpads_previsto ?? 0);
		document.getElementById('sdwan-quantidade-localizada').value = String(entry.quantidade_localizada ?? 0);
		document.getElementById('sdwan-pdv-numero').value = entry.pdv_numero || '';
		document.getElementById('sdwan-pdv-serie').value = entry.pdv_serie || '';
		document.getElementById('sdwan-loja').value = entry.loja || '';
		formTitle.textContent = 'Editar registro';
		submitBtn.textContent = 'Atualizar registro';
		cancelBtn.classList.remove('hidden');
	}

	function updateSummary(summary) {
		if (!summary) return;
		if (totalRowsEl) totalRowsEl.textContent = String(summary.total ?? 0);
		if (totalXpadsEl) totalXpadsEl.textContent = String(summary.xpads_previsto ?? 0);
		if (totalLocalizadaEl) totalLocalizadaEl.textContent = String(summary.quantidade_localizada ?? 0);
	}

	function rowHtml(entry) {
		return `
			<tr data-sdwan-id="${escapeHtml(String(entry.id))}">
				<td>${escapeHtml(String(entry.xpads_previsto ?? 0))}</td>
				<td>${escapeHtml(String(entry.quantidade_localizada ?? 0))}</td>
				<td>${escapeHtml(entry.pdv_numero || '-')}</td>
				<td>${escapeHtml(entry.pdv_serie || '-')}</td>
				<td>${escapeHtml(entry.loja || '-')}</td>
				<td class="text-right whitespace-nowrap">
					<button type="button" class="btn btn-secondary btn-sm" data-sdwan-edit="${escapeHtml(String(entry.id))}">Editar</button>
					<button type="button" class="btn btn-ghost btn-sm text-red-600" data-sdwan-delete="${escapeHtml(String(entry.id))}">Excluir</button>
				</td>
			</tr>
		`;
	}

	function renderTable(entries) {
		if (!Array.isArray(entries) || entries.length === 0) {
			tableBody.innerHTML = '<tr><td colspan="6" class="empty-state">Nenhum registro cadastrado.</td></tr>';
			return;
		}
		tableBody.innerHTML = entries.map(rowHtml).join('');
	}

	async function loadEntries() {
		try {
			const res = await fetch('/dashboard/sdwan-entries', {
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
			});
			const data = await res.json();
			if (!data.success) {
				if (typeof showToast === 'function') {
					showToast(data.message || 'Erro ao carregar registros SDWAN');
				}
				return;
			}
			renderTable(data.entries || []);
			sdwanEntries = data.entries || [];
			updateSummary(data.summary || {});
		} catch (error) {
			console.error('Erro ao carregar SDWAN:', error);
			if (typeof showToast === 'function') {
				showToast('Erro ao conectar com o servidor');
			}
		}
	}

	form.addEventListener('submit', async (event) => {
		event.preventDefault();

		const formData = new FormData(form);
		const editingId = entryIdInput.value.trim();
		const url = editingId ? '/dashboard/sdwan-entries/update' : '/dashboard/sdwan-entries/create';

		submitBtn.disabled = true;
		const originalText = submitBtn.textContent;
		submitBtn.textContent = 'Salvando...';

		try {
			const res = await fetch(url, {
				method: 'POST',
				body: formData,
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
			});
			const data = await res.json();

			if (data.success) {
				if (typeof showToast === 'function') {
					showToast(data.message || 'Registro salvo com sucesso', 'success');
				}
				resetForm();
				await loadEntries();
			} else if (typeof showToast === 'function') {
				showToast(data.message || 'Erro ao salvar registro');
			}
		} catch (error) {
			console.error('Erro ao salvar SDWAN:', error);
			if (typeof showToast === 'function') {
				showToast('Erro ao conectar com o servidor');
			}
		} finally {
			submitBtn.disabled = false;
			submitBtn.textContent = originalText;
		}
	});

	cancelBtn.addEventListener('click', resetForm);

	tableBody.addEventListener('click', async (event) => {
		const editBtn = event.target.closest('[data-sdwan-edit]');
		if (editBtn) {
			const id = editBtn.dataset.sdwanEdit;
			const entry = sdwanEntries.find((item) => String(item.id) === String(id));
			if (!entry) return;
			fillForm(entry);
			form.scrollIntoView({ behavior: 'smooth', block: 'start' });
			return;
		}

		const deleteBtn = event.target.closest('[data-sdwan-delete]');
		if (!deleteBtn) return;

		const id = deleteBtn.dataset.sdwanDelete;
		if (!id || !window.confirm('Deseja excluir este registro?')) return;

		const formData = new FormData();
		formData.append('id', id);
		formData.append('csrf_token', getCsrfToken());

		try {
			const res = await fetch('/dashboard/sdwan-entries/delete', {
				method: 'POST',
				body: formData,
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
			});
			const data = await res.json();
			if (data.success) {
				if (typeof showToast === 'function') {
					showToast(data.message || 'Registro excluído com sucesso', 'success');
				}
				if (entryIdInput.value === id) {
					resetForm();
				}
				updateSummary(data.summary || {});
				await loadEntries();
			} else if (typeof showToast === 'function') {
				showToast(data.message || 'Erro ao excluir registro');
			}
		} catch (error) {
			console.error('Erro ao excluir SDWAN:', error);
			if (typeof showToast === 'function') {
				showToast('Erro ao conectar com o servidor');
			}
		}
	});

	loadEntries();
});
