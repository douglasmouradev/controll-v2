<div id="tab-inventario" class="tab-content hidden px-4 md:px-0">
	<div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
		<div>
			<h1 class="text-3xl font-bold text-blue-900 mb-2">Projeto RFID</h1>
			<p class="text-gray-600">Distribuição de itens a partir da planilha de dashboard</p>
		</div>
		<div class="flex flex-wrap gap-2">
			<input type="file" id="inventory-file-input" accept=".xlsx,.xlsm,.xltx,.xltm" class="hidden">
			<button id="btn-inventory-import" class="bg-blue-700 text-white px-4 py-2 rounded hover:bg-blue-800 font-semibold text-sm">Importar</button>
			<a href="/dashboard/inventory-download" class="bg-white text-blue-700 border border-blue-700 px-4 py-2 rounded hover:bg-blue-50 font-semibold text-sm">Baixar planilha</a>
		</div>
	</div>

	<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
		<section class="bg-white rounded-lg shadow p-6 lg:col-span-2">
			<h2 class="text-blue-700 font-semibold mb-4 text-lg">Categorias do Projeto RFID</h2>
			<div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-4">
				<select id="inventory-filter-store" class="border rounded px-3 py-2 text-sm">
					<option value="">Todas as lojas</option>
				</select>
				<select id="inventory-filter-support-status" class="border rounded px-3 py-2 text-sm">
					<option value="">Suporte (todos)</option>
					<option value="pending">Suporte pendente</option>
					<option value="installed">Suporte instalado</option>
				</select>
				<input id="inventory-filter-start-date" type="date" class="border rounded px-3 py-2 text-sm">
				<input id="inventory-filter-end-date" type="date" class="border rounded px-3 py-2 text-sm">
			</div>
			<div class="h-80">
				<canvas id="inventory-pie-chart" class="w-full h-full"></canvas>
			</div>
			<div class="mt-4 border rounded p-3 bg-gray-50">
				<p class="text-sm font-semibold text-blue-900 mb-2">Onde está (clique na legenda do gráfico)</p>
				<div id="inventory-category-locations" class="text-sm text-gray-700">Selecione uma categoria na legenda para listar as lojas.</div>
			</div>
		</section>
		<section class="bg-white rounded-lg shadow p-6">
			<h2 class="text-blue-700 font-semibold mb-4 text-lg">Resumo</h2>
			<div class="space-y-2 text-sm">
				<p class="text-gray-700">Quantidade de lojas: <span id="inventory-metric-stores" class="font-semibold text-blue-900">0</span></p>
				<p class="text-gray-700">Previsto: <span id="inventory-metric-previsto" class="font-semibold text-blue-900">0</span></p>
				<p class="text-gray-700">Diárias consumidas: <span id="inventory-metric-diarias" class="font-semibold text-blue-900">0</span></p>
				<p class="text-gray-700">Quantidade de setup: <span id="inventory-metric-setup" class="font-semibold text-blue-900">0</span></p>
				<p class="text-gray-700">Quantidade de rollout: <span id="inventory-metric-rollout" class="font-semibold text-blue-900">0</span></p>
				<p class="text-gray-700">Ocorrências: <span id="inventory-metric-ocorrencias" class="font-semibold text-blue-900">0</span></p>
				<p class="text-gray-700">Quantidade de pdvs: <span id="inventory-metric-pdvs" class="font-semibold text-blue-900">0</span></p>
				<p class="text-gray-700">Suporte instalado: <span id="inventory-metric-suporte-instalado" class="font-semibold text-blue-900">0</span></p>
				<p class="text-gray-700">Suporte pendente: <span id="inventory-metric-suporte-pendente" class="font-semibold text-blue-900">0</span></p>
			</div>
		</section>
	</div>
</div>
