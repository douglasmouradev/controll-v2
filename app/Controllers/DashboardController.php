<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Auth;
use App\Services\Database;

final class DashboardController extends Controller
{
	public function index(): void
	{
		$this->requireAuth([]);
		$sessionUser = Auth::instance()->user();
		// Carrega dados completos do usuário (incluindo créditos) quando possível
		$fullUser = $sessionUser && isset($sessionUser['id'])
			? User::findById((int) $sessionUser['id'])
			: null;
		$user = $fullUser ?: $sessionUser;

		$filters = [
			'id' => $_GET['id'] ?? null,
			'status' => $_GET['status'] ?? null,
			'priority' => $_GET['priority'] ?? null,
			'user' => $_GET['user'] ?? null,
		];
		$tickets = Ticket::listForUser($user, $filters);

		// Carregar chamados fechados
		$closedFilters = [
			'id' => $_GET['closed_id'] ?? null,
			'status' => 'Fechado',
			'period' => $_GET['closed_period'] ?? null,
			'user' => $_GET['closed_user'] ?? null,
		];
		$closedTickets = Ticket::listClosed($user, $closedFilters);

		// Estatísticas para os cards
		try {
			$stats = $this->getStats($user);
		} catch (\Throwable $e) {
			error_log('Erro ao obter estatísticas: ' . $e->getMessage());
			$stats = [
				'total_tickets' => 0,
				'total_users' => 0,
				'support_agents' => 0,
				'avg_resolution_hours' => 0,
			];
		}

		// Lista de usuários (para admin/suporte)
		$users = [];
		if (in_array($user['role'], ['support', 'admin'], true)) {
			try {
				$users = User::listAll();
			} catch (\Throwable $e) {
				error_log('Erro ao listar usuários: ' . $e->getMessage());
				$users = [];
			}
		}

		$this->view('dashboard/index', [
			'user' => $user,
			'tickets' => $tickets,
			'closed_tickets' => $closedTickets,
			'filters' => $filters,
			'stats' => $stats,
			'users' => $users,
		]);
	}

