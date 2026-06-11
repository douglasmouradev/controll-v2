<?php
/** @var array $user */
if (!view_is_staff($user)) {
	return;
}
?>
<div id="tab-seguranca" class="tab-content hidden">
	<div class="ui-card ui-card-body max-w-2xl">
		<div class="page-header !mb-5">
			<h2 class="page-title text-xl">Segurança da conta</h2>
			<p class="page-subtitle">Autenticação em duas etapas (2FA) para admin e suporte</p>
		</div>

		<div id="two-factor-status" class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
			Carregando status do 2FA...
		</div>

		<div id="two-factor-setup" class="hidden mt-5 space-y-4">
			<p class="text-sm text-slate-600">Escaneie o QR code no Google Authenticator, Authy ou similar e informe o código gerado.</p>
			<div class="rounded-xl border border-slate-200 p-4 bg-white">
				<p class="text-xs text-slate-500 mb-2">Chave manual:</p>
				<code id="two-factor-secret" class="text-sm break-all"></code>
				<p class="text-xs text-slate-500 mt-3 mb-1">URI otpauth:</p>
				<code id="two-factor-uri" class="text-xs break-all text-slate-600"></code>
			</div>
			<div>
				<label class="label" for="two-factor-code">Código de verificação</label>
				<input type="text" id="two-factor-code" class="input max-w-xs" inputmode="numeric" maxlength="6" placeholder="000000">
			</div>
			<button type="button" id="two-factor-confirm" class="btn btn-primary">Ativar 2FA</button>
		</div>

		<div id="two-factor-disable" class="hidden mt-5 space-y-4">
			<p class="text-sm text-slate-600">Para desativar, informe um código válido do autenticador.</p>
			<div>
				<label class="label" for="two-factor-disable-code">Código</label>
				<input type="text" id="two-factor-disable-code" class="input max-w-xs" inputmode="numeric" maxlength="6">
			</div>
			<button type="button" id="two-factor-disable-btn" class="btn btn-secondary">Desativar 2FA</button>
		</div>

		<div class="mt-4">
			<button type="button" id="two-factor-start-setup" class="btn btn-primary hidden">Configurar 2FA</button>
		</div>
	</div>
</div>
