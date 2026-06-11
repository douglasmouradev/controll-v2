<?php
/** @var array $user */
/** @var array $tickets */
/** @var array $filters */
/** @var array $stats */
require_once BASE_PATH . '/app/Views/components/ui-helpers.php';
$countTotal   = (int)($stats['total_tickets'] ?? 0);
$countAberto  = (int)($stats['open_tickets'] ?? 0);
$countAndamento = (int)($stats['in_progress_tickets'] ?? 0);
$countFechado = (int)($stats['closed_tickets'] ?? 0);
?>
<div id="tab-chamados" class="tab-content">
	<div class="ui-card ui-card-body">
		<div class="page-header !mb-5">
			<h2 class="page-title text-xl">Gerenciamento de Chamados</h2>
			<p class="page-subtitle">Filtros por status, prioridade, unidade, cidade e UF</p>
			<div class="flex flex-wrap items-center gap-2 mt-4">
				<span class="badge badge-gray">Todos: <strong><?php echo $countTotal; ?></strong></span>
				<span class="badge badge-blue">Aberto: <strong><?php echo $countAberto; ?></strong></span>
				<span class="badge badge-yellow">Em andamento: <strong><?php echo $countAndamento; ?></strong></span>
				<span class="badge badge-green">Fechado: <strong><?php echo $countFechado; ?></strong></span>
			</div>
		</div>

		<div class="filter-bar">
				<div>
					<label class="label">ID</label>
					<input id="f-id" class="input w-24" placeholder="ID" value="<?php echo htmlspecialchars((string)($filters['id'] ?? '')); ?>">
				</div>
				<div>
					<label class="label">Status</label>
					<select id="f-status" class="select">
						<option value="">Todos</option>
						<option <?php echo (($filters['status'] ?? '')==='Aberto')?'selected':''; ?>>Aberto</option>
						<option <?php echo (($filters['status'] ?? '')==='Em andamento')?'selected':''; ?>>Em andamento</option>
						<option <?php echo (($filters['status'] ?? '')==='Fechado')?'selected':''; ?>>Fechado</option>
					</select>
				</div>
				<div>
					<label class="label">Prioridade</label>
					<select id="f-priority" class="select">
						<option value="">Todas</option>
						<option <?php echo (($filters['priority'] ?? '')==='Baixa')?'selected':''; ?>>Baixa</option>
						<option <?php echo (($filters['priority'] ?? '')==='Média')?'selected':''; ?>>Média</option>
						<option <?php echo (($filters['priority'] ?? '')==='Alta')?'selected':''; ?>>Alta</option>
					</select>
				</div>
				<div>
					<label class="label">Sigla (unidade)</label>
					<input id="f-sigla" class="input w-28 uppercase" placeholder="Sigla" value="<?php echo htmlspecialchars((string)($filters['sigla'] ?? '')); ?>" autocomplete="off">
				</div>
				<div>
					<label class="label">Cidade</label>
					<input id="f-cidade" class="input w-36" placeholder="Cidade" value="<?php echo htmlspecialchars((string)($filters['cidade'] ?? '')); ?>" autocomplete="off">
				</div>
				<div>
					<label class="label">UF</label>
					<input id="f-estado" class="input w-16 uppercase" placeholder="UF" maxlength="2" value="<?php echo htmlspecialchars((string)($filters['estado'] ?? '')); ?>" autocomplete="off">
				</div>
				<?php if (in_array($user['role'], ['support','admin'], true)): ?>
					<div>
						<label class="label">Usuário</label>
						<input id="f-user" class="input w-32" placeholder="ID ou Nome" value="<?php echo htmlspecialchars((string)($filters['user'] ?? '')); ?>">
					</div>
				<?php endif; ?>
				<button type="button" id="f-apply" class="btn btn-primary">Aplicar</button>
				<button type="button" id="btn-abrir-chamado" class="btn btn-secondary ml-auto">Abrir Chamado</button>
		</div>

		<div class="overflow-x-auto rounded-xl border border-slate-100">
			<table class="data-table">
				<thead>
					<tr>
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
						<th class="px-3 py-2">Data Atendimento</th>
						<th class="px-3 py-2">Ações</th>
					</tr>
				</thead>
				<tbody id="tickets-tbody">
					<?php if (empty($tickets)): ?>
						<tr>
							<td colspan="15" class="empty-state">Nenhum chamado encontrado.</td>
						</tr>
					<?php else: ?>
						<?php foreach ($tickets as $t): ?>
							<tr data-id="<?php echo (int) $t['id']; ?>">
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
									<span class="<?php echo ui_status_badge_class((string)($t['status'] ?? '')); ?>">
										<?php echo htmlspecialchars((string)($t['status'] ?? '')); ?>
									</span>
								</td>
								<td class="px-3 py-2">
									<span class="<?php echo ui_priority_badge_class((string)($t['priority'] ?? '')); ?>">
										<?php echo htmlspecialchars((string)($t['priority'] ?? '')); ?>
									</span>
								</td>
								<td class="px-3 py-2 assign-cell"><?php echo htmlspecialchars($t['assigned_name'] ?? '-'); ?></td>
								<td class="px-3 py-2"><?php echo date('d/m/Y', strtotime($t['created_at'])); ?></td>
								<td class="px-3 py-2">
									<?php
										$serviceDate = $t['service_date'] ?? null;
										$serviceTime = $t['service_time'] ?? null;
										if ($serviceDate) {
											$formattedDate = date('d/m/Y', strtotime((string) $serviceDate));
											$formattedTime = $serviceTime ? substr((string) $serviceTime, 0, 5) : '';
											echo htmlspecialchars(trim($formattedDate . ($formattedTime ? ' ' . $formattedTime : '')));
										} else {
											echo '-';
										}
									?>
								</td>
								<td class="px-3 py-2 whitespace-nowrap">
									<button type="button" class="btn-link btn-view">Ver</button>
							<?php if (in_array($user['role'], ['support','admin'], true)): ?>
								<a class="btn-link ml-2" href="/tickets/clone?id=<?php echo (int) $t['id']; ?>">Clonar</a>
							<?php endif; ?>
							<?php if ((int)($t['user_id'] ?? 0) === (int)($user['id'] ?? 0) || in_array($user['role'], ['support','admin'], true)): ?>
								<button type="button" class="btn-link ml-2 btn-edit-ticket">Editar</button>
							<?php endif; ?>
							<?php if (in_array($user['role'], ['support','admin'], true)): ?>
								<button type="button" class="btn-link ml-2 btn-assign">Atribuir</button>
								<button type="button" class="btn-link danger btn-delete-ticket ml-2">Excluir</button>
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
