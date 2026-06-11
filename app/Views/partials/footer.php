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
			el.textContent = msg;
			el.className = 'show toast-' + type;
			clearTimeout(el._toastTimer);
			el._toastTimer = setTimeout(function () {
				el.classList.remove('show');
			}, 3200);
		}
	</script>
</body>
</html>
