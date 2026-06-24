<?php
/** @var array $user */
$initial = strtoupper(substr($user['name'] ?? 'U', 0, 1));
$roleLabels = [
	'admin' => 'Administrador',
	'support' => 'Suporte',
	'user' => 'Usuário',
	'gerente' => 'Gerente',
];
$roleLabel = $roleLabels[$user['role'] ?? ''] ?? ($user['role'] ?? 'Usuário');
?>
<aside id="sidebar" class="sidebar transform -translate-x-full">
	<div class="sidebar-brand">
		<div class="flex items-center justify-between gap-2">
			<div class="flex items-center gap-2.5 min-w-0">
				<?php $variant = 'sidebar'; include BASE_PATH . '/app/Views/components/brand-logos.php'; ?>
				<div class="min-w-0">
					<div class="sidebar-brand-title truncate">Controll IT</div>
					<div class="sidebar-brand-sub">Help Desk C&amp;A</div>
				</div>
			</div>
			<button id="sidebar-close-desktop" type="button" class="hidden md:flex text-slate-400 hover:text-white p-1 rounded-lg hover:bg-white/10 transition-colors" aria-label="Fechar menu">
				<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
			</button>
		</div>
	</div>

	<nav class="sidebar-nav">
		<div class="sidebar-section">Principal</div>
		<button type="button" class="sidebar-menu-item sidebar-item active" data-tab="painel" data-title="Painel Operacional">
			<svg fill="currentColor" viewBox="0 0 20 20"><path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/></svg>
			Painel Operacional
		</button>
		<button type="button" class="sidebar-menu-item sidebar-item" data-tab="chamados" data-title="Chamados">
			<svg fill="currentColor" viewBox="0 0 20 20"><path d="M2 5a2 2 0 012-2h12a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V5z"/></svg>
			Chamados
		</button>
		<button type="button" class="sidebar-menu-item sidebar-item" data-tab="chamados-fechados" data-title="Chamados Fechados">
			<svg fill="currentColor" viewBox="0 0 20 20"><path d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"/></svg>
			Chamados Fechados
		</button>

		<?php require_once BASE_PATH . '/app/Views/helpers/auth.php'; ?>
		<?php if (view_is_staff($user)): ?>
			<button type="button" class="sidebar-menu-item sidebar-item" data-tab="seguranca" data-title="Segurança">
				<svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
				Segurança
			</button>
		<?php endif; ?>
		<?php if (view_is_support_or_admin($user)): ?>
			<div class="sidebar-section mt-2">Administração</div>
			<button type="button" class="sidebar-menu-item sidebar-item" data-tab="usuarios" data-title="Usuários">
				<svg fill="currentColor" viewBox="0 0 20 20"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>
				Usuários
			</button>
			<button type="button" class="sidebar-menu-item sidebar-item" data-tab="relatorios" data-title="Relatórios">
				<svg fill="currentColor" viewBox="0 0 20 20"><path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h12a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6z"/></svg>
				Relatórios
			</button>
			<button type="button" class="sidebar-menu-item sidebar-item" data-tab="diarias-compradas" data-title="Diárias compradas">
				<svg fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4zm0 5v5a2 2 0 002 2h8a2 2 0 002-2V9H4zm3 2h2v3H7v-3z"/></svg>
				Diárias compradas
			</button>
			<?php if (view_is_admin($user)): ?>
				<button type="button" class="sidebar-menu-item sidebar-item" data-tab="logs" data-title="Logs">
					<svg fill="currentColor" viewBox="0 0 20 20"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg>
					Logs
				</button>
				<button type="button" class="sidebar-menu-item sidebar-item" data-tab="configuracoes" data-title="Configurações">
					<svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.19 2.042-.948 2.286-1.56.38-1.56 2.6 0 2.98a1.532 1.532 0 01.948 2.286c-.836 1.372.734 2.942 2.106 2.106.886-.54 2.042-.19 2.286.948.38 1.56 2.6 1.56 2.98 0a1.533 1.533 0 012.286-.948c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.948-2.286c1.56-.38 1.56-2.6 0-2.98a1.532 1.532 0 01-.948-2.286c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.286-.948zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/></svg>
					Configurações
				</button>
			<?php endif; ?>
		<?php endif; ?>

		<div class="sidebar-section mt-2">Projetos</div>
		<button type="button" class="sidebar-menu-item sidebar-item" data-tab="inventario" data-title="Projeto RFID">
			<svg fill="currentColor" viewBox="0 0 20 20"><path d="M4 3a2 2 0 00-2 2v2h16V5a2 2 0 00-2-2H4zM18 9H2v6a2 2 0 002 2h12a2 2 0 002-2V9zm-11 2h6a1 1 0 110 2H7a1 1 0 110-2z"/></svg>
			Projeto RFID
		</button>
		<button type="button" class="sidebar-menu-item sidebar-item" data-tab="sdwan" data-title="Projeto SDWAN">
			<svg fill="currentColor" viewBox="0 0 20 20"><path d="M4 3a2 2 0 00-2 2v2h16V5a2 2 0 00-2-2H4zM18 9H2v6a2 2 0 002 2h12a2 2 0 002-2V9zm-11 2h6a1 1 0 110 2H7a1 1 0 110-2z"/></svg>
			Projeto SDWAN
		</button>
	</nav>

	<div class="sidebar-footer">
		<div class="sidebar-user">
			<div class="sidebar-avatar"><?php echo htmlspecialchars($initial); ?></div>
			<div class="min-w-0 flex-1">
				<p class="text-sm font-semibold text-white truncate"><?php echo htmlspecialchars($user['name'] ?? 'Usuário'); ?></p>
				<p class="text-xs text-slate-400 truncate"><?php echo htmlspecialchars($roleLabel); ?></p>
			</div>
			<a href="/logout" class="text-slate-400 hover:text-white p-1.5 rounded-lg hover:bg-white/10 transition-colors" title="Sair">
				<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
			</a>
		</div>
	</div>
</aside>
