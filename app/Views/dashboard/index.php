<?php
/** @var array $user */
/** @var array $tickets */
/** @var array $filters */
/** @var array $stats */
/** @var array $users */
$users = $users ?? [];
?>

<!-- Sidebar -->
<?php include __DIR__ . '/components/sidebar.php'; ?>

<!-- Overlay para mobile e desktop -->
<div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 hidden z-40"></div>

<!-- Main Content -->
<div id="main-content" class="transition-all duration-300">
	<!-- Header com Hamburguer (Desktop e Mobile) -->
	<div class="bg-white border-b border-gray-200 p-4 flex items-center gap-4 sticky top-0 z-30">
		<button id="sidebar-toggle" class="text-blue-900 hover:text-blue-700">
			<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
			</svg>
		</button>
		<h1 class="text-xl font-bold text-blue-900">Controll</h1>
	</div>

<div class="max-w-7xl mx-auto">
	<!-- Componentes de Tabs -->
	<?php include __DIR__ . '/components/painel-tab.php'; ?>
	<?php include __DIR__ . '/components/chamados-tab.php'; ?>
	<?php include __DIR__ . '/components/chamados-fechados-tab.php'; ?>
	<?php if (in_array($user['role'], ['support', 'admin'], true)): ?>
		<?php include __DIR__ . '/components/usuarios-tab.php'; ?>
		<?php include __DIR__ . '/components/relatorios-tab.php'; ?>
	<?php endif; ?>
	<?php include __DIR__ . '/components/inventario-tab.php'; ?>
</div>

<!-- Modal de Abrir Chamado -->
<dialog id="modal-abrir-chamado" class="rounded-lg w-11/12 max-w-3xl p-0">
	<div class="bg-blue-700 text-white px-6 py-4 rounded-t-lg">
		<h2 class="text-lg font-semibold">Abrir Novo Chamado</h2>
	</div>
	<div class="p-6">
		<form id="new-ticket-form" class="grid grid-cols-2 gap-4" enctype="multipart/form-data">
			<?php echo \App\Services\Csrf::field(); ?>
			<input type="hidden" name="ticket_id" id="ticket_id">
			<input type="hidden" name="original_qtd" id="original_qtd">
			<input class="col-span-2 border rounded px-3 py-2" name="title" placeholder="Título do Problema" required>
			<select class="border rounded px-3 py-2" name="priority" required>
				<option value="">Prioridade</option>
				<option>Baixa</option><option>Média</option><option>Alta</option>
			</select>
			<select class="border rounded px-3 py-2" name="category" required>
				<option value="">Categoria</option>
				<option>Ticket</option>
				<option>Diária</option>
				<option>Uso Geral</option>
				<option>Projeto</option>
			</select>
			<div id="project-name-field" class="col-span-2 hidden">
				<input class="border rounded px-3 py-2 w-full" name="project_name" id="project_name" placeholder="Nome do Projeto">
			</div>
			<input class="border rounded px-3 py-2" name="name" placeholder="Nome do Solicitante" required>
			<input class="border rounded px-3 py-2" name="registration" placeholder="Matrícula">
			<input class="border rounded px-3 py-2 w-full" name="unit" placeholder="Sigla da Loja" style="text-transform: uppercase;" oninput="this.value = this.value.toUpperCase();" required>
			<div class="col-span-2">
				<input class="border rounded px-3 py-2 w-full" name="cep" id="cep" placeholder="CEP" required>
			</div>
			<input class="col-span-2 border rounded px-3 py-2" name="address" id="address" placeholder="Endereço" required>
			<input class="border rounded px-3 py-2" name="address_number" id="address_number" placeholder="Número">
			<input class="border rounded px-3 py-2" name="city" id="city" placeholder="Cidade">
			<input class="border rounded px-3 py-2" name="uf" id="uf" placeholder="UF">
			<textarea class="col-span-2 border rounded px-3 py-2" name="description" placeholder="Descrição do Problema" rows="4" required></textarea>
			<input class="border rounded px-3 py-2" name="technician_name" placeholder="Nome do técnico (opcional)">
			<input class="border rounded px-3 py-2" name="technician_rg" placeholder="RG do técnico (opcional)">
			<input class="border rounded px-3 py-2" name="technician_cpf" placeholder="CPF do técnico (opcional)">
			<div>
				<label class="block text-sm text-gray-600 mb-1">Data para atendimento (opcional)</label>
				<input class="border rounded px-3 py-2 w-full" type="date" name="service_date" placeholder="Data para atendimento">
			</div>
			<div>
				<label class="block text-sm text-gray-600 mb-1">Hora para atendimento (opcional)</label>
				<input class="border rounded px-3 py-2 w-full" type="time" name="service_time" placeholder="Hora para atendimento">
			</div>
			<input class="border rounded px-3 py-2" name="internal_order" placeholder="Pedido (interno)">
			<div>
				<label class="block text-sm text-gray-600 mb-1">QTD</label>
				<div class="flex items-center gap-2">
					<button type="button" id="qtd-minus" class="px-3 py-2 rounded bg-gray-200 text-gray-700 hover:bg-gray-300">-</button>
					<input type="number" name="qtd" id="qtd" class="border rounded px-3 py-2 w-20 text-center" min="0" step="1" value="1">
					<button type="button" id="qtd-plus" class="px-3 py-2 rounded bg-gray-200 text-gray-700 hover:bg-gray-300">+</button>
				</div>
			</div>
			<input class="border rounded px-3 py-2" name="invoice" placeholder="NF">
			<input class="border rounded px-3 py-2" name="daily_destination" placeholder="Destino da diária">
			<div class="col-span-2">
				<label class="block text-sm text-gray-600 mb-1">Anexos (PDF, PNG, JPG, JPEG) - até 20 arquivos, 40MB cada</label>
				<input type="file" id="ticket-attachments" name="attachments[]" multiple accept=".pdf,image/*" class="w-full border rounded px-3 py-2">
				<div id="ticket-attachments-list" class="mt-2 space-y-1 text-sm text-gray-700"></div>
				<div id="ticket-existing-attachments" class="mt-4 space-y-2 text-sm text-gray-700"></div>
			</div>
			<div class="col-span-2 flex gap-2 justify-end">
				<button type="button" id="cancelar-chamado" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">Cancelar</button>
				<button type="submit" class="bg-blue-700 text-white px-4 py-2 rounded hover:bg-blue-800">Abrir Chamado</button>
			</div>
		</form>
	</div>
