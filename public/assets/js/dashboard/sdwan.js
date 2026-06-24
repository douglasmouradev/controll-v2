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
	const totalLojasEl = document.getElementById('sdwan-total-lojas');
	const lojaInput = document.getElementById('sdwan-loja');
	const lojaDatalist = document.getElementById('sdwan-loja-list');
	const lojaHint = document.getElementById('sdwan-loja-hint');
	const imageInput = document.getElementById('sdwan-image');
	const imagePreview = document.getElementById('sdwan-image-preview');
	const imagePreviewImg = document.getElementById('sdwan-image-preview-img');
	const imageCurrent = document.getElementById('sdwan-image-current');
	const imageCurrentImg = document.getElementById('sdwan-image-current-img');
	const imageCurrentLink = document.getElementById('sdwan-image-current-link');
	const removeImageWrap = document.getElementById('sdwan-remove-image-wrap');
	const removeImageInput = document.getElementById('sdwan-remove-image');
	let sdwanEntries = [];
	let previewObjectUrl = null;
	let storeSiglas = [];
	let sdwanPieChart = null;

	async function loadStoreSiglas() {
		try {
			let list = [];
			if (typeof loadStoreAddresses === 'function') {
				list = await loadStoreAddresses();
			} else {
				const res = await fetch('/dashboard/enderecos', {
					headers: { 'X-Requested-With': 'XMLHttpRequest' },
				});
				const data = await res.json();
				list = data.success && Array.isArray(data.data) ? data.data : [];
			}

			storeSiglas = list
				.map((item) => ({
					sigla: String(item.sigla || '').trim().toUpperCase(),
					endereco: String(item.endereco || item.ENDERECO || '').trim(),
				}))
				.filter((item) => item.sigla !== '')
				.sort((a, b) => a.sigla.localeCompare(b.sigla, 'pt-BR'));
		} catch (error) {
			console.error('Erro ao carregar siglas de lojas:', error);
			storeSiglas = [];
		}
	}

	function populateLojaDatalist() {
		if (!lojaDatalist) return;
		lojaDatalist.innerHTML = storeSiglas
			.map((item) => `<option value="${escapeHtml(item.sigla)}"></option>`)
			.join('');
	}

	function findStoreBySigla(query) {
		const sigla = String(query || '').trim().toUpperCase();
		if (!sigla) return null;
		return storeSiglas.find((item) => item.sigla === sigla) || null;
	}

	function findStoresByPrefix(query) {
		const sigla = String(query || '').trim().toUpperCase();
		if (!sigla) return [];
		return storeSiglas.filter((item) => item.sigla.startsWith(sigla));
	}

	function updateLojaHint(store) {
		if (!lojaHint) return;
		if (!store) {
			lojaHint.textContent = 'Digite a sigla para buscar na planilha de lojas.';
			return;
		}
		lojaHint.textContent = store.endereco || `Sigla ${store.sigla} encontrada na planilha de lojas.`;
	}

	function completeLojaSigla() {
		if (!lojaInput) return;
		const query = lojaInput.value.trim().toUpperCase();
		lojaInput.value = query;
		if (!query) {
			updateLojaHint(null);
			return;
		}

		const exact = findStoreBySigla(query);
		if (exact) {
			lojaInput.value = exact.sigla;
			updateLojaHint(exact);
			return;
		}

		const matches = findStoresByPrefix(query);
		if (matches.length === 1) {
			lojaInput.value = matches[0].sigla;
			updateLojaHint(matches[0]);
			return;
		}

		updateLojaHint(null);
	}

	function getCsrfToken() {
		return form.querySelector('input[name="csrf_token"]')?.value || '';
	}

	function clearImagePreview() {
		if (previewObjectUrl) {
			URL.revokeObjectURL(previewObjectUrl);
			previewObjectUrl = null;
		}
		if (imagePreview) imagePreview.classList.add('hidden');
		if (imagePreviewImg) imagePreviewImg.removeAttribute('src');
	}

	function clearCurrentImage() {
		if (imageCurrent) imageCurrent.classList.add('hidden');
		if (imageCurrentImg) imageCurrentImg.removeAttribute('src');
		if (imageCurrentLink) imageCurrentLink.setAttribute('href', '#');
		if (removeImageWrap) removeImageWrap.classList.add('hidden');
		if (removeImageInput) removeImageInput.checked = false;
	}

	function showCurrentImage(entry) {
		clearCurrentImage();
		if (!entry?.has_image || !entry?.image_url) return;
		if (imageCurrent) imageCurrent.classList.remove('hidden');
		if (imageCurrentImg) imageCurrentImg.src = entry.image_url;
		if (imageCurrentLink) imageCurrentLink.href = entry.image_url;
		if (removeImageWrap) {
			removeImageWrap.classList.remove('hidden');
			removeImageWrap.classList.add('flex');
		}
	}

	function resetForm() {
		form.reset();
		entryIdInput.value = '';
		formTitle.textContent = 'Novo registro';
		submitBtn.textContent = 'Salvar registro';
		cancelBtn.classList.add('hidden');
		updateLojaHint(null);
		clearImagePreview();
		clearCurrentImage();
		if (imageInput) imageInput.value = '';
	}

	function fillForm(entry) {
		entryIdInput.value = String(entry.id || '');
		document.getElementById('sdwan-xpads-previsto').value = String(entry.xpads_previsto ?? 0);
		document.getElementById('sdwan-quantidade-localizada').value = String(entry.quantidade_localizada ?? 0);
		document.getElementById('sdwan-pdv-numero').value = entry.pdv_numero || '';
		document.getElementById('sdwan-pdv-serie').value = entry.pdv_serie || '';
		if (lojaInput) {
			lojaInput.value = entry.loja || '';
			updateLojaHint(findStoreBySigla(entry.loja || ''));
		}
		clearImagePreview();
		if (imageInput) imageInput.value = '';
		showCurrentImage(entry);
		formTitle.textContent = 'Editar registro';
		submitBtn.textContent = 'Atualizar registro';
		cancelBtn.classList.remove('hidden');
	}

	function updateSummary(summary) {
		if (!summary) return;
		if (totalRowsEl) totalRowsEl.textContent = String(summary.total ?? 0);
		if (totalXpadsEl) totalXpadsEl.textContent = String(summary.xpads_previsto ?? 0);
		if (totalLocalizadaEl) totalLocalizadaEl.textContent = String(summary.quantidade_localizada ?? 0);
		if (totalLojasEl) totalLojasEl.textContent = String(summary.total_lojas ?? 0);
	}

	function renderSdwanPieChart(chart) {
		const canvas = document.getElementById('sdwan-pie-chart');
		const emptyEl = document.getElementById('sdwan-chart-empty');
		if (!canvas || typeof Chart === 'undefined') return;

		const labels = Array.isArray(chart?.labels) ? chart.labels : [];
		const dataValues = Array.isArray(chart?.data) ? chart.data : [];
		const total = dataValues.reduce((acc, value) => acc + Number(value || 0), 0);

		if (total === 0) {
			if (sdwanPieChart) {
				sdwanPieChart.destroy();
				sdwanPieChart = null;
			}
			canvas.classList.add('hidden');
			if (emptyEl) emptyEl.classList.remove('hidden');
			return;
		}

		canvas.classList.remove('hidden');
		if (emptyEl) emptyEl.classList.add('hidden');

		const palette = ['#1d4ed8', '#7c3aed', '#0891b2', '#dc2626', '#059669', '#f59e0b', '#6366f1', '#db2777', '#0f766e'];
		const data = {
			labels,
			datasets: [{
				label: 'Quantidade localizada',
				data: dataValues,
				backgroundColor: labels.map((_, idx) => palette[idx % palette.length]),
				borderColor: '#fff',
				borderWidth: 2,
			}],
		};
		const options = {
			responsive: true,
			maintainAspectRatio: false,
			plugins: {
				legend: { position: 'bottom' },
				tooltip: {
					callbacks: {
						label(ctx) {
							const value = Number(ctx.parsed || 0);
							const percent = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
							return ` ${ctx.label}: ${value} (${percent}%)`;
						},
					},
				},
			},
		};

		if (sdwanPieChart) {
			sdwanPieChart.data = data;
			sdwanPieChart.options = options;
			sdwanPieChart.update();
			return;
		}

		sdwanPieChart = new Chart(canvas.getContext('2d'), { type: 'pie', data, options });
	}

	function imageCellHtml(entry) {
		if (!entry?.has_image || !entry?.image_url) {
			return '<span class="text-slate-400">-</span>';
		}
		return `<a href="${escapeHtml(entry.image_url)}" target="_blank" rel="noopener noreferrer" class="inline-block">
			<img src="${escapeHtml(entry.image_url)}" alt="Imagem da loja ${escapeHtml(entry.loja || '')}" class="sdwan-table-thumb">
		</a>`;
	}

	function rowHtml(entry) {
		return `
			<tr data-sdwan-id="${escapeHtml(String(entry.id))}">
				<td>${escapeHtml(String(entry.xpads_previsto ?? 0))}</td>
				<td>${escapeHtml(String(entry.quantidade_localizada ?? 0))}</td>
				<td>${escapeHtml(entry.pdv_numero || '-')}</td>
				<td>${escapeHtml(entry.pdv_serie || '-')}</td>
				<td>${escapeHtml(entry.loja || '-')}</td>
				<td class="sdwan-image-cell">${imageCellHtml(entry)}</td>
				<td class="text-right whitespace-nowrap">
					<button type="button" class="btn btn-secondary btn-sm" data-sdwan-edit="${escapeHtml(String(entry.id))}">Editar</button>
					<button type="button" class="btn btn-ghost btn-sm text-red-600" data-sdwan-delete="${escapeHtml(String(entry.id))}">Excluir</button>
				</td>
			</tr>
		`;
	}

	function renderTable(entries) {
		if (!Array.isArray(entries) || entries.length === 0) {
			tableBody.innerHTML = '<tr><td colspan="7" class="empty-state">Nenhum registro cadastrado.</td></tr>';
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
			renderSdwanPieChart(data.chart || {});
		} catch (error) {
			console.error('Erro ao carregar SDWAN:', error);
			if (typeof showToast === 'function') {
				showToast('Erro ao conectar com o servidor');
			}
		}
	}

	form.addEventListener('submit', async (event) => {
		event.preventDefault();
		completeLojaSigla();

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
				if (data.chart) renderSdwanPieChart(data.chart);
				if (data.summary) updateSummary(data.summary);
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

	lojaInput?.addEventListener('input', () => {
		lojaInput.value = lojaInput.value.toUpperCase();
		const exact = findStoreBySigla(lojaInput.value);
		updateLojaHint(exact);
	});

	lojaInput?.addEventListener('blur', completeLojaSigla);

	lojaInput?.addEventListener('change', completeLojaSigla);

	imageInput?.addEventListener('change', () => {
		clearImagePreview();
		const file = imageInput.files && imageInput.files[0];
		if (!file) return;
		if (!file.type.startsWith('image/')) {
			if (typeof showToast === 'function') {
				showToast('Selecione um arquivo de imagem válido');
			}
			imageInput.value = '';
			return;
		}
		if (removeImageInput) removeImageInput.checked = false;
		previewObjectUrl = URL.createObjectURL(file);
		if (imagePreviewImg) imagePreviewImg.src = previewObjectUrl;
		if (imagePreview) imagePreview.classList.remove('hidden');
	});

	removeImageInput?.addEventListener('change', () => {
		if (removeImageInput.checked && imageInput) {
			imageInput.value = '';
			clearImagePreview();
		}
	});

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
				if (data.chart) renderSdwanPieChart(data.chart);
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

	function formatDateTime(value) {
		if (!value) return '';
		const normalized = String(value).includes('T') ? value : String(value).replace(' ', 'T');
		const date = new Date(normalized);
		if (Number.isNaN(date.getTime())) return value;
		return date.toLocaleString('pt-BR');
	}

	function renderAccessLink(link) {
		const box = document.getElementById('sdwan-access-link-box');
		const codeEl = document.getElementById('sdwan-access-code');
		const urlEl = document.getElementById('sdwan-access-url');
		const expiresEl = document.getElementById('sdwan-access-expires');
		if (!box || !link) return;
		if (codeEl) codeEl.textContent = link.code || '----';
		if (urlEl) {
			urlEl.textContent = link.url || '';
			urlEl.href = link.url || '#';
		}
		if (expiresEl) expiresEl.textContent = formatDateTime(link.expires_at || '');
		box.classList.remove('hidden');
	}

	async function loadAccessLink() {
		try {
			const res = await fetch('/dashboard/sdwan-access-link', {
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
			});
			const data = await res.json();
			if (data.success && data.link) {
				renderAccessLink(data.link);
			}
		} catch (error) {
			console.error('Erro ao carregar link SDWAN:', error);
		}
	}

	document.getElementById('btn-sdwan-generate-link')?.addEventListener('click', async () => {
		const btn = document.getElementById('btn-sdwan-generate-link');
		if (!btn) return;
		btn.disabled = true;
		const originalText = btn.textContent;
		btn.textContent = 'Gerando...';
		try {
			const formData = new FormData();
			formData.append('csrf_token', getCsrfToken());
			const res = await fetch('/dashboard/sdwan-access-link/generate', {
				method: 'POST',
				body: formData,
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
			});
			const data = await res.json();
			if (data.success && data.link) {
				renderAccessLink(data.link);
				if (typeof showToast === 'function') {
					showToast(data.message || 'Link gerado com sucesso', 'success');
				}
			} else if (typeof showToast === 'function') {
				showToast(data.message || 'Erro ao gerar link');
			}
		} catch (error) {
			console.error('Erro ao gerar link SDWAN:', error);
			if (typeof showToast === 'function') showToast('Erro ao conectar com o servidor');
		} finally {
			btn.disabled = false;
			btn.textContent = originalText;
		}
	});

	document.getElementById('btn-sdwan-copy-link')?.addEventListener('click', async () => {
		const urlEl = document.getElementById('sdwan-access-url');
		const url = urlEl?.href || urlEl?.textContent || '';
		if (!url || url === '#') return;
		try {
			await navigator.clipboard.writeText(url);
			if (typeof showToast === 'function') showToast('Link copiado', 'success');
		} catch (error) {
			if (typeof showToast === 'function') showToast('Não foi possível copiar o link');
		}
	});

	(async function initSdwanTab() {
		await loadStoreSiglas();
		populateLojaDatalist();
		await loadAccessLink();
		await loadEntries();
	})();
});
