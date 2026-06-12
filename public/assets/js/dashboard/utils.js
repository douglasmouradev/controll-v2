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
