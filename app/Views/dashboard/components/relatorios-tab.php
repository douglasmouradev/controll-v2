<?php
/** @var array $user */
?>
<div id="tab-relatorios" class="tab-content hidden">
	<div class="ui-card ui-card-body">
		<div class="page-header !mb-6">
			<h2 class="page-title text-xl">Exportação de Relatórios</h2>
			<p class="page-subtitle">Gere arquivos para análise, impressão ou integração com outras ferramentas</p>
		</div>

		<div class="grid grid-cols-1 md:grid-cols-3 gap-5">
			<div class="ui-card export-card">
				<h3>Relatório PDF</h3>
				<p>Layout pronto para impressão com resumo de chamados e cabeçalho corporativo.</p>
				<button type="button" id="btn-export-pdf" class="btn btn-primary btn-block">Exportar PDF</button>
			</div>
			<div class="ui-card export-card opacity-75">
				<h3>Planilha Excel</h3>
				<p>Dados completos para análise e filtros avançados em planilha.</p>
				<button type="button" id="btn-export-xlsx" class="btn btn-secondary btn-block" disabled>Em breve</button>
				<p class="text-xs text-slate-400 mt-3 mb-0">Funcionalidade indisponível no momento.</p>
			</div>
			<div class="ui-card export-card">
				<h3>Exportação CSV</h3>
				<p>Integração rápida com BI, Excel e ferramentas externas.</p>
				<button type="button" id="btn-export-csv" class="btn btn-secondary btn-block">Exportar CSV</button>
			</div>
		</div>
	</div>
</div>
