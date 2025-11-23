<?php
/** @var array $user */
?>
<aside id="sidebar" class="w-64 bg-blue-900 text-white h-screen fixed left-0 top-0 shadow-lg overflow-y-auto transform -translate-x-full transition-transform duration-300 z-50">
	<!-- Logo/Header -->
	<div class="p-6 border-b border-blue-800 flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold">Controll</h1>
			<p class="text-blue-200 text-sm mt-1">Painel de Controle</p>
		</div>
		<!-- Botão fechar apenas no desktop -->
		<button id="sidebar-close-desktop" class="hidden md:block text-white hover:text-blue-200 transition-colors">
			<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
			</svg>
		</button>
	</div>

	<!-- Menu Items -->
	<nav class="mt-6">
		<!-- Painel Operacional -->
		<button class="sidebar-menu-item w-full text-left px-6 py-3 hover:bg-blue-800 transition-colors border-l-4 border-transparent hover:border-blue-400 active" data-tab="painel">
			<div class="flex items-center">
				<svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
					<path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/>
				</svg>
				<span class="font-medium">Painel Operacional</span>
			</div>
			<p class="text-blue-200 text-xs mt-1 ml-8">Dashboard com métricas</p>
		</button>

		<!-- Chamados -->
		<button class="sidebar-menu-item w-full text-left px-6 py-3 hover:bg-blue-800 transition-colors border-l-4 border-transparent hover:border-blue-400" data-tab="chamados">
			<div class="flex items-center">
				<svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
					<path d="M2 5a2 2 0 012-2h12a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V5z"/>
				</svg>
				<span class="font-medium">Chamados</span>
			</div>
			<p class="text-blue-200 text-xs mt-1 ml-8">Gerenciar chamados</p>
		</button>

		<!-- Chamados Fechados -->
		<button class="sidebar-menu-item w-full text-left px-6 py-3 hover:bg-blue-800 transition-colors border-l-4 border-transparent hover:border-blue-400" data-tab="chamados-fechados">
			<div class="flex items-center">
				<svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
					<path d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"/>
				</svg>
				<span class="font-medium">Chamados Fechados</span>
			</div>
			<p class="text-blue-200 text-xs mt-1 ml-8">Histórico de chamados</p>
		</button>

		<!-- Usuários (apenas para suporte/admin) -->
		<?php if (in_array($user['role'], ['support', 'admin'], true)): ?>
			<button class="sidebar-menu-item w-full text-left px-6 py-3 hover:bg-blue-800 transition-colors border-l-4 border-transparent hover:border-blue-400" data-tab="usuarios">
				<div class="flex items-center">
					<svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
						<path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
					</svg>
					<span class="font-medium">Usuários</span>
				</div>
				<p class="text-blue-200 text-xs mt-1 ml-8">Gerenciar usuários</p>
			</button>

			<!-- Relatórios -->
			<button class="sidebar-menu-item w-full text-left px-6 py-3 hover:bg-blue-800 transition-colors border-l-4 border-transparent hover:border-blue-400" data-tab="relatorios">
				<div class="flex items-center">
					<svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
						<path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h12a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6z"/>
					</svg>
					<span class="font-medium">Relatórios</span>
				</div>
				<p class="text-blue-200 text-xs mt-1 ml-8">Exportar relatórios</p>
			</button>
		<?php endif; ?>
	</nav>

	<!-- User Info at Bottom -->
	<div class="absolute bottom-0 left-0 right-0 p-4 border-t border-blue-800 bg-blue-950">
		<div class="flex items-center">
			<div class="w-10 h-10 bg-blue-400 rounded-full flex items-center justify-center text-sm font-bold">
				<?php echo strtoupper(substr($user['name'] ?? 'U', 0, 1)); ?>
			</div>
			<div class="ml-3 flex-1 min-w-0">
				<p class="text-sm font-medium truncate"><?php echo htmlspecialchars($user['name'] ?? 'Usuário'); ?></p>
				<p class="text-xs text-blue-200 truncate"><?php echo htmlspecialchars($user['role'] ?? 'usuario'); ?></p>
			</div>
		</div>
	</div>
</aside>
