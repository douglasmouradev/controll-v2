<div id="tab-sdwan" class="tab-content hidden">
	<div class="page-header flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-6">
		<div>
			<h2 class="page-title">Projeto SDWAN</h2>
			<p class="page-subtitle">Cadastre manualmente os dados de XPads e PDVs por loja</p>
		</div>
		<div class="flex flex-wrap gap-2">
			<a href="/dashboard/sdwan-entries/export/pdf" id="sdwan-export-pdf" class="btn btn-secondary btn-sm">Exportar PDF</a>
			<a href="/dashboard/sdwan-entries/export/xlsx" id="sdwan-export-xlsx" class="btn btn-secondary btn-sm">Exportar Excel</a>
		</div>
	</div>

	<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
		<div class="ui-card ui-stat-card">
			<p class="ui-stat-label">Registros</p>
			<p class="ui-stat-value text-blue-900" id="sdwan-total-rows">0</p>
		</div>
		<div class="ui-card ui-stat-card">
			<p class="ui-stat-label">XPads previstos</p>
			<p class="ui-stat-value text-purple-700" id="sdwan-total-xpads">0</p>
		</div>
		<div class="ui-card ui-stat-card">
			<p class="ui-stat-label">Quantidade localizada</p>
			<p class="ui-stat-value text-orange-600" id="sdwan-total-localizada">0</p>
		</div>
		<div class="ui-card ui-stat-card">
			<p class="ui-stat-label">Lojas</p>
			<p class="ui-stat-value text-emerald-600" id="sdwan-total-lojas">0</p>
		</div>
	</div>

	<section class="ui-card ui-card-body mb-6" id="sdwan-goal-section">
		<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-3">
			<div>
				<h3 class="text-lg font-bold text-slate-800">Meta do projeto</h3>
				<p class="text-sm text-slate-600" id="sdwan-goal-label">Progresso em relação à meta de XPads</p>
			</div>
			<p class="text-sm font-semibold text-slate-700" id="sdwan-goal-percent">0%</p>
		</div>
		<div class="sdwan-goal-bar">
			<div class="sdwan-goal-bar-fill" id="sdwan-goal-bar-fill" style="width:0%"></div>
		</div>
		<p class="text-xs text-slate-500 mt-2" id="sdwan-goal-detail">0 de 0 XPads localizados</p>
	</section>

	<section class="ui-card ui-card-body mb-6 hidden" id="sdwan-admin-tools">
		<h3 class="text-lg font-bold text-slate-800 mb-4">Ferramentas administrativas</h3>
		<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
			<form id="sdwan-settings-form" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
				<div>
					<label class="label" for="sdwan-setting-goal">Meta global de XPads</label>
					<input class="input" type="number" min="0" id="sdwan-setting-goal" name="xpads_goal" placeholder="0">
				</div>
				<div>
					<label class="label" for="sdwan-setting-link-max">Limite por link técnico</label>
					<input class="input" type="number" min="1" max="500" id="sdwan-setting-link-max" name="link_max_submissions" placeholder="50">
				</div>
				<div class="sm:col-span-2">
					<button type="submit" class="btn btn-primary btn-sm">Salvar configurações</button>
				</div>
			</form>
			<div class="space-y-4">
				<form id="sdwan-import-form" class="flex flex-wrap items-end gap-2">
					<div class="flex-1 min-w-[12rem]">
						<label class="label" for="sdwan-import-file">Importar CSV</label>
						<input class="input" type="file" id="sdwan-import-file" name="file" accept=".csv,.txt">
					</div>
					<button type="submit" class="btn btn-secondary btn-sm">Importar</button>
				</form>
				<form id="sdwan-stores-upload-form" class="flex flex-wrap items-end gap-2">
					<div class="flex-1 min-w-[12rem]">
						<label class="label" for="sdwan-stores-file">Atualizar lojas (JSON)</label>
						<input class="input" type="file" id="sdwan-stores-file" name="file" accept=".json">
					</div>
					<button type="submit" class="btn btn-secondary btn-sm">Enviar</button>
				</form>
				<button type="button" id="sdwan-cleanup-btn" class="btn btn-ghost btn-sm text-red-600">Executar limpeza de arquivos</button>
			</div>
		</div>
	</section>

	<section class="ui-card ui-card-body mb-6">
		<h3 class="text-lg font-bold text-slate-800 mb-4">Filtros</h3>
		<form id="sdwan-filters-form" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
			<div>
				<label class="label" for="sdwan-filter-loja">Loja</label>
				<input class="input" id="sdwan-filter-loja" placeholder="Sigla" style="text-transform: uppercase;">
			</div>
			<div>
				<label class="label" for="sdwan-filter-pdv">PDV / Série</label>
				<input class="input" id="sdwan-filter-pdv" placeholder="Número">
			</div>
			<div>
				<label class="label" for="sdwan-filter-source">Origem</label>
				<select class="input" id="sdwan-filter-source">
					<option value="">Todas</option>
					<option value="dashboard">Dashboard</option>
					<option value="public">Link técnico</option>
				</select>
			</div>
			<div>
				<label class="label" for="sdwan-filter-date-from">Data inicial</label>
				<input class="input" type="date" id="sdwan-filter-date-from">
			</div>
			<div>
				<label class="label" for="sdwan-filter-date-to">Data final</label>
				<input class="input" type="date" id="sdwan-filter-date-to">
			</div>
			<div class="flex items-end gap-2">
				<button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
				<button type="button" id="sdwan-filter-clear" class="btn btn-secondary btn-sm">Limpar</button>
			</div>
		</form>
	</section>

	<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
		<section class="ui-card ui-card-body">
			<h3 class="text-lg font-bold text-slate-800 mb-1">Distribuição por loja</h3>
			<p class="text-sm text-slate-600 mb-4">Quantidade localizada por loja.</p>
			<div class="h-72">
				<canvas id="sdwan-pie-chart" class="w-full h-full"></canvas>
			</div>
			<p id="sdwan-chart-empty" class="hidden text-sm text-slate-500 text-center py-8">Nenhum dado para o gráfico.</p>
		</section>
		<section class="ui-card ui-card-body">
			<h3 class="text-lg font-bold text-slate-800 mb-1">Previsto vs localizado</h3>
			<p class="text-sm text-slate-600 mb-4">Comparativo geral dos registros filtrados.</p>
			<div class="h-72">
				<canvas id="sdwan-progress-chart" class="w-full h-full"></canvas>
			</div>
			<p id="sdwan-progress-empty" class="hidden text-sm text-slate-500 text-center py-8">Nenhum dado para o gráfico.</p>
		</section>
	</div>

	<section class="ui-card ui-card-body mb-6">
		<h3 class="text-lg font-bold text-slate-800 mb-4">Painel por loja</h3>
		<div class="overflow-x-auto">
			<table class="data-table">
				<thead>
					<tr>
						<th>Loja</th>
						<th>Registros</th>
						<th>XPads previstos</th>
						<th>Localizado</th>
						<th>Pendente</th>
						<th>%</th>
					</tr>
				</thead>
				<tbody id="sdwan-store-panel-body">
					<tr><td colspan="6" class="empty-state">Nenhum dado por loja.</td></tr>
				</tbody>
			</table>
		</div>
	</section>

	<section class="ui-card ui-card-body mb-6" id="sdwan-links-section">
		<div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
			<div>
				<h3 class="text-lg font-bold text-slate-800 mb-1">Links para técnicos</h3>
				<p class="text-sm text-slate-600">Gere links com código de 4 dígitos válidos por 24 horas. Revogue links que não devem mais ser usados.</p>
			</div>
			<button type="button" id="btn-sdwan-generate-link" class="btn btn-secondary shrink-0">Gerar novo link</button>
		</div>
		<div id="sdwan-access-link-box" class="hidden mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
			<div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
				<div>
					<p class="text-slate-500">Código</p>
					<p class="text-2xl font-bold tracking-widest text-slate-900" id="sdwan-access-code">----</p>
				</div>
				<div class="md:col-span-2">
					<p class="text-slate-500">Link de cadastro</p>
					<div class="flex flex-wrap items-center gap-2 mt-1">
						<a href="#" id="sdwan-access-url" target="_blank" rel="noopener noreferrer" class="text-blue-700 font-semibold break-all hover:underline"></a>
						<button type="button" id="btn-sdwan-copy-link" class="btn btn-ghost btn-sm">Copiar link</button>
					</div>
					<p class="text-xs text-slate-500 mt-2">Expira em: <span id="sdwan-access-expires" class="font-semibold text-slate-700"></span></p>
				</div>
				<div class="text-center">
					<p class="text-slate-500 mb-2">QR Code</p>
					<img id="sdwan-access-qr" src="" alt="QR Code do link" class="mx-auto h-28 w-28 border border-slate-200 rounded-lg bg-white">
				</div>
			</div>
		</div>
		<div class="mt-4 overflow-x-auto">
			<table class="data-table">
				<thead>
					<tr>
						<th>Código</th>
						<th>Link</th>
						<th>Expira em</th>
						<th>Cadastros</th>
						<th class="sdwan-actions-col">Ações</th>
					</tr>
				</thead>
				<tbody id="sdwan-links-table-body">
					<tr><td colspan="5" class="empty-state">Nenhum link ativo.</td></tr>
				</tbody>
			</table>
		</div>
	</section>

	<section class="ui-card ui-card-body mb-6" id="sdwan-form-section">
		<h3 class="text-lg font-bold text-slate-800 mb-4" id="sdwan-form-title">Novo registro</h3>
		<form id="sdwan-entry-form" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" enctype="multipart/form-data">
			<?php echo \App\Services\Csrf::field(); ?>
			<input type="hidden" name="id" id="sdwan-entry-id" value="">
			<div>
				<label class="label" for="sdwan-xpads-previsto">Quantidades prevista de XPad´s</label>
				<input class="input" type="number" min="0" step="1" name="xpads_previsto" id="sdwan-xpads-previsto" placeholder="0" required>
			</div>
			<div>
				<label class="label" for="sdwan-quantidade-localizada">Quantidade localizada</label>
				<input class="input" type="number" min="0" step="1" name="quantidade_localizada" id="sdwan-quantidade-localizada" placeholder="0" required>
			</div>
			<div>
				<label class="label" for="sdwan-pdv-numero">Nº PDV</label>
				<input class="input" name="pdv_numero" id="sdwan-pdv-numero" placeholder="Número do PDV">
			</div>
			<div>
				<label class="label" for="sdwan-pdv-serie">Nº Serie PDV</label>
				<input class="input" name="pdv_serie" id="sdwan-pdv-serie" placeholder="Série do PDV">
			</div>
			<div>
				<label class="label" for="sdwan-loja">Loja</label>
				<input class="input" name="loja" id="sdwan-loja" list="sdwan-loja-list" placeholder="Digite a sigla da loja" autocomplete="off" style="text-transform: uppercase;" required>
				<datalist id="sdwan-loja-list"></datalist>
				<p class="text-xs text-slate-500 mt-1" id="sdwan-loja-hint">Digite a sigla para buscar na planilha de lojas.</p>
			</div>
			<div class="md:col-span-2 lg:col-span-3">
				<label class="label" for="sdwan-image">Imagem</label>
				<input class="input" type="file" name="image" id="sdwan-image" accept="image/jpeg,image/png,image/webp,image/gif" capture="environment">
				<p class="text-xs text-slate-500 mt-1" id="sdwan-image-size-hint">A imagem será otimizada automaticamente antes do envio.</p>
				<label class="mt-2 hidden items-center gap-2 text-sm text-slate-600" id="sdwan-remove-image-wrap">
					<input type="checkbox" name="remove_image" id="sdwan-remove-image" value="1">
					Remover imagem atual
				</label>
				<div id="sdwan-image-preview" class="mt-3 hidden">
					<p class="text-xs font-semibold text-slate-600 mb-2">Pré-visualização</p>
					<img id="sdwan-image-preview-img" src="" alt="Pré-visualização da imagem" class="max-h-48 rounded-lg border border-slate-200">
				</div>
				<div id="sdwan-image-current" class="mt-3 hidden">
					<p class="text-xs font-semibold text-slate-600 mb-2">Imagem atual</p>
					<a id="sdwan-image-current-link" href="#" target="_blank" rel="noopener noreferrer" class="inline-block">
						<img id="sdwan-image-current-img" src="" alt="Imagem do registro" class="max-h-48 rounded-lg border border-slate-200">
					</a>
				</div>
			</div>
			<div class="md:col-span-2 lg:col-span-3 flex flex-wrap gap-2">
				<button type="submit" id="sdwan-form-submit" class="btn btn-primary">Salvar registro</button>
				<button type="button" id="sdwan-form-cancel" class="btn btn-secondary hidden">Cancelar edição</button>
			</div>
		</form>
	</section>

	<div class="ui-card overflow-hidden">
		<div class="overflow-x-auto">
			<table class="data-table">
				<thead>
					<tr>
						<th>Data</th>
						<th>Origem</th>
						<th>XPads previstos</th>
						<th>Qtd. localizada</th>
						<th>Nº PDV</th>
						<th>Nº Série PDV</th>
						<th>Loja</th>
						<th>Cadastrado por</th>
						<th>Imagem</th>
						<th class="sdwan-actions-col">Ações</th>
					</tr>
				</thead>
				<tbody id="sdwan-table-body">
					<tr>
						<td colspan="10" class="empty-state">Nenhum registro cadastrado.</td>
					</tr>
				</tbody>
			</table>
		</div>
		<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-4 border-t border-slate-200">
			<p class="text-sm text-slate-600" id="sdwan-pagination-info">Mostrando 0 registros</p>
			<div class="flex items-center gap-2">
				<button type="button" id="sdwan-page-prev" class="btn btn-secondary btn-sm" disabled>Anterior</button>
				<span class="text-sm text-slate-700" id="sdwan-page-label">Página 1 de 1</span>
				<button type="button" id="sdwan-page-next" class="btn btn-secondary btn-sm" disabled>Próxima</button>
			</div>
		</div>
	</div>
</div>