	private function getStats(array $user): array
	{
		$pdo = Database::pdo();
		
		// Detectar estrutura de status
		$hasTicketStatuses = $this->tableExists($pdo, 'ticket_statuses');
		$hasStatusesTable = !$hasTicketStatuses && $this->tableExists($pdo, 'statuses');
		$hasStatusColumn = $this->columnExists($pdo, 'tickets', 'status');
		
		// Total de chamados (apenas 'user' vê só os seus, 'support' e 'admin' veem todos)
		if ($user['role'] === 'user') {
			$stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM tickets WHERE user_id = :user_id');
			$stmt->execute([':user_id' => (int) $user['id']]);
			$row = $stmt->fetch(\PDO::FETCH_ASSOC);
			$totalTickets = $row ? (int)$row['cnt'] : 0;
		} else {
			$stmt = $pdo->query('SELECT COUNT(*) as cnt FROM tickets');
			$row = $stmt->fetch(\PDO::FETCH_ASSOC);
			$totalTickets = $row ? (int)$row['cnt'] : 0;
		}
		
		// Total de chamados fechados
		$closedTickets = 0;
		if ($hasTicketStatuses || $hasStatusesTable || $hasStatusColumn) {
			$params = [];
			$sql = 'SELECT COUNT(*) as cnt FROM tickets t';
			if ($hasTicketStatuses) {
				$sql .= ' LEFT JOIN ticket_statuses ts ON ts.id = t.status_id WHERE ts.name = :status';
			} elseif ($hasStatusesTable) {
				$sql .= ' LEFT JOIN statuses s ON s.id = t.status_id WHERE s.name = :status';
			} elseif ($hasStatusColumn) {
				$sql .= ' WHERE t.status = :status';
			}
			$params[':status'] = 'Fechado';
			if ($user['role'] === 'user') {
				// restringir por usuário logado
				if (strpos($sql, 'WHERE') === false) {
					$sql .= ' WHERE t.user_id = :user_id';
				} else {
					$sql .= ' AND t.user_id = :user_id';
				}
				$params[':user_id'] = (int)$user['id'];
			}
			$stmt = $pdo->prepare($sql);
			$stmt->execute($params);
			$row = $stmt->fetch(\PDO::FETCH_ASSOC);
			$closedTickets = $row ? (int)$row['cnt'] : 0;
		}
		
		// Diárias de Projeto (tickets com categoria 'Projeto')
		$projectDailies = 0;
		if ($this->columnExists($pdo, 'tickets', 'category')) {
			$params = [
				':category' => 'Projeto',
			];
			$sql = 'SELECT COUNT(*) as cnt FROM tickets WHERE category = :category';
			if (($user['role'] ?? null) === 'user') {
				$sql .= ' AND user_id = :user_id';
				$params[':user_id'] = (int) $user['id'];
			}
			$stmt = $pdo->prepare($sql);
			$stmt->execute($params);
			$row = $stmt->fetch(\PDO::FETCH_ASSOC);
			$projectDailies = $row ? (int) $row['cnt'] : 0;
		}
		
		// Total de usuários (só admin)
		$totalUsers = 0;
		if ($user['role'] === 'admin') {
			$stmt = $pdo->query('SELECT COUNT(*) as cnt FROM users');
			$row = $stmt->fetch(\PDO::FETCH_ASSOC);
			$totalUsers = $row ? (int)$row['cnt'] : 0;
		}
		
		// Agentes de suporte (user_type já é normalizado)
		$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM users WHERE user_type IN ('support', 'admin')");
		$row = $stmt->fetch(\PDO::FETCH_ASSOC);
		$supportAgents = $row ? (int)$row['cnt'] : 0;
		
		// Tempo médio de resolução (em horas) apenas para fechados
		$avgResolution = 0.0;
		if ($hasTicketStatuses || $hasStatusesTable || $hasStatusColumn) {
			$params = [];
			$sql = 'SELECT AVG(TIMESTAMPDIFF(HOUR, t.created_at, t.updated_at)) as avg_hours FROM tickets t';
			if ($hasTicketStatuses) {
				$sql .= ' LEFT JOIN ticket_statuses ts ON ts.id = t.status_id WHERE ts.name = :status';
			} elseif ($hasStatusesTable) {
				$sql .= ' LEFT JOIN statuses s ON s.id = t.status_id WHERE s.name = :status';
			} elseif ($hasStatusColumn) {
				$sql .= ' WHERE t.status = :status';
			}
			$params[':status'] = 'Fechado';
			$sql .= ' AND t.updated_at IS NOT NULL';
			
			$stmt = $pdo->prepare($sql);
			$stmt->execute($params);
			$row = $stmt->fetch(\PDO::FETCH_ASSOC);
			$avgResolution = ($row && $row['avg_hours'] !== null) ? round((float)$row['avg_hours'], 1) : 0.0;
		}

		// Total de créditos de Ticket comprados (apenas admin)
		$totalTicketCredits = 0;
		if ($user['role'] === 'admin') {
			$stmt = $pdo->query('SELECT COALESCE(SUM(credits), 0) as total FROM users');
			$row = $stmt->fetch(\PDO::FETCH_ASSOC);
			$totalTicketCredits = $row ? (int)$row['total'] : 0;
		}

		// Total de créditos de Diária comprados (apenas admin)
		$totalDailyCredits = 0;
		if ($user['role'] === 'admin') {
			$stmt = $pdo->query('SELECT COALESCE(SUM(daily_credits), 0) as total FROM users');
			$row = $stmt->fetch(\PDO::FETCH_ASSOC);
			$totalDailyCredits = $row ? (int)$row['total'] : 0;
		}

		// Total de créditos de Diárias Projeto comprados (apenas admin)
		$totalProjectDailiesCredits = 0;
		if ($user['role'] === 'admin') {
			$stmt = $pdo->query('SELECT COALESCE(SUM(project_dailies_credits), 0) as total FROM users');
			$row = $stmt->fetch(\PDO::FETCH_ASSOC);
			$totalProjectDailiesCredits = $row ? (int)$row['total'] : 0;
		}

		return [
			'total_tickets' => $totalTickets,
			'closed_tickets' => $closedTickets,
			'total_users' => $totalUsers,
			'support_agents' => $supportAgents,
			'avg_resolution_hours' => $avgResolution,
			'project_dailies' => $projectDailies,
			'total_ticket_credits' => $totalTicketCredits,
			'total_daily_credits' => $totalDailyCredits,
			'total_project_dailies_credits' => $totalProjectDailiesCredits,
		];
	}

