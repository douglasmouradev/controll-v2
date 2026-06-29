<script>
	const sidebarToggle = document.getElementById('sidebar-toggle');
	const sidebarClose = document.getElementById('sidebar-close');
	const sidebar = document.getElementById('sidebar');
	const sidebarOverlay = document.getElementById('sidebar-overlay');
	const mainContent = document.getElementById('main-content');
	const pageTitleEl = document.getElementById('page-title');
	const SIDEBAR_DESKTOP_KEY = 'controllit-sidebar-desktop';

	function setActiveSidebarItem(btn) {
		document.querySelectorAll('.sidebar-menu-item').forEach((b) => b.classList.remove('active'));
		btn.classList.add('active');
		if (pageTitleEl && btn.dataset.title) {
			pageTitleEl.textContent = btn.dataset.title;
		}
	}

	window.switchDashboardTab = function (tabId, options) {
		const btn = document.querySelector('[data-tab="' + tabId + '"]');
		if (!btn) return;
		setActiveSidebarItem(btn);
		document.querySelectorAll('.tab-content').forEach((content) => content.classList.add('hidden'));
		const selectedTab = document.getElementById('tab-' + tabId);
		if (selectedTab) selectedTab.classList.remove('hidden');
		if (tabId === 'sdwan' && !(options && options.skipAcupadEvent)) {
			document.dispatchEvent(new CustomEvent('acupad-tab-open'));
			const params = new URLSearchParams(window.location.search);
			if (params.get('tab') !== 'sdwan') {
				params.set('tab', 'sdwan');
				const hash = window.location.hash || '';
				window.history.replaceState({}, '', '?' + params.toString() + hash);
			}
		}
	};

	function openSidebar() {
		sidebar.classList.remove('-translate-x-full');
		sidebarOverlay.classList.remove('hidden');
		if (window.innerWidth >= 768) {
			mainContent.classList.add('md:ml-[16.5rem]');
			sessionStorage.setItem(SIDEBAR_DESKTOP_KEY, 'open');
		}
	}

	function closeSidebar() {
		sidebar.classList.add('-translate-x-full');
		sidebarOverlay.classList.add('hidden');
		mainContent.classList.remove('md:ml-[16.5rem]');
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

	document.querySelectorAll('.sidebar-menu-item').forEach((btn) => {
		btn.addEventListener('click', function () {
			const tab = this.dataset.tab;
			window.switchDashboardTab(tab);
			if (window.innerWidth < 768) closeSidebar();
		});
	});

	document.addEventListener('DOMContentLoaded', function () {
		const urlParams = new URLSearchParams(window.location.search);
		let tabToShow = urlParams.get('tab');
		if (!tabToShow) {
			const hasClosedFilters = urlParams.has('closed_id') || urlParams.has('closed_period') || urlParams.has('closed_user');
			const hasOpenFilters = urlParams.has('id') || urlParams.has('status') || urlParams.has('priority') || urlParams.has('user')
				|| urlParams.has('sigla') || urlParams.has('cidade') || urlParams.has('estado');
			if (hasClosedFilters) tabToShow = 'chamados-fechados';
			else if (hasOpenFilters) tabToShow = 'chamados';
			else tabToShow = 'painel';
		}
		document.querySelectorAll('.tab-content').forEach((content) => content.classList.add('hidden'));
		const selectedTab = document.getElementById('tab-' + tabToShow);
		if (selectedTab) selectedTab.classList.remove('hidden');
		const activeBtn = document.querySelector('[data-tab="' + tabToShow + '"]');
		if (activeBtn) setActiveSidebarItem(activeBtn);
		if (tabToShow === 'sdwan') {
			document.dispatchEvent(new CustomEvent('acupad-tab-open'));
		}
		if (window.innerWidth >= 768) {
			if (sessionStorage.getItem(SIDEBAR_DESKTOP_KEY) === 'closed') closeSidebar();
			else openSidebar();
		}
	});

	window.addEventListener('resize', function () {
		if (window.innerWidth >= 768) {
			if (sessionStorage.getItem(SIDEBAR_DESKTOP_KEY) === 'closed') closeSidebar();
			else openSidebar();
		} else {
			sidebar.classList.add('-translate-x-full');
			sidebarOverlay.classList.add('hidden');
			mainContent.classList.remove('md:ml-[16.5rem]');
		}
	});

	document.getElementById('btn-abrir-chamado-top')?.addEventListener('click', () => {
		if (typeof openNewTicketModal === 'function') {
			openNewTicketModal();
		} else {
			document.getElementById('btn-abrir-chamado-painel')?.click();
		}
	});
</script>
