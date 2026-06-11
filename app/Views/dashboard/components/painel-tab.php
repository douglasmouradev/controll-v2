<?php
/** @var array $stats */
/** @var int $closed_tickets */
?>
<div id="tab-painel" class="tab-content px-4 md:px-0" data-user-id="<?php echo (int) $user['id']; ?>">
	<!-- Título e Subtítulo -->
	<div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
		<div>
			<h1 class="text-3xl font-bold text-blue-900 mb-2">Painel Operacional</h1>
			<p class="text-gray-600">Gestão completa de chamados e diárias</p>
		</div>
		<div class="flex flex-wrap gap-2">
			<button id="btn-abrir-chamado" class="bg-blue-700 text-white px-6 py-3 rounded hover:bg-blue-800 font-semibold flex-shrink-0">Abrir Chamado</button>
			<?php if ($user['role'] === 'admin'): ?>
				<button id="btn-global-credits-ticket" class="bg-purple-700 text-white px-4 py-3 rounded hover:bg-purple-800 font-semibold text-sm flex-shrink-0" title="Ajustar Créditos Ticket para todos os usuários">Créditos Ticket</button>
				<button id="btn-global-credits-daily" class="bg-indigo-700 text-white px-4 py-3 rounded hover:bg-indigo-800 font-semibold text-sm flex-shrink-0" title="Ajustar Créditos Diária para todos os usuários">Créditos Diária</button>
				<button id="btn-global-credits-project" class="bg-orange-700 text-white px-4 py-3 rounded hover:bg-orange-800 font-semibold text-sm flex-shrink-0" title="Ajustar Créditos Projeto para todos os usuários">Créditos Projeto</button>
				<button id="btn-reset-credits-ticket" class="bg-red-700 text-white px-4 py-3 rounded hover:bg-red-800 font-semibold text-xs flex-shrink-0" title="Zerar créditos e histórico de Ticket de todos os usuários">Zerar Ticket</button>
				<button id="btn-reset-credits-daily" class="bg-red-700 text-white px-4 py-3 rounded hover:bg-red-800 font-semibold text-xs flex-shrink-0" title="Zerar créditos e histórico de Diária de todos os usuários">Zerar Diária</button>
				<button id="btn-reset-credits-project" class="bg-red-700 text-white px-4 py-3 rounded hover:bg-red-800 font-semibold text-xs flex-shrink-0" title="Zerar créditos e histórico de Projeto de todos os usuários">Zerar Projeto</button>
			<?php endif; ?>
		</div>
	</div>

	<!-- Cards de Métricas -->
	<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
		<!-- Card Créditos Ticket -->
		<div class="bg-white rounded-lg shadow-md p-6 flex flex-col hover:shadow-lg transition">
			<div class="flex items-start justify-between mb-4">
				<div class="flex-1">
					<p class="text-gray-600 text-xs font-semibold uppercase tracking-wide mb-1">Créditos Ticket</p>
					<p class="text-gray-500 text-xs mb-3">Disponível</p>
				</div>
				<div class="text-blue-900 ml-2">
					<svg class="w-10 h-10" fill="currentColor" viewBox="0 0 427.058 427.058">
						<path d="M364.845 11.045a19.692 19.692 0 0 0-21.416 3.254l-27.754 24.575-37.76-33.777c-7.586-6.796-19.071-6.796-26.657 0l-37.729 33.745-37.738-33.745c-7.588-6.796-19.074-6.796-26.662 0l-37.77 33.777L83.579 14.3c-8.197-7.286-20.749-6.547-28.035 1.65a19.858 19.858 0 0 0-5.015 13.329v368.5c-.065 10.983 8.786 19.939 19.769 20.004a19.891 19.891 0 0 0 13.331-5.024l27.754-24.575 37.76 33.777c7.586 6.796 19.071 6.796 26.657 0l37.729-33.749 37.735 33.745c7.588 6.795 19.074 6.795 26.662 0l37.77-33.776 27.78 24.574c8.196 7.288 20.748 6.552 28.035-1.644a19.86 19.86 0 0 0 5.018-13.336V29.279a19.685 19.685 0 0 0-11.684-18.234zm-35.94 362.156c-7.587-6.721-19.007-6.691-26.558.071l-37.759 33.776-37.73-33.745c-7.587-6.796-19.073-6.796-26.66 0l-37.734 33.745-37.775-33.776c-7.522-6.761-18.922-6.792-26.481-.072l-27.679 24.568v-53.305l-.1-315.179 27.724 24.569c7.587 6.721 19.007 6.691 26.558-.071l37.759-33.776 37.73 33.745c7.587 6.796 19.073 6.796 26.66 0l37.734-33.745 37.775 33.776c7.522 6.761 18.922 6.792 26.481.072l27.679-24.575v253.312l.1 115.179-27.724-24.569z"/>
						<path d="M308.322 203.527H118.736c-5.523 0-10 4.477-10 10s4.477 10 10 10h189.586c5.523 0 10-4.477 10-10s-4.478-10-10-10zM218.322 143.527h-99.586c-5.523 0-10 4.477-10 10s4.477 10 10 10h99.586c5.523 0 10-4.477 10-10s-4.478-10-10-10zM308.322 263.527H118.736c-5.523 0-10 4.477-10 10s4.477 10 10 10h189.586c5.523 0 10-4.477 10-10s-4.478-10-10-10z"/>
					</svg>
				</div>
			</div>
			<div class="flex-1">
				<p class="text-4xl font-bold text-blue-900" id="ticket-available">0</p>
			</div>
			<div class="mt-4 pt-4 border-t border-gray-200">
				<p class="text-xs text-gray-600 mb-3" id="ticket-summary">Comprados 0 / Consumidos 0 / Disponível 0</p>
				<button onclick="openCreditExtract('ticket', 0)" class="w-full bg-blue-700 text-white text-sm py-2 rounded hover:bg-blue-800 transition">Ver Mais</button>
			</div>
		</div>

		<!-- Card Créditos Diária -->
		<div class="bg-white rounded-lg shadow-md p-6 flex flex-col hover:shadow-lg transition">
			<div class="flex items-start justify-between mb-4">
				<div class="flex-1">
					<p class="text-gray-600 text-xs font-semibold uppercase tracking-wide mb-1">Créditos Diária</p>
					<p class="text-gray-500 text-xs mb-3">Disponível</p>
				</div>
				<div class="text-purple-700 ml-2">
					<svg class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24">
						<path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/>
					</svg>
				</div>
			</div>
			<div class="flex-1">
				<p class="text-4xl font-bold text-purple-700" id="daily-available">0</p>
			</div>
			<div class="mt-4 pt-4 border-t border-gray-200">
				<p class="text-xs text-gray-600 mb-3" id="daily-summary">Comprados 0 / Consumidos 0 / Disponível 0</p>
				<button onclick="openCreditExtract('daily', 0)" class="w-full bg-purple-700 text-white text-sm py-2 rounded hover:bg-purple-800 transition">Ver Mais</button>
			</div>
		</div>

		<!-- Card Diárias Projeto -->
		<div class="bg-white rounded-lg shadow-md p-6 flex flex-col hover:shadow-lg transition">
			<div class="flex items-start justify-between mb-4">
				<div class="flex-1">
					<p class="text-gray-600 text-xs font-semibold uppercase tracking-wide mb-1">Diárias Projeto</p>
					<p class="text-gray-500 text-xs mb-3">Disponível</p>
				</div>
				<div class="text-orange-600 ml-2">
					<svg class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24">
						<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
					</svg>
				</div>
			</div>
			<div class="flex-1">
				<p class="text-4xl font-bold text-orange-600" id="project-dailies-available">0</p>
			</div>
			<div class="mt-4 pt-4 border-t border-gray-200">
				<p class="text-xs text-gray-600 mb-3" id="project-dailies-summary">Comprados 0 / Consumidos 0 / Disponível 0</p>
				<button onclick="openCreditExtract('project_dailies', 0)" class="w-full bg-orange-600 text-white text-sm py-2 rounded hover:bg-orange-700 transition">Ver Mais</button>
			</div>
		</div>

		<!-- Card Diárias Utilizadas -->
		<div class="bg-white rounded-lg shadow-md p-6 flex flex-col hover:shadow-lg transition">
			<div class="flex items-start justify-between mb-4">
				<div class="flex-1">
					<p class="text-gray-600 text-xs font-semibold uppercase tracking-wide mb-1">Diárias Utilizadas</p>
					<p class="text-gray-500 text-xs mb-3">Consumidas</p>
				</div>
				<div class="text-red-600 ml-2">
					<svg class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24">
						<path d="M11 7h2v7h-2V7zm0 9h2v2h-2v-2z"/>
						<path d="M1 21h22L12 2 1 21z"/>
					</svg>
				</div>
			</div>
			<div class="flex-1">
				<p class="text-4xl font-bold text-red-600" id="daily-used">0</p>
			</div>
			<div class="mt-4 pt-4 border-t border-gray-200">
				<p class="text-xs text-gray-600 mb-3">Total de diárias já utilizadas no período</p>
			</div>
		</div>

		<!-- Card Total Créditos Ticket (Admin) -->
		<?php if (false && (($user['role'] ?? null) === 'admin')): ?>
		<div class="bg-white rounded-lg shadow-md p-6 flex flex-col">
			<div class="flex items-start justify-between mb-4">
				<div class="flex-1">
					<p class="text-gray-600 text-xs font-semibold uppercase tracking-wide mb-1">Total Créditos Ticket</p>
					<p class="text-gray-500 text-xs mb-3">Todos os usuários</p>
				</div>
				<div class="text-blue-900 ml-2">
					<svg class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24">
						<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
					</svg>
				</div>
			</div>
			<div class="flex-1">
				<p class="text-4xl font-bold text-blue-900"><?php echo isset($stats['total_ticket_credits']) ? (int) $stats['total_ticket_credits'] : 0; ?></p>
			</div>
		</div>

		<!-- Card Total Créditos Diária (Admin) -->
		<div class="bg-white rounded-lg shadow-md p-6 flex flex-col">
			<div class="flex items-start justify-between mb-4">
				<div class="flex-1">
					<p class="text-gray-600 text-xs font-semibold uppercase tracking-wide mb-1">Total Créditos Diária</p>
					<p class="text-gray-500 text-xs mb-3">Todos os usuários</p>
				</div>
				<div class="text-purple-700 ml-2">
					<svg class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24">
						<path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/>
					</svg>
				</div>
			</div>
			<div class="flex-1">
				<p class="text-4xl font-bold text-purple-700"><?php echo isset($stats['total_daily_credits']) ? (int) $stats['total_daily_credits'] : 0; ?></p>
			</div>
		</div>

		<!-- Card Total Créditos Diárias Projeto (Admin) -->
		<div class="bg-white rounded-lg shadow-md p-6 flex flex-col">
			<div class="flex items-start justify-between mb-4">
				<div class="flex-1">
					<p class="text-gray-600 text-xs font-semibold uppercase tracking-wide mb-1">Total Créditos Projeto</p>
					<p class="text-gray-500 text-xs mb-3">Todos os usuários</p>
				</div>
				<div class="text-orange-600 ml-2">
					<svg class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24">
						<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
					</svg>
				</div>
			</div>
			<div class="flex-1">
				<p class="text-4xl font-bold text-orange-600"><?php echo isset($stats['total_project_dailies_credits']) ? (int) $stats['total_project_dailies_credits'] : 0; ?></p>
			</div>
		</div>
		<?php endif; ?>

		<div class="bg-white rounded-lg shadow-md p-6">
			<div class="flex items-center justify-between">
				<div>
					<p class="text-gray-600 text-sm mb-2">Total de Chamados</p>
					<p class="text-3xl font-bold text-blue-900"><?php echo $stats['total_tickets']; ?></p>
				</div>
				<div class="text-blue-900">
					<svg class="w-14 h-14" fill="currentColor" viewBox="0 0 24 24">
						<path d="M6 2c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6H6zm0 2h7v5h5v11H6V4z" opacity="0.9"/>
						<path d="M4 4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-5-5H4z" opacity="0.6" transform="translate(2, 2)"/>
					</svg>
				</div>
			</div>
		</div>
		<div class="bg-white rounded-lg shadow-md p-6">
			<div class="flex items-center justify-between">
				<div>
					<p class="text-gray-600 text-sm mb-2">Chamados Fechados</p>
					<p class="text-3xl font-bold text-green-600"><?php echo $stats['closed_tickets'] ?? 0; ?></p>
				</div>
				<div class="text-green-600">
					<svg class="w-14 h-14" fill="currentColor" viewBox="0 0 20 20">
						<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
					</svg>
				</div>
			</div>
		</div>
		<!-- Card Usuários (apenas admin e support) -->
		<?php if (in_array($user['role'] ?? null, ['admin', 'support'], true)): ?>
		<div class="bg-white rounded-lg shadow-md p-6">
			<div class="flex items-center justify-between">
				<div>
					<p class="text-gray-600 text-sm mb-2">Usuários</p>
					<p class="text-3xl font-bold text-blue-900"><?php echo $stats['total_users'] ?: $stats['support_agents']; ?></p>
				</div>
				<div class="text-blue-900">
					<svg class="w-14 h-14" fill="currentColor" viewBox="0 0 20 20">
						<path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
					</svg>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<div class="bg-white rounded-lg shadow-md p-6">
			<div class="flex items-center justify-between">
				<div>
					<p class="text-gray-600 text-sm mb-2">Agentes de Suporte</p>
					<p class="text-3xl font-bold text-blue-900"><?php echo $stats['support_agents']; ?></p>
				</div>
				<div class="text-blue-900">
					<svg class="w-14 h-14" fill="currentColor" viewBox="0 0 20 20">
						<path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
					</svg>
				</div>
			</div>
		</div>
		<?php if ($user['role'] === 'admin'): ?>
			<div class="bg-white rounded-lg shadow-md p-6">
				<div class="flex items-center justify-between">
					<div>
						<p class="text-gray-600 text-sm mb-2">Tempo Médio Resolução</p>
						<p class="text-3xl font-bold text-blue-900"><?php echo $stats['avg_resolution_hours']; ?>h</p>
					</div>
					<div class="text-blue-900">
						<svg class="w-14 h-14" fill="currentColor" viewBox="0 0 20 20">
							<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
						</svg>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<!-- Seção de Gráficos -->
		<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
			<section class="bg-white rounded-lg shadow p-6">
				<h2 class="text-blue-700 font-semibold mb-4 text-lg">Créditos Ticket</h2>
				<p class="text-gray-600 text-sm mb-4">Distribuição: Comprados / Consumidos / Disponível</p>
				<div class="h-72">
					<canvas id="credits-ticket-pie" class="w-full h-full"></canvas>
				</div>
			</section>
			<section class="bg-white rounded-lg shadow p-6">
				<h2 class="text-blue-700 font-semibold mb-4 text-lg">Créditos Diária</h2>
				<p class="text-gray-600 text-sm mb-4">Distribuição: Comprados / Consumidos / Disponível</p>
				<div class="h-72">
					<canvas id="credits-daily-pie" class="w-full h-full"></canvas>
				</div>
			</section>
			<section class="bg-white rounded-lg shadow p-6">
				<h2 class="text-blue-700 font-semibold mb-4 text-lg">Diárias Projeto</h2>
				<p class="text-gray-600 text-sm mb-4">Distribuição: Comprados / Consumidos / Disponível</p>
				<div class="h-72">
					<canvas id="credits-project-pie" class="w-full h-full"></canvas>
				</div>
			</section>
		</div>


	<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
		<!-- Gráfico de Diárias por Dia -->
		<section class="bg-white rounded-lg shadow p-6">
			<h2 class="text-blue-700 font-semibold mb-4 text-lg">Chamados por Dia</h2>
			<p class="text-gray-600 text-sm mb-4">Evolução diária de chamados abertos</p>
			<div class="h-80">
				<canvas id="dailies-chart" class="w-full h-full"></canvas>
			</div>
		</section>

		<!-- Gráfico de Distribuição por Status -->
		<section class="bg-white rounded-lg shadow p-6">
			<h2 class="text-blue-700 font-semibold mb-4 text-lg">Distribuição por Status</h2>
			<p class="text-gray-600 text-sm mb-4">Análise do status dos chamados</p>
			<div class="h-80">
				<canvas id="status-chart" class="w-full h-full"></canvas>
			</div>
		</section>
	</div>

	<section class="bg-white rounded-lg shadow p-6 mb-6">
		<h2 class="text-blue-700 font-semibold mb-4 text-lg">Uso de Diárias por Destino</h2>
		<p class="text-gray-600 text-sm mb-4">Onde as diárias foram utilizadas</p>
		<div class="h-80">
			<canvas id="daily-destination-chart" class="w-full h-full"></canvas>
		</div>
	</section>
