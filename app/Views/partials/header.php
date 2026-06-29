<?php
/** @var array|null $user */

use App\Services\Csrf;

if (!function_exists('asset_url')) {
	require_once BASE_PATH . '/app/Views/helpers/assets.php';
}

$layout = $layout ?? 'default';
$isDashboard = $layout === 'dashboard';
$isAuth = $layout === 'auth';
$bodyClass = $isDashboard ? 'layout-dashboard' : ($isAuth ? 'layout-auth' : '');
?>
<!doctype html>
<html lang="pt-br">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="csrf-token" content="<?php echo htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
	<title>Controll IT — Help Desk</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('/assets/css/app.css'), ENT_QUOTES, 'UTF-8'); ?>">
	<link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('/assets/css/tw.css'), ENT_QUOTES, 'UTF-8'); ?>">
	<script>
	(function () {
		var meta = document.querySelector('meta[name="csrf-token"]');
		if (!meta) return;
		var csrfToken = meta.getAttribute('content') || '';
		if (!csrfToken) return;
		var originalFetch = window.fetch;
		window.fetch = function (input, init) {
			init = init || {};
			var method = String(init.method || 'GET').toUpperCase();
			if (method === 'POST' || method === 'PUT' || method === 'PATCH' || method === 'DELETE') {
				if (init.body instanceof FormData && !init.body.has('csrf_token')) {
					init.body.append('csrf_token', csrfToken);
				}
				var headers = new Headers(init.headers || {});
				if (!headers.has('X-CSRF-TOKEN')) {
					headers.set('X-CSRF-TOKEN', csrfToken);
				}
				if (!headers.has('X-Requested-With')) {
					headers.set('X-Requested-With', 'XMLHttpRequest');
				}
				init.headers = headers;
			}
			return originalFetch(input, init).then(function (response) {
				if (response.status === 401) {
					window.location.href = '/login';
					return response;
				}
				if (response.status === 403 && method !== 'GET') {
					window.location.reload();
				}
				return response;
			});
		};
	})();
	</script>
	<script src="/assets/js/vendor/chart.umd.min.js"></script>
	<link rel="icon" href="/favicon.svg">
</head>
<body class="<?php echo htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8'); ?>">
<?php if (!$isDashboard && !$isAuth): ?>
	<header class="bg-white border-b border-slate-200">
		<div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
			<div class="flex items-center gap-3">
				<?php $variant = 'header'; include BASE_PATH . '/app/Views/components/brand-logos.php'; ?>
				<div class="leading-tight">
					<div class="text-brand font-bold text-lg">Controll IT</div>
					<div class="text-xs text-slate-500">Help Desk Corporativo</div>
				</div>
			</div>
			<nav class="flex items-center gap-3">
				<?php if (!empty($auth->user())): ?>
					<span class="hidden sm:inline text-sm text-slate-600"><?php echo htmlspecialchars($auth->user()['name']); ?></span>
					<a class="btn btn-secondary btn-sm" href="/logout">Sair</a>
				<?php else: ?>
					<a class="btn btn-secondary btn-sm" href="/login">Login</a>
				<?php endif; ?>
			</nav>
		</div>
	</header>
<?php endif; ?>
<?php if ($isDashboard || $isAuth): ?>
	<div id="toast" class="hidden toast-info" role="status" aria-live="polite"></div>
<?php else: ?>
	<main class="max-w-7xl mx-auto px-4 py-6">
		<div id="toast" class="hidden toast-info" role="status" aria-live="polite"></div>
<?php endif; ?>
<?php if ($isAuth): ?>
	<main class="auth-wrap">
<?php elseif ($isDashboard): ?>
	<main>
<?php endif; ?>
