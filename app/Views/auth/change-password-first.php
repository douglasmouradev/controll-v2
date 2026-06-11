<!DOCTYPE html>
<html lang="pt-BR">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Trocar Senha - Primeiro Login</title>
	<script src="https://cdn.tailwindcss.com"></script>
	<style>
		body {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
		}
	</style>
</head>
<body>
	<div class="w-full max-w-md flex flex-col items-center">
		<!-- Header -->
		<div class="text-center mb-8">
			<h1 class="text-4xl font-bold text-white mb-2">Controll IT</h1>
			<p class="text-gray-100">Primeira Troca de Senha</p>
		</div>

		<!-- Card do Formulário -->
		<div class="bg-white rounded-lg shadow-2xl p-8 w-full">

			<?php if (!empty($error)): ?>
				<div class="mb-6 p-4 bg-red-50 border border-red-200 rounded text-red-700 text-sm">
					<?php echo htmlspecialchars($error); ?>
				</div>
			<?php endif; ?>

			<div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded text-blue-700 text-sm">
				<strong>Bem-vindo!</strong><br>
				Por segurança, você deve trocar sua senha no primeiro login.
			</div>

			<form method="POST" action="/change-password-first-update" class="space-y-4">
				<?php echo \App\Services\Csrf::field(); ?>
				<div>
					<label class="block text-sm font-medium text-gray-700 mb-2">Nova Senha</label>
					<input type="password" name="password" required minlength="6" class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Digite sua nova senha">
					<p class="text-xs text-gray-500 mt-1">Mínimo 6 caracteres</p>
				</div>

				<div>
					<label class="block text-sm font-medium text-gray-700 mb-2">Confirmar Senha</label>
					<input type="password" name="password_confirm" required minlength="6" class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Confirme sua nova senha">
				</div>

				<button type="submit" class="w-full bg-blue-700 text-white py-2 rounded font-semibold hover:bg-blue-800 transition">
					Trocar Senha
				</button>
			</form>

			<div class="mt-6 text-center">
				<p class="text-gray-600 text-sm">
					Usuário: <strong><?php echo htmlspecialchars($user['name'] ?? ''); ?></strong>
				</p>
			</div>
		</div>
	</div>
</body>
</html>
