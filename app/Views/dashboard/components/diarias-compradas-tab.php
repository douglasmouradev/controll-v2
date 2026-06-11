<?php
/** @var array $user */
?>
<div id="tab-diarias-compradas" class="tab-content hidden">
	<div class="page-header flex flex-col md:flex-row md:items-end md:justify-between gap-4">
		<div>
			<h2 class="page-title">Diárias compradas</h2>
			<p class="page-subtitle">Importe a planilha com as diárias adquiridas para controle e conferência</p>
		</div>
		<div class="flex flex-wrap gap-2">
			<input type="file" id="purchased-dailies-file-input" accept=".xlsx,.xlsm,.xltx,.xltm,.csv" class="hidden">
			<button type="button" id="btn-purchased-dailies-import" class="btn btn-primary">Importar planilha</button>
			<a href="/dashboard/purchased-dailies-download" class="btn btn-secondary">Baixar planilha atual</a>
		</div>
	</div>

	<div class="grid grid-cols-1 md:grid-cols-4 gap-5 mb-6">
		<div class="ui-card ui-stat-card">
			<p class="ui-stat-label">Registros</p>
			<p class="ui-stat-value text-blue-900" id="purchased-dailies-total-rows">0</p>
		</div>
		<div class="ui-card ui-stat-card">
			<p class="ui-stat-label">Diárias compradas</p>
			<p class="ui-stat-value text-purple-700" id="purchased-dailies-daily-total">0</p>
		</div>
		<div class="ui-card ui-stat-card">
			<p class="ui-stat-label">Diárias projeto</p>
			<p class="ui-stat-value text-orange-600" id="purchased-dailies-project-total">0</p>
		</div>
		<div class="ui-card ui-stat-card">
			<p class="ui-stat-label">Total geral</p>
			<p class="ui-stat-value text-indigo-700" id="purchased-dailies-grand-total">0</p>
		</div>
	</div>

	<div class="ui-card ui-card-body mb-5">
		<p class="text-sm text-slate-600 mb-2">
			<strong>Formato esperado:</strong> colunas como Data, Loja, Atividade, Pedido, Número e Quantidade. A atividade também pode vir no topo da planilha.
		</p>
		<p class="text-xs text-slate-500" id="purchased-dailies-source">Nenhuma planilha importada.</p>
	</div>

	<div class="ui-card overflow-hidden">
		<div class="overflow-x-auto">
			<table class="data-table">
				<thead>
					<tr>
						<th>Data</th>
						<th>Loja / Unidade</th>
						<th>Atividade</th>
						<th>Pedido</th>
						<th>Número</th>
						<th>Tipo</th>
						<th class="text-right">Quantidade</th>
						<th>Descrição</th>
					</tr>
				</thead>
				<tbody id="purchased-dailies-table-body">
					<tr>
						<td colspan="8" class="empty-state">Importe uma planilha para visualizar os registros.</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>
