<div class="auth-card max-w-3xl w-full">
	<div class="auth-card-header">
		<?php $variant = 'auth'; include BASE_PATH . '/app/Views/components/brand-logos.php'; ?>
		<h1 class="text-2xl font-extrabold text-slate-900 tracking-tight mt-4">Cadastro Projeto ACUPAD</h1>
		<p class="text-slate-500 mt-2 text-sm">Preencha os dados do atendimento na loja</p>
		<?php if (!empty($expiresAt)): ?>
			<p class="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2 mt-3">
				Este link expira em: <strong id="sdwan-public-expires-at"><?php echo htmlspecialchars($expiresAt); ?></strong>
			</p>
		<?php endif; ?>
		<?php if (!empty($linkInfo['max_submissions'])): ?>
			<p class="text-xs text-slate-600 bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 mt-2">
				Cadastros restantes neste link: <strong><?php echo (int) ($linkInfo['submissions_remaining'] ?? 0); ?></strong>
			</p>
		<?php endif; ?>
	</div>
	<div class="px-6 md:px-8 pb-8">
		<div id="sdwan-public-success" class="hidden mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
			<h2 class="text-lg font-bold text-emerald-900 mb-2">Registro enviado!</h2>
			<p class="text-sm text-emerald-800" id="sdwan-public-success-text"></p>
			<button type="button" id="sdwan-public-new-entry" class="btn btn-primary btn-sm mt-4">Cadastrar outro PDV</button>
		</div>
		<form id="sdwan-public-form" class="grid grid-cols-1 md:grid-cols-2 gap-4" enctype="multipart/form-data">
			<?php echo \App\Services\Csrf::field(); ?>
			<input type="hidden" name="code" value="<?php echo htmlspecialchars($code ?? '', ENT_QUOTES, 'UTF-8'); ?>">
			<div>
				<label class="label" for="sdwan-public-xpads-previsto">Quantidades prevista de XPad´s</label>
				<input class="input" type="number" min="0" step="1" name="xpads_previsto" id="sdwan-public-xpads-previsto" placeholder="0" required>
			</div>
			<div>
				<label class="label" for="sdwan-public-quantidade-localizada">Quantidade localizada</label>
				<input class="input" type="number" min="0" step="1" name="quantidade_localizada" id="sdwan-public-quantidade-localizada" placeholder="0" required>
			</div>
			<div>
				<label class="label" for="sdwan-public-pdv-numero">Nº PDV</label>
				<input class="input" name="pdv_numero" id="sdwan-public-pdv-numero" placeholder="Número do PDV">
			</div>
			<div>
				<label class="label" for="sdwan-public-pdv-serie">Nº Serie PDV</label>
				<input class="input" name="pdv_serie" id="sdwan-public-pdv-serie" placeholder="Série do PDV">
			</div>
			<div class="md:col-span-2">
				<label class="label" for="sdwan-public-loja">Loja</label>
				<input class="input" name="loja" id="sdwan-public-loja" list="sdwan-public-loja-list" placeholder="Digite a sigla da loja" autocomplete="off" style="text-transform: uppercase;" required>
				<datalist id="sdwan-public-loja-list"></datalist>
				<p class="text-xs text-slate-500 mt-1" id="sdwan-public-loja-hint">Digite a sigla para buscar na planilha de lojas.</p>
				<p class="text-xs font-medium text-slate-700 mt-1 hidden" id="sdwan-public-loja-address"></p>
			</div>
			<div class="md:col-span-2">
				<label class="label" for="sdwan-public-image">Imagem</label>
				<input class="input" type="file" name="image" id="sdwan-public-image" accept="image/jpeg,image/png,image/webp,image/gif" capture="environment">
				<p class="text-xs text-slate-500 mt-1" id="sdwan-public-image-size-hint">A imagem será otimizada automaticamente antes do envio.</p>
				<div id="sdwan-public-image-preview" class="mt-3 hidden">
					<p class="text-xs font-semibold text-slate-600 mb-2">Pré-visualização</p>
					<img id="sdwan-public-image-preview-img" src="" alt="Pré-visualização da imagem" class="max-h-48 rounded-lg border border-slate-200">
				</div>
			</div>
			<div class="md:col-span-2">
				<button type="submit" id="sdwan-public-submit" class="btn btn-primary btn-block">Enviar registro</button>
			</div>
		</form>
	</div>
</div>
<script>
	window.SDWAN_PUBLIC_CODE = <?php echo json_encode($code ?? '', JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(asset_url('/assets/js/utils/image-compress.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(asset_url('/assets/js/sdwan-public.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
