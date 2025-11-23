<?php
/** @var array|null $user */
?>
<!doctype html>
<html lang="pt-br">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Controll IT - Help Desk</title>
	<script src="https://cdn.tailwindcss.com"></script>
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
	<link rel="icon" href="/favicon.svg">
</head>
<body class="bg-gray-50">
	<header class="bg-white text-gray-800">
		<div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
			<div class="flex items-center gap-3">
				<img src="/logo-controll-it.png" onerror="this.onerror=null;this.src='/logo-controll-it.svg';" class="h-[84px] object-contain bg-white rounded-md p-1" alt="Controll IT">
				<div class="leading-tight">
					<div class="text-red-600 font-semibold text-lg">Controll IT</div>
					<div class="text-xs text-gray-500">Grupo Corporation</div>
				</div>
				<img src="/logo-ca.png" class="h-[84px] object-contain bg-white rounded-md p-1 ml-2" alt="C&A">
			</div>
			<nav class="flex items-center gap-3">
				<?php if (!empty($auth->user())): ?>
					<span class="hidden sm:inline text-sm text-gray-600"><?php echo htmlspecialchars($auth->user()['name']); ?> (<?php echo htmlspecialchars($auth->user()['role']); ?>)</span>
					<a class="px-4 py-1.5 rounded-md bg-red-50 text-red-600 hover:bg-red-100" href="/logout">Sair</a>
				<?php else: ?>
					<a class="px-4 py-1.5 rounded-md bg-red-50 text-red-600 hover:bg-red-100" href="/login">Login</a>
				<?php endif; ?>
			</nav>
		</div>
	</header>
	<main class="max-w-7xl mx-auto px-4 py-6">
		<div id="toast" class="hidden fixed top-4 right-4 z-50 bg-blue-700 text-white px-4 py-2 rounded shadow"></div>


