<?php
/** @var array $user */
/** @var array $users */
$users = $users ?? [];
?>
<div id="tab-usuarios" class="tab-content hidden px-4 md:px-0">
	<div class="bg-white rounded-lg shadow p-6">
		<div class="mb-6">
			<h2 class="text-blue-700 font-semibold text-lg mb-2">Gerenciamento de Usuários</h2>
			<p class="text-gray-600 text-sm mb-4">Crie e gerencie usuários do sistema</p>
			<div class="flex gap-2">
				<button id="btn-criar-usuario" class="bg-blue-700 text-white px-4 py-2 rounded hover:bg-blue-800">Criar Usuário</button>
				<?php if ($user['role'] === 'admin'): ?>
					<button id="btn-global-credits-ticket-users" class="bg-purple-700 text-white px-4 py-2 rounded hover:bg-purple-800 text-sm" title="Ajustar Créditos Ticket para todos os usuários">Créditos Ticket</button>
					<button id="btn-global-credits-daily-users" class="bg-indigo-700 text-white px-4 py-2 rounded hover:bg-indigo-800 text-sm" title="Ajustar Créditos Diária para todos os usuários">Créditos Diária</button>
					<button id="btn-global-credits-project-users" class="bg-orange-700 text-white px-4 py-2 rounded hover:bg-orange-800 text-sm" title="Ajustar Créditos Projeto para todos os usuários">Créditos Projeto</button>
				<?php endif; ?>
			</div>
		</div>

		<!-- Tabela de Usuários -->
		<div class="overflow-x-auto">
			<table class="min-w-full text-sm">
				<thead>
					<tr class="text-left text-gray-600 border-b">
						<th class="px-3 py-2">ID</th>
						<th class="px-3 py-2">Nome</th>
						<th class="px-3 py-2">Email</th>
						<th class="px-3 py-2">Perfil</th>
						<th class="px-3 py-2">Créd. Ticket</th>
						<th class="px-3 py-2">Créd. Diária</th>
						<th class="px-3 py-2">Créd. Projeto</th>
						<th class="px-3 py-2">Data de Criação</th>
						<th class="px-3 py-2">Ações</th>
					</tr>
				</thead>
				<tbody id="users-tbody">
					<?php if (empty($users)): ?>
						<tr>
							<td colspan="8" class="px-3 py-4 text-center text-gray-500">Nenhum usuário encontrado.</td>
						</tr>
					<?php else: ?>
						<?php foreach ($users as $u): ?>
							<tr data-id="<?php echo (int) $u['id']; ?>" class="border-b hover:bg-gray-50">
								<td class="px-3 py-2"><?php echo (int) $u['id']; ?></td>
								<td class="px-3 py-2"><?php echo htmlspecialchars($u['name']); ?></td>
								<td class="px-3 py-2"><?php echo htmlspecialchars($u['email']); ?></td>
								<td class="px-3 py-2">
									<span class="px-2 py-1 rounded text-xs bg-blue-100 text-blue-800">
										<?php echo htmlspecialchars($u['role']); ?>
									</span>
								</td>
								<td class="px-3 py-2 credits-ticket-cell"><?php echo isset($u['credits']) ? (int) $u['credits'] : 0; ?></td>
								<td class="px-3 py-2 credits-daily-cell"><?php echo isset($u['daily_credits']) ? (int) $u['daily_credits'] : 0; ?></td>
								<td class="px-3 py-2 credits-project-dailies-cell"><?php echo isset($u['project_dailies_credits']) ? (int) $u['project_dailies_credits'] : 0; ?></td>
								<td class="px-3 py-2"><?php echo date('d/m/Y H:i', strtotime($u['created_at'])); ?></td>
								<td class="px-3 py-2">
									<button class="text-blue-700 underline btn-edit-user hover:text-blue-900">Editar</button>
									<?php if ($user['role'] === 'admin' && (int) $u['id'] !== (int) $user['id']): ?>
										<button class="ml-2 text-purple-700 underline btn-credits-ticket hover:text-purple-900">Créd. Ticket</button>
										<button class="ml-2 text-indigo-700 underline btn-credits-daily hover:text-indigo-900">Créd. Diária</button>
										<button class="ml-2 text-orange-700 underline btn-credits-project-dailies hover:text-orange-900">Créd. Projeto</button>
										<button class="ml-2 text-red-700 underline btn-delete-user hover:text-red-900">Excluir</button>
									<?php endif; ?>
									<?php if ($user['role'] === 'admin'): ?>
										<button class="ml-2 text-gray-700 underline btn-view-credit-history hover:text-gray-900 text-xs" data-user-id="<?php echo (int) $u['id']; ?>" data-user-name="<?php echo htmlspecialchars($u['name']); ?>">Ver Histórico</button>
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
