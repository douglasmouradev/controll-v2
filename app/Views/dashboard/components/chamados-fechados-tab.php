<?php
/** @var array $user */
/** @var array $closed_tickets */
/** @var array $closed_filters */
$closed_filters = $closed_filters ?? [];
$closed_pagination = $closed_pagination ?? ['page' => 1, 'per_page' => 50, 'total' => 0, 'pages' => 1];
require_once BASE_PATH . '/app/Views/components/ui-helpers.php';
require_once BASE_PATH . '/app/Views/helpers/auth.php';
?>
<div id="tab-chamados-fechados" class="tab-content hidden">
	<div class="ui-card ui-card-body">
		<div class="page-header !mb-5">
			<h2 class="page-title text-xl">Chamados Fechados</h2>
			<p class="page-subtitle">Histórico de chamados resolvidos</p>
		</div>

		<div class="filter-bar">
			<div>
				<label class="label">ID</label>
				<input id="f-closed-id" class="input w-24" placeholder="ID" value="<?php echo htmlspecialchars((string)($closed_filters['id'] ?? '')); ?>">
			</div>
			<div>
				<label class="label">Período</label>
				<select id="f-closed-period" class="select">
					<option value="" <?php echo empty($closed_filters['period']) ? 'selected' : ''; ?>>Todos</option>
					<option value="7" <?php echo (string)($closed_filters['period'] ?? '') === '7' ? 'selected' : ''; ?>>Últimos 7 dias</option>
					<option value="30" <?php echo (string)($closed_filters['period'] ?? '') === '30' ? 'selected' : ''; ?>>Últimos 30 dias</option>
					<option value="90" <?php echo (string)($closed_filters['period'] ?? '') === '90' ? 'selected' : ''; ?>>Últimos 90 dias</option>
				</select>
			</div>
			<?php if (view_is_support_or_admin($user)): ?>
				<div>
					<label class="label">Usuário</label>
					<input id="f-closed-user" class="input w-36" placeholder="ID ou nome" value="<?php echo htmlspecialchars((string)($closed_filters['user'] ?? '')); ?>">
				</div>
			<?php endif; ?>
			<button type="button" id="f-closed-apply" class="btn btn-primary">Aplicar</button>
		</div>

		<div class="overflow-x-auto rounded-xl border border-slate-100">
			<table class="data-table">
				<thead>
					<tr>
						<th>ID</th>
						<th>Título</th>
						<th class="hide-mobile">Categoria</th>
						<th>Usuário</th>
						<th class="hide-mobile">Unidade</th>
						<th>Prioridade</th>
						<th>Abertura</th>
						<th>Fechamento</th>
						<th>Ações</th>
					</tr>
				</thead>
				<tbody id="closed-tickets-tbody">
					<?php if (empty($closed_tickets)): ?>
						<tr>
							<td colspan="9" class="empty-state">Nenhum chamado fechado encontrado.</td>
						</tr>
					<?php else: ?>
						<?php foreach ($closed_tickets as $t): ?>
							<tr data-id="<?php echo (int) $t['id']; ?>">
								<td><?php echo (int) $t['id']; ?></td>
								<td class="font-medium text-slate-800"><?php echo htmlspecialchars($t['title']); ?></td>
								<td class="hide-mobile"><?php echo htmlspecialchars((string)($t['category'] ?? '')); ?></td>
								<td><?php echo htmlspecialchars($t['user_name'] ?? '-'); ?></td>
								<td class="hide-mobile"><?php echo htmlspecialchars((string)($t['unit'] ?? '')); ?></td>
								<td>
									<span class="<?php echo ui_priority_badge_class((string)($t['priority'] ?? '')); ?>">
										<?php echo htmlspecialchars((string)($t['priority'] ?? '')); ?>
									</span>
								</td>
								<td><?php echo date('d/m/Y', strtotime($t['created_at'])); ?></td>
								<td><?php echo date('d/m/Y', strtotime($t['updated_at'])); ?></td>
								<td>
									<button type="button" class="btn-link btn-view-closed">Ver</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<?php if (($closed_pagination['pages'] ?? 1) > 1): ?>
			<div class="flex flex-wrap items-center justify-between gap-3 mt-4 text-sm text-slate-600">
				<p>
					Página <strong><?php echo (int) $closed_pagination['page']; ?></strong>
					de <strong><?php echo (int) $closed_pagination['pages']; ?></strong>
					— <?php echo (int) $closed_pagination['total']; ?> chamado(s) fechado(s)
				</p>
				<div class="flex items-center gap-2">
					<?php
					$buildClosedPageUrl = static function (int $targetPage) use ($closed_filters): string {
						$params = array_filter([
							'closed_id' => $closed_filters['id'] ?? null,
							'closed_period' => $closed_filters['period'] ?? null,
							'closed_user' => $closed_filters['user'] ?? null,
							'closed_page' => $targetPage > 1 ? $targetPage : null,
							'tab' => 'chamados-fechados',
						], static fn ($value) => $value !== null && $value !== '');
						return '/?' . http_build_query($params);
					};
					$closedCurrentPage = (int) ($closed_pagination['page'] ?? 1);
					$closedTotalPages = (int) ($closed_pagination['pages'] ?? 1);
					?>
					<?php if ($closedCurrentPage > 1): ?>
						<a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars($buildClosedPageUrl($closedCurrentPage - 1)); ?>">Anterior</a>
					<?php endif; ?>
					<?php if ($closedCurrentPage < $closedTotalPages): ?>
						<a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars($buildClosedPageUrl($closedCurrentPage + 1)); ?>">Próxima</a>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>
