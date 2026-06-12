<?php
/** @var array $user */
/** @var array $tickets */
/** @var array $filters */
/** @var array $stats */
/** @var array $users */
/** @var array $ticket_pagination */
/** @var array $closed_pagination */
/** @var array $access_logs */
require_once BASE_PATH . '/app/Views/helpers/auth.php';
$users = $users ?? [];
$ticket_pagination = $ticket_pagination ?? ['page' => 1, 'per_page' => 50, 'total' => 0, 'pages' => 1];
$closed_pagination = $closed_pagination ?? ['page' => 1, 'per_page' => 50, 'total' => 0, 'pages' => 1];
$access_logs = $access_logs ?? [];
?>

<!-- Sidebar -->
<?php include __DIR__ . '/components/sidebar.php'; ?>

<div id="sidebar-overlay" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm hidden z-40"></div>

<div id="main-content" class="transition-all duration-300 min-h-screen">
	<header class="topbar">
		<div class="topbar-left">
			<button id="sidebar-toggle" type="button" class="btn btn-ghost p-2" aria-label="Abrir menu">
				<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
			</button>
			<div class="min-w-0">
				<h1 id="page-title" class="topbar-title">Painel Operacional</h1>
				<p class="topbar-breadcrumb hidden sm:block">Controll IT Help Desk</p>
			</div>
		</div>
		<div class="flex items-center gap-3">
			<?php if (view_is_admin($user) && !empty($maintenance_mode)): ?>
				<span id="maintenance-topbar-badge" class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-amber-100 text-amber-900 border border-amber-200 px-3 py-1 text-xs font-semibold">
					Manutenção ativa
				</span>
			<?php else: ?>
				<span id="maintenance-topbar-badge" class="hidden"></span>
			<?php endif; ?>
			<?php $variant = 'topbar'; include BASE_PATH . '/app/Views/components/brand-logos.php'; ?>
			<div class="relative" id="notifications-root">
				<button type="button" id="btn-notifications" class="btn btn-ghost p-2 relative" aria-label="Notificações">
					<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
					<span id="notifications-badge" class="hidden absolute -top-0.5 -right-0.5 min-w-[1.1rem] h-[1.1rem] rounded-full bg-accent text-white text-[10px] font-bold flex items-center justify-center px-1">0</span>
				</button>
				<div id="notifications-panel" class="hidden absolute right-0 mt-2 w-80 max-h-96 overflow-y-auto bg-white border border-slate-200 rounded-xl shadow-lg z-50">
					<div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
						<span class="font-semibold text-sm text-slate-800">Notificações</span>
						<button type="button" id="notifications-mark-all" class="text-xs text-brand hover:underline">Marcar todas</button>
					</div>
					<div id="notifications-list" class="divide-y divide-slate-100"></div>
				</div>
			</div>
			<span class="hidden md:inline text-sm text-slate-500"><?php echo htmlspecialchars($user['name'] ?? ''); ?></span>
			<button type="button" id="btn-abrir-chamado-top" class="btn btn-primary btn-sm">+ Chamado</button>
		</div>
	</header>

<div class="max-w-[1400px] mx-auto px-4 md:px-6 py-6">
	<!-- Componentes de Tabs -->
	<?php include __DIR__ . '/components/painel-tab.php'; ?>
	<?php include __DIR__ . '/components/chamados-tab.php'; ?>
	<?php include __DIR__ . '/components/chamados-fechados-tab.php'; ?>
	<?php if (view_is_admin($user)): ?>
		<?php include __DIR__ . '/components/configuracoes-tab.php'; ?>
		<?php include __DIR__ . '/components/logs-tab.php'; ?>
	<?php endif; ?>
	<?php if (view_is_staff($user)): ?>
		<?php include __DIR__ . '/components/seguranca-tab.php'; ?>
	<?php endif; ?>
	<?php if (view_is_support_or_admin($user)): ?>
		<?php include __DIR__ . '/components/usuarios-tab.php'; ?>
		<?php include __DIR__ . '/components/relatorios-tab.php'; ?>
		<?php include __DIR__ . '/components/diarias-compradas-tab.php'; ?>
	<?php endif; ?>
	<?php include __DIR__ . '/components/inventario-tab.php'; ?>
