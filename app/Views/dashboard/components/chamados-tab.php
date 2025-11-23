<?php
/** @var array $user */
/** @var array $tickets */
/** @var array $filters */
?>
<div id="tab-chamados" class="tab-content px-4 md:px-0">
	<div class="bg-white rounded-lg shadow p-6">
		<div class="mb-6">
			<h2 class="text-blue-700 font-semibold text-lg mb-2">Gerenciamento de Chamados</h2>
			<p class="text-gray-600 text-sm mb-4">Filtros rápidos para status, prioridade e solicitante</p>
			
			<div class="flex flex-wrap gap-3 items-end mb-4">
				<div>
					<label class="block text-sm text-gray-600 mb-1">Buscar por ID</label>
					<input id="f-id" class="border rounded px-3 py-2 w-24" placeholder="ID" value="<?php echo htmlspecialchars((string)($filters['id'] ?? '')); ?>">
				</div>
				<div>
					<label class="block text-sm text-gray-600 mb-1">Status</label>
					<select id="f-status" class="border rounded px-3 py-2">
						<option value="">Todos</option>
						<option <?php echo (($filters['status'] ?? '')==='Aberto')?'selected':''; ?>>Aberto</option>
						<option <?php echo (($filters['status'] ?? '')==='Em andamento')?'selected':''; ?>>Em andamento</option>
						<option <?php echo (($filters['status'] ?? '')==='Fechado')?'selected':''; ?>>Fechado</option>
					</select>
				</div>
				<div>
					<label class="block text-sm text-gray-600 mb-1">Prioridade</label>
					<select id="f-priority" class="border rounded px-3 py-2">
						<option value="">Todas</option>
						<option>Baixa</option>
						<option>Média</option>
						<option>Alta</option>
					</select>
				</div>
				<?php if (in_array($user['role'], ['support','admin'], true)): ?>
					<div>
						<label class="block text-sm text-gray-600 mb-1">Usuário</label>
						<input id="f-user" class="border rounded px-3 py-2 w-32" placeholder="ID ou Nome">
					</div>
				<?php endif; ?>
				<button id="f-apply" class="bg-blue-700 text-white px-4 py-2 rounded hover:bg-blue-800">Aplicar</button>
				<button id="btn-abrir-chamado" class="bg-blue-700 text-white px-4 py-2 rounded hover:bg-blue-800 ml-auto">Abrir Chamado</button>
			</div>
		</div>

		<!-- Tabela de Chamados -->
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
						<th class="px-3 py-2">Endereço</th>
						<th class="px-3 py-2">Número</th>
						<th class="px-3 py-2">Cidade / UF</th>
						<th class="px-3 py-2">Status</th>
						<th class="px-3 py-2">Prioridade</th>
						<th class="px-3 py-2">Atribuído a</th>
						<th class="px-3 py-2">Data</th>
						<th class="px-3 py-2">Ações</th>
					</tr>
				</thead>
				<tbody id="tickets-tbody">
					<?php if (empty($tickets)): ?>
						<tr>
							<td colspan="13" class="px-3 py-4 text-center text-gray-500">Nenhum chamado encontrado.</td>
						</tr>
					<?php else: ?>
						<?php foreach ($tickets as $t): ?>
							<tr data-id="<?php echo (int) $t['id']; ?>" class="border-b hover:bg-gray-50">
								<td class="px-3 py-2"><?php echo (int) $t['id']; ?></td>
								<td class="px-3 py-2"><?php echo htmlspecialchars($t['title']); ?></td>
								<td class="px-3 py-2"><?php echo htmlspecialchars((string)($t['category'] ?? '')); ?></td>
								<td class="px-3 py-2"><?php echo htmlspecialchars($t['user_name'] ?? '-'); ?></td>
								<td class="px-3 py-2"><?php echo htmlspecialchars((string)($t['registration'] ?? '')); ?></td>
								<td class="px-3 py-2"><?php echo htmlspecialchars((string)($t['unit'] ?? '')); ?></td>
								<td class="px-3 py-2"><?php echo htmlspecialchars((string)($t['address'] ?? '')); ?></td>
								<td class="px-3 py-2"><?php echo htmlspecialchars((string)($t['address_number'] ?? '')); ?></td>
								<td class="px-3 py-2"><?php echo htmlspecialchars((string)($t['city'] ?? '')); ?>/<?php echo htmlspecialchars((string)($t['uf'] ?? '')); ?></td>
								<td class="px-3 py-2 status-cell">
									<span class="px-2 py-1 rounded text-xs <?php 
										$statusVal = (string)($t['status'] ?? '');
										echo $statusVal === 'Fechado' ? 'bg-green-100 text-green-800' : 
											($statusVal === 'Em andamento' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800'); 
									?>"><?php echo htmlspecialchars((string)($t['status'] ?? '')); ?></span>
								</td>
								<td class="px-3 py-2">
									<span class="px-2 py-1 rounded text-xs bg-gray-100"><?php echo htmlspecialchars((string)($t['priority'] ?? '')); ?></span>
								</td>
								<td class="px-3 py-2 assign-cell"><?php echo htmlspecialchars($t['assigned_name'] ?? '-'); ?></td>
								<td class="px-3 py-2"><?php echo date('d/m/Y', strtotime($t['created_at'])); ?></td>
								<td class="px-3 py-2">
									<button class="text-blue-700 underline btn-view hover:text-blue-900">Ver</button>
									<?php if (in_array($user['role'], ['support','admin'], true)): ?>
										<button class="ml-2 text-green-700 underline btn-assign hover:text-green-900">Atribuir p/ mim</button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
