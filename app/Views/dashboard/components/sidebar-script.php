<script>
	// Gerenciar Sidebar Mobile
	const sidebarToggle = document.getElementById('sidebar-toggle');
	const sidebarClose = document.getElementById('sidebar-close');
	const sidebar = document.getElementById('sidebar');
	const sidebarOverlay = document.getElementById('sidebar-overlay');
	const mainContent = document.getElementById('main-content');
	const SIDEBAR_DESKTOP_KEY = 'controllit-sidebar-desktop';

	function openSidebar() {
		sidebar.classList.remove('-translate-x-full');
		sidebarOverlay.classList.remove('hidden');
		// Adicionar margin esquerda ao main content em desktop
		if (window.innerWidth >= 768) {
			mainContent.classList.add('md:ml-64');
			sessionStorage.setItem(SIDEBAR_DESKTOP_KEY, 'open');
		}
	}

	function closeSidebar() {
		sidebar.classList.add('-translate-x-full');
		sidebarOverlay.classList.add('hidden');
		// Remover margin esquerda do main content
		mainContent.classList.remove('md:ml-64');
		if (window.innerWidth >= 768) {
			sessionStorage.setItem(SIDEBAR_DESKTOP_KEY, 'closed');
		}
	}

	function toggleSidebar() {
		if (sidebar.classList.contains('-translate-x-full')) {
			openSidebar();
		} else {
			closeSidebar();
		}
	}

	const sidebarCloseDesktop = document.getElementById('sidebar-close-desktop');

	sidebarToggle?.addEventListener('click', toggleSidebar);
	sidebarClose?.addEventListener('click', closeSidebar);
	sidebarCloseDesktop?.addEventListener('click', closeSidebar);
	sidebarOverlay?.addEventListener('click', closeSidebar);

	// Gerenciar Abas do Sidebar
	document.querySelectorAll('.sidebar-menu-item').forEach(btn => {
		btn.addEventListener('click', function() {
			const tab = this.dataset.tab;
			
			// Remover classe active de todos os botões
			document.querySelectorAll('.sidebar-menu-item').forEach(b => {
				b.classList.remove('active', 'bg-blue-800', 'border-blue-400');
				b.classList.add('border-transparent');
			});
			
			// Adicionar classe active ao botão clicado
			this.classList.add('active', 'bg-blue-800', 'border-blue-400');
			this.classList.remove('border-transparent');
			
			// Ocultar todas as abas
			document.querySelectorAll('.tab-content').forEach(content => {
				content.classList.add('hidden');
			});
			
			// Mostrar aba selecionada
			const selectedTab = document.getElementById('tab-' + tab);
			if (selectedTab) {
				selectedTab.classList.remove('hidden');
			}
			
			// Fechar sidebar apenas em mobile após clicar em item do menu
			if (window.innerWidth < 768) {
				closeSidebar();
			}
		});
	});

	// Garantir que a primeira aba (Painel Operacional) esteja visível ao carregar
	document.addEventListener('DOMContentLoaded', function() {
		// Verificar se há parâmetro 'tab' na URL
		const urlParams = new URLSearchParams(window.location.search);
		let tabToShow = urlParams.get('tab');
		
		// Se não houver tab explícito, detectar pela presença de filtros
		if (!tabToShow) {
			const hasClosedFilters = urlParams.has('closed_id') || urlParams.has('closed_period') || urlParams.has('closed_user');
			const hasOpenFilters = urlParams.has('id') || urlParams.has('status') || urlParams.has('priority') || urlParams.has('user')
				|| urlParams.has('sigla') || urlParams.has('cidade') || urlParams.has('estado');
			
			if (hasClosedFilters) {
				tabToShow = 'chamados-fechados';
			} else if (hasOpenFilters) {
				tabToShow = 'chamados';
			} else {
				tabToShow = 'painel';
			}
		}
		
		// Ocultar todas as abas
		document.querySelectorAll('.tab-content').forEach(content => {
			content.classList.add('hidden');
		});
		
		// Remover classe active de todos os botões
		document.querySelectorAll('.sidebar-menu-item').forEach(b => {
			b.classList.remove('active', 'bg-blue-800', 'border-blue-400');
			b.classList.add('border-transparent');
		});
		
		// Mostrar aba selecionada
		const selectedTab = document.getElementById('tab-' + tabToShow);
		if (selectedTab) {
			selectedTab.classList.remove('hidden');
		}
		
		// Ativar botão correspondente
		const activeBtn = document.querySelector('[data-tab="' + tabToShow + '"]');
		if (activeBtn) {
			activeBtn.classList.add('active', 'bg-blue-800', 'border-blue-400');
			activeBtn.classList.remove('border-transparent');
		}
		
		// Desktop: respeitar preferência (evita reabrir o menu a cada filtro/recarregar)
		if (window.innerWidth >= 768) {
			if (sessionStorage.getItem(SIDEBAR_DESKTOP_KEY) === 'closed') {
				closeSidebar();
			} else {
				openSidebar();
			}
		}
	});
	
	// Gerenciar responsividade do sidebar
	window.addEventListener('resize', function() {
		if (window.innerWidth >= 768) {
			if (sessionStorage.getItem(SIDEBAR_DESKTOP_KEY) === 'closed') {
				closeSidebar();
			} else {
				openSidebar();
			}
		} else {
			sidebar.classList.add('-translate-x-full');
			sidebarOverlay.classList.add('hidden');
			mainContent.classList.remove('md:ml-64');
		}
	});
</script>
