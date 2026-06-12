	// Função para escapar HTML
	function escapeHtml(text) {
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	function uiStatusBadgeClass(status) {
		const map = {
			'Fechado': 'badge badge-green',
			'Em andamento': 'badge badge-yellow',
			'Em Andamento': 'badge badge-yellow',
			'Agendado': 'badge badge-purple',
			'Aberto': 'badge badge-blue',
		};
		return map[status] || 'badge badge-gray';
	}

	function statusBadgeHtml(status) {
		return `<span class="${uiStatusBadgeClass(status)}">${escapeHtml(status)}</span>`;
	}

	function getTicketSubmitBtn() {
		return document.getElementById('ticket-form-submit');
	}

	function getUserSubmitBtn() {
		return document.getElementById('user-form-submit');
	}

	function ticketDetailHtml(t) {
		let createdDateText = '';
		let createdTimeText = '';
		if (t.created_at) {
			const formatted = convertToSaoPauloTime(t.created_at);
			createdDateText = formatted.date;
			createdTimeText = formatted.time;
		}
		let serviceDateText = '';
		if (t.service_date) {
			const rawDate = String(t.service_date).substring(0, 10);
			const parts = rawDate.split('-');
			serviceDateText = parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : rawDate;
		}
		const serviceTimeText = t.service_time ? String(t.service_time).substring(0, 5) : '';
		const projectName = (t.project_name ?? t.projectName) || '-';
		const addressLine = escapeHtml(t.address || '') + (t.address_number ? ', ' + escapeHtml(t.address_number) : '');
		const desc = escapeHtml(t.description || '').replace(/\n/g, '<br>');
		return `
			<div class="grid grid-cols-2 gap-3 text-sm">
				<div><strong>Título:</strong> ${escapeHtml(t.title || '')}</div>
				<div><strong>Prioridade:</strong> ${escapeHtml(t.priority || '')}</div>
				<div><strong>Categoria:</strong> ${escapeHtml(t.category || '')}</div>
				<div><strong>QTD:</strong> ${t.qtd != null ? escapeHtml(String(t.qtd)) : '-'}</div>
				<div class="col-span-2"><strong>Nome do Projeto:</strong> ${escapeHtml(projectName)}</div>
				<div><strong>Nome:</strong> ${escapeHtml(t.name || '')}</div>
				<div><strong>Matrícula:</strong> ${escapeHtml(t.registration || '')}</div>
				<div><strong>Unidade:</strong> ${escapeHtml(t.unit || '')}</div>
				<div><strong>CEP:</strong> ${escapeHtml(t.cep || '')}</div>
				<div><strong>Endereço:</strong> ${addressLine}</div>
				<div><strong>Cidade/UF:</strong> ${escapeHtml(t.city || '')}/${escapeHtml(t.uf || '')}</div>
				${createdDateText ? `<div><strong>Data de abertura:</strong> ${escapeHtml(createdDateText)}</div>` : ''}
				${createdTimeText ? `<div><strong>Hora de abertura:</strong> ${escapeHtml(createdTimeText)}</div>` : ''}
				${serviceDateText ? `<div><strong>Data para atendimento:</strong> ${escapeHtml(serviceDateText)}</div>` : ''}
				${serviceTimeText ? `<div><strong>Hora para atendimento:</strong> ${escapeHtml(serviceTimeText)}</div>` : ''}
				${t.technician_name ? `<div><strong>Técnico:</strong> ${escapeHtml(t.technician_name)}</div>` : ''}
				${t.technician_rg ? `<div><strong>RG Técnico:</strong> ${escapeHtml(t.technician_rg)}</div>` : ''}
				${t.technician_cpf ? `<div><strong>CPF Técnico:</strong> ${escapeHtml(t.technician_cpf)}</div>` : ''}
				${t.internal_order ? `<div><strong>Pedido:</strong> ${escapeHtml(t.internal_order)}</div>` : ''}
				${t.invoice ? `<div><strong>NF:</strong> ${escapeHtml(t.invoice)}</div>` : ''}
				${t.external_ticket ? `<div><strong>Ticket Externo:</strong> ${escapeHtml(t.external_ticket)}</div>` : ''}
				<div class="col-span-2"><strong>Descrição:</strong><br>${desc}</div>
				${t.support_response ? `<div class="col-span-2 mt-3 p-3 bg-blue-50 rounded"><strong>Resposta do Suporte:</strong><br>${escapeHtml(t.support_response).replace(/\n/g, '<br>')}</div>` : ''}
			</div>
			<div id="attachments-container" class="mt-4"></div>
		`;
	}

	let storeAddressesCache = null;
	async function loadStoreAddresses() {
		if (storeAddressesCache) return storeAddressesCache;
		try {
			const res = await fetch('/dashboard/enderecos', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
			const data = await res.json();
			if (!data.success || !Array.isArray(data.data)) return [];
			storeAddressesCache = data.data;
			return storeAddressesCache;
		} catch (e) {
			return [];
		}
	}

	function extractAddressParts(raw) {
		if (!raw) return { cep: '', address: '', city: '', uf: '' };
		const text = String(raw).trim();
		let cep = '';
		const cepMatches = text.match(/\d{2,5}[.\s-]*\d{3}[.\s-]*\d{3}/g);
		if (cepMatches && cepMatches.length > 0) {
			cep = cepMatches[cepMatches.length - 1].replace(/\D/g, '');
			if (cep.length === 8) cep = cep.slice(0, 5) + '-' + cep.slice(5);
		}
		let city = '';
		let uf = '';
		const m = text.match(/([A-Za-zÀ-ÿ\s]+?)\s*[-]\s*([A-Z]{2})(?:\s|$)/);
		if (m) {
			city = m[1].trim();
			uf = m[2].trim();
		}
		let address = text;
		if (m && typeof m.index === 'number') address = text.substring(0, m.index).trim();
		else address = text.replace(/\s*CEP\s*\d{2,5}[.\s-]*\d{3}[.\s-]*\d{3}\s*$/i, '').trim();
		address = address.replace(/^Endereco:\s*/i, '').replace(/[,-]\s*$/, '').trim();
		return { cep, address, city, uf };
	}

	function openNewTicketModal() {
		const modalAbrir = document.getElementById('modal-abrir-chamado');
		if (!modalAbrir) return;
		const form = document.getElementById('new-ticket-form');
		if (form) {
			form.reset();
			const ticketIdInput = document.getElementById('ticket_id');
			if (ticketIdInput) ticketIdInput.value = '';
			const originalQtdInput = document.getElementById('original_qtd');
			if (originalQtdInput) originalQtdInput.value = '0';
			const submitBtn = getTicketSubmitBtn();
			if (submitBtn) {
				submitBtn.disabled = false;
				submitBtn.textContent = 'Abrir chamado';
			}
			const existingAtt = document.getElementById('ticket-existing-attachments');
			if (existingAtt) existingAtt.innerHTML = '';
			const attList = document.getElementById('ticket-attachments-list');
			if (attList) attList.innerHTML = '';
			const titleEl = modalAbrir.querySelector('h2');
			if (titleEl) titleEl.textContent = 'Abrir chamado';
		}
		modalAbrir.showModal();
	}

	document.addEventListener('input', async (e) => {
		if (e.target.name !== 'unit') return;
		setTimeout(async () => {
			const sigla = e.target.value.trim().toUpperCase();
			if (sigla.length < 2) return;
			const list = await loadStoreAddresses();
			const found = list.find(item => (item.sigla || '').toUpperCase() === sigla);
			if (!found) return;
			const parts = extractAddressParts(found.endereco || found.ENDERECO || '');
			const cepEl = document.getElementById('cep');
			const addrEl = document.getElementById('address');
			const cityEl = document.getElementById('city');
			const ufEl = document.getElementById('uf');
			if (cepEl && parts.cep) cepEl.value = parts.cep;
			if (addrEl && parts.address) addrEl.value = parts.address;
			if (cityEl && parts.city) cityEl.value = parts.city;
			if (ufEl && parts.uf) ufEl.value = parts.uf;
		}, 10);
	});

	document.addEventListener('blur', async (e) => {
		if (e.target.id !== 'cep') return;
		const digits = String(e.target.value || '').replace(/\D/g, '');
		if (digits.length !== 8) return;
		try {
			const res = await fetch('https://viacep.com.br/ws/' + digits + '/json/');
			const data = await res.json();
			if (data.erro) return;
			const addrEl = document.getElementById('address');
			const cityEl = document.getElementById('city');
			const ufEl = document.getElementById('uf');
			if (addrEl && data.logradouro) addrEl.value = data.logradouro;
			if (cityEl && data.localidade) cityEl.value = data.localidade;
			if (ufEl && data.uf) ufEl.value = data.uf;
		} catch (_) {}
	}, true);

	// Função para converter data/hora UTC para fuso horário de Brasília
	function convertToSaoPauloTime(dateTimeStr) {
		if (!dateTimeStr) {
			return { date: '', time: '' };
		}
		const str = String(dateTimeStr).trim();
		if (!str) {
			return { date: '', time: '' };
		}
		
		const parts = str.split(' ');
		const datePart = parts[0] || '';
		const timePartRaw = parts[1] || '';
		
		if (!datePart) {
			return { date: '', time: timePartRaw.substring(0, 5) };
		}
		
		const dParts = datePart.split('-');
		if (dParts.length !== 3) {
			return { date: datePart, time: timePartRaw.substring(0, 5) };
		}
		
		const year = parseInt(dParts[0] || '0', 10);
		const month = parseInt(dParts[1] || '1', 10);
		const day = parseInt(dParts[2] || '1', 10);
		
		let hour = 0;
		let minute = 0;
		let second = 0;
		
		if (timePartRaw) {
			const tParts = timePartRaw.split(':');
			if (tParts.length > 0) hour = parseInt(tParts[0] || '0', 10);
			if (tParts.length > 1) minute = parseInt(tParts[1] || '0', 10);
			if (tParts.length > 2) second = parseInt(tParts[2] || '0', 10);
		}
		
		const utcDate = new Date(Date.UTC(year, (month || 1) - 1, day || 1, hour || 0, minute || 0, second || 0));
		
		try {
			const formatter = new Intl.DateTimeFormat('pt-BR', {
				timeZone: 'America/Sao_Paulo',
				year: 'numeric',
				month: '2-digit',
				day: '2-digit',
				hour: '2-digit',
				minute: '2-digit',
				hour12: false
			});
			
			const fParts = formatter.formatToParts(utcDate);
			let dd = '';
			let mmStr = '';
			let yyyy = '';
			let hhStr = '';
			let minStr = '';
			
			for (const p of fParts) {
				if (p.type === 'day') dd = p.value;
				else if (p.type === 'month') mmStr = p.value;
				else if (p.type === 'year') yyyy = p.value;
				else if (p.type === 'hour') hhStr = p.value;
				else if (p.type === 'minute') minStr = p.value;
			}
			
			return {
				date: dd && mmStr && yyyy ? `${dd}/${mmStr}/${yyyy}` : '',
				time: hhStr && minStr ? `${hhStr}:${minStr}` : ''
			};
		} catch (e) {
			const fallbackDate = `${dParts[2]}/${dParts[1]}/${dParts[0]}`;
			const fallbackTime = timePartRaw.substring(0, 5);
			return { date: fallbackDate, time: fallbackTime };
		}
	}

	function startVisibilityAwareInterval(fn, ms) {
		return setInterval(() => {
			if (document.visibilityState === 'hidden') {
				return;
			}
			fn();
		}, ms);
	}

	function uiPriorityBadgeClass(priority) {
		const map = {
			'Alta': 'badge badge-red',
			'Média': 'badge badge-yellow',
			'Media': 'badge badge-yellow',
			'Baixa': 'badge badge-green',
		};
		return map[priority] || 'badge badge-gray';
	}

	function formatDashboardDate(value) {
		if (!value) return '-';
		const raw = String(value).substring(0, 10);
		const parts = raw.split('-');
		if (parts.length === 3) {
			return `${parts[2]}/${parts[1]}/${parts[0]}`;
		}
		return raw;
	}

	function renderOpenTicketRow(t, meta) {
		const isSupport = !!meta.is_support;
		const currentUserId = Number(meta.current_user_id || 0);
		const canEdit = Number(t.user_id || 0) === currentUserId || isSupport;
		const serviceTime = t.service_time ? String(t.service_time).substring(0, 5) : '';
		const serviceLabel = t.service_date
			? `${formatDashboardDate(t.service_date)}${serviceTime ? ' ' + serviceTime : ''}`
			: '-';
		let actions = `<button type="button" class="btn-link btn-view">Ver</button>`;
		if (isSupport) {
			actions += `<button type="button" class="btn-link ml-2 btn-clone-ticket" data-id="${Number(t.id)}">Clonar</button>`;
		}
		if (canEdit) {
			actions += `<button type="button" class="btn-link ml-2 btn-edit-ticket">Editar</button>`;
		}
		if (isSupport) {
			actions += `<button type="button" class="btn-link ml-2 btn-assign">Atribuir</button>`;
			actions += `<button type="button" class="btn-link danger btn-delete-ticket ml-2">Excluir</button>`;
		}
		return `
			<tr data-id="${Number(t.id)}">
				<td class="px-3 py-2">${Number(t.id)}</td>
				<td class="px-3 py-2">${escapeHtml(t.title || '')}</td>
				<td class="px-3 py-2">${escapeHtml(String(t.category || ''))}</td>
				<td class="px-3 py-2">${escapeHtml(t.user_name || '-')}</td>
				<td class="px-3 py-2">${escapeHtml(String(t.registration || ''))}</td>
				<td class="px-3 py-2">${escapeHtml(String(t.unit || ''))}</td>
				<td class="px-3 py-2">${escapeHtml(String(t.address || ''))}</td>
				<td class="px-3 py-2">${escapeHtml(String(t.address_number || ''))}</td>
				<td class="px-3 py-2">${escapeHtml(String(t.city || ''))}/${escapeHtml(String(t.uf || ''))}</td>
				<td class="px-3 py-2 status-cell">${statusBadgeHtml(t.status || '')}</td>
				<td class="px-3 py-2"><span class="${uiPriorityBadgeClass(t.priority || '')}">${escapeHtml(String(t.priority || ''))}</span></td>
				<td class="px-3 py-2 assign-cell">${escapeHtml(t.assigned_name || '-')}</td>
				<td class="px-3 py-2">${formatDashboardDate(t.created_at)}</td>
				<td class="px-3 py-2">${escapeHtml(serviceLabel)}</td>
				<td class="px-3 py-2 whitespace-nowrap">${actions}</td>
			</tr>
		`;
	}

	async function refreshOpenTicketsTable() {
		const tbody = document.getElementById('tickets-tbody');
		if (!tbody) return;
		const params = new URLSearchParams(window.location.search);
		const query = new URLSearchParams();
		['id', 'status', 'priority', 'user', 'sigla', 'cidade', 'estado', 'page'].forEach((key) => {
			if (params.has(key) && params.get(key)) {
				query.set(key, params.get(key));
			}
		});
		try {
			const res = await fetch('/dashboard/tickets-open?' + query.toString(), {
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
			});
			const data = await res.json();
			if (!data.success) return;
			const tickets = Array.isArray(data.tickets) ? data.tickets : [];
			if (tickets.length === 0) {
				tbody.innerHTML = '<tr><td colspan="15" class="empty-state">Nenhum chamado encontrado.</td></tr>';
				return;
			}
			const meta = {
				is_support: !!data.is_support,
				current_user_id: data.current_user_id,
			};
			tbody.innerHTML = tickets.map((t) => renderOpenTicketRow(t, meta)).join('');
		} catch (error) {
			console.error('Erro ao atualizar tabela de chamados:', error);
		}
	}

	function renderUserRow(u, options) {
		const isAdmin = !!options.isAdmin;
		const currentUserId = Number(options.currentUserId || 0);
		const createdAt = u.created_at ? formatDashboardDate(String(u.created_at).replace(' ', 'T')) : '-';
		let actions = '<button type="button" class="btn-link btn-edit-user">Editar</button>';
		if (isAdmin && Number(u.id) !== currentUserId) {
			actions += '<button type="button" class="btn-link danger btn-delete-user ml-2">Excluir</button>';
		}
		if (isAdmin) {
			actions += `<button type="button" class="btn-link muted btn-view-credit-history ml-2" data-user-id="${Number(u.id)}" data-user-name="${escapeHtml(u.name || '')}">Histórico</button>`;
		}
		return `
			<tr data-id="${Number(u.id)}">
				<td>${Number(u.id)}</td>
				<td class="font-medium text-slate-800">${escapeHtml(u.name || '')}</td>
				<td>${escapeHtml(u.email || '')}</td>
				<td><span class="badge badge-gray">${escapeHtml(u.role || '')}</span></td>
				<td class="hide-mobile credits-ticket-cell">${Number(u.credits || 0)}</td>
				<td class="hide-mobile credits-daily-cell">${Number(u.daily_credits || 0)}</td>
				<td class="hide-mobile credits-project-dailies-cell">${Number(u.project_dailies_credits || 0)}</td>
				<td>${escapeHtml(createdAt)}</td>
				<td class="whitespace-nowrap">${actions}</td>
			</tr>
		`;
	}

	async function refreshUsersTable(page) {
		const tbody = document.getElementById('users-tbody');
		if (!tbody) return;
		const targetPage = page || Number(document.getElementById('users-tbody')?.dataset.page || 1);
		try {
			const res = await fetch('/users?page=' + targetPage + '&per_page=50', {
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
			});
			const data = await res.json();
			if (!data.success) return;
			const users = Array.isArray(data.users) ? data.users : [];
			const tab = document.getElementById('tab-usuarios');
			const isAdmin = tab?.dataset.isAdmin === '1';
			const currentUserId = Number(tab?.dataset.currentUserId || 0);
			if (users.length === 0) {
				tbody.innerHTML = '<tr><td colspan="9" class="empty-state">Nenhum usuário encontrado.</td></tr>';
			} else {
				tbody.innerHTML = users.map((u) => renderUserRow(u, { isAdmin, currentUserId })).join('');
			}
			tbody.dataset.page = String(data.pagination?.page || targetPage);
			const paginationEl = document.getElementById('users-pagination');
			if (paginationEl && data.pagination) {
				const p = data.pagination;
				paginationEl.innerHTML = `
					<p>Página <strong>${p.page}</strong> de <strong>${p.pages}</strong> — ${p.total} usuário(s)</p>
					<div class="flex items-center gap-2">
						<button type="button" class="btn btn-secondary btn-sm" data-users-page="${Math.max(1, p.page - 1)}" ${p.page <= 1 ? 'disabled' : ''}>Anterior</button>
						<button type="button" class="btn btn-secondary btn-sm" data-users-page="${Math.min(p.pages, p.page + 1)}" ${p.page >= p.pages ? 'disabled' : ''}>Próxima</button>
					</div>
				`;
			}
		} catch (error) {
			console.error('Erro ao atualizar tabela de usuários:', error);
		}
	}

	async function refreshDashboardAfterMutation() {
		const tasks = [];
		if (typeof loadDashboardChartsBundle === 'function') {
			tasks.push(loadDashboardChartsBundle());
		}
		if (typeof loadCreditSummaries === 'function') {
			tasks.push(loadCreditSummaries());
		}
		if (typeof loadCreditPieCharts === 'function') {
			tasks.push(loadCreditPieCharts());
		}
		tasks.push(refreshOpenTicketsTable());
		await Promise.allSettled(tasks);
	}
