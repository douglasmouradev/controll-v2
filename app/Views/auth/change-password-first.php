<div class="auth-card">
	<div class="auth-card-header">
		<img src="/logo-controll-it.svg" alt="Controll IT" class="auth-logo">
		<h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Primeira troca de senha</h1>
		<p class="text-slate-500 mt-2 text-sm">Controll IT Help Desk</p>
	</div>
	<div class="px-8 pb-8">
		<?php if (!empty($error)): ?>
			<div class="mb-4 bg-red-50 text-red-700 px-4 py-3 rounded-xl border border-red-200 text-sm"><?php echo htmlspecialchars($error); ?></div>
		<?php endif; ?>
		<div class="mb-5 p-4 bg-blue-50 border border-blue-100 rounded-xl text-blue-800 text-sm">
			<strong>Bem-vindo!</strong> Por segurança, altere sua senha no primeiro acesso.
		</div>
		<form method="POST" action="/change-password-first-update" class="space-y-4">
			<?php echo \App\Services\Csrf::field(); ?>
			<div>
				<label class="label">Nova senha</label>
				<input type="password" name="password" required minlength="6" class="input" placeholder="Mínimo 6 caracteres">
			</div>
			<div>
				<label class="label">Confirmar senha</label>
				<input type="password" name="password_confirm" required minlength="6" class="input" placeholder="Repita a nova senha">
			</div>
			<button type="submit" class="btn btn-primary btn-block">Trocar senha</button>
		</form>
		<p class="text-center text-sm text-slate-500 mt-5">
			Usuário: <strong class="text-slate-700"><?php echo htmlspecialchars($user['name'] ?? ''); ?></strong>
		</p>
	</div>
</div>