</dialog>

<!-- Modal de Criar/Editar Usuário -->
<dialog id="modal-usuario" class="rounded-lg w-11/12 max-w-2xl p-0">
	<div class="bg-blue-700 text-white px-6 py-4 rounded-t-lg">
		<h2 class="text-lg font-semibold" id="modal-usuario-title">Criar Usuário</h2>
	</div>
	<div class="p-6">
		<form id="user-form" class="space-y-4">
			<input type="hidden" id="user-id" name="id">
			<div>
				<label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
				<input type="text" id="user-name" name="name" class="w-full border rounded px-3 py-2" required>
			</div>
			<div>
				<label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
				<input type="email" id="user-email" name="email" class="w-full border rounded px-3 py-2" required>
			</div>
			<div>
				<label class="block text-sm font-medium text-gray-700 mb-1">Senha</label>
				<input type="password" id="user-password" name="password" class="w-full border rounded px-3 py-2">
				<p class="text-xs text-gray-500 mt-1 hidden" id="password-hint">Deixe em branco para manter a senha atual ao editar</p>
			</div>
			<div>
				<label class="block text-sm font-medium text-gray-700 mb-1">Perfil</label>
				<select id="user-role" name="role" class="w-full border rounded px-3 py-2" required>
					<option value="usuario">Usuário</option>
					<option value="suporte">Suporte</option>
					<option value="admin">Admin</option>
					</select>
			</div>
			<div class="flex gap-2 justify-end">
				<button type="button" id="cancelar-usuario" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">Cancelar</button>
				<button type="submit" class="bg-blue-700 text-white px-4 py-2 rounded hover:bg-blue-800">Salvar</button>
			</div>
		</form>
	</div>
