<?php
/** @var string|null $error */
$error = $error ?? null;
?>
<div class="auth-card">
	<h1 class="auth-title">Verificação em duas etapas</h1>
	<p class="auth-subtitle">Informe o código de 6 dígitos do seu aplicativo autenticador.</p>

	<?php if ($error): ?>
		<div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
	<?php endif; ?>

	<form method="post" action="/two-factor" class="space-y-4">
		<div>
			<label class="label" for="code">Código</label>
			<input type="text" id="code" name="code" class="input text-center tracking-widest" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus autocomplete="one-time-code">
		</div>
		<button type="submit" class="btn btn-primary w-full">Confirmar</button>
	</form>

	<p class="text-sm text-slate-500 mt-4 text-center">
		<a href="/logout" class="text-brand hover:underline">Sair e tentar novamente</a>
	</p>
</div>
