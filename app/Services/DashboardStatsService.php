<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class DashboardStatsService
{
	public static function getStats(array $user): array
	{
		$pdo = Database::pdo();
		$role = TicketAccess::normalizeRole((string) ($user['role'] ?? ''));

		$hasTicketStatuses = DatabaseSchema::tableExists($pdo, 'ticket_statuses');
		$hasStatusesTable = !$hasTicketStatuses && DatabaseSchema::tableExists($pdo, 'statuses');
		$hasStatusColumn = DatabaseSchema::columnExists($pdo, 'tickets', 'status');

		if ($role === 'user') {
			$stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM tickets WHERE user_id = :user_id');
			$stmt->execute([':user_id' => (int) $user['id']]);
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			$totalTickets = $row ? (int) $row['cnt'] : 0;
		} else {
			$totalTickets = 0;
			$stmt = $pdo->query('SELECT COUNT(*) as cnt FROM tickets');
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			$totalTickets += $row ? (int) $row['cnt'] : 0;

			try {
				$dbName = (string) ($pdo->query('SELECT DATABASE()')->fetchColumn() ?: '');
				if ($dbName !== '') {
					$stExists = $pdo->prepare(
						'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :db AND table_name = :t'
					);
					$stExists->execute([':db' => $dbName, ':t' => 'tickets_backup']);
					if ((int) ($stExists->fetchColumn() ?: 0) > 0) {
						$stmtB = $pdo->query('SELECT COUNT(*) as cnt FROM tickets_backup');
						$rowB = $stmtB->fetch(PDO::FETCH_ASSOC);
						$totalTickets += $rowB ? (int) $rowB['cnt'] : 0;
					}
				}
			} catch (\Throwable) {
			}
		}

		$closedTickets = self::countByStatus($pdo, $user, $role, $hasTicketStatuses, $hasStatusesTable, $hasStatusColumn, 'Fechado');
		$openTickets = self::countByStatus($pdo, $user, $role, $hasTicketStatuses, $hasStatusesTable, $hasStatusColumn, 'Aberto');
		$inProgressTickets = self::countByStatus($pdo, $user, $role, $hasTicketStatuses, $hasStatusesTable, $hasStatusColumn, 'Em andamento');
		$scheduledTickets = self::countByStatus($pdo, $user, $role, $hasTicketStatuses, $hasStatusesTable, $hasStatusColumn, 'Agendado');

		$projectDailies = 0;
		if (DatabaseSchema::columnExists($pdo, 'tickets', 'category')) {
			$params = [':category' => 'Projeto'];
			$sql = 'SELECT COUNT(*) as cnt FROM tickets WHERE category = :category';
			if ($role === 'user') {
				$sql .= ' AND user_id = :user_id';
				$params[':user_id'] = (int) $user['id'];
			}
			$stmt = $pdo->prepare($sql);
			$stmt->execute($params);
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			$projectDailies = $row ? (int) $row['cnt'] : 0;
		}

		$totalUsers = 0;
		if ($role === 'admin') {
			$stmt = $pdo->query('SELECT COUNT(*) as cnt FROM users');
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			$totalUsers = $row ? (int) $row['cnt'] : 0;
		}

		$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM users WHERE user_type IN ('support', 'admin', 'suporte')");
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		$supportAgents = $row ? (int) $row['cnt'] : 0;

		$avgResolution = 0.0;
		if ($hasTicketStatuses || $hasStatusesTable || $hasStatusColumn) {
			$params = [':status' => 'Fechado'];
			$sql = 'SELECT AVG(TIMESTAMPDIFF(HOUR, t.created_at, t.updated_at)) as avg_hours FROM tickets t';
			if ($hasTicketStatuses) {
				$sql .= ' LEFT JOIN ticket_statuses ts ON ts.id = t.status_id WHERE ts.name = :status';
			} elseif ($hasStatusesTable) {
				$sql .= ' LEFT JOIN statuses s ON s.id = t.status_id WHERE s.name = :status';
			} else {
				$sql .= ' WHERE t.status = :status';
			}
			$sql .= ' AND t.updated_at IS NOT NULL';
			$stmt = $pdo->prepare($sql);
			$stmt->execute($params);
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			$avgResolution = ($row && $row['avg_hours'] !== null) ? round((float) $row['avg_hours'], 1) : 0.0;
		}

		$totalTicketCredits = 0;
		$totalDailyCredits = 0;
		$totalProjectDailiesCredits = 0;
		if ($role === 'admin') {
			$row = $pdo->query('SELECT COALESCE(SUM(credits), 0) as total FROM users')->fetch(PDO::FETCH_ASSOC);
			$totalTicketCredits = $row ? (int) $row['total'] : 0;
			$row = $pdo->query('SELECT COALESCE(SUM(daily_credits), 0) as total FROM users')->fetch(PDO::FETCH_ASSOC);
			$totalDailyCredits = $row ? (int) $row['total'] : 0;
			$row = $pdo->query('SELECT COALESCE(SUM(project_dailies_credits), 0) as total FROM users')->fetch(PDO::FETCH_ASSOC);
			$totalProjectDailiesCredits = $row ? (int) $row['total'] : 0;
		}

		return [
			'total_tickets' => $totalTickets,
			'closed_tickets' => $closedTickets,
			'open_tickets' => $openTickets,
			'in_progress_tickets' => $inProgressTickets,
			'scheduled_tickets' => $scheduledTickets,
			'total_users' => $totalUsers,
			'support_agents' => $supportAgents,
			'avg_resolution_hours' => $avgResolution,
			'project_dailies' => $projectDailies,
			'total_ticket_credits' => $totalTicketCredits,
			'total_daily_credits' => $totalDailyCredits,
			'total_project_dailies_credits' => $totalProjectDailiesCredits,
		];
	}

	private static function countByStatus(
		PDO $pdo,
		array $user,
		string $role,
		bool $hasTicketStatuses,
		bool $hasStatusesTable,
		bool $hasStatusColumn,
		string $status
	): int {
		if (!$hasTicketStatuses && !$hasStatusesTable && !$hasStatusColumn) {
			return 0;
		}

		$params = [':status' => $status];
		$sql = 'SELECT COUNT(*) as cnt FROM tickets t';
		if ($hasTicketStatuses) {
			$sql .= ' LEFT JOIN ticket_statuses ts ON ts.id = t.status_id WHERE ts.name = :status';
		} elseif ($hasStatusesTable) {
			$sql .= ' LEFT JOIN statuses s ON s.id = t.status_id WHERE s.name = :status';
		} else {
			$sql .= ' WHERE t.status = :status';
		}

		if ($role === 'user') {
			$sql .= strpos($sql, 'WHERE') === false ? ' WHERE t.user_id = :user_id' : ' AND t.user_id = :user_id';
			$params[':user_id'] = (int) $user['id'];
		}

		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return $row ? (int) $row['cnt'] : 0;
	}
}
