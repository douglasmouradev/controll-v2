<script>
	console.log('components/scripts.php carregado');
	// Função para escapar HTML
	function escapeHtml(text) {
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	let dailiesChart, statusChart, creditsTicketPie, creditsDailyPie, creditsProjectPie;
	
	async function loadDailies() {
		try {
			const res = await fetch('/dashboard/dailies');
			const json = await res.json();
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
					label: 'Chamados',
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
								return `Chamados: ${ctx.parsed.y}`;
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

	async function loadStatusChart() {
		try {
			const res = await fetch('/dashboard/status-stats');
			const json = await res.json();
			if (!json.success) throw new Error('Erro ao buscar dados');
			
			const ctx = document.getElementById('status-chart').getContext('2d');
			const colors = {
				'Aberto': '#f59e0b',
				'Em Andamento': '#3b82f6',
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

	// Renderizar um gráfico de pizza de créditos
	function renderCreditsPie(canvasId, summary, chartInstance) {
		const canvas = document.getElementById(canvasId);
		if (!canvas) return chartInstance;
		const ctx = canvas.getContext('2d');
		const values = [
			Math.max(0, summary.purchased || 0),
			Math.max(0, summary.spent || 0),
			Math.max(0, summary.available || 0)
		];
		const total = values.reduce((a, b) => a + b, 0);
		const data = {
			labels: ['Comprados', 'Consumidos', 'Disponível'],
			datasets: [{
				label: 'Créditos',
				data: values,
				backgroundColor: ['#1d4ed8', '#dc2626', '#059669'],
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
			const res = await fetch('/users/credit-history?id=0');
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

	// Modal Abrir Chamado - usando delegação de eventos (fora de DOMContentLoaded)
	document.addEventListener('click', (e) => {
		if (e.target.id === 'btn-abrir-chamado') {
			const modalAbrir = document.getElementById('modal-abrir-chamado');
			if (modalAbrir) {
				modalAbrir.showModal();
			}
		}
		if (e.target.id === 'cancelar-chamado') {
			const modalAbrir = document.getElementById('modal-abrir-chamado');
			if (modalAbrir) {
				modalAbrir.close();
			}
		}
		// Botões de Créditos Globais - Painel Operacional
		if (e.target.id === 'btn-global-credits-ticket') {
			openGlobalCreditsModal('ticket');
		}
		if (e.target.id === 'btn-global-credits-daily') {
			openGlobalCreditsModal('daily');
		}
		if (e.target.id === 'btn-global-credits-project') {
			openGlobalCreditsModal('project_dailies');
		}
		// Botões de Créditos Globais - Gerenciamento de Usuários
		if (e.target.id === 'btn-global-credits-ticket-users') {
			openGlobalCreditsModal('ticket');
		}
		if (e.target.id === 'btn-global-credits-daily-users') {
			openGlobalCreditsModal('daily');
		}
		if (e.target.id === 'btn-global-credits-project-users') {
			openGlobalCreditsModal('project_dailies');
		}
	});

	// Função para abrir modal de créditos globais
	function openGlobalCreditsModal(type) {
		const modalCredits = document.getElementById('modal-credits');
		if (!modalCredits) return;

		const creditsUserNameEl = document.getElementById('credits-modal-user-name');
		const creditsCurrentEl = document.getElementById('credits-current');
		const creditsDeltaInput = document.getElementById('credits-delta');
		const creditsUserIdInput = document.getElementById('credits-user-id');
		const creditsTypeInput = document.getElementById('credits-type');
		const creditsTypeLabelEl = document.getElementById('credits-type-label');
		const creditsPreviewEl = document.getElementById('credits-preview');

		if (!creditsUserNameEl || !creditsCurrentEl || !creditsDeltaInput || !creditsUserIdInput) {
			return;
		}

		// Usar ID 0 para indicar ajuste global (todos os usuários)
		creditsUserIdInput.value = '0';
		creditsDeltaInput.value = '0';
		creditsTypeInput.value = type;

		// Atualizar labels
		creditsUserNameEl.textContent = 'Todos os Usuários (Global)';

		// Buscar o saldo atual de um usuário do tipo 'user' da tabela
		let currentBalance = 0;
		const table = document.querySelector('#users-tbody');
		if (table) {
			const rows = table.querySelectorAll('tr');
			for (let row of rows) {
				const perfil = row.querySelector('td:nth-child(4)')?.textContent?.trim();
				if (perfil === 'user') {
					// Encontrou um usuário do tipo 'user', pegar o saldo do tipo correto
					let cellIndex = 5; // Crédito Ticket por padrão
					if (type === 'daily') {
						cellIndex = 6; // Crédito Diária
					} else if (type === 'project_dailies') {
						cellIndex = 7; // Crédito Projeto
					}
					const cell = row.querySelector(`td:nth-child(${cellIndex})`);
					if (cell) {
						currentBalance = parseInt(cell.textContent?.trim() || '0', 10);
					}
					break;
				}
			}
		}

		// Atualizar o valor global creditsCurrentValue para ser usado em updateCreditsPreview()
		creditsCurrentValue = currentBalance;
		currentCreditsType = type;

		creditsCurrentEl.textContent = currentBalance;
		creditsPreviewEl.textContent = currentBalance;

		if (creditsTypeLabelEl) {
			if (type === 'daily') {
				creditsTypeLabelEl.textContent = 'Ajustando Créditos de Diária para TODOS os usuários';
			} else if (type === 'project_dailies') {
				creditsTypeLabelEl.textContent = 'Ajustando Créditos de Diárias Projeto para TODOS os usuários';
			} else {
				creditsTypeLabelEl.textContent = 'Ajustando Créditos de Ticket para TODOS os usuários';
			}
		}

		modalCredits.showModal();
	}

	document.addEventListener('DOMContentLoaded', function() {
		// Inicializar gráficos
		if (document.getElementById('dailies-chart')) {
			loadDailies();
			setInterval(loadDailies, 10000);
		}
		if (document.getElementById('status-chart')) {
			loadStatusChart();
			setInterval(loadStatusChart, 10000);
		}

		// Gráficos de pizza de créditos (globais per-user)
		if (
			document.getElementById('credits-ticket-pie') ||
			document.getElementById('credits-daily-pie') ||
			document.getElementById('credits-project-pie')
		) {
			loadCreditPieCharts();
		}

		// Sidebar Menu - Tabs
		document.querySelectorAll('.sidebar-menu-item').forEach(function(btn) {
			btn.addEventListener('click', function() {
				var tab = btn.dataset.tab;
				
				// Atualizar ativo no sidebar
				document.querySelectorAll('.sidebar-menu-item').forEach(function(b) {
					b.classList.remove('active', 'bg-blue-800', 'border-blue-400');
					b.classList.add('border-transparent');
				});
				btn.classList.add('active', 'bg-blue-800', 'border-blue-400');
				btn.classList.remove('border-transparent');
				
				// Mostrar/ocultar conteúdo
				document.querySelectorAll('.tab-content').forEach(function(c) {
					c.classList.add('hidden');
				});
				var target = document.getElementById('tab-' + tab);
				if (target) {
					target.classList.remove('hidden');
				}
			});
		});

		// Filtros
		document.addEventListener('click', (e) => {
			if (e.target.id === 'f-apply') {
				const params = new URLSearchParams();
				const id = document.getElementById('f-id')?.value || '';
				const s = document.getElementById('f-status').value;
				const p = document.getElementById('f-priority').value;
				const u = document.getElementById('f-user')?.value || '';
				if (id) params.set('id', id);
				if (s) params.set('status', s);
				if (p) params.set('priority', p);
				if (u) params.set('user', u);
				location.href = '/?' + params.toString();
			}
		});

		// Formulário de novo chamado
		const formEl = document.getElementById('new-ticket-form');
		if (formEl) {
			formEl.addEventListener('submit', async (e) => {
				e.preventDefault();
				const form = e.target;
				const submitBtn = form.querySelector('button[type="submit"]');
				if (submitBtn) {
					submitBtn.disabled = true;
					submitBtn.textContent = 'Abrindo...';
				}
				const fd = new FormData(form);
				try {
					const res = await fetch('/tickets/create', { 
						method: 'POST', 
						body: fd,
						headers: { 'X-Requested-With': 'XMLHttpRequest' }
					});
					const contentType = res.headers.get('content-type');
					let data;
					if (contentType && contentType.includes('application/json')) {
						data = await res.json();
					} else {
						const text = await res.text();
						console.error('Resposta não é JSON:', text.substring(0, 500));
						throw new Error(`Servidor retornou ${contentType || 'texto'} em vez de JSON.`);
					}
					if (data && data.success) {
						showToast('Chamado aberto');
						form.reset();
						modalAbrir.close();
						setTimeout(() => location.reload(), 500);
					} else {
						// Exibir mensagem retornada pela API (ex: Saldo de créditos insuficiente)
						const msg = data && data.message ? data.message : `Erro ao abrir chamado${res.ok ? '' : ` (HTTP ${res.status})`}`;
						showToast(msg);
						if (submitBtn) {
							submitBtn.disabled = false;
							submitBtn.textContent = 'Abrir Chamado';
						}
					}
				} catch (error) {
					console.error('Erro ao criar chamado:', error);
					showToast('Erro ao conectar com o servidor');
					if (submitBtn) {
						submitBtn.disabled = false;
						submitBtn.textContent = 'Abrir Chamado';
					}
				}
			});
		}

		// Modal de detalhes
		const modal = document.getElementById('ticket-modal');
		const modalBody = document.getElementById('ticket-modal-body');

		document.querySelectorAll('.btn-view').forEach(btn => {
			btn.addEventListener('click', async (e) => {
				const tr = e.target.closest('tr');
				const id = tr.dataset.id;
				const res = await fetch('/tickets/view?id=' + id);
				const data = await res.json();
				if (!data.success) { showToast('Erro ao carregar chamado'); return; }
				const t = data.ticket;
				modalBody.innerHTML = `
					<div class="grid grid-cols-2 gap-3 text-sm">
						<div><strong>Título:</strong> ${t.title}</div>
						<div><strong>Prioridade:</strong> ${t.priority}</div>
						<div><strong>Categoria:</strong> ${t.category}</div>
						<div><strong>Nome:</strong> ${t.name}</div>
						<div><strong>Matrícula:</strong> ${t.registration}</div>
						<div><strong>Unidade:</strong> ${t.unit}</div>
						<div><strong>CEP:</strong> ${t.cep}</div>
						<div><strong>Endereço:</strong> ${t.address}${t.address_number ? ', ' + t.address_number : ''}</div>
						<div><strong>Cidade/UF:</strong> ${t.city}/${t.uf}</div>
						${t.internal_order ? `<div><strong>Pedido:</strong> ${t.internal_order}</div>` : ''}
						${t.invoice ? `<div><strong>NF:</strong> ${t.invoice}</div>` : ''}
						${t.external_ticket ? `<div><strong>Ticket Externo:</strong> ${t.external_ticket}</div>` : ''}
						<div class="col-span-2"><strong>Descrição:</strong><br>${t.description}</div>
						${t.support_response ? `<div class="col-span-2 mt-3 p-3 bg-blue-50 rounded"><strong>Resposta do Suporte:</strong><br>${escapeHtml(t.support_response).replace(/\n/g, '<br>')}</div>` : ''}
					</div>
					<div id="attachments-container" class="mt-4"></div>
				`;
				// Preencher campo de resposta se existir
				const responseField = document.getElementById('support-response');
				if (responseField) {
					responseField.value = t.support_response || '';
				}
				modal.showModal();
				modal.querySelectorAll('.status-btn').forEach(b => {
					b.onclick = async () => {
						const status = b.dataset.status;
						const fd = new FormData();
						fd.set('id', id);
						fd.set('status', status);
						const r2 = await fetch('/tickets/status', { method: 'POST', body: fd });
						const j2 = await r2.json();
						if (j2.success) {
							document.querySelector(`tr[data-id="${id}"] .status-cell`).innerHTML = `<span class="px-2 py-1 rounded text-xs bg-blue-100 text-blue-800">${status}</span>`;
							showToast('Status atualizado');
						} else {
							showToast('Falha ao atualizar');
						}
					};
				});

				// Preview de imagens
				const imageInput = document.getElementById('support-images');
				const imagePreview = document.getElementById('image-preview');
				
				if (imageInput && imagePreview) {
					imageInput.addEventListener('change', (e) => {
						imagePreview.innerHTML = '';
						const files = Array.from(e.target.files);
						
						files.forEach((file) => {
							if (file.type.startsWith('image/')) {
								const reader = new FileReader();
								reader.onload = (event) => {
									const div = document.createElement('div');
									div.className = 'relative';
									div.innerHTML = `
										<img src="${event.target.result}" class="w-full h-24 object-cover rounded border">
										<span class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-50 text-white text-xs p-1 truncate">${escapeHtml(file.name)}</span>
									`;
									imagePreview.appendChild(div);
								};
								reader.readAsDataURL(file);
							}
						});
					});
				}

				// Salvar resposta do suporte
				const saveResponseBtn = document.getElementById('btn-save-response');
				if (saveResponseBtn) {
					// Remover listeners anteriores
					const newBtn = saveResponseBtn.cloneNode(true);
					saveResponseBtn.parentNode.replaceChild(newBtn, saveResponseBtn);
					
					newBtn.addEventListener('click', async () => {
						const responseText = document.getElementById('support-response').value;
						const fd = new FormData();
						fd.set('id', id);
						fd.set('response', responseText);
						
						// Adicionar imagens
						const currentImageInput = document.getElementById('support-images');
						if (currentImageInput && currentImageInput.files.length > 0) {
							Array.from(currentImageInput.files).forEach((file) => {
								fd.append('images[]', file);
							});
						}
						
						try {
							const res = await fetch('/tickets/response', { 
								method: 'POST', 
								body: fd,
								headers: { 'X-Requested-With': 'XMLHttpRequest' }
							});
							const data = await res.json();
							
							if (data.success) {
								showToast('Resposta salva com sucesso');
								// Limpar preview
								const currentPreview = document.getElementById('image-preview');
								const currentInput = document.getElementById('support-images');
								if (currentPreview) currentPreview.innerHTML = '';
								if (currentInput) currentInput.value = '';
								selectedImages = [];
								
								// Atualizar exibição da resposta no modal
								const grid = modalBody.querySelector('.grid');
								if (grid) {
									let responseDiv = grid.querySelector('.bg-blue-50');
									if (responseText) {
										if (responseDiv) {
											responseDiv.innerHTML = `<strong>Resposta do Suporte:</strong><br>${escapeHtml(responseText).replace(/\n/g, '<br>')}`;
										} else {
											const newDiv = document.createElement('div');
											newDiv.className = 'col-span-2 mt-3 p-3 bg-blue-50 rounded';
											newDiv.innerHTML = `<strong>Resposta do Suporte:</strong><br>${escapeHtml(responseText).replace(/\n/g, '<br>')}`;
											grid.appendChild(newDiv);
										}
									} else if (responseDiv) {
										responseDiv.remove();
									}
								}
								// Recarregar anexos
								loadAttachments(id);
							} else {
								showToast(data.message || 'Erro ao salvar resposta');
							}
						} catch (error) {
							console.error('Erro:', error);
							showToast('Erro ao conectar com o servidor');
						}
					});
				}

				// Carregar anexos existentes
				loadAttachments(id);
			});
		});

		document.querySelectorAll('.btn-assign').forEach(btn => {
			btn.addEventListener('click', async (e) => {
				const tr = e.target.closest('tr');
				const id = tr.dataset.id;
				const fd = new FormData();
				fd.set('id', id);
				const res = await fetch('/tickets/assign', { method: 'POST', body: fd });
				const data = await res.json();
				if (data.success) {
					tr.querySelector('.assign-cell').textContent = 'Você';
					tr.querySelector('.status-cell').innerHTML = `<span class="px-2 py-1 rounded text-xs bg-yellow-100 text-yellow-800">Em andamento</span>`;
					showToast('Chamado atribuído');
				} else {
					showToast('Falha ao atribuir');
				}
			});
		});

		document.getElementById('modal-close')?.addEventListener('click', () => modal.close());

		// Gerenciamento de Usuários
		const modalUsuario = document.getElementById('modal-usuario');
		const userForm = document.getElementById('user-form');
		const modalCredits = document.getElementById('modal-credits');
		const creditsUserNameEl = document.getElementById('credits-modal-user-name');
		const creditsCurrentEl = document.getElementById('credits-current');
		const creditsPreviewEl = document.getElementById('credits-preview');
		const creditsDeltaInput = document.getElementById('credits-delta');
		const creditsUserIdInput = document.getElementById('credits-user-id');
		const creditsTypeInput = document.getElementById('credits-type');
		const creditsTypeLabelEl = document.getElementById('credits-type-label');
		const creditsMinusBtn = document.getElementById('credits-minus');
		const creditsPlusBtn = document.getElementById('credits-plus');
		const creditsApplyBtn = document.getElementById('credits-apply');
		const creditsCancelBtn = document.getElementById('credits-cancel');
		let editingUserId = null;
		let creditsCurrentValue = 0;
		let creditsCurrentRow = null;
		let currentCreditsType = 'ticket';

		// Botão Criar Usuário
		document.getElementById('btn-criar-usuario')?.addEventListener('click', () => {
			editingUserId = null;
			document.getElementById('modal-usuario-title').textContent = 'Criar Usuário';
			document.getElementById('user-id').value = '';
			document.getElementById('user-name').value = '';
			document.getElementById('user-email').value = '';
			document.getElementById('user-password').value = '';
			document.getElementById('user-password').required = true;
			document.getElementById('password-hint').classList.add('hidden');
			document.getElementById('user-role').value = 'usuario';
			modalUsuario.showModal();
		});

		// Botão Cancelar
		document.getElementById('cancelar-usuario')?.addEventListener('click', () => {
			modalUsuario.close();
			userForm.reset();
			editingUserId = null;
		});

		// Formulário de Usuário
		userForm?.addEventListener('submit', async (e) => {
			e.preventDefault();
			
			// Validar senha apenas ao criar
			if (!editingUserId) {
				const password = document.getElementById('user-password').value;
				if (!password) {
					showToast('Senha é obrigatória ao criar usuário');
					return;
				}
			}
			
			const formData = new FormData(userForm);
			// Se estiver editando e senha estiver vazia, não enviar
			if (editingUserId && !formData.get('password')) {
				formData.delete('password');
			}
			
			const submitBtn = userForm.querySelector('button[type="submit"]');
			
			if (submitBtn) {
				submitBtn.disabled = true;
				submitBtn.textContent = 'Salvando...';
			}

			try {
				const url = editingUserId ? '/users/update' : '/users/create';
				const res = await fetch(url, {
					method: 'POST',
					body: formData,
					headers: { 'X-Requested-With': 'XMLHttpRequest' }
				});
				const data = await res.json();
				
				if (data.success) {
					showToast(data.message || 'Usuário salvo com sucesso');
					modalUsuario.close();
					userForm.reset();
					editingUserId = null;
					setTimeout(() => location.reload(), 500);
				} else {
					showToast(data.message || 'Erro ao salvar usuário');
					if (submitBtn) {
						submitBtn.disabled = false;
						submitBtn.textContent = 'Salvar';
					}
				}
			} catch (error) {
				console.error('Erro:', error);
				showToast('Erro ao conectar com o servidor');
				if (submitBtn) {
					submitBtn.disabled = false;
					submitBtn.textContent = 'Salvar';
				}
			}
		});

		// Botão Editar
		document.querySelectorAll('.btn-edit-user').forEach(btn => {
			btn.addEventListener('click', async (e) => {
				const tr = e.target.closest('tr');
				const id = tr.dataset.id;
				const res = await fetch('/users?id=' + id);
				const data = await res.json();
				
				if (data.success && data.users && data.users.length > 0) {
					const u = data.users[0];
					editingUserId = id;
					document.getElementById('modal-usuario-title').textContent = 'Editar Usuário';
					document.getElementById('user-id').value = u.id;
					document.getElementById('user-name').value = u.name;
					document.getElementById('user-email').value = u.email;
					document.getElementById('user-password').value = '';
					document.getElementById('user-password').required = false;
					document.getElementById('password-hint').classList.remove('hidden');
					document.getElementById('user-role').value = u.role;
					modalUsuario.showModal();
				} else {
					showToast('Erro ao carregar usuário');
				}
			});
		});

		// Botão Excluir
		document.querySelectorAll('.btn-delete-user').forEach(btn => {
			btn.addEventListener('click', async (e) => {
				const tr = e.target.closest('tr');
				const id = tr.dataset.id;
				const name = tr.querySelector('td:nth-child(2)').textContent;
				
				if (!confirm(`Tem certeza que deseja excluir o usuário "${name}"?`)) {
					return;
				}

				const formData = new FormData();
				formData.set('id', id);
				
				try {
					const res = await fetch('/users/delete', {
						method: 'POST',
						body: formData,
						headers: { 'X-Requested-With': 'XMLHttpRequest' }
					});
					const data = await res.json();
					
					if (data.success) {
						showToast('Usuário excluído com sucesso');
						setTimeout(() => location.reload(), 500);
					} else {
						showToast(data.message || 'Erro ao excluir usuário');
					}
				} catch (error) {
					console.error('Erro:', error);
					showToast('Erro ao conectar com o servidor');
				}
			});
		});

		function updateCreditsPreview() {
			if (!creditsPreviewEl || !creditsDeltaInput) {
				return;
			}
			const delta = parseInt(creditsDeltaInput.value || '0', 10) || 0;
			const preview = creditsCurrentValue + delta;
			creditsPreviewEl.textContent = preview;
		}

		function openCreditsModalForRow(tr, type) {
			if (!modalCredits || !creditsUserNameEl || !creditsCurrentEl || !creditsDeltaInput || !creditsUserIdInput) {
				return;
			}
			const id = tr.dataset.id;
			const nameCell = tr.querySelector('td:nth-child(2)');
			const ticketCell = tr.querySelector('.credits-ticket-cell');
			const dailyCell = tr.querySelector('.credits-daily-cell');
			const projectDailiesCell = tr.querySelector('.credits-project-dailies-cell');
			const name = nameCell ? nameCell.textContent.trim() : '';
			let current = 0;
			if (type === 'daily') {
				current = dailyCell ? (parseInt(dailyCell.textContent.trim() || '0', 10) || 0) : 0;
			} else if (type === 'project_dailies') {
				current = projectDailiesCell ? (parseInt(projectDailiesCell.textContent.trim() || '0', 10) || 0) : 0;
			} else {
				current = ticketCell ? (parseInt(ticketCell.textContent.trim() || '0', 10) || 0) : 0;
			}

			creditsCurrentRow = tr;
			creditsCurrentValue = current;
			currentCreditsType = type;

			creditsUserNameEl.textContent = name;
			creditsCurrentEl.textContent = current;
			creditsDeltaInput.value = '0';
			creditsUserIdInput.value = id;
			if (creditsTypeInput) {
				creditsTypeInput.value = type;
			}
			if (creditsTypeLabelEl) {
				if (type === 'daily') {
					creditsTypeLabelEl.textContent = 'Ajustando créditos de Diária';
				} else if (type === 'project_dailies') {
					creditsTypeLabelEl.textContent = 'Ajustando créditos de Diárias Projeto';
				} else {
					creditsTypeLabelEl.textContent = 'Ajustando créditos de Ticket';
				}
			}
			updateCreditsPreview();

			modalCredits.showModal();
		}

		// Botão Créditos Ticket
		document.querySelectorAll('.btn-credits-ticket').forEach(btn => {
			btn.addEventListener('click', (e) => {
				const tr = e.target.closest('tr');
				if (!tr) return;
				openCreditsModalForRow(tr, 'ticket');
			});
		});

		// Botão Créditos Diária
		document.querySelectorAll('.btn-credits-daily').forEach(btn => {
			btn.addEventListener('click', (e) => {
				const tr = e.target.closest('tr');
				if (!tr) return;
				openCreditsModalForRow(tr, 'daily');
			});
		});

		// Botão Créditos Projeto
		document.querySelectorAll('.btn-credits-project-dailies').forEach(btn => {
			btn.addEventListener('click', (e) => {
				const tr = e.target.closest('tr');
				if (!tr) return;
				openCreditsModalForRow(tr, 'project_dailies');
			});
		});

		if (creditsMinusBtn && creditsDeltaInput) {
			creditsMinusBtn.addEventListener('click', () => {
				const currentDelta = parseInt(creditsDeltaInput.value || '0', 10) || 0;
				creditsDeltaInput.value = String(currentDelta - 1);
				updateCreditsPreview();
			});
		}

		if (creditsPlusBtn && creditsDeltaInput) {
			creditsPlusBtn.addEventListener('click', () => {
				const currentDelta = parseInt(creditsDeltaInput.value || '0', 10) || 0;
				creditsDeltaInput.value = String(currentDelta + 1);
				updateCreditsPreview();
			});
		}

		if (creditsDeltaInput) {
			creditsDeltaInput.addEventListener('input', () => {
				updateCreditsPreview();
			});
		}

		if (creditsCancelBtn && modalCredits) {
			creditsCancelBtn.addEventListener('click', () => {
				modalCredits.close();
			});
		}

		if (creditsApplyBtn && modalCredits) {
			creditsApplyBtn.addEventListener('click', async () => {
				if (!creditsUserIdInput || !creditsDeltaInput) {
					return;
				}
				const id = creditsUserIdInput.value;
				const delta = parseInt(creditsDeltaInput.value || '0', 10);
				if (!delta || Number.isNaN(delta)) {
					if (typeof showToast === 'function') {
						showToast('Informe um valor diferente de zero');
					}
					return;
				}
				const type = creditsTypeInput ? (creditsTypeInput.value || 'ticket') : 'ticket';
				const fd = new FormData();
				fd.set('id', id);
				fd.set('delta', String(delta));
				fd.set('type', type);
				try {
					const res = await fetch('/users/credits', {
						method: 'POST',
						body: fd,
						headers: { 'X-Requested-With': 'XMLHttpRequest' }
					});
					const data = await res.json();
					if (data.success && typeof data.credits !== 'undefined') {
						if (creditsCurrentEl) {
							creditsCurrentEl.textContent = data.credits;
						}
						if (creditsPreviewEl) {
							creditsPreviewEl.textContent = data.credits;
						}
						if (creditsCurrentRow) {
							let cls = '.credits-ticket-cell';
							if (type === 'daily') {
								cls = '.credits-daily-cell';
							} else if (type === 'project_dailies') {
								cls = '.credits-project-dailies-cell';
							}
							const cell = creditsCurrentRow.querySelector(cls);
							if (cell) {
								cell.textContent = data.credits;
							}
						}
						if (typeof showToast === 'function') {
							showToast(data.message || 'Créditos atualizados');
						}
						modalCredits.close();
						// Como os créditos agora são globais para todos os usuários do tipo "usuario",
						// recarregamos a página para atualizar todas as linhas da tabela.
						setTimeout(() => location.reload(), 400);
					} else {
						if (typeof showToast === 'function') {
							showToast(data.message || 'Erro ao ajustar créditos');
						}
					}
				} catch (error) {
					console.error('Erro ao ajustar créditos:', error);
					if (typeof showToast === 'function') {
						showToast('Erro ao conectar com o servidor');
					}
				}
			});
		}

		// Exportação de Relatórios
		document.getElementById('btn-export-pdf')?.addEventListener('click', () => {
			window.location.href = '/reports/pdf';
		});

		document.getElementById('btn-export-xlsx')?.addEventListener('click', () => {
			window.location.href = '/reports/xlsx';
		});

		document.getElementById('btn-export-csv')?.addEventListener('click', () => {
			window.location.href = '/reports/csv';
		});

		// Função para carregar anexos
		async function loadAttachments(ticketId) {
			try {
				const res = await fetch('/tickets/attachments?id=' + ticketId);
				const data = await res.json();
				const container = document.getElementById('attachments-container');
				if (!container) return;
				
				if (data.success && data.attachments && data.attachments.length > 0) {
					container.innerHTML = '<div class="mt-4"><strong class="text-sm text-gray-700">Imagens Anexadas:</strong><div class="grid grid-cols-3 gap-2 mt-2">';
					data.attachments.forEach(att => {
						if (att.file_type.startsWith('image/')) {
							container.innerHTML += `
								<div class="relative">
									<img src="${att.file_path}" class="w-full h-24 object-cover rounded border cursor-pointer" onclick="window.open('${att.file_path}', '_blank')">
									<span class="text-xs text-gray-500 block mt-1 truncate">${escapeHtml(att.file_name)}</span>
								</div>
							`;
						}
					});
					container.innerHTML += '</div></div>';
				} else {
					container.innerHTML = '';
				}
			} catch (error) {
				console.error('Erro ao carregar anexos:', error);
			}
		}

		// Filtros para Chamados Fechados
		document.getElementById('f-closed-apply')?.addEventListener('click', () => {
			const params = new URLSearchParams();
			const id = document.getElementById('f-closed-id')?.value || '';
			const period = document.getElementById('f-closed-period')?.value || '';
			const u = document.getElementById('f-closed-user')?.value || '';
			if (id) params.set('closed_id', id);
			if (period) params.set('closed_period', period);
			if (u) params.set('closed_user', u);
			location.href = '/dashboard?' + params.toString();
		});

		// Visualizar chamado fechado
		document.querySelectorAll('.btn-view-closed').forEach(btn => {
			btn.addEventListener('click', async (e) => {
				const tr = e.target.closest('tr');
				const id = tr.dataset.id;
				const res = await fetch('/tickets/view?id=' + id);
				const data = await res.json();
				if (!data.success) { showToast('Erro ao carregar chamado'); return; }
				const t = data.ticket;
				const modalBody = document.getElementById('ticket-modal-body');
				modalBody.innerHTML = `
					<div class="grid grid-cols-2 gap-3 text-sm">
						<div><strong>Título:</strong> ${t.title}</div>
						<div><strong>Prioridade:</strong> ${t.priority}</div>
						<div><strong>Categoria:</strong> ${t.category}</div>
						<div><strong>Nome:</strong> ${t.name}</div>
						<div><strong>Matrícula:</strong> ${t.registration}</div>
						<div><strong>Unidade:</strong> ${t.unit}</div>
						<div><strong>CEP:</strong> ${t.cep}</div>
						<div><strong>Endereço:</strong> ${t.address}${t.address_number ? ', ' + t.address_number : ''}</div>
						<div><strong>Cidade/UF:</strong> ${t.city}/${t.uf}</div>
						${t.internal_order ? `<div><strong>Pedido:</strong> ${t.internal_order}</div>` : ''}
						${t.invoice ? `<div><strong>NF:</strong> ${t.invoice}</div>` : ''}
						${t.external_ticket ? `<div><strong>Ticket Externo:</strong> ${t.external_ticket}</div>` : ''}
						<div class="col-span-2"><strong>Descrição:</strong><br>${t.description}</div>
						${t.support_response ? `<div class="col-span-2 mt-3 p-3 bg-blue-50 rounded"><strong>Resposta do Suporte:</strong><br>${escapeHtml(t.support_response).replace(/\n/g, '<br>')}</div>` : ''}
					</div>
					<div id="attachments-container" class="mt-4"></div>
				`;
				const modal = document.getElementById('ticket-modal');
				modal.showModal();
				loadAttachments(id);
			});
		});
	});

	// Função para abrir extrato de créditos
	async function openCreditExtract(type, userId) {
		const modal = document.getElementById('credit-extract-modal');
		const titleEl = document.getElementById('extract-title');
		const historyEl = document.getElementById('extract-history');
		const purchasedEl = document.getElementById('extract-purchased');
		const spentEl = document.getElementById('extract-spent');
		const availableEl = document.getElementById('extract-available');

		let typeLabel = 'Créditos Ticket';
		if (type === 'daily') {
			typeLabel = 'Créditos Diária';
		} else if (type === 'project_dailies') {
			typeLabel = 'Créditos Diárias Projeto';
		}
		titleEl.textContent = typeLabel;

		historyEl.innerHTML = '<p class="text-gray-500 text-sm">Carregando...</p>';

		try {
			const res = await fetch(`/users/credit-history?id=${userId}&type=${type}`);
			const data = await res.json();

			if (!data.success) {
				historyEl.innerHTML = '<p class="text-red-500 text-sm">Erro ao carregar histórico</p>';
				return;
			}

			// Para id=0 (global) o backend retorna summary direto; para id>0 retorna summary por tipo
			let summary = { purchased: 0, spent: 0, available: 0 };
			if (data.summary) {
				if (typeof data.summary.purchased !== 'undefined') {
					summary = data.summary;
				} else if (data.summary[type]) {
					summary = data.summary[type];
				}
			}
			purchasedEl.textContent = summary.purchased;
			spentEl.textContent = summary.spent;
			availableEl.textContent = summary.available;

			if (data.history.length === 0) {
				historyEl.innerHTML = '<p class="text-gray-500 text-sm">Nenhuma transação registrada</p>';
			} else {
				historyEl.innerHTML = data.history.map(h => {
					const isPositive = h.amount > 0;
					const icon = isPositive ? '✓' : '✕';
					const color = isPositive ? 'text-green-600' : 'text-red-600';
					const bgColor = isPositive ? 'bg-green-50' : 'bg-red-50';
					const date = new Date(h.created_at).toLocaleDateString('pt-BR', { 
						year: 'numeric', 
						month: '2-digit', 
						day: '2-digit',
						hour: '2-digit',
						minute: '2-digit'
					});

					return `
						<div class="${bgColor} p-3 rounded border-l-4 ${isPositive ? 'border-green-600' : 'border-red-600'}">
							<div class="flex justify-between items-start">
								<div class="flex-1">
									<p class="font-semibold text-gray-700">${escapeHtml(h.description)}</p>
									<p class="text-xs text-gray-500 mt-1">${date}</p>
								</div>
								<p class="${color} font-bold text-lg">${isPositive ? '+' : ''}${h.amount}</p>
							</div>
						</div>
					`;
				}).join('');
			}

			modal.showModal();
		} catch (err) {
			console.error('Erro ao carregar extrato:', err);
			historyEl.innerHTML = '<p class="text-red-500 text-sm">Erro ao carregar histórico</p>';
		}
	}

	// Carregar resumo de créditos nos cards (modo global)
	async function loadCreditSummaries() {
		try {
			const res = await fetch(`/users/credit-history?id=0`);
			const data = await res.json();

			if (!data.success) return;

			// Atualizar card Ticket
			const ticketSummary = data.summary.ticket || { purchased: 0, spent: 0, available: 0 };
			const ticketEl = document.getElementById('ticket-summary');
			if (ticketEl) {
				ticketEl.textContent = `Comprados ${ticketSummary.purchased} / Consumidos ${ticketSummary.spent} / Disponível ${ticketSummary.available}`;
			}
			const ticketAvailEl = document.getElementById('ticket-available');
			if (ticketAvailEl) {
				ticketAvailEl.textContent = ticketSummary.available;
			}

			// Atualizar card Diária
			const dailySummary = data.summary.daily || { purchased: 0, spent: 0, available: 0 };
			const dailyEl = document.getElementById('daily-summary');
			if (dailyEl) {
				dailyEl.textContent = `Comprados ${dailySummary.purchased} / Consumidos ${dailySummary.spent} / Disponível ${dailySummary.available}`;
			}
			const dailyAvailEl = document.getElementById('daily-available');
			if (dailyAvailEl) {
				dailyAvailEl.textContent = dailySummary.available;
			}

			// Atualizar card Diárias Projeto
			const projectDailiesSummary = data.summary.project_dailies || { purchased: 0, spent: 0, available: 0 };
			const projectDailiesEl = document.getElementById('project-dailies-summary');
			if (projectDailiesEl) {
				projectDailiesEl.textContent = `Comprados ${projectDailiesSummary.purchased} / Consumidos ${projectDailiesSummary.spent} / Disponível ${projectDailiesSummary.available}`;
			}
			const projectAvailEl = document.getElementById('project-dailies-available');
			if (projectAvailEl) {
				projectAvailEl.textContent = projectDailiesSummary.available;
			}
		} catch (err) {
			console.error('Erro ao carregar resumo de créditos:', err);
		}
	}

	// Variáveis globais para o modal de histórico
	let currentHistoryUserId = null;
	let currentHistoryType = 'ticket';
	let historyData = {};

	// Função para abrir modal de histórico completo (para admin)
	async function openCreditHistoryModal(userId, userName) {
		const modal = document.getElementById('credit-history-modal');
		if (!modal) return;

		currentHistoryUserId = userId;
		currentHistoryType = 'ticket';
		const userNameEl = document.getElementById('history-modal-user-name');
		if (userNameEl) {
			userNameEl.textContent = `Usuário: ${escapeHtml(userName)}`;
		}

		// Carregar dados de todos os tipos
		try {
			const res = await fetch(`/users/credit-history?id=${userId}`);
			const data = await res.json();

			if (!data.success) {
				alert('Erro ao carregar histórico');
				return;
			}

			historyData = data;
			updateHistoryDisplay('ticket');
			modal.showModal();
		} catch (err) {
			console.error('Erro ao carregar histórico:', err);
			alert('Erro ao conectar com o servidor');
		}
	}

	// Função para trocar de aba no modal de histórico
	function switchCreditTab(type) {
		currentHistoryType = type;
		updateHistoryDisplay(type);

		// Atualizar visual das abas
		const buttons = document.querySelectorAll('#credit-history-modal button[onclick^="switchCreditTab"]');
		buttons.forEach(btn => {
			btn.classList.remove('border-blue-700', 'text-blue-700', 'font-semibold');
			btn.classList.add('border-transparent', 'text-gray-600');
		});

		// Marcar aba ativa
		event.target.classList.remove('border-transparent', 'text-gray-600');
		event.target.classList.add('border-blue-700', 'text-blue-700', 'font-semibold');
	}

	// Função para atualizar exibição do histórico
	function updateHistoryDisplay(type) {
		const purchasedEl = document.getElementById('history-purchased');
		const spentEl = document.getElementById('history-spent');
		const availableEl = document.getElementById('history-available');
		const historyListEl = document.getElementById('history-list');

		if (!historyData.summary || !historyData.summary[type]) {
			historyListEl.innerHTML = '<p class="text-gray-500 text-sm">Nenhum dado disponível</p>';
			return;
		}

		const summary = historyData.summary[type];
		purchasedEl.textContent = summary.purchased;
		spentEl.textContent = summary.spent;
		availableEl.textContent = summary.available;

		// Filtrar histórico pelo tipo
		const filteredHistory = historyData.history.filter(h => h.type === type);

		if (filteredHistory.length === 0) {
			historyListEl.innerHTML = '<p class="text-gray-500 text-sm">Nenhuma transação registrada</p>';
		} else {
			historyListEl.innerHTML = filteredHistory.map(h => {
				const isPositive = h.amount > 0;
				const icon = isPositive ? '✓' : '✕';
				const color = isPositive ? 'text-green-600' : 'text-red-600';
				const bgColor = isPositive ? 'bg-green-50' : 'bg-red-50';
				const date = new Date(h.created_at).toLocaleDateString('pt-BR', {
					year: 'numeric',
					month: '2-digit',
					day: '2-digit',
					hour: '2-digit',
					minute: '2-digit'
				});

				return `
					<div class="${bgColor} p-3 rounded border-l-4 ${isPositive ? 'border-green-600' : 'border-red-600'}">
						<div class="flex justify-between items-start">
							<div class="flex-1">
								<p class="font-semibold text-gray-700">${escapeHtml(h.description)}</p>
								<p class="text-xs text-gray-500 mt-1">${date}</p>
							</div>
							<p class="${color} font-bold text-lg">${isPositive ? '+' : ''}${h.amount}</p>
						</div>
					</div>
				`;
			}).join('');
		}
	}

	// Carregar ao iniciar a página
	document.addEventListener('DOMContentLoaded', function() {
		// Carregar resumos globais para todos os perfis
		loadCreditSummaries();

		// Handler para botão "Ver Histórico" na tabela de usuários
		document.querySelectorAll('.btn-view-credit-history').forEach(btn => {
			btn.addEventListener('click', (e) => {
				const userId = parseInt(btn.getAttribute('data-user-id'));
				const userName = btn.getAttribute('data-user-name');
				openCreditHistoryModal(userId, userName);
			});
		});
	});
</script>