</dialog>

<dialog id="modal-credits" class="rounded-lg w-11/12 max-w-md p-0">
	<div class="bg-purple-700 text-white px-6 py-4 rounded-t-lg">
		<h2 class="text-lg font-semibold">Ajustar Créditos</h2>
		<p class="text-sm mt-1" id="credits-modal-user-name"></p>
		<p class="text-xs text-purple-100" id="credits-type-label"></p>
	</div>
	<div class="p-6 space-y-4">
		<div>
			<p class="text-sm text-gray-600">Saldo atual:</p>
			<p class="text-2xl font-bold text-gray-900"><span id="credits-current"></span></p>
		</div>
		<div>
			<label class="block text-sm font-medium text-gray-700 mb-1">Ajuste de créditos</label>
			<div class="flex items-center gap-2">
				<button type="button" id="credits-minus" class="px-3 py-2 rounded bg-gray-200 text-gray-700 hover:bg-gray-300">-</button>
				<input type="number" id="credits-delta" class="w-24 border rounded px-3 py-2 text-center" value="0" step="1">
				<button type="button" id="credits-plus" class="px-3 py-2 rounded bg-gray-200 text-gray-700 hover:bg-gray-300">+</button>
			</div>
			<p class="text-xs text-gray-500 mt-1">Valores positivos adicionam créditos; negativos removem.</p>
		</div>
		<div>
			<p class="text-sm text-gray-600">Saldo após ajuste:</p>
			<p class="text-lg font-semibold text-gray-900"><span id="credits-preview"></span></p>
		</div>
		<input type="hidden" id="credits-user-id">
		<input type="hidden" id="credits-type">
		<div class="flex justify-end gap-2 mt-4">
			<button type="button" id="credits-cancel" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">Cancelar</button>
			<button type="button" id="credits-apply" class="bg-purple-700 text-white px-4 py-2 rounded hover:bg-purple-800">Aplicar</button>
		</div>
	</div>
</dialog>

<!-- Modal de Detalhes do Chamado -->
<dialog id="ticket-modal" class="rounded-lg w-11/12 max-w-2xl p-0">
	<div class="bg-blue-700 text-white px-4 py-3 rounded-t-lg">Detalhes do Chamado</div>
	<div class="p-4 space-y-2" id="ticket-modal-body"></div>
	<?php if (in_array($user['role'], ['support','admin'], true)): ?>
		<div class="p-4 border-t">
			<label class="block text-sm font-medium text-gray-700 mb-2">Resposta do Suporte</label>
			<textarea id="support-response" rows="4" class="w-full border rounded px-3 py-2" placeholder="Digite sua resposta para o usuário..."></textarea>
			
			<div class="mt-3">
				<label class="block text-sm font-medium text-gray-700 mb-2">Anexar Imagens</label>
				<input type="file" id="support-images" accept="image/*" multiple class="w-full border rounded px-3 py-2">
				<div id="image-preview" class="mt-2 grid grid-cols-3 gap-2"></div>
			</div>
			
			<button id="btn-save-response" class="mt-2 bg-blue-700 text-white px-4 py-2 rounded hover:bg-blue-800">Salvar Resposta</button>
		</div>
	<?php endif; ?>
	<div class="p-4 pt-0 flex gap-2 justify-end">
		<?php if (in_array($user['role'], ['support','admin'], true)): ?>
			<button data-status="Aberto" class="status-btn bg-gray-100 px-3 py-1 rounded hover:bg-gray-200">Aberto</button>
			<button data-status="Em andamento" class="status-btn bg-yellow-100 px-3 py-1 rounded hover:bg-yellow-200">Em andamento</button>
			<button data-status="Fechado" class="status-btn bg-green-100 px-3 py-1 rounded hover:bg-green-200">Fechado</button>
		<?php endif; ?>
		<button id="modal-close" class="bg-blue-700 text-white px-4 py-1.5 rounded hover:bg-blue-800">Fechar</button>
	</div>
</dialog>