	/**
	 * Helpers locais para evitar dependência de métodos privados do modelo Ticket
	 */
	private function tableExists(\PDO $pdo, string $table): bool
	{
		$stmt = $pdo->query("SHOW TABLES LIKE '" . str_replace("'", "''", $table) . "'");
		return (bool)$stmt->rowCount();
	}

	private function columnExists(\PDO $pdo, string $table, string $column): bool
	{
		$stmt = $pdo->query("SHOW COLUMNS FROM `" . str_replace("`", "``", $table) . "` LIKE '" . str_replace("'", "''", $column) . "'");
		return (bool)$stmt->rowCount();
	}

	public function dailyStats(): void
	{
		$this->requireAuth([]);
		$user = Auth::instance()->user();
		
		$sql = "
			SELECT DATE(created_at) AS dia,
			       COUNT(*) AS total
			FROM tickets
			WHERE 1=1
		";
		$params = [];
		if ($user['role'] === 'usuario') {
			$sql .= " AND user_id = :user_id";
			$params[':user_id'] = (int) $user['id'];
		}
		$sql .= " GROUP BY DATE(created_at) ORDER BY dia ASC LIMIT 60";
		
		$stmt = Database::pdo()->prepare($sql);
		$stmt->execute($params);
		$rows = $stmt->fetchAll();
		$labels = [];
		$data = [];
		foreach ($rows as $r) {
			$labels[] = $r['dia'];
			$data[] = (int) $r['total'];
		}
		$this->json([
			'success' => true,
			'labels' => $labels,
			'data' => $data,
		]);
	}

	public function statusStats(): void
	{
		$this->requireAuth([]);
		$user = Auth::instance()->user();
		$pdo = Database::pdo();
		
		// Buscar distribuição de chamados por status
		$sql = "
			SELECT ts.name, COUNT(t.id) as total
			FROM tickets t
			LEFT JOIN ticket_statuses ts ON t.status_id = ts.id
			WHERE 1=1
		";
		$params = [];
		if ($user['role'] === 'usuario') {
			$sql .= " AND t.user_id = :user_id";
			$params[':user_id'] = (int) $user['id'];
		}
		$sql .= " GROUP BY ts.id, ts.name ORDER BY ts.id ASC";
		
		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);
		$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
		
		$labels = [];
		$data = [];
		foreach ($rows as $r) {
			if ($r['name']) {
				$labels[] = $r['name'];
				$data[] = (int) $r['total'];
			}
		}
		
		$this->json([
			'success' => true,
			'labels' => $labels,
			'data' => $data,
		]);
	}

	public function storeAddresses(): void
	{
		$this->requireAuth([]);
		$path = BASE_PATH . '/endereco.json';
		if (!is_file($path) || !is_readable($path)) {
			$this->json([
				'success' => false,
				'message' => 'Arquivo de endereços não encontrado',
			], 500);
		}

		$raw = trim((string) file_get_contents($path));
		if ($raw === '') {
			$this->json([
				'success' => true,
				'data' => [],
			]);
		}

		// O arquivo é uma sequência de objetos sem colchetes; adaptar para JSON válido
		$json = '[' . rtrim($raw, ", \n\r\t") . ']';
		$data = json_decode($json, true);
		if (!is_array($data)) {
			$this->json([
				'success' => false,
				'message' => 'Erro ao ler arquivo de endereços',
			], 500);
		}

		$result = [];
		foreach ($data as $row) {
			if (!is_array($row)) {
				continue;
			}
			$sigla = trim((string) ($row['SIGLA'] ?? ''));
			$endereco = trim((string) ($row['ENDEREÇO'] ?? ($row['ENDERECO'] ?? '')));
			if ($sigla === '' || $endereco === '') {
				continue;
			}
			$result[] = [
				'sigla' => $sigla,
				'endereco' => $endereco,
			];
		}

		$this->json([
			'success' => true,
			'data' => $result,
		]);
	}
}


