<?php
/** @var array $user */
?>
<div id="tab-relatorios" class="tab-content hidden px-4 md:px-0">
	<div class="bg-white rounded-lg shadow p-6">
		<h2 class="text-blue-900 font-semibold text-xl mb-6">Exportação de Relatórios</h2>
		
		<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
			<!-- Card PDF -->
			<div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
				<h3 class="text-blue-900 font-bold text-lg mb-2">Relatório PDF</h3>
				<p class="text-gray-600 text-sm mb-4">Layout pronto para impressão com resumo de chamados.</p>
				<button id="btn-export-pdf" class="w-full bg-blue-700 text-white px-4 py-2 rounded hover:bg-blue-800">
					Exportar PDF
				</button>
			</div>
			
			<!-- Card Excel -->
			<div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
				<h3 class="text-blue-900 font-bold text-lg mb-2">Planilha Excel</h3>
				<p class="text-gray-600 text-sm mb-4">Dados completos para análise e filtros avançados.</p>
				<button id="btn-export-xlsx" class="w-full bg-gray-400 text-white px-4 py-2 rounded cursor-not-allowed opacity-60" disabled>
					Exportar XLSX
				</button>
				<p class="text-gray-500 text-xs mt-3">Funcionalidade indisponível no momento.</p>
			</div>
			
			<!-- Card CSV -->
			<div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
				<h3 class="text-blue-900 font-bold text-lg mb-2">Exportação CSV</h3>
				<p class="text-gray-600 text-sm mb-4">Integrações rápidas com BI e ferramentas externas.</p>
				<button id="btn-export-csv" class="w-full bg-gray-700 text-white px-4 py-2 rounded hover:bg-gray-800">
					Exportar CSV
				</button>
			</div>
		</div>
	</div>
</div>