<script>
	console.log('dashboard/index.php script carregado');
	// Função para escapar HTML
	function escapeHtml(text) {
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	// Preenchimento automatico de endereco pela sigla da loja (FORA de DOMContentLoaded para funcionar com modal)
	let storeAddressesCache = null;
	async function loadStoreAddresses() {
		if (storeAddressesCache) {
			return storeAddressesCache;
		}
		try {
			const res = await fetch('/dashboard/enderecos', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
			const data = await res.json();
			if (!data.success || !Array.isArray(data.data)) {
				console.error('Falha ao carregar enderecos das lojas:', data);
				return [];
			}
			storeAddressesCache = data.data;
			console.log('Enderecos carregados:', storeAddressesCache.length, 'lojas');
			return storeAddressesCache;
		} catch (e) {
			console.error('Erro ao buscar enderecos das lojas:', e);
			return [];
		}
	}

	function extractAddressParts(raw) {
		if (!raw) return { cep: '', address: '', city: '', uf: '' };
		let text = String(raw).trim();
		console.log('[PARSE] Parseando endereco:', text);

		// CEP: procurar ULTIMO padrao de 8 digitos (com ou sem formatacao)
		let cep = '';
		// Procurar todos os padroes de CEP: "66645-900", "66.645-900", "66645900", "66 645 900"
		const cepMatches = text.match(/\d{2,5}[.\s-]*\d{3}[.\s-]*\d{3}/g);
		if (cepMatches && cepMatches.length > 0) {
			// Pegar o ULTIMO CEP encontrado
			const lastCep = cepMatches[cepMatches.length - 1];
			cep = lastCep.replace(/\D/g, ''); // Remove tudo que nao eh digito
			if (cep.length === 8) {
				cep = cep.slice(0, 5) + '-' + cep.slice(5);
			}
		}
		console.log('[CEP] CEP extraido:', cep);

		// Cidade e UF: procurar padrao "Cidade - UF" (UF sempre 2 letras maiusculas)
		let city = '';
		let uf = '';
		const cityUfRegex = /([A-Za-z\s]+?)\s*[-]\s*([A-Z]{2})(?:\s|$)/;
		const m = text.match(cityUfRegex);
		if (m) {
			city = m[1].trim();
			uf = m[2].trim();
			console.log('[CITY] Cidade/UF extraido:', city, '/', uf);
		}

		// Endereco: tudo antes de "Cidade - UF" ou antes de "CEP"
		let address = text;
		if (m && typeof m.index === 'number') {
			address = text.substring(0, m.index).trim();
		} else {
			// Tentar remover CEP do final
			address = text.replace(/\s*CEP\s*\d{2,5}[.\s-]*\d{3}[.\s-]*\d{3}\s*$/i, '').trim();
		}
		// Remover "Endereco:" do inicio se existir
		address = address.replace(/^Endereco:\s*/i, '').trim();
		// Remover virgulas ou hifens finais
		address = address.replace(/[,-]\s*$/, '').trim();
		console.log('[ADDR] Endereco extraido:', address);

		return { cep, address, city, uf };
	}

	// Listener na sigla da loja (delegação de eventos para funcionar com modal)
	document.addEventListener('input', async (e) => {
		console.log('[INPUT] Input event disparado em:', e.target.name, 'Valor:', e.target.value);
		if (e.target.name === 'unit') {
			console.log('[INPUT] Campo unit detectado!');
			// Usar setTimeout para capturar o valor DEPOIS da conversão de maiúsculas
			setTimeout(async () => {
				const sigla = e.target.value.trim().toUpperCase();
				console.log('[SIGLA] Sigla digitada:', sigla, 'Comprimento:', sigla.length);
				if (sigla.length < 2) {
					console.log('[SIGLA] Sigla muito curta, ignorando');
					return;
				}
				const list = await loadStoreAddresses();
				console.log('[LOAD] Total de lojas carregadas:', list.length);
				if (!list.length) {
					console.log('[LOAD] Lista de enderecos vazia');
					return;
				}
				console.log('[SEARCH] Procurando sigla:', sigla);
				console.log('[SEARCH] Primeiras 5 siglas disponíveis:', list.slice(0, 5).map(x => x.sigla));
				const found = list.find(item => (item.sigla || '').toUpperCase() === sigla);
				if (!found) {
					console.log('[SEARCH] Sigla NAO encontrada:', sigla);
					return;
				}
				console.log('[FOUND] Sigla encontrada:', found);
				const parts = extractAddressParts(found.endereco || found.ENDERECO || '');
				const cepEl = document.getElementById('cep');
				const addrEl = document.getElementById('address');
				const cityEl = document.getElementById('city');
				const ufEl = document.getElementById('uf');
				console.log('[PARTS] Partes extraidas:', parts);
				if (cepEl && parts.cep) {
					cepEl.value = parts.cep;
					console.log('[FILL] CEP preenchido:', parts.cep);
				}
				if (addrEl && parts.address) {
					addrEl.value = parts.address;
					console.log('[FILL] Endereco preenchido:', parts.address);
				}
				if (cityEl && parts.city) {
					cityEl.value = parts.city;
					console.log('[FILL] Cidade preenchida:', parts.city);
				}
				if (ufEl && parts.uf) {
					ufEl.value = parts.uf;
					console.log('[FILL] UF preenchido:', parts.uf);
				}
			}, 10);
		}
	});
	
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

		const categorySelect = document.querySelector('select[name="category"]');
		const projectNameField = document.getElementById('project-name-field');
		const projectNameInput = document.getElementById('project_name');
		const toggleProjectType = () => {
			const isProject = ((categorySelect?.value || '').toLowerCase() === 'projeto');
			if (projectNameField) projectNameField.classList.toggle('hidden', !isProject);
			if (projectNameInput) {
				projectNameInput.required = isProject;
				if (!isProject) projectNameInput.value = '';
			}
		};
		if (categorySelect) {
			categorySelect.addEventListener('change', toggleProjectType);
			toggleProjectType();
		}

		const qtdInput = document.getElementById('qtd');
		const qtdMinus = document.getElementById('qtd-minus');
		const qtdPlus = document.getElementById('qtd-plus');
		const originalQtdInput = document.getElementById('original_qtd');
		if (qtdInput && qtdMinus && qtdPlus) {
			const getOriginalQtd = () => {
				const raw = originalQtdInput ? originalQtdInput.value : '';
				const n = parseInt(raw || '0', 10);
				return Number.isFinite(n) && n >= 0 ? n : 0;
			};
			qtdMinus.addEventListener('click', () => {
				const current = parseInt(qtdInput.value || '0', 10) || 0;
				const original = getOriginalQtd();
				const next = Math.max(original, current - 1);
				qtdInput.value = String(next);
			});
			qtdPlus.addEventListener('click', () => {
				const current = parseInt(qtdInput.value || '0', 10) || 0;
				const next = current + 1;
				qtdInput.value = String(next);
			});
			qtdInput.addEventListener('input', () => {
				let val = parseInt(qtdInput.value || '0', 10);
				if (!Number.isFinite(val) || val < 0) {
					val = 0;
				}
				const original = getOriginalQtd();
				if (val < original) {
					val = original;
				}
				qtdInput.value = String(val);
			});
		}

		// Tabs (botões de navegação principais)
		var tabButtons = document.querySelectorAll('.tab-btn');
		if (tabButtons && tabButtons.length > 0) {
			tabButtons.forEach(function(btn) {
				btn.addEventListener('click', function() {
					var tab = btn.dataset.tab;
					// remover estado ativo de todos os botões
					tabButtons.forEach(function(b) {
						b.classList.remove('border-blue-700', 'text-blue-700', 'font-semibold');
						b.classList.add('text-gray-600');
					});
					// ativar botão clicado
					btn.classList.add('border-blue-700', 'text-blue-700', 'font-semibold');
					btn.classList.remove('text-gray-600');
					// esconder todos os conteúdos
					var tabContents = document.querySelectorAll('.tab-content');
					if (tabContents) {
						tabContents.forEach(function(c) {
							c.classList.add('hidden');
						});
					}
					// mostrar conteúdo selecionado
					var target = document.getElementById('tab-' + tab);
					if (target) {
						target.classList.remove('hidden');
					}
				});
			});
		}

		// Filtros
		var applyBtn = document.getElementById('f-apply');
		if (applyBtn) {
			applyBtn.addEventListener('click', function() {
				var params = new URLSearchParams();
				var idInput = document.getElementById('f-id');
				var userInput = document.getElementById('f-user');
				var siglaInput = document.getElementById('f-sigla');
				var cidadeInput = document.getElementById('f-cidade');
				var estadoInput = document.getElementById('f-estado');
				var id = idInput ? (idInput.value || '') : '';
				var s = document.getElementById('f-status').value;
				var p = document.getElementById('f-priority').value;
				var u = userInput ? (userInput.value || '') : '';
				var sigla = siglaInput ? (siglaInput.value || '').trim() : '';
				var cidade = cidadeInput ? (cidadeInput.value || '').trim() : '';
				var estado = estadoInput ? (estadoInput.value || '').trim() : '';
				if (id) params.set('id', id);
				if (s) params.set('status', s);
				if (p) params.set('priority', p);
				if (u) params.set('user', u);
				if (sigla) params.set('sigla', sigla);
				if (cidade) params.set('cidade', cidade);
				if (estado) params.set('estado', estado);
				params.set('tab', 'chamados');
				location.href = '/?' + params.toString();
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
					if (parts.length === 3) {
						serviceDateText = `${parts[2]}/${parts[1]}/${parts[0]}`;
					} else {
						serviceDateText = rawDate;
					}
				}
				let serviceTimeText = '';
				if (t.service_time) {
					serviceTimeText = String(t.service_time).substring(0, 5);
				}
				modalBody.innerHTML = `
					<div class="grid grid-cols-2 gap-3 text-sm">
						<div><strong>Título:</strong> ${t.title}</div>
						<div><strong>Prioridade:</strong> ${t.priority}</div>
						<div><strong>Categoria:</strong> ${t.category}</div>
						<div><strong>QTD:</strong> ${t.qtd ?? '-'}</div>
						<div class="col-span-2"><strong>Nome do Projeto:</strong> ${ (t.project_name ?? t.projectName) || '-' }</div>
						<div><strong>Nome:</strong> ${t.name}</div>
						<div><strong>Matrícula:</strong> ${t.registration}</div>
						<div><strong>Unidade:</strong> ${t.unit}</div>
						<div><strong>CEP:</strong> ${t.cep}</div>
						<div><strong>Endereço:</strong> ${t.address}${t.address_number ? ', ' + t.address_number : ''}</div>
						<div><strong>Cidade/UF:</strong> ${t.city}/${t.uf}</div>
						${createdDateText ? `<div><strong>Data de abertura:</strong> ${createdDateText}</div>` : ''}
						${createdTimeText ? `<div><strong>Hora de abertura:</strong> ${createdTimeText}</div>` : ''}
						${serviceDateText ? `<div><strong>Data para atendimento:</strong> ${serviceDateText}</div>` : ''}
						${serviceTimeText ? `<div><strong>Hora para atendimento:</strong> ${serviceTimeText}</div>` : ''}
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
						const type = att.file_type || '';
						const name = att.file_name || '';
						const ext = String(name).toLowerCase().split('.').pop();
						const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
						const isImage = type.startsWith('image/') || imageExts.includes(ext);
						if (isImage) {
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
				modalBody.innerHTML = `
					<div class="grid grid-cols-2 gap-3 text-sm">
						<div><strong>Título:</strong> ${t.title}</div>
						<div><strong>Prioridade:</strong> ${t.priority}</div>
						<div><strong>Categoria:</strong> ${t.category}</div>
						<div><strong>QTD:</strong> ${t.qtd ?? '-'}</div>
						<div class="col-span-2"><strong>Nome do Projeto:</strong> ${ (t.project_name ?? t.projectName) || '-' }</div>
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
</script>

<!-- Scripts do Sidebar e Abas -->
<?php include __DIR__ . '/components/sidebar-script.php'; ?>
<?php include __DIR__ . '/components/scripts.php'; ?>