</div>

<!-- Modal de Abrir Chamado -->
<dialog id="modal-abrir-chamado" class="ui-modal">
	<div class="ui-modal-header">
		<h2 class="text-lg font-semibold">Abrir Novo Chamado</h2>
		<p class="text-sm text-blue-100 mt-0.5 opacity-90">Preencha os dados do atendimento</p>
	</div>
	<div class="ui-modal-body">
		<form id="new-ticket-form" class="grid grid-cols-1 md:grid-cols-2 gap-4" enctype="multipart/form-data">
			<?php echo \App\Services\Csrf::field(); ?>
			<input type="hidden" name="ticket_id" id="ticket_id">
			<input type="hidden" name="original_qtd" id="original_qtd">

			<div class="form-section">
				<p class="form-section-title">Chamado</p>
				<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
					<div class="md:col-span-2">
						<label class="label">Título</label>
						<input class="input" name="title" placeholder="Descreva o problema" required>
					</div>
					<div>
						<label class="label">Prioridade</label>
						<select class="select" name="priority" required>
							<option value="">Selecione</option>
							<option>Baixa</option><option>Média</option><option>Alta</option>
						</select>
					</div>
					<div>
						<label class="label">Categoria</label>
						<select class="select" name="category" required>
							<option value="">Selecione</option>
							<option>Ticket</option>
							<option>Diária</option>
							<option>Uso Geral</option>
							<option>Projeto</option>
						</select>
					</div>
					<div id="project-name-field" class="md:col-span-2 hidden">
						<label class="label">Nome do projeto</label>
						<input class="input" name="project_name" id="project_name" placeholder="Nome do projeto">
					</div>
					<div class="md:col-span-2">
						<label class="label">Descrição</label>
						<textarea class="textarea" name="description" placeholder="Detalhes do atendimento" rows="4" required></textarea>
					</div>
				</div>
			</div>

			<div class="form-section">
				<p class="form-section-title">Solicitante</p>
				<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
					<div>
						<label class="label">Nome</label>
						<input class="input" name="name" placeholder="Nome completo" required>
					</div>
					<div>
						<label class="label">Matrícula</label>
						<input class="input" name="registration" placeholder="Matrícula">
					</div>
					<div>
						<label class="label">Sigla da loja</label>
						<input class="input" name="unit" placeholder="Ex: SP01" style="text-transform: uppercase;" oninput="this.value = this.value.toUpperCase();" required>
					</div>
				</div>
			</div>

			<div class="form-section">
				<p class="form-section-title">Local</p>
				<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
					<div class="md:col-span-2">
						<label class="label">CEP</label>
						<input class="input" name="cep" id="cep" placeholder="00000-000" required>
					</div>
					<div class="md:col-span-2">
						<label class="label">Endereço</label>
						<input class="input" name="address" id="address" placeholder="Rua, avenida..." required>
					</div>
					<div>
						<label class="label">Número</label>
						<input class="input" name="address_number" id="address_number" placeholder="Nº">
					</div>
					<div>
						<label class="label">Cidade</label>
						<input class="input" name="city" id="city" placeholder="Cidade">
					</div>
					<div>
						<label class="label">UF</label>
						<input class="input" name="uf" id="uf" placeholder="UF" maxlength="2">
					</div>
				</div>
			</div>

			<div class="form-section">
				<p class="form-section-title">Atendimento</p>
				<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
					<div>
						<label class="label">Técnico (opcional)</label>
						<input class="input" name="technician_name" placeholder="Nome do técnico">
					</div>
					<div>
						<label class="label">RG do técnico</label>
						<input class="input" name="technician_rg" placeholder="RG">
					</div>
					<div>
						<label class="label">CPF do técnico</label>
						<input class="input" name="technician_cpf" placeholder="CPF">
					</div>
					<div>
						<label class="label">Data de atendimento</label>
						<input class="input" type="date" name="service_date">
					</div>
					<div>
						<label class="label">Hora de atendimento</label>
						<input class="input" type="time" name="service_time">
					</div>
					<div>
						<label class="label">Pedido interno</label>
						<input class="input" name="internal_order" placeholder="Nº pedido">
					</div>
					<div>
						<label class="label">Nota fiscal</label>
						<input class="input" name="invoice" placeholder="NF">
					</div>
					<div>
						<label class="label">Destino da diária</label>
						<input class="input" name="daily_destination" placeholder="Destino">
					</div>
					<div>
						<label class="label">Quantidade</label>
						<div class="flex items-center gap-2">
							<button type="button" id="qtd-minus" class="btn btn-secondary btn-sm px-3">−</button>
							<input type="number" name="qtd" id="qtd" class="input w-20 text-center" min="0" step="1" value="1">
							<button type="button" id="qtd-plus" class="btn btn-secondary btn-sm px-3">+</button>
						</div>
					</div>
				</div>
			</div>

			<div class="form-section">
				<p class="form-section-title">Anexos</p>
				<label class="label">Arquivos (PDF ou imagem, até 20 arquivos)</label>
				<input type="file" id="ticket-attachments" name="attachments[]" multiple accept=".pdf,image/*" class="input">
				<div id="ticket-attachments-list" class="mt-2 space-y-1 text-sm text-slate-600"></div>
				<div id="ticket-existing-attachments" class="mt-4 space-y-2 text-sm text-slate-600"></div>
			</div>
		</form>
	</div>
	<div class="ui-modal-footer">
		<button type="button" id="cancelar-chamado" class="btn btn-secondary">Cancelar</button>
		<button type="submit" form="new-ticket-form" id="ticket-form-submit" class="btn btn-primary">Abrir chamado</button>
	</div>
