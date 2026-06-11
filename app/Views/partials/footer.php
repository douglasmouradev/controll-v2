<?php
$layout = $layout ?? 'default';
$isDashboard = $layout === 'dashboard';
$isAuth = $layout === 'auth';
?>
	</main>
<?php if (!$isDashboard): ?>
	<footer class="<?php echo $isAuth ? 'py-6 text-center text-sm text-white/70' : 'mt-10 py-6 text-center text-sm text-slate-500'; ?>">
		&copy; <?php echo date('Y'); ?> Controll IT &bull; Plataforma de Help Desk
	</footer>
<?php endif; ?>
	<script>
		function showToast(msg, type) {
			const el = document.getElementById('toast');
			if (!el) return;
			type = type || 'info';
			const icons = { success: '✓', error: '✕', info: 'ℹ' };
			el.innerHTML = '<span class="toast-icon" aria-hidden="true">' + (icons[type] || icons.info) + '</span><span>' + msg + '</span>';
			el.className = 'show toast-' + type;
			clearTimeout(el._toastTimer);
			el._toastTimer = setTimeout(function () {
				el.classList.remove('show');
			}, 3200);
		}
	</script>
</body>
</html>
