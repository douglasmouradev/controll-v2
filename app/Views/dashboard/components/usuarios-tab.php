<?php
/** @var array $user */
/** @var array $users */
$users = $users ?? [];
require_once BASE_PATH . '/app/Views/components/ui-helpers.php';
?>
<div id="tab-usuarios" class="tab-content hidden">
	<div class="ui-card ui-card-body">
		<div class="page-header !mb-5 flex flex-col md:flex-row md:items-end md:justify-between gap-4">
			<div>
				<h2 class="page-title text-xl">Gerenciamento de Usuários</h2>
				<p class="page-subtitle">Crie e gerencie usuários do sistema</p>
			</div>
			<div class="flex flex-wrap gap-2">
				<button type="button" id="btn-criar-usuario" class="btn btn-primary">Criar usuário</button>
				<?php if ($user['role'] === 'admin'): ?>
					<div class="relative">
						<button type="button" id="users-credits-toggle" class="btn btn-secondary">Créditos ▾</button>
						<div id="users-credits-menu" class="hidden absolute right-0 mt-2 w-52 ui-dropdown z-10">
							<button type="button" id="btn-global-credits-ticket-users">Ajustar Ticket</button>
							<button type="button" id="btn-global-credits-daily-users">Ajustar Diária</button>
							<button type="button" id="btn-global-credits-project-users">Ajustar Projeto</button>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<div class="overflow-x-auto rounded-xl border border-slate-100">
			<table class="data-table">
				<thead>
					<tr>
						<th>ID</th>
						<th>Nome</th>
						<th>Email</th>
						<th>Perfil</th>
						<th class="hide-mobile credits-ticket-cell">Créd. Ticket</th>
						<th class="hide-mobile credits-daily-cell">Créd. Diária</th>
						<th class="hide-mobile credits-project-dailies-cell">Créd. Projeto</th>
						<th>Criação</th>
						<th>Ações</th>
					</tr>
				</thead>
				<tbody id="users-tbody">
					<?php if (empty($users)): ?>
						<tr>
							<td colspan="9" class="empty-state">Nenhum usuário encontrado.</td>
						</tr>
					<?php else: ?>
						<?php foreach ($users as $u): ?>
							<tr data-id="<?php echo (int) $u['id']; ?>">
								<td><?php echo (int) $u['id']; ?></td>
								<td class="font-medium text-slate-800"><?php echo htmlspecialchars($u['name']); ?></td>
								<td><?php echo htmlspecialchars($u['email']); ?></td>
								<td>
									<span class="<?php echo ui_role_badge_class((string) ($u['role'] ?? '')); ?>">
										<?php echo htmlspecialchars($u['role']); ?>
									</span>
								</td>
								<td class="hide-mobile credits-ticket-cell"><?php echo isset($u['credits']) ? (int) $u['credits'] : 0; ?></td>
								<td class="hide-mobile credits-daily-cell"><?php echo isset($u['daily_credits']) ? (int) $u['daily_credits'] : 0; ?></td>
								<td class="hide-mobile credits-project-dailies-cell"><?php echo isset($u['project_dailies_credits']) ? (int) $u['project_dailies_credits'] : 0; ?></td>
								<td><?php echo date('d/m/Y H:i', strtotime($u['created_at'])); ?></td>
								<td class="whitespace-nowrap">
									<button type="button" class="btn-link btn-edit-user">Editar</button>
									<?php if ($user['role'] === 'admin' && (int) $u['id'] !== (int) $user['id']): ?>
										<button type="button" class="btn-link danger btn-delete-user ml-2">Excluir</button>
									<?php endif; ?>
									<?php if ($user['role'] === 'admin'): ?>
										<button type="button" class="btn-link muted btn-view-credit-history ml-2" data-user-id="<?php echo (int) $u['id']; ?>" data-user-name="<?php echo htmlspecialchars($u['name']); ?>">Histórico</button>
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