</div>

<!-- Modal de Extrato de Créditos -->
<dialog id="credit-extract-modal" class="rounded-lg w-11/12 max-w-2xl p-0">
	<div class="bg-blue-700 text-white px-6 py-4 rounded-t-lg">
		<h2 class="text-lg font-semibold">Extrato de Créditos</h2>
		<p class="text-sm mt-1" id="extract-title"></p>
	</div>
	<div class="p-6 max-h-96 overflow-y-auto">
		<!-- Resumo -->
		<div class="grid grid-cols-3 gap-4 mb-6">
			<div class="bg-blue-50 p-4 rounded">
				<p class="text-gray-600 text-xs font-semibold uppercase mb-1">Comprados</p>
				<p class="text-2xl font-bold text-blue-700" id="extract-purchased">0</p>
			</div>
			<div class="bg-red-50 p-4 rounded">
				<p class="text-gray-600 text-xs font-semibold uppercase mb-1">Consumidos</p>
				<p class="text-2xl font-bold text-red-700" id="extract-spent">0</p>
			</div>
			<div class="bg-green-50 p-4 rounded">
				<p class="text-gray-600 text-xs font-semibold uppercase mb-1">Disponível</p>
				<p class="text-2xl font-bold text-green-700" id="extract-available">0</p>
			</div>
		</div>
	</div>
	<div class="p-4 border-t flex justify-end gap-2">
		<button type="button" onclick="document.getElementById('credit-extract-modal').close()" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">Fechar</button>
	</div>