</dialog>

<!-- Modal de Criar/Editar Usuário -->
<dialog id="modal-usuario" class="ui-modal">
	<div class="ui-modal-header">
		<h2 class="text-lg font-semibold" id="modal-usuario-title">Criar usuário</h2>
	</div>
	<div class="ui-modal-body">
		<form id="user-form" class="space-y-4">
			<input type="hidden" id="user-id" name="id">
			<div>
				<label class="label">Nome</label>
				<input type="text" id="user-name" name="name" class="input" required>
			</div>
			<div>
				<label class="label">E-mail</label>
				<input type="email" id="user-email" name="email" class="input" required>
			</div>
			<div>
				<label class="label">Senha</label>
				<input type="password" id="user-password" name="password" class="input">
				<p class="text-xs text-slate-500 mt-1 hidden" id="password-hint">Deixe em branco para manter a senha atual ao editar</p>
			</div>
			<div>
				<label class="label">Perfil</label>
				<select id="user-role" name="role" class="select" required>
					<option value="usuario">Usuário</option>
					<option value="suporte">Suporte</option>
					<option value="admin">Admin</option>
				</select>
			</div>
		</form>
	</div>
	<div class="ui-modal-footer">
		<button type="button" id="cancelar-usuario" class="btn btn-secondary">Cancelar</button>
		<button type="submit" form="user-form" id="user-form-submit" class="btn btn-primary">Salvar</button>
	</div>
</dialog>

