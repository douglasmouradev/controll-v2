<div id="tab-inventario" class="tab-content hidden">
	<div class="page-header flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-6">
		<div>
			<h2 class="page-title">Projeto RFID</h2>
			<p class="page-subtitle">Distribuição de itens a partir da planilha de dashboard</p>
		</div>
		<div class="flex flex-wrap gap-2">
			<input type="file" id="inventory-file-input" accept=".xlsx,.xlsm,.xltx,.xltm" class="hidden">
			<button type="button" id="btn-inventory-import" class="btn btn-primary">Importar</button>
			<a href="/dashboard/inventory-download" class="btn btn-secondary">Baixar planilha</a>
		</div>
	</div>

	<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">
		<section class="ui-card ui-card-body lg:col-span-2">
			<h3 class="text-lg font-bold text-slate-800 mb-4">Categorias do Projeto RFID</h3>
			<div class="filter-bar !mb-4">
				<select id="inventory-filter-store" class="select text-sm">
					<option value="">Todas as lojas</option>
				</select>
				<select id="inventory-filter-support-status" class="select text-sm">
					<option value="">Suporte (todos)</option>
					<option value="pending">Suporte pendente</option>
					<option value="installed">Suporte instalado</option>
				</select>
				<input id="inventory-filter-start-date" type="date" class="input text-sm">
				<input id="inventory-filter-end-date" type="date" class="input text-sm">
			</div>
			<div class="h-80">
				<canvas id="inventory-pie-chart" class="w-full h-full"></canvas>
			</div>
			<div class="mt-4 ui-card ui-card-body !p-4 bg-slate-50">
				<p class="text-sm font-semibold text-slate-800 mb-2">Onde está (clique na legenda do gráfico)</p>
				<div id="inventory-category-locations" class="text-sm text-slate-600">Selecione uma categoria na legenda para listar as lojas.</div>
			</div>
		</section>
		<section class="ui-card ui-card-body">
			<h3 class="text-lg font-bold text-slate-800 mb-4">Resumo</h3>
			<div class="space-y-2.5 text-sm text-slate-600">
				<p>Quantidade de lojas: <span id="inventory-metric-stores" class="font-bold text-slate-900">0</span></p>
				<p>Previsto: <span id="inventory-metric-previsto" class="font-bold text-slate-900">0</span></p>
				<p>Diárias consumidas: <span id="inventory-metric-diarias" class="font-bold text-slate-900">0</span></p>
				<p>Quantidade de setup: <span id="inventory-metric-setup" class="font-bold text-slate-900">0</span></p>
				<p>Quantidade de rollout: <span id="inventory-metric-rollout" class="font-bold text-slate-900">0</span></p>
				<p>Ocorrências: <span id="inventory-metric-ocorrencias" class="font-bold text-slate-900">0</span></p>
				<p>Quantidade de PDVs: <span id="inventory-metric-pdvs" class="font-bold text-slate-900">0</span></p>
				<p>Suporte instalado: <span id="inventory-metric-suporte-instalado" class="font-bold text-slate-900">0</span></p>
				<p>Suporte pendente: <span id="inventory-metric-suporte-pendente" class="font-bold text-slate-900">0</span></p>
			</div>
		</section>
	</div>
</div>
