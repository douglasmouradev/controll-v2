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
								${attachmentDeleteBtnHtml(att.id, 'image')}
								<img src="${escapeHtml(fileUrl)}" class="w-full h-24 object-cover rounded border cursor-pointer" onclick="window.open('${escapeHtml(fileUrl)}', '_blank')">
								<span class="text-xs text-gray-500 block mt-1 truncate">${escapeHtml(name)}</span>
							</div>
						`;
					} else if (isPdf) {
						html += `
							<div class="flex flex-col items-start justify-start p-2 border rounded bg-gray-50 cursor-pointer hover:bg-gray-100" data-attachment-id="${att.id}">
								${attachmentDeleteBtnHtml(att.id, 'pdf')}
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

	async function loadAttachments(ticketId) {
		try {
			const res = await fetch('/tickets/attachments?id=' + ticketId);
			const data = await res.json();
			const container = document.getElementById('attachments-container');
			if (!container) return;
			container.dataset.ticketId = String(ticketId);

			if (data.success && Array.isArray(data.attachments) && data.attachments.length > 0) {
				let html = '<div class="mt-4"><strong class="text-sm text-gray-700">Anexos:</strong><div class="grid grid-cols-3 gap-3 mt-2">';
				data.attachments.forEach((att) => {
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
								${attachmentDeleteBtnHtml(att.id, 'image')}
								<img src="${escapeHtml(fileUrl)}" class="w-full h-24 object-cover rounded border cursor-pointer" onclick="window.open('${escapeHtml(fileUrl)}', '_blank')">
								<span class="text-xs text-gray-500 block mt-1 truncate">${escapeHtml(name)}</span>
							</div>
						`;
					} else if (isPdf) {
						html += `
							<div class="flex flex-col items-start justify-start p-2 border rounded bg-gray-50 cursor-pointer hover:bg-gray-100" data-attachment-id="${att.id}">
								${attachmentDeleteBtnHtml(att.id, 'pdf')}
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
			console.error('Erro ao carregar anexos:', error);
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
						refreshDashboardAfterMutation();
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
			const editContainer = document.getElementById('ticket-existing-attachments');
			const detailContainer = document.getElementById('attachments-container');
			const ticketModal = document.getElementById('ticket-modal');
			let ticketId = editContainer?.dataset?.ticketId || detailContainer?.dataset?.ticketId || ticketModal?.dataset?.ticketId;
			const isDetailView = Boolean(detailContainer?.dataset?.ticketId || (!editContainer?.dataset?.ticketId && ticketModal?.dataset?.ticketId));
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
						if (isDetailView) {
							loadAttachments(ticketId);
						} else {
							loadAttachmentsForEdit(ticketId);
						}
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
		if (e.target.id === 'btn-toggle-maintenance') {
			if (window.DashboardMaintenance && typeof window.DashboardMaintenance.toggleMaintenanceMode === 'function') {
				window.DashboardMaintenance.toggleMaintenanceMode();
			}
		}
	});

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

		// Inicializar gráficos (um único poll agregado)
		if (
			document.getElementById('dailies-chart') ||
			document.getElementById('status-chart') ||
			document.getElementById('daily-destination-chart')
		) {
			loadDashboardChartsBundle();
			startVisibilityAwareInterval(loadDashboardChartsBundle, 30000);
		}
		if (document.getElementById('purchased-dailies-table-body')) {
			loadPurchasedDailiesData().catch((error) => {
				console.error('Erro ao carregar diárias compradas:', error);
			});
			document.getElementById('purchased-dailies-table-body')?.addEventListener('click', (event) => {
				const button = event.target.closest('[data-purchased-dailies-show-more]');
				if (!button || typeof showMorePurchasedDailiesRows !== 'function') return;
				showMorePurchasedDailiesRows(button.dataset.purchasedDailiesShowMore);
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
			startVisibilityAwareInterval(loadInventoryPieChart, 30000);
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
						if (data.attachment_warning && typeof showToast === 'function') {
							showToast(data.attachment_warning, 'error');
						}
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
						refreshDashboardAfterMutation();
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

		if (modal && !modal.dataset.statusHandlerBound) {
			modal.dataset.statusHandlerBound = '1';
			modal.addEventListener('click', async (e) => {
				const statusBtn = e.target.closest('.status-btn');
				if (!statusBtn) return;
				const ticketId = modal.dataset.ticketId;
				if (!ticketId) return;
				const status = statusBtn.dataset.status || '';
				const fd = new FormData();
				fd.set('id', ticketId);
				fd.set('status', status);
				try {
					const r2 = await fetch('/tickets/status', {
						method: 'POST',
						body: fd,
						headers: { 'X-Requested-With': 'XMLHttpRequest' },
					});
					const j2 = await r2.json();
					if (j2.success) {
						const displayStatus = j2.status || status;
						const statusCell = document.querySelector(`tr[data-id="${ticketId}"] .status-cell`);
						if (statusCell) statusCell.innerHTML = statusBadgeHtml(displayStatus);
						const noticeMessage = j2.message || `Status alterado para ${displayStatus}`;
						showTicketStatusNotice(modal, noticeMessage, displayStatus);
						if (typeof showToast === 'function') {
							showToast(noticeMessage, 'success');
						}
						if (typeof refreshDashboardAfterMutation === 'function') {
							refreshDashboardAfterMutation();
						}
					} else {
						const errorMsg = j2.message || 'Falha ao atualizar status';
						if (typeof showToast === 'function') {
							showToast(errorMsg, 'error');
						}
					}
				} catch (error) {
					console.error('Erro ao atualizar status:', error);
					showToast('Erro ao conectar com o servidor', 'error');
				}
			});
		}

		async function openTicketDetailModal(id) {
			if (!modal || !modalBody || !id) return;
			const res = await fetch('/tickets/view?id=' + id);
			const data = await res.json();
			if (!data.success) { showToast('Erro ao carregar chamado'); return; }
			const t = data.ticket;
			modal.dataset.ticketId = String(id);
			const statusNotice = document.getElementById('ticket-status-notice');
			if (statusNotice) {
				statusNotice.classList.add('hidden');
				statusNotice.classList.remove('show');
				statusNotice.innerHTML = '';
			}
			modal.querySelectorAll('.status-btn').forEach((btn) => {
				const isActive = (btn.dataset.status || '') === String(t.status || '');
				btn.classList.toggle('btn-primary', isActive);
				btn.classList.toggle('btn-secondary', !isActive);
			});
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
								showToast(data.message || 'Salvo com sucesso', 'success');
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
		}

		document.getElementById('tickets-tbody')?.addEventListener('click', async (e) => {
			const viewBtn = e.target.closest('.btn-view');
			if (!viewBtn) return;
			const tr = viewBtn.closest('tr');
			const id = tr?.dataset?.id;
			if (!id) return;
			try {
				await openTicketDetailModal(id);
			} catch (error) {
				console.error('Erro ao abrir chamado:', error);
				showToast('Erro ao carregar chamado');
			}
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
					if (statusCell) statusCell.innerHTML = statusBadgeHtml('Em Andamento');
					showToast('Chamado atribuído', 'success');
				} else {
					showToast('Falha ao atribuir', 'error');
				}
			});
		});

		document.getElementById('modal-close')?.addEventListener('click', () => modal.close());
});
