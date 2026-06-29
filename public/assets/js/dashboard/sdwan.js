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
	const totalUtilizadaEl = document.getElementById('sdwan-total-utilizada');
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
	const storePanelBody = document.getElementById('sdwan-store-panel-body');
	const adminTools = document.getElementById('sdwan-admin-tools');
	const formSection = document.getElementById('sdwan-form-section');
	const linksSection = document.getElementById('sdwan-links-section');
	const goalPercentEl = document.getElementById('sdwan-goal-percent');
	const goalBarFill = document.getElementById('sdwan-goal-bar-fill');
	const goalDetailEl = document.getElementById('sdwan-goal-detail');
	const goalFilteredSummaryEl = document.getElementById('sdwan-filtered-summary');
	const statsFilterNoteEl = document.getElementById('sdwan-stats-filter-note');
	const inconsistenciesBody = document.getElementById('sdwan-inconsistencies-body');
	const inconsistenciesTotalEl = document.getElementById('sdwan-inconsistencies-total');
	const importResultEl = document.getElementById('sdwan-import-result');
	const settingsForm = document.getElementById('sdwan-settings-form');
	const settingGoalInput = document.getElementById('sdwan-setting-goal');
	const settingLinkMaxInput = document.getElementById('sdwan-setting-link-max');
	const settingLinkTtlInput = document.getElementById('sdwan-setting-link-ttl');
	const auditBody = document.getElementById('sdwan-audit-body');

	let sdwanEntries = [];
	let canManage = false;
	let canAdmin = false;
	let previewObjectUrl = null;
	let compressedImageFile = null;
	let storeSiglas = [];
	let sdwanPieChart = null;
	let sdwanProgressChart = null;
	let currentPage = 1;
	let tabInitialized = false;
	let storePanelRows = [];
	let storePanelSort = { key: 'loja', dir: 'asc' };
	let highlightEntryId = null;

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

	function syncFiltersToUrl() {
		const params = new URLSearchParams();
		params.set('tab', 'sdwan');
		const filters = getFilters();
		if (filters.loja) params.set('loja', filters.loja);
		if (filters.pdv) params.set('pdv', filters.pdv);
		if (filters.source) params.set('source', filters.source);
		if (filters.date_from) params.set('date_from', filters.date_from);
		if (filters.date_to) params.set('date_to', filters.date_to);
		if (filters.page && filters.page > 1) params.set('page', String(filters.page));
		const query = params.toString();
		const hash = window.location.hash || '';
		const newUrl = query ? `${window.location.pathname}?${query}${hash}` : `${window.location.pathname}${hash}`;
		window.history.replaceState({}, '', newUrl);
	}

	function loadFiltersFromUrl() {
		const params = new URLSearchParams(window.location.search);
		if (filterLoja) filterLoja.value = (params.get('loja') || '').toUpperCase();
		if (filterPdv) filterPdv.value = params.get('pdv') || '';
		if (filterSource) filterSource.value = params.get('source') || '';
		if (filterDateFrom) filterDateFrom.value = params.get('date_from') || '';
		if (filterDateTo) filterDateTo.value = params.get('date_to') || '';
		currentPage = Math.max(1, parseInt(params.get('page') || '1', 10) || 1);
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
		document.getElementById('sdwan-quantidade-utilizada').value = String(entry.quantidade_utilizada ?? 0);
		document.getElementById('sdwan-pdv-numero').value = entry.pdv_numero || '';
		document.getElementById('sdwan-pdv-serie').value = entry.pdv_serie || '';
		document.getElementById('sdwan-serie-antena').value = entry.serie_antena || '';
		document.getElementById('sdwan-serie-acupad').value = entry.serie_acupad || '';
		document.getElementById('sdwan-setor').value = entry.setor || '';
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
		if (totalUtilizadaEl) totalUtilizadaEl.textContent = String(summary.quantidade_utilizada ?? 0);
		if (totalLojasEl) totalLojasEl.textContent = String(summary.total_lojas ?? 0);
		if (goalFilteredSummaryEl) {
			const previsto = Number(summary.xpads_previsto ?? 0);
			const localizada = Number(summary.quantidade_localizada ?? 0);
			const utilizada = Number(summary.quantidade_utilizada ?? 0);
			goalFilteredSummaryEl.textContent = `Filtrado: ${localizada.toLocaleString('pt-BR')} localizados, ${utilizada.toLocaleString('pt-BR')} utilizados de ${previsto.toLocaleString('pt-BR')} previstos`;
		}
		const hasFilters = !!(filterLoja?.value.trim() || filterPdv?.value.trim() || filterSource?.value || filterDateFrom?.value || filterDateTo?.value);
		if (statsFilterNoteEl) {
			statsFilterNoteEl.textContent = hasFilters
				? 'Totais conforme filtros aplicados.'
				: 'Totais gerais (sem filtros). A meta do projeto permanece global.';
		}
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

		const labels = Array.isArray(chart?.labels) ? chart.labels : ['Acupad previstos', 'Quantidade localizada', 'Quantidade utilizada'];
		const dataValues = Array.isArray(chart?.data) ? chart.data : [0, 0, 0];
		const total = dataValues.reduce((acc, value) => acc + Number(value || 0), 0);
		const barColors = ['#7c3aed', '#f97316', '#059669'];

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
				backgroundColor: labels.map((_, idx) => barColors[idx] || '#64748b'),
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
				<td>${escapeHtml(String(entry.quantidade_utilizada ?? 0))}</td>
				<td>${escapeHtml(entry.pdv_numero || '-')}</td>
				<td>${escapeHtml(entry.pdv_serie || '-')}</td>
				<td>${escapeHtml(entry.serie_antena || '-')}</td>
				<td>${escapeHtml(entry.serie_acupad || '-')}</td>
				<td>${escapeHtml(entry.setor || '-')}</td>
				<td>${escapeHtml(entry.loja || '-')}</td>
				<td class="text-sm">${escapeHtml(entry.created_by_name || '-')}</td>
				<td class="sdwan-image-cell">${imageCellHtml(entry)}</td>
				<td class="sdwan-actions-col whitespace-nowrap sdwan-row-actions">
					<button type="button" class="btn btn-secondary btn-sm" data-sdwan-edit="${escapeHtml(String(entry.id))}">Editar</button>
					<button type="button" class="btn btn-ghost btn-sm text-red-600" data-sdwan-delete="${escapeHtml(String(entry.id))}">Excluir</button>
				</td>
			</tr>
		`;
	}

	function renderTable(entries) {
		if (!Array.isArray(entries) || entries.length === 0) {
			tableBody.innerHTML = '<tr><td colspan="14" class="empty-state">Nenhum registro encontrado.</td></tr>';
			return;
		}
		tableBody.innerHTML = entries.map(rowHtml).join('');
	}

	function sortStorePanelRows(rows) {
		const key = storePanelSort.key;
		const dir = storePanelSort.dir === 'desc' ? -1 : 1;
		return [...rows].sort((a, b) => {
			const av = a[key];
			const bv = b[key];
			if (typeof av === 'number' && typeof bv === 'number') {
				return (av - bv) * dir;
			}
			return String(av || '').localeCompare(String(bv || ''), 'pt-BR') * dir;
		});
	}

	function renderStorePanel(rows) {
		if (!storePanelBody) return;
		storePanelRows = Array.isArray(rows) ? rows : [];
		const sorted = sortStorePanelRows(storePanelRows);
		if (sorted.length === 0) {
			storePanelBody.innerHTML = '<tr><td colspan="6" class="empty-state">Nenhum dado por loja.</td></tr>';
			return;
		}
		storePanelBody.innerHTML = sorted.map((row) => `
			<tr>
				<td class="font-semibold">
					<button type="button" class="sdwan-store-loja-btn" data-sdwan-filter-loja="${escapeHtml(row.loja || '')}" title="Filtrar por ${escapeHtml(row.loja || '')}">${escapeHtml(row.loja || '')}</button>
				</td>
				<td>${escapeHtml(String(row.registros ?? 0))}</td>
				<td>${escapeHtml(String(row.xpads_previsto ?? 0))}</td>
				<td>${escapeHtml(String(row.quantidade_localizada ?? 0))}</td>
				<td>${escapeHtml(String(row.pendente ?? 0))}</td>
				<td><span class="sdwan-store-percent">${escapeHtml(String(row.percent ?? 0))}%</span></td>
			</tr>
		`).join('');
	}

	function renderInconsistencies(payload) {
		if (!inconsistenciesBody) return;
		const counts = payload?.counts || {};
		const totalAlerts = Object.values(counts).reduce((acc, value) => acc + Number(value || 0), 0);
		if (inconsistenciesTotalEl) {
			inconsistenciesTotalEl.textContent = `${totalAlerts} alerta(s)`;
		}

		if (totalAlerts === 0) {
			inconsistenciesBody.innerHTML = '<p class="text-slate-500 col-span-full">Nenhuma inconsistência nos registros filtrados.</p>';
			return;
		}

		const cards = [];

		const storesPending = Array.isArray(payload?.stores_pending) ? payload.stores_pending : [];
		if ((counts.stores_pending || 0) > 0) {
			const items = storesPending.map((row) => `<li><button type="button" class="sdwan-store-loja-btn" data-sdwan-filter-loja="${escapeHtml(row.loja || '')}">${escapeHtml(row.loja || '')}</button> — pendente ${escapeHtml(String(row.pendente ?? 0))}</li>`).join('');
			const more = (counts.stores_pending || 0) > storesPending.length ? `<li class="text-slate-500">+ ${(counts.stores_pending || 0) - storesPending.length} loja(s)</li>` : '';
			cards.push(`<div class="sdwan-inconsistency-card"><h4>Lojas com pendência (${counts.stores_pending})</h4><ul class="space-y-1">${items}${more}</ul></div>`);
		}

		const overLocalizada = Array.isArray(payload?.over_localizada) ? payload.over_localizada : [];
		if ((counts.over_localizada || 0) > 0) {
			const items = overLocalizada.map((row) => `<li>${escapeHtml(row.loja || '')} — localizado ${escapeHtml(String(row.quantidade_localizada ?? 0))} / previsto ${escapeHtml(String(row.xpads_previsto ?? 0))}</li>`).join('');
			const more = (counts.over_localizada || 0) > overLocalizada.length ? `<li class="text-slate-500">+ ${(counts.over_localizada || 0) - overLocalizada.length} registro(s)</li>` : '';
			cards.push(`<div class="sdwan-inconsistency-card"><h4>Localizado acima do previsto (${counts.over_localizada})</h4><ul class="space-y-1">${items}${more}</ul></div>`);
		}

		const withoutPdv = Array.isArray(payload?.without_pdv) ? payload.without_pdv : [];
		if ((counts.without_pdv || 0) > 0) {
			const items = withoutPdv.map((row) => `<li>${escapeHtml(row.loja || '')} — registro #${escapeHtml(String(row.id || ''))}</li>`).join('');
			const more = (counts.without_pdv || 0) > withoutPdv.length ? `<li class="text-slate-500">+ ${(counts.without_pdv || 0) - withoutPdv.length} registro(s)</li>` : '';
			cards.push(`<div class="sdwan-inconsistency-card"><h4>Sem número de PDV (${counts.without_pdv})</h4><ul class="space-y-1">${items}${more}</ul></div>`);
		}

		const withoutImage = Array.isArray(payload?.without_image) ? payload.without_image : [];
		if ((counts.without_image || 0) > 0) {
			const items = withoutImage.map((row) => `<li>${escapeHtml(row.loja || '')} — registro #${escapeHtml(String(row.id || ''))}</li>`).join('');
			const more = (counts.without_image || 0) > withoutImage.length ? `<li class="text-slate-500">+ ${(counts.without_image || 0) - withoutImage.length} registro(s)</li>` : '';
			cards.push(`<div class="sdwan-inconsistency-card"><h4>Sem imagem (${counts.without_image})</h4><ul class="space-y-1">${items}${more}</ul></div>`);
		}

		inconsistenciesBody.innerHTML = cards.join('');
	}

	function renderImportResult(data, mode = 'preview') {
		if (!importResultEl) return;
		if (!data) {
			importResultEl.classList.add('hidden');
			importResultEl.innerHTML = '';
			return;
		}

		const errors = Array.isArray(data.errors) ? data.errors : [];
		const preview = Array.isArray(data.preview) ? data.preview : [];
		let html = `<p class="font-semibold ${data.success ? 'text-emerald-700' : 'text-red-700'}">${escapeHtml(data.message || '')}</p>`;

		if (preview.length > 0) {
			html += '<p class="text-xs text-slate-600 mt-2">Prévia das primeiras linhas válidas:</p><ul class="text-xs text-slate-600 mt-1 space-y-1">';
			preview.forEach((row) => {
				html += `<li>${escapeHtml(row.loja || '')} — previsto ${escapeHtml(String(row.xpads_previsto ?? 0))}, localizado ${escapeHtml(String(row.quantidade_localizada ?? 0))}, PDV ${escapeHtml(row.pdv_numero || '-')}</li>`;
			});
			html += '</ul>';
		}

		if (errors.length > 0) {
			html += `<ul class="sdwan-import-errors">${errors.map((err) => `<li>${escapeHtml(err)}</li>`).join('')}</ul>`;
		} else if (mode === 'import' && data.success) {
			html += '<p class="text-xs text-slate-500 mt-1">Nenhum erro reportado.</p>';
		}

		importResultEl.innerHTML = html;
		importResultEl.classList.remove('hidden');
	}

	function applyStoreFilter(loja) {
		const sigla = String(loja || '').trim().toUpperCase();
		if (!sigla || !filterLoja) return;
		filterLoja.value = sigla;
		loadEntries(1);
	}

	function highlightEntryRow(entryId) {
		if (!entryId || !tableBody) return;
		tableBody.querySelectorAll('.sdwan-row-highlight').forEach((el) => el.classList.remove('sdwan-row-highlight'));
		const row = tableBody.querySelector(`[data-sdwan-id="${String(entryId)}"]`);
		if (row) {
			row.classList.add('sdwan-row-highlight');
			row.scrollIntoView({ behavior: 'smooth', block: 'center' });
		}
	}

	function renderGoalProgress(settings) {
		const progress = settings?.goal_progress || {};
		const goal = Number(progress.goal || 0);
		const localizada = Number(progress.localizada || 0);
		const percent = Number(progress.percent || 0);
		if (goalPercentEl) goalPercentEl.textContent = `${percent}%`;
		if (goalBarFill) goalBarFill.style.width = `${percent}%`;
		if (goalDetailEl) {
			goalDetailEl.textContent = goal > 0
				? `${localizada.toLocaleString('pt-BR')} de ${goal.toLocaleString('pt-BR')} Acupad localizados (meta global)`
				: `${localizada.toLocaleString('pt-BR')} Acupad localizados (defina uma meta nas configurações)`;
		}
		if (settingGoalInput) settingGoalInput.value = String(settings?.xpads_goal ?? goal);
		if (settingLinkMaxInput) settingLinkMaxInput.value = String(settings?.link_max_submissions ?? 50);
		if (settingLinkTtlInput) settingLinkTtlInput.value = String(settings?.link_ttl_hours ?? 24);
	}

	function applyManageUi() {
		const show = !!canManage;
		if (adminTools) adminTools.classList.toggle('hidden', !show);
		if (formSection) formSection.classList.toggle('hidden', !show);
		if (linksSection) linksSection.classList.toggle('hidden', !show);
		document.querySelectorAll('.sdwan-row-actions').forEach((el) => {
			el.classList.toggle('hidden', !show);
		});
		const generateBtn = document.getElementById('btn-sdwan-generate-link');
		if (generateBtn) generateBtn.classList.toggle('hidden', !show);
		const cleanupBtn = document.getElementById('sdwan-cleanup-btn');
		if (cleanupBtn) cleanupBtn.classList.toggle('hidden', !canAdmin);
	}

	function applyListResponse(data) {
		canManage = !!data.can_manage;
		canAdmin = !!data.can_admin;
		renderTable(data.entries || []);
		sdwanEntries = data.entries || [];
		updateSummary(data.summary || {});
		renderSdwanPieChart(data.chart || {});
		renderProgressChart(data.progress || {});
		renderPagination(data.pagination || {});
		renderStorePanel(data.store_panel || []);
		renderInconsistencies(data.inconsistencies || {});
		renderGoalProgress(data.settings || {});
		applyManageUi();
		updateExportLinks();
		syncFiltersToUrl();
		if (highlightEntryId) {
			highlightEntryRow(highlightEntryId);
		}
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
					showToast(data.message || 'Erro ao carregar registros ACUPAD');
				}
				return;
			}
			applyListResponse(data);
		} catch (error) {
			console.error('Erro ao carregar ACUPAD:', error);
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
			console.error('Erro ao salvar ACUPAD:', error);
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
			console.error('Erro ao excluir ACUPAD:', error);
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
				<td class="text-sm">${escapeHtml(String(link.submission_count ?? 0))} / ${escapeHtml(String(link.max_submissions ?? '-'))}</td>
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
			linksTableBody.innerHTML = '<tr><td colspan="5" class="empty-state">Nenhum link ativo.</td></tr>';
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
			console.error('Erro ao carregar links ACUPAD:', error);
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
			console.error('Erro ao revogar link ACUPAD:', error);
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
			console.error('Erro ao gerar link ACUPAD:', error);
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

	async function loadAuditLogs() {
		if (!auditBody || !canAdmin) return;
		try {
			const res = await fetch('/dashboard/sdwan-audit', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
			const data = await res.json();
			if (!data.success || !Array.isArray(data.logs) || data.logs.length === 0) {
				auditBody.innerHTML = '<tr><td colspan="4" class="empty-state">Nenhum registro de auditoria.</td></tr>';
				return;
			}
			auditBody.innerHTML = data.logs.map((log) => `
				<tr>
					<td class="whitespace-nowrap text-xs">${escapeHtml(formatDateTime(log.created_at || ''))}</td>
					<td class="text-xs">${escapeHtml(log.action_label || log.action || '')}</td>
					<td class="text-xs">${escapeHtml(log.resource || '-')}</td>
					<td class="text-xs">${escapeHtml(log.user_name || 'Sistema')}</td>
				</tr>
			`).join('');
		} catch (error) {
			console.error('Erro ao carregar auditoria ACUPAD:', error);
		}
	}

	async function initAcupadTab(options = {}) {
		if (options.loja && filterLoja) {
			filterLoja.value = String(options.loja).trim().toUpperCase();
		}
		if (options.entryId) {
			highlightEntryId = Number(options.entryId) || null;
		}

		if (tabInitialized) {
			await loadEntries(currentPage);
			if (highlightEntryId) highlightEntryRow(highlightEntryId);
			return;
		}

		tabInitialized = true;
		loadFiltersFromUrl();
		if (options.loja && filterLoja) {
			filterLoja.value = String(options.loja).trim().toUpperCase();
		}
		await loadStoreSiglas();
		populateLojaDatalist();
		updateExportLinks();
		await loadAccessLinks();
		await loadEntries(currentPage);
		await loadAuditLogs();
	}

	window.openAcupadTab = function (options) {
		if (typeof window.switchDashboardTab === 'function') {
			window.switchDashboardTab('sdwan', { skipAcupadEvent: true });
		} else {
			document.querySelector('[data-tab="sdwan"]')?.click();
		}
		initAcupadTab(options || {});
	};

	document.addEventListener('acupad-tab-open', () => {
		const params = new URLSearchParams(window.location.search);
		initAcupadTab({
			loja: params.get('loja') || '',
			entryId: params.get('entry_id') || params.get('entry') || null,
		});
	});

	document.getElementById('sdwan-store-panel-table')?.addEventListener('click', (event) => {
		const filterBtn = event.target.closest('[data-sdwan-filter-loja]');
		if (filterBtn) {
			applyStoreFilter(filterBtn.dataset.sdwanFilterLoja);
			return;
		}

		const btn = event.target.closest('[data-sort]');
		if (!btn) return;
		const key = btn.getAttribute('data-sort');
		if (!key) return;
		if (storePanelSort.key === key) {
			storePanelSort.dir = storePanelSort.dir === 'asc' ? 'desc' : 'asc';
		} else {
			storePanelSort.key = key;
			storePanelSort.dir = 'asc';
		}
		renderStorePanel(storePanelRows);
	});

	inconsistenciesBody?.addEventListener('click', (event) => {
		const filterBtn = event.target.closest('[data-sdwan-filter-loja]');
		if (filterBtn) {
			applyStoreFilter(filterBtn.dataset.sdwanFilterLoja);
		}
	});

	settingsForm?.addEventListener('submit', async (event) => {
		event.preventDefault();
		if (!canManage) return;
		const formData = new FormData(settingsForm);
		formData.append('csrf_token', getCsrfToken());
		try {
			const res = await fetch('/dashboard/sdwan-settings', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
			const data = await res.json();
			if (data.success) {
				if (typeof showToast === 'function') showToast(data.message || 'Configurações salvas', 'success');
				renderGoalProgress(data.settings || {});
			} else if (typeof showToast === 'function') showToast(data.message || 'Erro ao salvar');
		} catch (error) {
			if (typeof showToast === 'function') showToast('Erro ao conectar com o servidor');
		}
	});

	document.getElementById('sdwan-import-preview-btn')?.addEventListener('click', async () => {
		if (!canManage) return;
		const fileInput = document.getElementById('sdwan-import-file');
		if (!fileInput?.files?.[0]) {
			if (typeof showToast === 'function') showToast('Selecione um arquivo CSV');
			return;
		}
		const formData = new FormData();
		formData.append('file', fileInput.files[0]);
		formData.append('csrf_token', getCsrfToken());
		try {
			const res = await fetch('/dashboard/sdwan-import/preview', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
			const data = await res.json();
			renderImportResult(data, 'preview');
			if (typeof showToast === 'function') {
				showToast(data.message || (data.success ? 'Validação concluída' : 'Erro na validação'), data.success ? 'success' : undefined);
			}
		} catch (error) {
			if (typeof showToast === 'function') showToast('Erro ao conectar com o servidor');
		}
	});

	document.getElementById('sdwan-import-form')?.addEventListener('submit', async (event) => {
		event.preventDefault();
		if (!canManage) return;
		const fileInput = document.getElementById('sdwan-import-file');
		if (!fileInput?.files?.[0]) return;
		const formData = new FormData();
		formData.append('file', fileInput.files[0]);
		formData.append('csrf_token', getCsrfToken());
		try {
			const res = await fetch('/dashboard/sdwan-import', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
			const data = await res.json();
			renderImportResult(data, 'import');
			if (data.success) {
				if (typeof showToast === 'function') showToast(data.message || 'Importação concluída', 'success');
				fileInput.value = '';
				await loadEntries(1);
			} else if (typeof showToast === 'function') showToast(data.message || 'Erro na importação');
		} catch (error) {
			if (typeof showToast === 'function') showToast('Erro ao conectar com o servidor');
		}
	});

	document.getElementById('sdwan-stores-upload-form')?.addEventListener('submit', async (event) => {
		event.preventDefault();
		if (!canManage) return;
		const fileInput = document.getElementById('sdwan-stores-file');
		if (!fileInput?.files?.[0]) return;
		const formData = new FormData();
		formData.append('file', fileInput.files[0]);
		formData.append('csrf_token', getCsrfToken());
		try {
			const res = await fetch('/dashboard/sdwan-stores/upload', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
			const data = await res.json();
			if (data.success) {
				if (typeof showToast === 'function') showToast(data.message || 'Lojas atualizadas', 'success');
				fileInput.value = '';
				await loadStoreSiglas();
				populateLojaDatalist();
			} else if (typeof showToast === 'function') showToast(data.message || 'Erro ao enviar arquivo');
		} catch (error) {
			if (typeof showToast === 'function') showToast('Erro ao conectar com o servidor');
		}
	});

	document.getElementById('sdwan-cleanup-btn')?.addEventListener('click', async () => {
		if (!canAdmin || !window.confirm('Executar limpeza de imagens órfãs e links expirados?')) return;
		const formData = new FormData();
		formData.append('csrf_token', getCsrfToken());
		try {
			const res = await fetch('/dashboard/sdwan-cleanup', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
			const data = await res.json();
			if (data.success && typeof showToast === 'function') showToast(data.message || 'Limpeza concluída', 'success');
			else if (typeof showToast === 'function') showToast(data.message || 'Erro na limpeza');
		} catch (error) {
			if (typeof showToast === 'function') showToast('Erro ao conectar com o servidor');
		}
	});
});
