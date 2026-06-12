document.addEventListener('DOMContentLoaded', function() {
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
			
			const submitBtn = getUserSubmitBtn();
			
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
					showToast(data.message || 'Usuário salvo com sucesso', 'success');
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
						showToast('Usuário excluído com sucesso', 'success');
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
			const baseValue = typeof window.creditsCurrentValue === 'number' ? window.creditsCurrentValue : creditsCurrentValue;
			const preview = baseValue + delta;
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
			// Usar consumo real (QTD) + comprados fixos definidos no backend
			const res = await fetch(`/dashboard/credit-usage`);
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

			const dailyUsedEl = document.getElementById('daily-used');
			if (dailyUsedEl) {
				const totalUsedDailies = Number(data.summary.total_used_dailies)
					|| ((Number(dailySummary.spent) || 0) + (Number(projectDailiesSummary.spent) || 0));
				dailyUsedEl.textContent = totalUsedDailies;
			}
		} catch (err) {
			console.error('Erro ao carregar resumo de créditos:', err);
		} finally {
			clearStatSkeleton('ticket-available', 'daily-available', 'project-dailies-available', 'daily-used');
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

		// Apagar histórico (admin)
		const clearBtn = document.getElementById('btn-clear-credit-history');
		if (clearBtn) {
			clearBtn.addEventListener('click', async () => {
				if (!confirm('Tem certeza que deseja apagar o histórico deste tipo?')) {
					return;
				}
				const fd = new FormData();
				fd.set('type', currentHistoryType || '');
				try {
					const res = await fetch('/users/credit-history/clear', {
						method: 'POST',
						body: fd,
						headers: { 'X-Requested-With': 'XMLHttpRequest' }
					});
					const data = await res.json();
					if (data.success) {
						if (typeof showToast === 'function') {
							showToast(data.message || 'Histórico apagado');
						}
						// Recarregar modal e cards/gráficos
						if (currentHistoryUserId != null) {
							openCreditHistoryModal(currentHistoryUserId, (document.getElementById('history-modal-user-name')?.textContent || '').trim());
						}
						loadCreditSummaries();
						loadCreditPieCharts();
					} else if (typeof showToast === 'function') {
						showToast(data.message || 'Erro ao apagar histórico');
					}
				} catch (e) {
					console.error('Erro ao apagar histórico:', e);
					if (typeof showToast === 'function') {
						showToast('Erro ao conectar com o servidor');
					}
				}
			});
		}

		// Handler para botão "Ver Histórico" na tabela de usuários
		document.querySelectorAll('.btn-view-credit-history').forEach(btn => {
			btn.addEventListener('click', (e) => {
				const userId = parseInt(btn.getAttribute('data-user-id'));
				const userName = btn.getAttribute('data-user-name');
				openCreditHistoryModal(userId, userName);
			});
		});
	});
