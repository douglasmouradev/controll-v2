function chartLibReady() {
	if (typeof Chart === 'undefined') {
		console.error('Chart.js não foi carregado.');
		return false;
	}
	return true;
}

let dailiesChart, statusChart, creditsTicketPie, creditsDailyPie, creditsProjectPie, dailyDestinationChart, inventoryPieChart;
let inventoryLocationsByCategory = {};
const PURCHASED_DAILIES_PAGE_SIZE = 500;
let purchasedDailiesRows = [];
let purchasedDailiesShown = PURCHASED_DAILIES_PAGE_SIZE;

	async function loadDailies(prefetched) {
		if (!chartLibReady()) return;
		try {
			const json = prefetched || await (await fetch('/dashboard/dailies')).json();
			const ctx = document.getElementById('dailies-chart').getContext('2d');
			
			let labels = json.labels || [];
			let dataValues = json.data || [];
			
			if (labels.length === 0 || dataValues.every(v => v === 0)) {
				labels = ['Sem dados'];
				dataValues = [0];
			}
			
			const data = {
				labels: labels,
				datasets: [{
					label: 'Diárias',
					data: dataValues,
					borderColor: '#2563eb',
					backgroundColor: 'rgba(37, 99, 235, 0.1)',
					borderWidth: 2,
					fill: true,
					tension: 0.4,
					pointRadius: 4,
					pointBackgroundColor: '#2563eb',
					pointBorderColor: '#fff',
					pointBorderWidth: 2,
				}],
			};
			const options = {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: { position: 'top' },
					tooltip: {
						backgroundColor: 'rgba(0,0,0,0.8)',
						padding: 12,
						titleFont: { size: 14 },
						bodyFont: { size: 13 },
						callbacks: {
							label: function (ctx) {
								return `Diárias: ${ctx.parsed.y}`;
							}
						}
					}
				},
				scales: {
					y: { beginAtZero: true, ticks: { stepSize: 1 } }
				}
			};
			if (dailiesChart) {
				dailiesChart.data = data;
				dailiesChart.options = options;
				dailiesChart.update();
			} else {
				dailiesChart = new Chart(ctx, { type: 'line', data, options });
			}
		} catch (e) {
			console.error('Erro ao carregar gráfico de chamados:', e);
		}
	}

	async function loadStatusChart(prefetched) {
		try {
			const json = prefetched || await (await fetch('/dashboard/status-stats')).json();
			if (!json.success) throw new Error('Erro ao buscar dados');
			
			const ctx = document.getElementById('status-chart').getContext('2d');
			const colors = {
				'Aberto': '#f59e0b',
				'Em Andamento': '#3b82f6',
				'Em andamento': '#3b82f6',
				'Agendado': '#8b5cf6',
				'Fechado': '#10b981',
				'Cancelado': '#6b7280'
			};
			
			const labels = json.labels || [];
			const dataValues = json.data || [];
			const total = dataValues.reduce((a, b) => a + b, 0);
			
			const data = {
				labels: labels,
				datasets: [{
					label: 'Quantidade',
					data: dataValues,
					backgroundColor: labels.map(label => colors[label] || '#9ca3af'),
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
						backgroundColor: 'rgba(0,0,0,0.8)',
						padding: 12,
						callbacks: {
							label: function (ctx) {
								const value = ctx.parsed || 0;
								const percent = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
								return `${ctx.label}: ${value} (${percent}%)`;
							}
						}
					}
				}
			};
			if (statusChart) {
				statusChart.data = data;
				statusChart.options = options;
				statusChart.update();
			} else {
				statusChart = new Chart(ctx, { type: 'doughnut', data, options });
			}
		} catch (e) {
			console.error('Erro ao carregar gráfico de status:', e);
		}
	}

	async function loadDailyDestinationChart(prefetched) {
		try {
			const canvas = document.getElementById('daily-destination-chart');
			if (!canvas) return;

			const json = prefetched || await (await fetch('/dashboard/daily-destinations')).json();

			let labels = json.labels || [];
			let dataValues = json.data || [];

			if (labels.length === 0 || dataValues.every(v => v === 0)) {
				labels = ['Sem dados'];
				dataValues = [0];
			}

			const ctx = canvas.getContext('2d');
			const data = {
				labels,
				datasets: [{
					label: 'Diárias',
					data: dataValues,
					backgroundColor: '#2563eb',
					borderColor: '#1d4ed8',
					borderWidth: 1,
					borderRadius: 6,
				}],
			};

			const options = {
				indexAxis: 'y',
				responsive: true,
				maintainAspectRatio: false,
				scales: {
					x: {
						beginAtZero: true,
						title: { display: true, text: 'Quantidade' },
					},
					y: {
						title: { display: true, text: 'Destino' },
					}
				},
				plugins: {
					legend: { display: false },
					tooltip: {
						backgroundColor: 'rgba(0,0,0,0.8)',
						padding: 12,
						callbacks: {
							label: function(ctx) {
								return ` ${ctx.parsed.x} diárias`;
							}
						}
					}
				}
			};

			if (dailyDestinationChart) {
				dailyDestinationChart.data = data;
				dailyDestinationChart.options = options;
				dailyDestinationChart.update();
			} else {
				dailyDestinationChart = new Chart(ctx, { type: 'bar', data, options });
			}
		} catch (e) {
			console.error('Erro ao carregar gráfico de destinos de diárias:', e);
		}
	}

	async function loadInventoryPieChart() {
		try {
			const canvas = document.getElementById('inventory-pie-chart');
			if (!canvas) return;

			const params = new URLSearchParams();
			const storeFilterEl = document.getElementById('inventory-filter-store');
			const supportStatusEl = document.getElementById('inventory-filter-support-status');
			const startDateEl = document.getElementById('inventory-filter-start-date');
			const endDateEl = document.getElementById('inventory-filter-end-date');
			if (storeFilterEl && storeFilterEl.value) {
				params.set('store', storeFilterEl.value);
			}
			if (supportStatusEl && supportStatusEl.value) {
				params.set('support_status', supportStatusEl.value);
			}
			if (startDateEl && startDateEl.value) {
				params.set('start_date', startDateEl.value);
			}
			if (endDateEl && endDateEl.value) {
				params.set('end_date', endDateEl.value);
			}

			const url = '/dashboard/inventory-stats' + (params.toString() ? `?${params.toString()}` : '');
			const res = await fetch(url);
			const json = await res.json();
			if (!json.success) {
				const detail = json.details ? ` (${json.details})` : '';
				throw new Error((json.message || 'Falha ao carregar inventário') + detail);
			}
			if (json.debug) {
				console.log('[Inventario][DEBUG]', json.debug);
			}

			const labels = json.labels || [];
			const displayLabels = labels.map((label) => {
				if (label === 'HEXAPADS') return 'XPADS';
				if (label === 'DEFEITO') return 'OCORRÊNCIAS';
				if (label === 'SUPORTE_INSTALADO') return 'SUPORTE INSTALADO';
				if (label === 'SUPORTE_PENDENTE') return 'SUPORTE PENDENTE';
				return label;
			});
			const dataValues = json.data || [];
			inventoryLocationsByCategory = json.locations_by_category || {};
			const total = dataValues.reduce((acc, value) => acc + Number(value || 0), 0);
			const palette = ['#1d4ed8', '#7c3aed', '#0891b2', '#dc2626', '#059669', '#f59e0b'];

			const data = {
				labels: displayLabels,
				datasets: [{
					label: 'Inventário',
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
					legend: {
						position: 'bottom',
						onClick: function(e, legendItem, legend) {
							const chart = legend.chart;
							const index = legendItem.index;
							const ci = chart;
							const meta = ci.getDatasetMeta(0);
							meta.data[index].hidden = !meta.data[index].hidden;
							ci.update();
							renderInventoryLocationsByCategory(legendItem.text);
						},
					},
					tooltip: {
						callbacks: {
							label: function(ctx) {
								const value = Number(ctx.parsed || 0);
								const percent = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
								return `${ctx.label}: ${value} (${percent}%)`;
							}
						}
					}
				}
			};

			const totalEl = document.getElementById('inventory-total-items');
			if (totalEl) {
				totalEl.textContent = String(total);
			}
			const summary = json.summary_metrics || {};
			const storesEl = document.getElementById('inventory-metric-stores');
			const previstoEl = document.getElementById('inventory-metric-previsto');
			const executadoEl = document.getElementById('inventory-metric-executado');
			const diariasEl = document.getElementById('inventory-metric-diarias');
			const setupEl = document.getElementById('inventory-metric-setup');
			const rolloutEl = document.getElementById('inventory-metric-rollout');
			const formatacaoEl = document.getElementById('inventory-metric-formatacao');
			const ocorrenciasEl = document.getElementById('inventory-metric-ocorrencias');
			const pdvsEl = document.getElementById('inventory-metric-pdvs');
			const suporteInstaladoEl = document.getElementById('inventory-metric-suporte-instalado');
			const suportePendenteEl = document.getElementById('inventory-metric-suporte-pendente');
			if (storesEl) storesEl.textContent = String(Number(summary.stores || 0));
			if (previstoEl) previstoEl.textContent = String(Number(summary.previsto || 0));
			if (executadoEl) executadoEl.textContent = String(Number(summary.executado || 0));
			if (diariasEl) diariasEl.textContent = String(Number(summary.diarias_consumidas || 0));
			if (setupEl) setupEl.textContent = String(Number(summary.setup || 0));
			if (rolloutEl) rolloutEl.textContent = String(Number(summary.rollout || 0));
			if (formatacaoEl) formatacaoEl.textContent = String(Number(summary.formatacao || 0));
			if (ocorrenciasEl) ocorrenciasEl.textContent = String(Number(summary.ocorrencias || 0));
			if (pdvsEl) pdvsEl.textContent = String(Number(summary.pdvs || 0));
			if (suporteInstaladoEl) suporteInstaladoEl.textContent = String(Number(summary.suporte_instalado || 0));
			if (suportePendenteEl) suportePendenteEl.textContent = String(Number(summary.suporte_pendente || 0));
			if (total === 0 && json.debug) {
				console.warn('[Inventario] Total zerado com debug:', json.debug);
			}
			const sourceEl = document.getElementById('inventory-source-path');
			if (sourceEl) {
				sourceEl.textContent = json.source || '-';
			}
			const storeFilter = document.getElementById('inventory-filter-store');
			const supportStatusFilter = document.getElementById('inventory-filter-support-status');
			if (storeFilter) {
				const selectedBefore = storeFilter.value;
				const stores = Array.isArray(json.stores) ? json.stores : [];
				storeFilter.innerHTML = '<option value="">Todas as lojas</option>' + stores.map(store => `<option value="${store}">${store}</option>`).join('');
				if (stores.includes(selectedBefore)) {
					storeFilter.value = selectedBefore;
				}
			}
			if (supportStatusFilter && json.debug && json.debug.filters && typeof json.debug.filters.support_status !== 'undefined') {
				supportStatusFilter.value = String(json.debug.filters.support_status || '');
			}
			if (displayLabels.length > 0) {
				renderInventoryLocationsByCategory(displayLabels[0]);
			} else {
				renderInventoryLocationsByCategory('');
			}

			const ctx = canvas.getContext('2d');
			if (inventoryPieChart) {
				inventoryPieChart.data = data;
				inventoryPieChart.options = options;
				inventoryPieChart.update();
			} else {
				inventoryPieChart = new Chart(ctx, { type: 'pie', data, options });
			}
		} catch (error) {
			console.error('Erro ao carregar gráfico de inventário:', error);
			if (typeof showToast === 'function') {
				showToast(error?.message || 'Erro ao carregar inventário');
			}
		}
	}

	function renderInventoryLocationsByCategory(categoryLabel) {
		const container = document.getElementById('inventory-category-locations');
		if (!container) return;
		if (!categoryLabel) {
			container.textContent = 'Selecione uma categoria na legenda para listar as lojas.';
			return;
		}
		const categoryKey = categoryLabel === 'XPADS'
			? 'HEXAPADS'
			: (categoryLabel === 'OCORRÊNCIAS'
				? 'DEFEITO'
				: (categoryLabel === 'SUPORTE INSTALADO'
					? 'SUPORTE_INSTALADO'
					: (categoryLabel === 'SUPORTE PENDENTE' ? 'SUPORTE_PENDENTE' : categoryLabel)));
		const rows = Array.isArray(inventoryLocationsByCategory[categoryKey]) ? inventoryLocationsByCategory[categoryKey] : [];
		if (rows.length === 0) {
			container.textContent = `Sem registros para ${categoryLabel} com os filtros atuais.`;
			return;
		}
		const items = rows.slice(0, 20).map((row) => {
			const store = escapeHtml(String(row.store || '-'));
			const total = Number(row.total || 0);
			return `<li class="flex justify-between gap-3"><span>${store}</span><span class="font-semibold text-blue-900">${total}</span></li>`;
		}).join('');
		container.innerHTML = `<p class="mb-2 text-gray-800">Categoria: <span class="font-semibold text-blue-900">${escapeHtml(categoryLabel)}</span></p><ul class="space-y-1">${items}</ul>`;
	}

	async function uploadInventoryFile(file) {
		const formData = new FormData();
		formData.append('file', file);
		const res = await fetch('/dashboard/inventory-upload', {
			method: 'POST',
			body: formData,
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		});
		const json = await res.json();
		if (!json.success) {
			throw new Error(json.message || 'Falha ao importar planilha');
		}
		return json;
	}

	async function uploadPurchasedDailiesFile(file) {
		const formData = new FormData();
		formData.append('file', file);
		const res = await fetch('/dashboard/purchased-dailies-upload', {
			method: 'POST',
			body: formData,
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		});
		const json = await res.json();
		if (!json.success) {
			throw new Error(json.message || 'Falha ao importar planilha');
		}
		return json;
	}

	function purchasedDailiesRowHtml(row) {
		return `
			<tr class="hover:bg-gray-50">
				<td class="px-4 py-2">${escapeHtml(String(row.date || '-'))}</td>
				<td class="px-4 py-2">${escapeHtml(String(row.store || '-'))}</td>
				<td class="px-4 py-2">${escapeHtml(String(row.activity || '-'))}</td>
				<td class="px-4 py-2">${escapeHtml(String(row.order || '-'))}</td>
				<td class="px-4 py-2">${escapeHtml(String(row.number || '-'))}</td>
				<td class="px-4 py-2">${escapeHtml(String(row.type_label || 'Diária'))}</td>
				<td class="px-4 py-2 text-right font-semibold text-blue-900">${Number(row.quantity || 0)}</td>
				<td class="px-4 py-2">${escapeHtml(String(row.description || '-'))}</td>
			</tr>
		`;
	}

	function renderPurchasedDailiesTable(rows, options = {}) {
		const tbody = document.getElementById('purchased-dailies-table-body');
		if (!tbody) return;

		if (Array.isArray(rows)) {
			purchasedDailiesRows = rows;
			if (options.reset !== false) {
				purchasedDailiesShown = PURCHASED_DAILIES_PAGE_SIZE;
			}
		}

		if (!Array.isArray(purchasedDailiesRows) || purchasedDailiesRows.length === 0) {
			tbody.innerHTML = '<tr><td colspan="8" class="empty-state">Nenhum registro encontrado na planilha.</td></tr>';
			return;
		}

		const visibleCount = Math.min(purchasedDailiesShown, purchasedDailiesRows.length);
		const limited = purchasedDailiesRows.slice(0, visibleCount);
		tbody.innerHTML = limited.map(purchasedDailiesRowHtml).join('');

		if (purchasedDailiesRows.length > visibleCount) {
			const remaining = purchasedDailiesRows.length - visibleCount;
			const nextBatch = Math.min(remaining, PURCHASED_DAILIES_PAGE_SIZE);
			tbody.innerHTML += `
				<tr>
					<td colspan="8" class="px-4 py-3 text-center text-xs text-gray-500">
						Exibindo ${visibleCount} de ${purchasedDailiesRows.length} registros.
						<button type="button" data-purchased-dailies-show-more="page" class="ml-2 text-blue-700 font-semibold hover:underline">
							Ver mais ${nextBatch}
						</button>
						<span class="mx-1 text-gray-300">|</span>
						<button type="button" data-purchased-dailies-show-more="all" class="text-blue-700 font-semibold hover:underline">
							Ver todos
						</button>
					</td>
				</tr>
			`;
		}
	}

	function showMorePurchasedDailiesRows(mode) {
		if (!Array.isArray(purchasedDailiesRows) || purchasedDailiesRows.length === 0) return;
		if (mode === 'all') {
			purchasedDailiesShown = purchasedDailiesRows.length;
		} else {
			purchasedDailiesShown = Math.min(
				purchasedDailiesShown + PURCHASED_DAILIES_PAGE_SIZE,
				purchasedDailiesRows.length
			);
		}
		renderPurchasedDailiesTable(null, { reset: false });
	}

	function updatePurchasedDailiesSummary(summary, source) {
		const totalRowsEl = document.getElementById('purchased-dailies-total-rows');
		const dailyEl = document.getElementById('purchased-dailies-daily-total');
		const projectEl = document.getElementById('purchased-dailies-project-total');
		const grandEl = document.getElementById('purchased-dailies-grand-total');
		const sourceEl = document.getElementById('purchased-dailies-source');

		if (totalRowsEl) totalRowsEl.textContent = String(Number(summary?.total_rows || 0));
		if (dailyEl) dailyEl.textContent = String(Number(summary?.daily_purchased || 0));
		if (projectEl) projectEl.textContent = String(Number(summary?.project_purchased || 0));
		if (grandEl) grandEl.textContent = String(Number(summary?.total_purchased || 0));
		if (sourceEl) {
			if (source && source.file) {
				const activityHint = summary?.sheet_activity ? ` — Atividade: ${summary.sheet_activity}` : '';
				sourceEl.textContent = `Arquivo: ${source.file} — importado em ${source.imported_at || '-'}${activityHint}`;
			} else {
				sourceEl.textContent = 'Nenhuma planilha importada.';
			}
		}
	}

	async function loadPurchasedDailiesData() {
		const res = await fetch('/dashboard/purchased-dailies', {
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		});
		const data = await res.json();
		if (!data.success) {
			throw new Error(data.message || 'Falha ao carregar diárias compradas');
		}
		updatePurchasedDailiesSummary(data.summary || {}, data.source || null);
		renderPurchasedDailiesTable(data.rows || []);
		return data;
	}

	// Renderizar um gráfico de pizza de créditos
	function renderCreditsPie(canvasId, summary, chartInstance) {
		const canvas = document.getElementById(canvasId);
		if (!canvas) return chartInstance;
		const ctx = canvas.getContext('2d');

		const purchased = Number(summary.purchased || 0);
		const spent = Number(summary.spent || 0);
		const availableCalc = purchased - spent;
		const availableForChart = availableCalc > 0 ? availableCalc : 0;
		const deficit = availableCalc < 0 ? Math.abs(availableCalc) : 0;

		const labels = deficit > 0
			? ['Comprados', 'Consumidos', 'Disponível', 'Déficit']
			: ['Comprados', 'Consumidos', 'Disponível'];
		const values = deficit > 0
			? [purchased, spent, availableForChart, deficit]
			: [purchased, spent, availableForChart];
		const colors = deficit > 0
			? ['#1d4ed8', '#dc2626', '#059669', '#f97316']
			: ['#1d4ed8', '#dc2626', '#059669'];
		const total = values.reduce((a, b) => a + b, 0);
		const data = {
			labels: labels,
			datasets: [{
				label: 'Créditos',
				data: values,
				backgroundColor: colors,
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
						label: function(ctx) {
							const v = ctx.parsed || 0;
							const p = total > 0 ? ((v / total) * 100).toFixed(1) : 0;
							return `${ctx.label}: ${v} (${p}%)`;
						}
					}
				}
			}
		};
		if (chartInstance) {
			chartInstance.data = data;
			chartInstance.options = options;
			chartInstance.update();
			return chartInstance;
		}
		return new Chart(ctx, { type: 'doughnut', data, options });
	}

	// Carregar gráficos de pizza de créditos (globais per-user)
	async function loadCreditPieCharts() {
		try {
			// Usar consumo real (diárias/tickets usados) a partir dos chamados (somando QTD)
			const res = await fetch('/dashboard/credit-usage');
			const data = await res.json();
			if (!data.success || !data.summary) return;
			const ticket = data.summary.ticket || { purchased: 0, spent: 0, available: 0 };
			const daily = data.summary.daily || { purchased: 0, spent: 0, available: 0 };
			const project = data.summary.project_dailies || { purchased: 0, spent: 0, available: 0 };

			creditsTicketPie = renderCreditsPie('credits-ticket-pie', ticket, creditsTicketPie);
			creditsDailyPie = renderCreditsPie('credits-daily-pie', daily, creditsDailyPie);
			creditsProjectPie = renderCreditsPie('credits-project-pie', project, creditsProjectPie);
		} catch (e) {
			console.error('Erro ao carregar gráficos de créditos:', e);
		}
	}

	async function loadDashboardChartsBundle() {
		try {
			const res = await fetch('/dashboard/charts-bundle');
			const json = await res.json();
			if (!json.success) return;
			if (document.getElementById('dailies-chart')) {
				await loadDailies(json.dailies);
			}
			if (document.getElementById('status-chart')) {
				await loadStatusChart(json.status);
			}
			if (document.getElementById('daily-destination-chart')) {
				await loadDailyDestinationChart(json.daily_destinations);
			}
		} catch (e) {
			console.error('Erro ao carregar bundle de gráficos:', e);
		}
	}