<dialog id="modal-credits" class="ui-modal" style="max-width: 28rem;">
	<div class="ui-modal-header">
		<h2 class="text-lg font-semibold">Ajustar créditos</h2>
		<p class="text-sm text-blue-100 mt-1" id="credits-modal-user-name"></p>
		<p class="text-xs text-blue-100 opacity-90" id="credits-type-label"></p>
	</div>
	<div class="ui-modal-body space-y-4">
		<div>
			<p class="text-sm text-slate-500">Saldo atual</p>
			<p class="text-2xl font-bold text-slate-900"><span id="credits-current"></span></p>
		</div>
		<div>
			<label class="label">Ajuste</label>
			<div class="flex items-center gap-2">
				<button type="button" id="credits-minus" class="btn btn-secondary btn-sm px-3">−</button>
				<input type="number" id="credits-delta" class="input w-24 text-center" value="0" step="1">
				<button type="button" id="credits-plus" class="btn btn-secondary btn-sm px-3">+</button>
			</div>
			<p class="text-xs text-slate-500 mt-1">Positivo adiciona; negativo remove créditos.</p>
		</div>
		<div>
			<p class="text-sm text-slate-500">Saldo após ajuste</p>
			<p class="text-lg font-semibold text-slate-900"><span id="credits-preview"></span></p>
		</div>
		<input type="hidden" id="credits-user-id">
		<input type="hidden" id="credits-type">
	</div>
	<div class="ui-modal-footer">
		<button type="button" id="credits-cancel" class="btn btn-secondary">Cancelar</button>
		<button type="button" id="credits-apply" class="btn btn-primary">Aplicar</button>
	</div>
</dialog>

<!-- Modal de Detalhes do Chamado -->
<dialog id="ticket-modal" class="ui-modal">
	<div class="ui-modal-header">
		<h2 class="text-lg font-semibold">Detalhes do chamado</h2>
	</div>
	<div class="ui-modal-body space-y-2" id="ticket-modal-body"></div>
	<?php if (view_is_support_or_admin($user)): ?>
		<div class="ui-modal-body border-t border-slate-100 pt-4">
			<label class="label">Resposta do suporte</label>
			<textarea id="support-response" rows="4" class="textarea" placeholder="Digite sua resposta para o usuário..."></textarea>
			<div class="mt-3">
				<label class="label">Anexar imagens</label>
				<input type="file" id="support-images" accept="image/*" multiple class="input">
				<div id="image-preview" class="mt-2 grid grid-cols-3 gap-2"></div>
			</div>
			<button type="button" id="btn-save-response" class="btn btn-primary mt-3">Salvar resposta</button>
		</div>
	<?php endif; ?>
	<div class="ui-modal-footer flex-wrap">
		<?php if (view_is_support_or_admin($user)): ?>
			<div class="flex gap-2 mr-auto">
				<button type="button" data-status="Aberto" class="status-btn btn btn-secondary btn-sm">Aberto</button>
				<button type="button" data-status="Em andamento" class="status-btn btn btn-secondary btn-sm">Em andamento</button>
				<button type="button" data-status="Agendado" class="status-btn btn btn-secondary btn-sm">Agendado</button>
				<button type="button" data-status="Fechado" class="status-btn btn btn-secondary btn-sm">Fechado</button>
			</div>
		<?php endif; ?>
		<button type="button" id="modal-close" class="btn btn-primary">Fechar</button>
	</div>
</dialog>

<!-- Scripts do Sidebar e Abas -->
<?php include __DIR__ . '/components/sidebar-script.php'; ?>
<script src="/assets/js/dashboard/utils.js"></script>
<script src="/assets/js/dashboard/charts.js"></script>
<script src="/assets/js/dashboard/notifications.js"></script>
<script src="/assets/js/dashboard/tickets.js"></script>
<?php if (view_is_staff($user)): ?>
	<script src="/assets/js/dashboard/security.js"></script>
<?php endif; ?>
<?php if (view_is_admin($user)): ?>
	<script src="/assets/js/dashboard/maintenance.js"></script>
<?php endif; ?>
<script src="/assets/js/dashboard/users.js"></script>

