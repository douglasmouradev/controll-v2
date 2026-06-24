<div id="tab-sdwan" class="tab-content hidden">
	<div class="page-header flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-6">
		<div>
			<h2 class="page-title">Projeto SDWAN</h2>
			<p class="page-subtitle">Cadastre manualmente os dados de XPads e PDVs por loja</p>
		</div>
	</div>

	<div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
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
	</div>

	<section class="ui-card ui-card-body mb-6">
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
				<input class="input" type="file" name="image" id="sdwan-image" accept="image/*">
				<p class="text-xs text-slate-500 mt-1">JPG, PNG, GIF ou WEBP. Tamanho máximo: 10 MB.</p>
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
						<th>XPads previstos</th>
						<th>Qtd. localizada</th>
						<th>Nº PDV</th>
						<th>Nº Série PDV</th>
						<th>Loja</th>
						<th>Imagem</th>
						<th class="text-right">Ações</th>
					</tr>
				</thead>
				<tbody id="sdwan-table-body">
					<tr>
						<td colspan="7" class="empty-state">Nenhum registro cadastrado.</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>
