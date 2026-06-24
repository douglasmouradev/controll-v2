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
	const imageSizeHint = document.getElementById('sdwan-image-size-hint');
	const filterLoja = document.getElementById('sdwan-filter-loja');
	const filterPdv = document.getElementById('sdwan-filter-pdv');
	const filterSource = document.getElementById('sdwan-filter-source');
	const filterDateFrom = document.getElementById('sdwan-filter-date-from');
	const filterDateTo = document.getElementById('sdwan-filter-date-to');
	const filtersForm = document.getElementById('sdwan-filters-form');
	const filterClearBtn = document.getElementById('sdwan-filter-clear');
	const pagePrevBtn = document.getElementById('sdwan-page-prev');
	const pageNextBtn = document.getElementById('sdwan-page-next');
	const pageLabel = document.getElementById('sdwan-page-label');
	const paginationInfo = document.getElementById('sdwan-pagination-info');
	const exportPdfLink = document.getElementById('sdwan-export-pdf');
	const exportXlsxLink = document.getElementById('sdwan-export-xlsx');
	const linksTableBody = document.getElementById('sdwan-links-table-body');

	let sdwanEntries = [];
	let previewObjectUrl = null;
	let compressedImageFile = null;
	let storeSiglas = [];
	let sdwanPieChart = null;
	let sdwanProgressChart = null;
	let currentPage = 1;

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

	function getFilters() {
		return {
			loja: filterLoja?.value.trim().toUpperCase() || '',
			pdv: filterPdv?.value.trim() || '',
			source: filterSource?.value || '',
			date_from: filterDateFrom?.value || '',
			date_to: filterDateTo?.value || '',
			page: currentPage,
		};
	}

	function buildFilterQuery(overrides = {}) {
		const filters = { ...getFilters(), ...overrides };
		const params = new URLSearchParams();
		if (filters.loja) params.set('loja', filters.loja);
		if (filters.pdv) params.set('pdv', filters.pdv);
		if (filters.source) params.set('source', filters.source);
		if (filters.date_from) params.set('date_from', filters.date_from);
		if (filters.date_to) params.set('date_to', filters.date_to);
		if (filters.page && filters.page > 1) params.set('page', String(filters.page));
		const query = params.toString();
		return query ? `?${query}` : '';
	}

	function updateExportLinks() {
		const query = buildFilterQuery();
		if (exportPdfLink) exportPdfLink.href = `/dashboard/sdwan-entries/export/pdf${query}`;
		if (exportXlsxLink) exportXlsxLink.href = `/dashboard/sdwan-entries/export/xlsx${query}`;
	}

	function clearImagePreview() {
		if (previewObjectUrl) {
			URL.revokeObjectURL(previewObjectUrl);
			previewObjectUrl = null;
		}
		compressedImageFile = null;
		if (imagePreview) imagePreview.classList.add('hidden');
		if (imagePreviewImg) imagePreviewImg.removeAttribute('src');
		if (imageSizeHint) {
			imageSizeHint.textContent = 'A imagem será otimizada automaticamente antes do envio.';
		}
	}

	function updateImageSizeHint(result) {
		if (!imageSizeHint || !result) return;
		const formatSize = typeof formatFileSize === 'function' ? formatFileSize : (bytes) => String(bytes);
		if (result.optimized && result.compressedSize < result.originalSize) {
			imageSizeHint.textContent = `Imagem otimizada: ${formatSize(result.originalSize)} → ${formatSize(result.compressedSize)}`;
			return;
		}
		imageSizeHint.textContent = `Tamanho da imagem: ${formatSize(result.compressedSize || result.originalSize || 0)}`;
	}

	async function prepareSelectedImage(file) {
		if (imageSizeHint) imageSizeHint.textContent = 'Otimizando imagem...';
		if (typeof compressImageFile !== 'function') {
			compressedImageFile = file;
			updateImageSizeHint({ originalSize: file.size, compressedSize: file.size, optimized: false });
			return file;
		}
		const result = await compressImageFile(file);
		compressedImageFile = result.file;
		updateImageSizeHint(result);
		return result.file;
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

	function renderProgressChart(chart) {
		const canvas = document.getElementById('sdwan-progress-chart');
		const emptyEl = document.getElementById('sdwan-progress-empty');
		if (!canvas || typeof Chart === 'undefined') return;

		const labels = Array.isArray(chart?.labels) ? chart.labels : ['XPads previstos', 'Quantidade localizada'];
		const dataValues = Array.isArray(chart?.data) ? chart.data : [0, 0];
		const total = dataValues.reduce((acc, value) => acc + Number(value || 0), 0);

		if (total === 0) {
			if (sdwanProgressChart) {
				sdwanProgressChart.destroy();
				sdwanProgressChart = null;
			}
			canvas.classList.add('hidden');
			if (emptyEl) emptyEl.classList.remove('hidden');
			return;
		}

		canvas.classList.remove('hidden');
		if (emptyEl) emptyEl.classList.add('hidden');

		const data = {
			labels,
			datasets: [{
				label: 'Totais',
				data: dataValues,
				backgroundColor: ['#7c3aed', '#f97316'],
				borderRadius: 6,
			}],
		};
		const options = {
			responsive: true,
			maintainAspectRatio: false,
			plugins: { legend: { display: false } },
			scales: {
				y: { beginAtZero: true, ticks: { precision: 0 } },
			},
		};

		if (sdwanProgressChart) {
			sdwanProgressChart.data = data;
			sdwanProgressChart.options = options;
			sdwanProgressChart.update();
			return;
		}

		sdwanProgressChart = new Chart(canvas.getContext('2d'), { type: 'bar', data, options });
	}

	function renderPagination(pagination) {
		const page = pagination?.page || 1;
		const totalPages = pagination?.total_pages || 1;
		const total = pagination?.total || 0;
		const perPage = pagination?.per_page || 25;
		currentPage = page;

		if (pageLabel) pageLabel.textContent = `Página ${page} de ${totalPages}`;
		if (paginationInfo) {
			const from = total === 0 ? 0 : (page - 1) * perPage + 1;
			const to = Math.min(page * perPage, total);
			paginationInfo.textContent = total === 0
				? 'Nenhum registro encontrado'
				: `Mostrando ${from}–${to} de ${total} registros`;
		}
		if (pagePrevBtn) pagePrevBtn.disabled = page <= 1;
		if (pageNextBtn) pageNextBtn.disabled = page >= totalPages;
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
		const warningBadge = entry.warning_localizada
			? '<span class="sdwan-warning-badge" title="Quantidade localizada maior que o previsto">!</span>'
			: '';
		const sourceClass = entry.entry_source === 'public' ? 'sdwan-source-public' : 'sdwan-source-dashboard';

		return `
			<tr data-sdwan-id="${escapeHtml(String(entry.id))}">
				<td class="whitespace-nowrap text-sm">${escapeHtml(entry.created_at_formatted || '-')}</td>
				<td><span class="sdwan-source-badge ${sourceClass}">${escapeHtml(entry.source_label || 'Dashboard')}</span></td>
				<td>${escapeHtml(String(entry.xpads_previsto ?? 0))}${warningBadge}</td>
				<td>${escapeHtml(String(entry.quantidade_localizada ?? 0))}</td>
				<td>${escapeHtml(entry.pdv_numero || '-')}</td>
				<td>${escapeHtml(entry.pdv_serie || '-')}</td>
				<td>${escapeHtml(entry.loja || '-')}</td>
				<td class="sdwan-image-cell">${imageCellHtml(entry)}</td>
				<td class="sdwan-actions-col whitespace-nowrap">
					<button type="button" class="btn btn-secondary btn-sm" data-sdwan-edit="${escapeHtml(String(entry.id))}">Editar</button>
					<button type="button" class="btn btn-ghost btn-sm text-red-600" data-sdwan-delete="${escapeHtml(String(entry.id))}">Excluir</button>
				</td>
			</tr>
		`;
	}

	function renderTable(entries) {
		if (!Array.isArray(entries) || entries.length === 0) {
			tableBody.innerHTML = '<tr><td colspan="9" class="empty-state">Nenhum registro encontrado.</td></tr>';
			return;
		}
		tableBody.innerHTML = entries.map(rowHtml).join('');
	}

	function applyListResponse(data) {
		renderTable(data.entries || []);
		sdwanEntries = data.entries || [];
		updateSummary(data.summary || {});
		renderSdwanPieChart(data.chart || {});
		renderProgressChart(data.progress || {});
		renderPagination(data.pagination || {});
		updateExportLinks();
	}

	async function loadEntries(page) {
		if (typeof page === 'number') currentPage = page;
		try {
			const res = await fetch(`/dashboard/sdwan-entries${buildFilterQuery()}`, {
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
			});
			const data = await res.json();
			if (!data.success) {
				if (typeof showToast === 'function') {
					showToast(data.message || 'Erro ao carregar registros SDWAN');
				}
				return;
			}
			applyListResponse(data);
		} catch (error) {
			console.error('Erro ao carregar SDWAN:', error);
			if (typeof showToast === 'function') {
				showToast('Erro ao conectar com o servidor');
			}
		}
	}

	function showSaveWarning(data) {
		if (!data?.warning || typeof showToast !== 'function') return;
		showToast(data.warning, 'warning');
	}

	form.addEventListener('submit', async (event) => {
		event.preventDefault();
		completeLojaSigla();

		const formData = new FormData(form);
		if (compressedImageFile) {
			formData.set('image', compressedImageFile, compressedImageFile.name);
		} else if (imageInput?.files?.[0] && typeof compressImageFile === 'function') {
			submitBtn.disabled = true;
			const originalText = submitBtn.textContent;
			submitBtn.textContent = 'Otimizando imagem...';
			const result = await compressImageFile(imageInput.files[0]);
			compressedImageFile = result.file;
			formData.set('image', result.file, result.file.name);
			submitBtn.textContent = originalText;
		}

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
				showSaveWarning(data);
				resetForm();
				await loadEntries(1);
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

	filtersForm?.addEventListener('submit', (event) => {
		event.preventDefault();
		loadEntries(1);
	});

	filterClearBtn?.addEventListener('click', () => {
		if (filterLoja) filterLoja.value = '';
		if (filterPdv) filterPdv.value = '';
		if (filterSource) filterSource.value = '';
		if (filterDateFrom) filterDateFrom.value = '';
		if (filterDateTo) filterDateTo.value = '';
		loadEntries(1);
	});

	filterLoja?.addEventListener('input', () => {
		filterLoja.value = filterLoja.value.toUpperCase();
	});

	pagePrevBtn?.addEventListener('click', () => {
		if (currentPage > 1) loadEntries(currentPage - 1);
	});

	pageNextBtn?.addEventListener('click', () => {
		loadEntries(currentPage + 1);
	});

	lojaInput?.addEventListener('input', () => {
		lojaInput.value = lojaInput.value.toUpperCase();
		const exact = findStoreBySigla(lojaInput.value);
		updateLojaHint(exact);
	});

	lojaInput?.addEventListener('blur', completeLojaSigla);
	lojaInput?.addEventListener('change', completeLojaSigla);

	imageInput?.addEventListener('change', async () => {
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

		try {
			const prepared = await prepareSelectedImage(file);
			previewObjectUrl = URL.createObjectURL(prepared);
			if (imagePreviewImg) imagePreviewImg.src = previewObjectUrl;
			if (imagePreview) imagePreview.classList.remove('hidden');
		} catch (error) {
			console.error('Erro ao otimizar imagem:', error);
			if (typeof showToast === 'function') {
				showToast('Não foi possível otimizar a imagem. Tente outra foto.');
			}
			imageInput.value = '';
			clearImagePreview();
		}
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
		const qrEl = document.getElementById('sdwan-access-qr');
		if (!box || !link) return;
		if (codeEl) codeEl.textContent = link.code || '----';
		if (urlEl) {
			urlEl.textContent = link.url || '';
			urlEl.href = link.url || '#';
		}
		if (expiresEl) expiresEl.textContent = formatDateTime(link.expires_at || '');
		if (qrEl && link.qr_url) {
			qrEl.src = link.qr_url;
			qrEl.classList.remove('hidden');
		}
		box.classList.remove('hidden');
	}

	function linkRowHtml(link) {
		return `
			<tr data-link-id="${escapeHtml(String(link.id))}">
				<td class="font-mono font-bold tracking-widest">${escapeHtml(link.code || '')}</td>
				<td>
					<a href="${escapeHtml(link.url || '#')}" target="_blank" rel="noopener noreferrer" class="text-blue-700 break-all hover:underline">${escapeHtml(link.url || '')}</a>
				</td>
				<td class="whitespace-nowrap text-sm">${escapeHtml(formatDateTime(link.expires_at || ''))}</td>
				<td class="sdwan-actions-col whitespace-nowrap">
					<button type="button" class="btn btn-ghost btn-sm" data-sdwan-copy-link-row="${escapeHtml(String(link.id))}" data-url="${escapeHtml(link.url || '')}">Copiar</button>
					<a href="${escapeHtml(link.qr_url || '#')}" target="_blank" rel="noopener noreferrer" class="btn btn-ghost btn-sm">QR</a>
					<button type="button" class="btn btn-ghost btn-sm text-red-600" data-sdwan-revoke-link="${escapeHtml(String(link.id))}">Revogar</button>
				</td>
			</tr>
		`;
	}

	function renderLinksList(links) {
		if (!linksTableBody) return;
		if (!Array.isArray(links) || links.length === 0) {
			linksTableBody.innerHTML = '<tr><td colspan="4" class="empty-state">Nenhum link ativo.</td></tr>';
			return;
		}
		linksTableBody.innerHTML = links.map(linkRowHtml).join('');
	}

	async function loadAccessLinks() {
		try {
			const res = await fetch('/dashboard/sdwan-access-link', {
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
			});
			const data = await res.json();
			if (!data.success) return;
			if (data.link) renderAccessLink(data.link);
			renderLinksList(data.links || []);
		} catch (error) {
			console.error('Erro ao carregar links SDWAN:', error);
		}
	}

	linksTableBody?.addEventListener('click', async (event) => {
		const copyBtn = event.target.closest('[data-sdwan-copy-link-row]');
		if (copyBtn) {
			const url = copyBtn.dataset.url || '';
			if (!url) return;
			try {
				await navigator.clipboard.writeText(url);
				if (typeof showToast === 'function') showToast('Link copiado', 'success');
			} catch (error) {
				if (typeof showToast === 'function') showToast('Não foi possível copiar o link');
			}
			return;
		}

		const revokeBtn = event.target.closest('[data-sdwan-revoke-link]');
		if (!revokeBtn) return;

		const id = revokeBtn.dataset.sdwanRevokeLink;
		if (!id || !window.confirm('Revogar este link? Técnicos não poderão mais usá-lo.')) return;

		const formData = new FormData();
		formData.append('id', id);
		formData.append('csrf_token', getCsrfToken());

		try {
			const res = await fetch('/dashboard/sdwan-access-link/revoke', {
				method: 'POST',
				body: formData,
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
			});
			const data = await res.json();
			if (data.success) {
				if (typeof showToast === 'function') {
					showToast(data.message || 'Link revogado', 'success');
				}
				renderLinksList(data.links || []);
				const box = document.getElementById('sdwan-access-link-box');
				const revokedStillShown = data.links?.some((link) => String(link.id) === String(id));
				if (!revokedStillShown && box) {
					const latest = data.links?.[0];
					if (latest) renderAccessLink(latest);
					else box.classList.add('hidden');
				}
			} else if (typeof showToast === 'function') {
				showToast(data.message || 'Erro ao revogar link');
			}
		} catch (error) {
			console.error('Erro ao revogar link SDWAN:', error);
			if (typeof showToast === 'function') showToast('Erro ao conectar com o servidor');
		}
	});

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
				if (data.links) renderLinksList(data.links);
				else await loadAccessLinks();
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
		updateExportLinks();
		await loadAccessLinks();
		await loadEntries(1);
	})();
});
