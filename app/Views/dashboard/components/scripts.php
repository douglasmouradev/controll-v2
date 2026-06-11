<script>

	// Função global para carregar anexos já salvos no modal de Abrir/Editar Chamado
	async function loadAttachmentsForEdit(ticketId) {
		try {
			const res = await fetch('/tickets/attachments?id=' + ticketId);
			const data = await res.json();
			const container = document.getElementById('ticket-existing-attachments');
			if (!container) return;
			container.dataset.ticketId = String(ticketId);
			if (data.success && Array.isArray(data.attachments) && data.attachments.length > 0) {
				let html = '<div><strong class="text-sm text-gray-700">Anexos já salvos neste chamado:</strong><div class="grid grid-cols-3 gap-3 mt-2">';
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
							<div class="relative" data-attachment-id="${att.id}">
								<button type="button" class="absolute top-1 right-1 bg-red-600 text-white text-xs rounded px-1 py-0.5 attachment-delete-btn" data-attachment-id="${att.id}">X</button>
								<img src="${escapeHtml(fileUrl)}" class="w-full h-24 object-cover rounded border cursor-pointer" onclick="window.open('${escapeHtml(fileUrl)}', '_blank')">
								<span class="text-xs text-gray-500 block mt-1 truncate">${escapeHtml(name)}</span>
							</div>
						`;
					} else if (isPdf) {
						html += `
							<div class="flex flex-col items-start justify-start p-2 border rounded bg-gray-50 cursor-pointer hover:bg-gray-100" data-attachment-id="${att.id}">
								<button type="button" class="mb-1 self-end bg-red-600 text-white text-xs rounded px-1 py-0.5 attachment-delete-btn" data-attachment-id="${att.id}">X</button>
								<div class="flex items-center gap-2" onclick="window.open('${escapeHtml(fileUrl)}', '_blank')">
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
			console.error('Erro ao carregar anexos para edição:', error);
		}
	}

	// Modal Abrir Chamado - usando delegação de eventos (fora de DOMContentLoaded)
	document.addEventListener('click', (e) => {
		if (e.target.id === 'btn-abrir-chamado-painel' || e.target.id === 'btn-abrir-chamado-list') {
			openNewTicketModal();
			return;
		}
		if (e.target.classList.contains('btn-clone-ticket') || e.target.closest('.btn-clone-ticket')) {
			const btn = e.target.closest('.btn-clone-ticket');
			const id = btn?.dataset?.id;
			if (!id || !confirm('Deseja clonar este chamado?')) return;
			const fd = new FormData();
			fd.set('id', id);
			fetch('/tickets/clone', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
				.then(r => r.json())
				.then(data => {
					if (data.success) {
						showToast(data.message || 'Chamado clonado', 'success');
						setTimeout(() => location.reload(), 500);
					} else {
						showToast(data.message || 'Erro ao clonar', 'error');
					}
				})
				.catch(() => showToast('Erro ao conectar com o servidor', 'error'));
			return;
		}
		if (e.target.id === 'cancelar-chamado') {
			const modalAbrir = document.getElementById('modal-abrir-chamado');
			if (modalAbrir) {
				modalAbrir.close();
			}
		}
		if (e.target.classList && e.target.classList.contains('attachment-delete-btn')) {
			const attachmentId = e.target.dataset.attachmentId;
			const container = document.getElementById('ticket-existing-attachments');
			const ticketId = container && container.dataset ? container.dataset.ticketId : null;
			if (!attachmentId || !ticketId) {
				return;
			}
			if (!confirm('Deseja realmente excluir este anexo?')) {
				return;
			}
			const fd = new FormData();
			fd.set('id', attachmentId);
			fd.set('ticket_id', ticketId);
			fetch('/tickets/attachment-delete', {
				method: 'POST',
				body: fd,
				headers: { 'X-Requested-With': 'XMLHttpRequest' }
			})
				.then(res => res.json())
				.then(data => {
					if (data.success) {
						if (typeof showToast === 'function') {
							showToast(data.message || 'Anexo excluído');
						}
						loadAttachmentsForEdit(ticketId);
					} else if (typeof showToast === 'function') {
						showToast(data.message || 'Erro ao excluir anexo');
					}
				})
				.catch(error => {
					console.error('Erro ao excluir anexo:', error);
					if (typeof showToast === 'function') {
						showToast('Erro ao conectar com o servidor');
					}
				});
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
		if (e.target.id === 'btn-reset-credits-ticket') {
			resetGlobalCredits('ticket');
		}
		if (e.target.id === 'btn-reset-credits-daily') {
			resetGlobalCredits('daily');
		}
		if (e.target.id === 'btn-reset-credits-project') {
			resetGlobalCredits('project_dailies');
		}
		if (e.target.id === 'btn-toggle-maintenance') {
			if (window.DashboardMaintenance && typeof window.DashboardMaintenance.toggleMaintenanceMode === 'function') {
				window.DashboardMaintenance.toggleMaintenanceMode();
			}
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

	async function resetGlobalCredits(type) {
		if (!confirm('Tem certeza que deseja ZERAR os créditos e o histórico deste tipo para TODOS os usuários? Esta ação não pode ser desfeita.')) {
			return;
		}
		const fd = new FormData();
		fd.set('type', type);
		try {
			const res = await fetch('/users/credits/reset', {
				method: 'POST',
				body: fd,
				headers: { 'X-Requested-With': 'XMLHttpRequest' }
			});
			const data = await res.json();
			if (data.success) {
				if (typeof showToast === 'function') {
					showToast(data.message || 'Créditos resetados com sucesso');
				}
				// Recarregar cards e gráficos globais
				try {
					const res2 = await fetch('/dashboard/credit-usage');
					const data2 = await res2.json();
					if (data2.success && data2.summary) {
						const ticket = data2.summary.ticket || { purchased: 0, spent: 0, available: 0 };
						const daily = data2.summary.daily || { purchased: 0, spent: 0, available: 0 };
						const project = data2.summary.project_dailies || { purchased: 0, spent: 0, available: 0 };

						// Atualizar textos dos cards principais
						const ticketEl = document.getElementById('ticket-summary');
						if (ticketEl) {
							ticketEl.textContent = `Comprados ${ticket.purchased} / Consumidos ${ticket.spent} / Disponível ${ticket.available}`;
						}
						const ticketAvailEl = document.getElementById('ticket-available');
						if (ticketAvailEl) {
							ticketAvailEl.textContent = ticket.available;
						}

						const dailyEl = document.getElementById('daily-summary');
						if (dailyEl) {
							dailyEl.textContent = `Comprados ${daily.purchased} / Consumidos ${daily.spent} / Disponível ${daily.available}`;
						}
						const dailyAvailEl = document.getElementById('daily-available');
						if (dailyAvailEl) {
							dailyAvailEl.textContent = daily.available;
						}
						const dailyUsedEl = document.getElementById('daily-used');
						if (dailyUsedEl) {
							const totalUsedDailies = Number(data2.summary.total_used_dailies)
								|| ((Number(daily.spent) || 0) + (Number(project.spent) || 0));
							dailyUsedEl.textContent = totalUsedDailies;
						}

						const projectEl = document.getElementById('project-dailies-summary');
						if (projectEl) {
							projectEl.textContent = `Comprados ${project.purchased} / Consumidos ${project.spent} / Disponível ${project.available}`;
						}
						const projectAvailEl = document.getElementById('project-dailies-available');
						if (projectAvailEl) {
							projectAvailEl.textContent = project.available;
						}

						// Re-renderizar gráficos de pizza
						creditsTicketPie = renderCreditsPie('credits-ticket-pie', ticket, creditsTicketPie);
						creditsDailyPie = renderCreditsPie('credits-daily-pie', daily, creditsDailyPie);
						creditsProjectPie = renderCreditsPie('credits-project-pie', project, creditsProjectPie);
					}
				} catch (e) {
					console.error('Erro ao recarregar dados de créditos após reset:', e);
				}
			} else if (typeof showToast === 'function') {
				showToast(data.message || 'Erro ao resetar créditos');
			}
		} catch (error) {
			console.error('Erro ao resetar créditos:', error);
			if (typeof showToast === 'function') {
				showToast('Erro ao conectar com o servidor');
			}
		}
	}

	function clearStatSkeleton(...ids) {
		ids.forEach((id) => {
			const el = document.getElementById(id);
			if (el) el.classList.remove('skeleton', 'skeleton-stat');
		});
	}

	function bindDropdown(toggleId, menuId) {
		const toggle = document.getElementById(toggleId);
		const menu = document.getElementById(menuId);
		if (!toggle || !menu) return;
		toggle.addEventListener('click', function (e) {
			e.stopPropagation();
			menu.classList.toggle('hidden');
		});
		document.addEventListener('click', function () {
			menu.classList.add('hidden');
		});
	}

	document.addEventListener('DOMContentLoaded', function() {
		bindDropdown('admin-actions-toggle', 'admin-actions-menu');
		bindDropdown('users-credits-toggle', 'users-credits-menu');

		const settingsForm = document.getElementById('form-system-settings');
		if (settingsForm) {
			settingsForm.addEventListener('submit', async function (e) {
				e.preventDefault();
				const fd = new FormData(settingsForm);
				fd.set('maintenance_mode', document.getElementById('setting-maintenance-mode')?.checked ? '1' : '0');
				fd.set('audit_lock_enabled', document.getElementById('setting-audit-lock')?.checked ? '1' : '0');
				try {
					const res = await fetch('/settings/update', {
						method: 'POST',
						body: fd,
						headers: { 'X-Requested-With': 'XMLHttpRequest' }
					});
					const data = await res.json();
					if (data.success) {
						if (window.DashboardMaintenance && data.settings) {
							window.DashboardMaintenance.updateMaintenanceUI(!!data.settings.maintenance_mode);
						}
						if (typeof showToast === 'function') {
							showToast(data.message || 'Configurações salvas');
						}
					} else if (typeof showToast === 'function') {
						showToast(data.message || 'Erro ao salvar configurações');
					}
				} catch (error) {
					console.error('Erro ao salvar configurações:', error);
					if (typeof showToast === 'function') {
						showToast('Erro ao conectar com o servidor');
					}
				}
			});
		}

		// Inicializar gráficos
		if (document.getElementById('dailies-chart')) {
			loadDailies();
			setInterval(loadDailies, 10000);
		}
		if (document.getElementById('status-chart')) {
			loadStatusChart();
			setInterval(loadStatusChart, 10000);
		}
		if (document.getElementById('daily-destination-chart')) {
			loadDailyDestinationChart();
			setInterval(loadDailyDestinationChart, 15000);
		}
		if (document.getElementById('purchased-dailies-table-body')) {
			loadPurchasedDailiesData().catch((error) => {
				console.error('Erro ao carregar diárias compradas:', error);
			});
			const importBtn = document.getElementById('btn-purchased-dailies-import');
			const fileInput = document.getElementById('purchased-dailies-file-input');
			importBtn?.addEventListener('click', () => fileInput?.click());
			fileInput?.addEventListener('change', async () => {
				const file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
				if (!file) return;
				try {
					importBtn.disabled = true;
					importBtn.textContent = 'Importando...';
					await uploadPurchasedDailiesFile(file);
					if (typeof showToast === 'function') {
						showToast('Planilha de diárias compradas importada', 'success');
					}
					await loadPurchasedDailiesData();
					if (typeof loadCreditSummaries === 'function') {
						await loadCreditSummaries();
					}
					if (typeof loadCreditPieCharts === 'function') {
						await loadCreditPieCharts();
					}
				} catch (error) {
					console.error('Erro ao importar diárias compradas:', error);
					if (typeof showToast === 'function') {
						showToast(error.message || 'Erro ao importar planilha');
					}
				} finally {
					importBtn.disabled = false;
					importBtn.textContent = 'Importar planilha';
					fileInput.value = '';
				}
			});
		}
		if (document.getElementById('inventory-pie-chart')) {
			loadInventoryPieChart();
			setInterval(loadInventoryPieChart, 30000);
			const importBtn = document.getElementById('btn-inventory-import');
			const fileInput = document.getElementById('inventory-file-input');
			const storeFilter = document.getElementById('inventory-filter-store');
			const supportStatusFilter = document.getElementById('inventory-filter-support-status');
			const startDate = document.getElementById('inventory-filter-start-date');
			const endDate = document.getElementById('inventory-filter-end-date');

			importBtn?.addEventListener('click', () => fileInput?.click());
			fileInput?.addEventListener('change', async () => {
				const file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
				if (!file) return;
				try {
					importBtn.disabled = true;
					importBtn.textContent = 'Importando...';
					await uploadInventoryFile(file);
					if (typeof showToast === 'function') {
						showToast('Planilha importada com sucesso', 'success');
					}
					await loadInventoryPieChart();
				} catch (error) {
					console.error('Erro ao importar planilha:', error);
					if (typeof showToast === 'function') {
						showToast(error.message || 'Erro ao importar planilha');
					}
				} finally {
					importBtn.disabled = false;
					importBtn.textContent = 'Importar';
					fileInput.value = '';
				}
			});

			storeFilter?.addEventListener('change', loadInventoryPieChart);
			supportStatusFilter?.addEventListener('change', loadInventoryPieChart);
			startDate?.addEventListener('change', loadInventoryPieChart);
			endDate?.addEventListener('change', loadInventoryPieChart);
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
				const sigla = document.getElementById('f-sigla')?.value?.trim() || '';
				const cidade = document.getElementById('f-cidade')?.value?.trim() || '';
				const estado = document.getElementById('f-estado')?.value?.trim() || '';
				if (id) params.set('id', id);
				if (s) params.set('status', s);
				if (p) params.set('priority', p);
				if (u) params.set('user', u);
				if (sigla) params.set('sigla', sigla);
				if (cidade) params.set('cidade', cidade);
				if (estado) params.set('estado', estado);
				params.set('page', '1');
				params.set('tab', 'chamados');
				location.href = '/?' + params.toString();
			}
		});

		// Formulário de novo chamado
		const formEl = document.getElementById('new-ticket-form');
		if (formEl) {
			formEl.addEventListener('submit', async (e) => {
				e.preventDefault();
				const form = e.target;
				const submitBtn = getTicketSubmitBtn();
				const ticketIdInput = form.querySelector('#ticket_id');
				const ticketId = ticketIdInput ? (ticketIdInput.value || '').trim() : '';
				const isEdit = ticketId !== '';
				const originalText = submitBtn ? submitBtn.textContent : '';
				if (submitBtn) {
					submitBtn.disabled = true;
					submitBtn.textContent = isEdit ? 'Salvando...' : 'Abrindo...';
				}
				const fd = new FormData(form);
				if (isEdit) {
					fd.set('ticket_id', ticketId);
				} else {
					fd.delete('ticket_id');
				}
				try {
					const res = await fetch(isEdit ? '/tickets/update' : '/tickets/create', { 
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
						showToast(isEdit ? 'Chamado atualizado' : 'Chamado aberto', 'success');
						form.reset();
						if (ticketIdInput) {
							ticketIdInput.value = '';
						}
						const modalAbrirEl = document.getElementById('modal-abrir-chamado');
						if (modalAbrirEl && typeof modalAbrirEl.close === 'function') {
							modalAbrirEl.close();
						}
						if (submitBtn) {
							submitBtn.disabled = false;
							submitBtn.textContent = 'Abrir chamado';
						}
						setTimeout(() => location.reload(), 500);
					} else {
						const msg = data && data.message ? data.message : `Erro ao ${isEdit ? 'atualizar' : 'abrir'} chamado${res.ok ? '' : ` (HTTP ${res.status})`}`;
						showToast(msg, 'error');
						if (submitBtn) {
							submitBtn.disabled = false;
							submitBtn.textContent = originalText || (isEdit ? 'Salvar Alterações' : 'Abrir Chamado');
						}
					}
				} catch (error) {
					console.error('Erro ao salvar chamado:', error);
					showToast('Erro ao conectar com o servidor');
					if (submitBtn) {
						submitBtn.disabled = false;
						submitBtn.textContent = originalText || (isEdit ? 'Salvar Alterações' : 'Abrir Chamado');
					}
				}
			});
			// Preview e validação de anexos (até 20 arquivos, 40MB cada, PDF/imagem)
			const attachmentsInput = document.getElementById('ticket-attachments');
			const attachmentsList = document.getElementById('ticket-attachments-list');
			if (attachmentsInput && attachmentsList) {
				const renderAttachmentsList = () => {
					attachmentsList.innerHTML = '';
					const dt = new DataTransfer();
					if (!selectedAttachments || selectedAttachments.length === 0) {
						attachmentsInput.value = '';
						return;
					}
					selectedAttachments.forEach((file, index) => {
						if (!file) return;
						dt.items.add(file);
						const ext = (file.name.split('.').pop() || '').toLowerCase();
						const type = file.type || '';
						const isImage = type.startsWith('image/');
						const isPdf = type === 'application/pdf' || ext === 'pdf';
						const row = document.createElement('div');
						row.className = 'flex items-center gap-2 text-sm text-gray-700';
						const icon = document.createElement('div');
						icon.className = 'w-8 h-8 flex items-center justify-center rounded border bg-gray-50 text-xs font-semibold';
						if (isImage) {
							const img = document.createElement('img');
							img.className = 'w-8 h-8 object-cover rounded';
							const reader = new FileReader();
							reader.onload = (ev) => { img.src = ev.target?.result || ''; };
							reader.readAsDataURL(file);
							icon.innerHTML = '';
							icon.appendChild(img);
						} else if (isPdf) {
							icon.textContent = 'PDF';
							icon.classList.add('bg-red-50', 'text-red-700', 'border-red-200');
						}
						const info = document.createElement('div');
						info.className = 'flex-1 truncate';
						const sizeMb = (file.size / (1024 * 1024)).toFixed(1);
						info.textContent = `${file.name} (${sizeMb} MB)`;
						const removeBtn = document.createElement('button');
						removeBtn.type = 'button';
						removeBtn.className = 'text-xs text-red-600 hover:text-red-800 font-semibold ml-2';
						removeBtn.textContent = 'X';
						removeBtn.addEventListener('click', () => {
							selectedAttachments.splice(index, 1);
							renderAttachmentsList();
						});
						row.appendChild(icon);
						row.appendChild(info);
						row.appendChild(removeBtn);
						attachmentsList.appendChild(row);
					});
					attachmentsInput.files = dt.files;
					if (dt.files.length === 0) {
						attachmentsInput.value = '';
						attachmentsList.innerHTML = '';
					}
				};
				attachmentsInput.addEventListener('change', () => {
					const MAX_FILES = 20;
					const MAX_SIZE = 40 * 1024 * 1024; // 40MB
					attachmentsList.innerHTML = '';
					selectedAttachments = [];
					const allFiles = Array.from(attachmentsInput.files || []);
					if (allFiles.length === 0) {
						renderAttachmentsList();
						return;
					}
					let count = 0;
					allFiles.forEach((file) => {
						if (count >= MAX_FILES) {
							return;
						}
						const ext = (file.name.split('.').pop() || '').toLowerCase();
						const type = file.type || '';
						const isImage = type.startsWith('image/');
						const isPdf = type === 'application/pdf' || ext === 'pdf';
						if (!isImage && !isPdf) {
							if (typeof showToast === 'function') {
								showToast(`Tipo de arquivo não permitido: ${file.name}`);
							}
							return;
						}
						if (file.size > MAX_SIZE) {
							if (typeof showToast === 'function') {
								showToast(`Arquivo muito grande (máx. 40MB): ${file.name}`);
							}
							return;
						}
						selectedAttachments.push(file);
						count++;
					});
					if (count === 0) {
						selectedAttachments = [];
					}
					renderAttachmentsList();
				});
			}
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
				modalBody.innerHTML = ticketDetailHtml(t);

				// Preencher campos de dados do técnico (se existirem no DOM)
				const techNameDetail = document.getElementById('technician-name-detail');
				const techRgDetail = document.getElementById('technician-rg-detail');
				const techCpfDetail = document.getElementById('technician-cpf-detail');
				if (techNameDetail) techNameDetail.value = t.technician_name || '';
				if (techRgDetail) techRgDetail.value = t.technician_rg || '';
				if (techCpfDetail) techCpfDetail.value = t.technician_cpf || '';
				// Preencher campo de resposta se existir
				const responseField = document.getElementById('support-response');
				if (responseField) {
					responseField.value = t.support_response || '';
				}
				modal.showModal();
				// Carregar anexos (inclui anexos enviados na criação do chamado)
				loadAttachments(id);
				modal.querySelectorAll('.status-btn').forEach(b => {
					b.onclick = async () => {
						const status = b.dataset.status;
						const fd = new FormData();
						fd.set('id', id);
						fd.set('status', status);
						const r2 = await fetch('/tickets/status', { method: 'POST', body: fd });
						const j2 = await r2.json();
						if (j2.success) {
							const statusCell = document.querySelector(`tr[data-id="${id}"] .status-cell`);
							if (statusCell) statusCell.innerHTML = statusBadgeHtml(status);
							showToast('Status atualizado', 'success');
						} else {
							showToast('Falha ao atualizar', 'error');
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

				// Salvar dados do técnico (para suporte/admin)
				const saveTechnicianBtn = document.getElementById('btn-save-technician');
				if (saveTechnicianBtn) {
					const newTechBtn = saveTechnicianBtn.cloneNode(true);
					saveTechnicianBtn.parentNode.replaceChild(newTechBtn, saveTechnicianBtn);
					newTechBtn.addEventListener('click', async () => {
						const nameVal = (document.getElementById('technician-name-detail')?.value || '').trim();
						const rgVal = (document.getElementById('technician-rg-detail')?.value || '').trim();
						const cpfVal = (document.getElementById('technician-cpf-detail')?.value || '').trim();
						const fd = new FormData();
						fd.set('id', id);
						fd.set('technician_name', nameVal);
						fd.set('technician_rg', rgVal);
						fd.set('technician_cpf', cpfVal);
						try {
							const res = await fetch('/tickets/update-technician', {
								method: 'POST',
								body: fd,
								headers: { 'X-Requested-With': 'XMLHttpRequest' }
							});
							const data = await res.json();
							if (data.success) {
								showToast(data.message || 'Dados do técnico atualizados');
							} else {
								showToast(data.message || 'Erro ao atualizar dados do técnico');
							}
						} catch (error) {
							console.error('Erro ao atualizar dados do técnico:', error);
							showToast('Erro ao conectar com o servidor');
						}
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
								showToast('Resposta salva com sucesso', 'success');
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

		// Edição de chamado pelo próprio usuário
		const editButtons = document.querySelectorAll('.btn-edit-ticket');
		editButtons.forEach(btn => {
			btn.addEventListener('click', async (e) => {
				const tr = e.target.closest('tr');
				const id = tr?.dataset.id;
				if (!id) return;
				try {
					const res = await fetch('/tickets/view?id=' + id);
					const data = await res.json();
					if (!data.success) {
						showToast('Erro ao carregar chamado para edição');
						return;
					}
					const t = data.ticket;
					const modalAbrirEl = document.getElementById('modal-abrir-chamado');
					const form = document.getElementById('new-ticket-form');
					if (!modalAbrirEl || !form) return;

					form.reset();
					const ticketIdInput = document.getElementById('ticket_id');
					if (ticketIdInput) {
						ticketIdInput.value = id;
					}

					form.querySelector('[name="title"]').value = t.title || '';
					form.querySelector('[name="priority"]').value = t.priority || '';
					form.querySelector('[name="category"]').value = t.category || '';
					form.querySelector('[name="name"]').value = t.name || '';
					form.querySelector('[name="registration"]').value = t.registration || '';
					form.querySelector('[name="unit"]').value = t.unit || '';
					form.querySelector('[name="cep"]').value = t.cep || '';
					form.querySelector('[name="address"]').value = t.address || '';
					form.querySelector('[name="address_number"]').value = t.address_number || '';
					form.querySelector('[name="city"]').value = t.city || '';
					form.querySelector('[name="uf"]').value = t.uf || '';
					form.querySelector('[name="description"]').value = t.description || '';
					const techName = form.querySelector('[name="technician_name"]');
					if (techName) techName.value = t.technician_name || '';
					const techRg = form.querySelector('[name="technician_rg"]');
					if (techRg) techRg.value = t.technician_rg || '';
					const techCpf = form.querySelector('[name="technician_cpf"]');
					if (techCpf) techCpf.value = t.technician_cpf || '';

					const sd = form.querySelector('[name="service_date"]');
					if (sd && t.service_date) {
						sd.value = String(t.service_date).substring(0, 10);
					}
					const st = form.querySelector('[name="service_time"]');
					if (st && t.service_time) {
						st.value = String(t.service_time).substring(0, 5);
					}

					const io = form.querySelector('[name="internal_order"]');
					if (io) io.value = t.internal_order || '';
					const inv = form.querySelector('[name="invoice"]');
					if (inv) inv.value = t.invoice || '';
					const dd = form.querySelector('[name="daily_destination"]');
					if (dd && typeof t.daily_destination !== 'undefined') {
						dd.value = t.daily_destination || '';
					}
					const qtdInput = form.querySelector('[name="qtd"]');
					const originalQtdInput = form.querySelector('[name="original_qtd"]');
					let currentQtd = 1;
					if (typeof t.qtd !== 'undefined' && t.qtd !== null) {
						const parsed = parseInt(String(t.qtd), 10);
						if (Number.isFinite(parsed) && parsed > 0) {
							currentQtd = parsed;
						}
					}
					if (qtdInput) {
						qtdInput.value = String(currentQtd);
					}
					if (originalQtdInput) {
						originalQtdInput.value = String(currentQtd);
					}

					const submitBtn = getTicketSubmitBtn();
					if (submitBtn) {
						submitBtn.disabled = false;
						submitBtn.textContent = 'Salvar Alterações';
					}
					const titleEl = modalAbrirEl.querySelector('h2');
					if (titleEl) {
						titleEl.textContent = 'Editar Chamado';
					}
					// Carregar anexos já salvos para este chamado no modal de edição
					await loadAttachmentsForEdit(id);
					modalAbrirEl.showModal();
				} catch (error) {
					console.error('Erro ao carregar chamado para edição:', error);
					showToast('Erro ao carregar chamado para edição');
				}
			});
		});

		document.querySelectorAll('.btn-delete-ticket').forEach(btn => {
			btn.addEventListener('click', async (e) => {
				const tr = e.target.closest('tr');
				const id = tr?.dataset.id;
				if (!id) return;
				if (!confirm('Deseja realmente excluir este chamado?')) {
					return;
				}
				const fd = new FormData();
				fd.set('id', id);
				try {
					const res = await fetch('/tickets/delete', {
						method: 'POST',
						body: fd,
						headers: { 'X-Requested-With': 'XMLHttpRequest' }
					});
					const data = await res.json();
					if (data.success) {
						tr.remove();
						if (typeof showToast === 'function') {
							showToast(data.message || 'Chamado excluído com sucesso');
						}
					} else if (typeof showToast === 'function') {
						showToast(data.message || 'Erro ao excluir chamado');
					}
				} catch (error) {
					console.error('Erro ao excluir chamado:', error);
					if (typeof showToast === 'function') {
						showToast('Erro ao conectar com o servidor');
					}
				}
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
					const statusCell = tr.querySelector('.status-cell');
					if (statusCell) statusCell.innerHTML = statusBadgeHtml('Em andamento');
					showToast('Chamado atribuído', 'success');
				} else {
					showToast('Falha ao atribuir', 'error');
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

		// Filtros para Chamados Fechados
		document.getElementById('f-closed-apply')?.addEventListener('click', () => {
			const params = new URLSearchParams();
			const id = document.getElementById('f-closed-id')?.value || '';
			const period = document.getElementById('f-closed-period')?.value || '';
			const u = document.getElementById('f-closed-user')?.value || '';
			if (id) params.set('closed_id', id);
			if (period) params.set('closed_period', period);
			if (u) params.set('closed_user', u);
			params.set('tab', 'chamados-fechados');
			location.href = '/?' + params.toString();
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
				modalBody.innerHTML = ticketDetailHtml(t);
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
</script>
