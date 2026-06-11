<div class="max-w-md mx-auto">
	<div class="bg-white rounded-2xl shadow-[0_24px_48px_-24px_rgba(0,0,0,0.25)] border border-gray-200 overflow-hidden">
		<div class="px-8 pt-10 pb-4 text-center">
			<h1 class="text-[28px] leading-7 font-extrabold text-red-600 tracking-tight">TDesk Solutions</h1>
			<p class="text-gray-600 mt-3 text-[15px]">Sistema de gestão de chamados técnicos</p>
		</div>
		<div class="px-8">
			<div class="flex items-center justify-center mb-6">
				<div class="h-10 w-1.5 bg-red-600 rounded-l-lg"></div>
				<div class="inline-flex items-center gap-2 bg-[#eef2ff] text-[#1f2937] border border-red-300 rounded-r-lg px-4 py-2.5">
					<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2l6 3v4c0 5-3.5 7-6 9-2.5-2-6-4-6-9V5l6-3z"/></svg>
					<span class="text-[14px]">Sistema seguro e confiável</span>
				</div>
			</div>
			<?php if (!empty($error)): ?>
				<div class="mb-4 bg-red-50 text-red-700 px-4 py-2.5 rounded border border-red-200"><?php echo htmlspecialchars($error); ?></div>
			<?php endif; ?>
			<form method="post" action="/login" class="space-y-4">
				<div>
					<label class="block text-[11px] font-bold tracking-wider text-gray-600 mb-1">USUÁRIO:</label>
					<input name="email" type="text" class="w-full h-11 border border-gray-300 rounded-xl px-3 shadow-inner focus:ring-2 focus:ring-blue-500 outline-none" required>
				</div>
				<div>
					<label class="block text-[11px] font-bold tracking-wider text-gray-600 mb-1">SENHA:</label>
					<input name="password" type="password" class="w-full h-11 border border-gray-300 rounded-xl px-3 shadow-inner focus:ring-2 focus:ring-blue-500 outline-none" required>
				</div>
				<button class="w-full bg-[#2F66F5] hover:bg-[#2558de] text-white rounded-xl px-4 py-2.5 font-semibold shadow-md">Entrar</button>
			</form>
			<div class="text-center mt-4 mb-8">
				<a class="text-red-600 text-sm hover:underline" href="#">Esqueceu sua senha?</a>
			</div>
		</div>
	</div>
</div>