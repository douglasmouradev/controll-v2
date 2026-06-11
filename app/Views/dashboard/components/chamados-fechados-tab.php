<?php
/** @var array $user */
/** @var array $closed_tickets */
/** @var array $filters */
?>
<div id="tab-chamados-fechados" class="tab-content hidden px-4 md:px-0">
	<div class="bg-white rounded-lg shadow p-6">
		<div class="mb-6">
			<h2 class="text-blue-700 font-semibold text-lg mb-2">Chamados Fechados</h2>
			<p class="text-gray-600 text-sm mb-4">Histórico de chamados resolvidos</p>
			
			<div class="flex flex-wrap gap-3 items-end mb-4">
				<div>
					<label class="block text-sm text-gray-600 mb-1">Buscar por ID</label>
					<input id="f-closed-id" class="border rounded px-3 py-2 w-24" placeholder="ID" value="<?php echo htmlspecialchars((string)($filters['id'] ?? '')); ?>">
				</div>
				<div>
					<label class="block text-sm text-gray-600 mb-1">Período</label>
					<select id="f-closed-period" class="border rounded px-3 py-2">
						<option value="">Todos</option>
						<option value="7">Últimos 7 dias</option>
						<option value="30">Últimos 30 dias</option>
						<option value="90">Últimos 90 dias</option>
					</select>
				</div>
				<?php if (in_array($user['role'], ['support','admin'], true)): ?>
					<div>
						<label class="block text-sm text-gray-600 mb-1">Usuário</label>
						<input id="f-closed-user" class="border rounded px-3 py-2 w-32" placeholder="ID ou Nome">
					</div>
				<?php endif; ?>
				<button id="f-closed-apply" class="bg-blue-700 text-white px-4 py-2 rounded hover:bg-blue-800">Aplicar</button>
			</div>
		</div>

		<!-- Tabela de Chamados Fechados -->
		<div class="overflow-x-auto">
			<table class="min-w-full text-sm">
				<thead>
					<tr class="text-left text-gray-600 border-b">
						<th class="px-3 py-2">ID</th>
						<th class="px-3 py-2">Título</th>
						<th class="px-3 py-2">Categoria</th>
						<th class="px-3 py-2">Usuário</th>
						<th class="px-3 py-2">Matrícula</th>
						<th class="px-3 py-2">Unidade</th>
						<th class="px-3 py-2">Prioridade</th>
						<th class="px-3 py-2">Data Abertura</th>
						<th class="px-3 py-2">Data Fechamento</th>
						<th class="px-3 py-2">Ações</th>
					</tr>
				</thead>
				<tbody id="closed-tickets-tbody">
					<?php if (empty($closed_tickets)): ?>
						<tr>
							<td colspan="10" class="px-3 py-4 text-center text-gray-500">Nenhum chamado fechado encontrado.</td>
						</tr>
					<?php else: ?>
						<?php foreach ($closed_tickets as $t): ?>
							<tr data-id="<?php echo (int) $t['id']; ?>" class="border-b hover:bg-gray-50">
								<td class="px-3 py-2"><?php echo (int) $t['id']; ?></td>
								<td class="px-3 py-2"><?php echo htmlspecialchars($t['title']); ?></td>
								<td class="px-3 py-2"><?php echo htmlspecialchars((string)($t['category'] ?? '')); ?></td>
								<td class="px-3 py-2"><?php echo htmlspecialchars($t['user_name'] ?? '-'); ?></td>
								<td class="px-3 py-2"><?php echo htmlspecialchars((string)($t['registration'] ?? '')); ?></td>
								<td class="px-3 py-2"><?php echo htmlspecialchars((string)($t['unit'] ?? '')); ?></td>
								<td class="px-3 py-2">
									<span class="px-2 py-1 rounded text-xs bg-gray-100"><?php echo htmlspecialchars((string)($t['priority'] ?? '')); ?></span>
								</td>
								<td class="px-3 py-2"><?php echo date('d/m/Y', strtotime($t['created_at'])); ?></td>
								<td class="px-3 py-2"><?php echo date('d/m/Y', strtotime($t['updated_at'])); ?></td>
								<td class="px-3 py-2">
									<button class="text-blue-700 underline btn-view-closed hover:text-blue-900">Ver</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
