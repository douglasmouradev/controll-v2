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
		<form id="sdwan-entry-form" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
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
				<input class="input" name="loja" id="sdwan-loja" placeholder="Ex: SP01" style="text-transform: uppercase;" oninput="this.value = this.value.toUpperCase();" required>
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
						<th class="text-right">Ações</th>
					</tr>
				</thead>
				<tbody id="sdwan-table-body">
					<tr>
						<td colspan="6" class="empty-state">Nenhum registro cadastrado.</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>
