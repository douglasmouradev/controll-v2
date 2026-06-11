<?php
/** @var array|null $user */
use App\Services\AuditLock;
?>
<div class="auth-card">
	<div class="auth-card-header">
		<?php $variant = 'auth'; include BASE_PATH . '/app/Views/components/brand-logos.php'; ?>
		<h1 class="text-2xl font-extrabold text-slate-900 tracking-tight mt-4">Sistema em auditoria</h1>
		<p class="text-slate-500 mt-2 text-sm">Controll IT Help Desk</p>
	</div>
	<div class="px-8 pb-8">
		<div class="mb-6 p-5 bg-amber-50 border border-amber-200 rounded-xl text-amber-950 text-center">
			<p class="text-base font-semibold mb-2">O sistema está temporariamente indisponível para usuários finais.</p>
			<p class="text-sm leading-relaxed">
				Estamos realizando uma auditoria e melhorias na plataforma.<br>
				O acesso será liberado em <strong><?php echo htmlspecialchars(AuditLock::availableDateFormatted()); ?></strong>.
			</p>
		</div>
		<?php if (!empty($user['name'])): ?>
			<p class="text-center text-sm text-slate-500 mb-5">
				Usuário: <strong class="text-slate-700"><?php echo htmlspecialchars((string) $user['name']); ?></strong>
			</p>
		<?php endif; ?>
		<a href="/logout" class="btn btn-secondary btn-block">Sair</a>
	</div>
</div>
