<?php
/** @var array $user */
?>
<div id="tab-diarias-compradas" class="tab-content hidden px-4 md:px-0">
	<div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
		<div>
			<h1 class="text-3xl font-bold text-blue-900 mb-2">Diárias compradas</h1>
			<p class="text-gray-600">Importe a planilha com as diárias adquiridas para controle e conferência</p>
		</div>
		<div class="flex flex-wrap gap-2">
			<input type="file" id="purchased-dailies-file-input" accept=".xlsx,.xlsm,.xltx,.xltm,.csv" class="hidden">
			<button id="btn-purchased-dailies-import" class="bg-indigo-700 text-white px-4 py-2 rounded hover:bg-indigo-800 font-semibold text-sm">Importar planilha</button>
			<a href="/dashboard/purchased-dailies-download" class="bg-white text-indigo-700 border border-indigo-700 px-4 py-2 rounded hover:bg-indigo-50 font-semibold text-sm">Baixar planilha atual</a>
		</div>
	</div>

	<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
		<div class="bg-white rounded-lg shadow p-5">
			<p class="text-xs font-semibold uppercase text-gray-500 mb-1">Registros</p>
			<p class="text-3xl font-bold text-blue-900" id="purchased-dailies-total-rows">0</p>
		</div>
		<div class="bg-white rounded-lg shadow p-5">
			<p class="text-xs font-semibold uppercase text-gray-500 mb-1">Diárias compradas</p>
			<p class="text-3xl font-bold text-purple-700" id="purchased-dailies-daily-total">0</p>
		</div>
		<div class="bg-white rounded-lg shadow p-5">
			<p class="text-xs font-semibold uppercase text-gray-500 mb-1">Diárias projeto</p>
			<p class="text-3xl font-bold text-orange-600" id="purchased-dailies-project-total">0</p>
		</div>
		<div class="bg-white rounded-lg shadow p-5">
			<p class="text-xs font-semibold uppercase text-gray-500 mb-1">Total geral</p>
			<p class="text-3xl font-bold text-indigo-700" id="purchased-dailies-grand-total">0</p>
		</div>
	</div>

	<div class="bg-white rounded-lg shadow p-4 mb-4">
		<p class="text-sm text-gray-600 mb-2">
			<strong>Formato esperado:</strong> colunas como Data, Loja/Unidade/Sigla, Quantidade (ou Qtd/Diárias) e, opcionalmente, Tipo (Diária ou Projeto) e Descrição.
		</p>
		<p class="text-xs text-gray-500" id="purchased-dailies-source">Nenhuma planilha importada.</p>
	</div>

	<div class="bg-white rounded-lg shadow overflow-hidden">
		<div class="overflow-x-auto">
			<table class="min-w-full text-sm">
				<thead class="bg-gray-50 text-gray-700">
					<tr>
						<th class="px-4 py-3 text-left font-semibold">Data</th>
						<th class="px-4 py-3 text-left font-semibold">Loja / Unidade</th>
						<th class="px-4 py-3 text-left font-semibold">Tipo</th>
						<th class="px-4 py-3 text-right font-semibold">Quantidade</th>
						<th class="px-4 py-3 text-left font-semibold">Descrição</th>
					</tr>
				</thead>
				<tbody id="purchased-dailies-table-body" class="divide-y divide-gray-100">
					<tr>
						<td colspan="5" class="px-4 py-8 text-center text-gray-500">Importe uma planilha para visualizar os registros.</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>
