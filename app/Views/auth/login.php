<div class="auth-card">
	<div class="auth-card-header">
		<img src="/logo-controll-it.svg" alt="Controll IT" class="auth-logo">
		<h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Controll IT</h1>
		<p class="text-slate-500 mt-2 text-sm">Sistema de gestão de chamados técnicos</p>
	</div>
	<div class="px-8 pb-8">
		<div class="flex items-center justify-center mb-6">
			<span class="inline-flex items-center gap-2 bg-blue-50 text-slate-700 border border-blue-100 rounded-full px-4 py-2 text-sm font-medium">
				<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-700" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2l6 3v4c0 5-3.5 7-6 9-2.5-2-6-4-6-9V5l6-3z"/></svg>
				Acesso seguro e confiável
			</span>
		</div>
		<?php if (!empty($error)): ?>
			<div class="mb-4 bg-red-50 text-red-700 px-4 py-3 rounded-xl border border-red-200 text-sm"><?php echo htmlspecialchars($error); ?></div>
		<?php endif; ?>
		<form method="post" action="/login" class="space-y-4">
			<?php echo \App\Services\Csrf::field(); ?>
			<div>
				<label class="label">Usuário</label>
				<input name="email" type="text" class="input" placeholder="E-mail ou usuário" required>
			</div>
			<div>
				<label class="label">Senha</label>
				<input name="password" type="password" class="input" placeholder="••••••••" required>
			</div>
			<button type="submit" class="btn btn-primary btn-block mt-2">Entrar</button>
		</form>
	</div>
</div>