</dialog>

<!-- Modal de Histórico Completo de Créditos (para Admin) -->
<dialog id="credit-history-modal" class="rounded-lg w-11/12 max-w-4xl p-0">
	<div class="bg-gray-800 text-white px-6 py-4 rounded-t-lg">
		<h2 class="text-lg font-semibold">Histórico de Créditos</h2>
		<p class="text-sm mt-1" id="history-modal-user-name"></p>
	</div>
	<div class="p-6 max-h-96 overflow-y-auto">
		<!-- Abas para cada tipo de crédito -->
		<div class="flex gap-2 mb-6 border-b">
			<button class="px-4 py-2 border-b-2 border-blue-700 text-blue-700 font-semibold" onclick="switchCreditTab('ticket')">Ticket</button>
			<button class="px-4 py-2 border-b-2 border-transparent text-gray-600 hover:text-gray-800" onclick="switchCreditTab('daily')">Diária</button>
			<button class="px-4 py-2 border-b-2 border-transparent text-gray-600 hover:text-gray-800" onclick="switchCreditTab('project_dailies')">Projeto</button>
		</div>

		<!-- Resumo -->
		<div class="grid grid-cols-3 gap-4 mb-6">
			<div class="bg-blue-50 p-4 rounded">
				<p class="text-gray-600 text-xs font-semibold uppercase mb-1">Comprados</p>
				<p class="text-2xl font-bold text-blue-700" id="history-purchased">0</p>
			</div>
			<div class="bg-red-50 p-4 rounded">
				<p class="text-gray-600 text-xs font-semibold uppercase mb-1">Consumidos</p>
				<p class="text-2xl font-bold text-red-700" id="history-spent">0</p>
			</div>
			<div class="bg-green-50 p-4 rounded">
				<p class="text-gray-600 text-xs font-semibold uppercase mb-1">Disponível</p>
				<p class="text-2xl font-bold text-green-700" id="history-available">0</p>
			</div>
		</div>

		<!-- Histórico -->
		<div>
			<h3 class="font-semibold text-gray-700 mb-3">Transações</h3>
			<div id="history-list" class="space-y-2">
				<p class="text-gray-500 text-sm">Carregando...</p>
			</div>
		</div>
	</div>
	<div class="p-4 border-t flex justify-end gap-2">
		<button type="button" id="btn-clear-credit-history" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">Apagar Histórico</button>
		<button type="button" onclick="document.getElementById('credit-history-modal').close()" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">Fechar</button>
	</div>
</dialog>
