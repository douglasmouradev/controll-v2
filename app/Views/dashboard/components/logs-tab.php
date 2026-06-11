<?php
/** @var array $user */
/** @var array $access_logs */

if (!view_is_admin($user ?? null)) {
	return;
}

$access_logs = $access_logs ?? [];
?>
<div id="tab-logs" class="tab-content hidden">
	<div class="page-header">
		<div>
			<h2 class="page-title">Logs de Auditoria</h2>
			<p class="page-subtitle">Ações administrativas recentes no sistema</p>
		</div>
	</div>

	<div class="ui-card overflow-x-auto">
		<table class="data-table">
			<thead>
				<tr>
					<th>Data/Hora</th>
					<th>Usuário</th>
					<th>Ação</th>
					<th>Recurso</th>
					<th>IP</th>
					<th>Status</th>
				</tr>
			</thead>
			<tbody>
				<?php if ($access_logs === []): ?>
					<tr><td colspan="6" class="empty-state">Nenhum log registrado.</td></tr>
				<?php else: ?>
					<?php foreach ($access_logs as $log): ?>
						<tr>
							<td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string) ($log['created_at'] ?? 'now')))); ?></td>
							<td><?php echo htmlspecialchars((string) ($log['user_name'] ?? 'Sistema')); ?></td>
							<td><?php echo htmlspecialchars((string) ($log['action'] ?? '')); ?></td>
							<td><?php echo htmlspecialchars((string) ($log['resource'] ?? '-')); ?></td>
							<td><?php echo htmlspecialchars((string) ($log['ip_address'] ?? '-')); ?></td>
							<td>
								<span class="badge <?php echo !empty($log['success']) ? 'badge-green' : 'badge-red'; ?>">
									<?php echo !empty($log['success']) ? 'OK' : 'Falha'; ?>
								</span>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
