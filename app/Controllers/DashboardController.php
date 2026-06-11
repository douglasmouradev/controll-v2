<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Auth;
use App\Services\Database;
use App\Services\PurchasedDailies;

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
			'sigla' => $_GET['sigla'] ?? null,
			'cidade' => $_GET['cidade'] ?? null,
			'estado' => $_GET['estado'] ?? null,
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
				'open_tickets' => 0,
				'in_progress_tickets' => 0,
				'closed_tickets' => 0,
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
		// Em alguns ambientes existe também a tabela `tickets_backup` com chamados legados.
		// Para admin/suporte, somamos `tickets` + `tickets_backup` (se existir) para refletir o total real do sistema.
		if ($user['role'] === 'user') {
			$stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM tickets WHERE user_id = :user_id');
			$stmt->execute([':user_id' => (int) $user['id']]);
			$row = $stmt->fetch(\PDO::FETCH_ASSOC);
			$totalTickets = $row ? (int)$row['cnt'] : 0;
		} else {
			$totalTickets = 0;
			$stmt = $pdo->query('SELECT COUNT(*) as cnt FROM tickets');
			$row = $stmt->fetch(\PDO::FETCH_ASSOC);
			$totalTickets += $row ? (int)$row['cnt'] : 0;

			// Contar também `tickets_backup` (se existir).
			// Usar information_schema para não depender de SHOW TABLES (que pode falhar por permissão).
			try {
				$dbName = (string) ($pdo->query('SELECT DATABASE()')->fetchColumn() ?: '');
				if ($dbName !== '') {
					$stExists = $pdo->prepare('
						SELECT COUNT(*) 
						FROM information_schema.tables 
						WHERE table_schema = :db AND table_name = :t
					');
					$stExists->execute([':db' => $dbName, ':t' => 'tickets_backup']);
					$exists = (int) ($stExists->fetchColumn() ?: 0);
					if ($exists > 0) {
						$stmtB = $pdo->query('SELECT COUNT(*) as cnt FROM tickets_backup');
						$rowB = $stmtB->fetch(\PDO::FETCH_ASSOC);
						$totalTickets += $rowB ? (int)$rowB['cnt'] : 0;
					}
				}
			} catch (\Throwable $e) {
				// Sem acesso ao information_schema ou sem tabela: ignorar backup
			}
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

		// Total de chamados abertos
		$openTickets = 0;
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
			$params[':status'] = 'Aberto';
			if ($user['role'] === 'user') {
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
			$openTickets = $row ? (int)$row['cnt'] : 0;
		}

		// Total de chamados em andamento
		$inProgressTickets = 0;
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
			$params[':status'] = 'Em andamento';
			if ($user['role'] === 'user') {
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
			$inProgressTickets = $row ? (int)$row['cnt'] : 0;
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
			'open_tickets' => $openTickets,
			'in_progress_tickets' => $inProgressTickets,
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

		$pdo = Database::pdo();
		$hasQtd = $this->columnExists($pdo, 'tickets', 'qtd');
		$hasTicketCategories = $this->tableExists($pdo, 'ticket_categories');
		$hasCategories = !$hasTicketCategories && $this->tableExists($pdo, 'categories');

		$qtdExpr = $hasQtd
			? 'CASE WHEN t.qtd IS NULL OR t.qtd = 0 THEN 1 ELSE t.qtd END'
			: '1';

		$sql = "
			SELECT DATE(t.created_at) AS dia,
			       SUM($qtdExpr) AS total
			FROM tickets t
		";

		if ($hasTicketCategories) {
			$sql .= " LEFT JOIN ticket_categories tc ON tc.id = t.category_id";
		} elseif ($hasCategories) {
			$sql .= " LEFT JOIN categories c ON c.id = t.category_id";
		}

		$sql .= " WHERE 1=1";

		// Considerar apenas chamados da categoria Diária (para bater com créditos de Diária)
		if ($hasTicketCategories) {
			$sql .= " AND tc.name = 'Diária'";
		} elseif ($hasCategories) {
			$sql .= " AND c.name = 'Diária'";
		} elseif ($this->columnExists($pdo, 'tickets', 'category')) {
			$sql .= " AND t.category = 'Diária'";
		}

		$params = [];
		if ($user['role'] === 'usuario') {
			$sql .= " AND t.user_id = :user_id";
			$params[':user_id'] = (int) $user['id'];
		}

		$sql .= " GROUP BY DATE(t.created_at) ORDER BY dia ASC LIMIT 60";

		$stmt = $pdo->prepare($sql);
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

	public function creditUsageStats(): void
	{
		$this->requireAuth([]);
		$user = Auth::instance()->user();
		$pdo = Database::pdo();

		$hasQtd = $this->columnExists($pdo, 'tickets', 'qtd');
		$hasTicketCategories = $this->tableExists($pdo, 'ticket_categories');
		$hasCategories = !$hasTicketCategories && $this->tableExists($pdo, 'categories');
		$hasCategoryColumn = $this->columnExists($pdo, 'tickets', 'category');

		$qtdExpr = $hasQtd
			? 'CASE WHEN t.qtd IS NULL OR t.qtd = 0 THEN 1 ELSE t.qtd END'
			: '1';

		$categoryExpr = "''";
		if ($hasTicketCategories) {
			$categoryExpr = 'tc.name';
		} elseif ($hasCategories) {
			$categoryExpr = 'c.name';
		} elseif ($hasCategoryColumn) {
			$categoryExpr = 't.category';
		}

		$sql = "SELECT $categoryExpr AS category, SUM($qtdExpr) AS total
		        FROM tickets t";
		if ($hasTicketCategories) {
			$sql .= ' LEFT JOIN ticket_categories tc ON tc.id = t.category_id';
		} elseif ($hasCategories) {
			$sql .= ' LEFT JOIN categories c ON c.id = t.category_id';
		}
		$sql .= ' WHERE 1=1';
		$params = [];
		if (($user['role'] ?? '') === 'usuario') {
			$sql .= ' AND t.user_id = :user_id';
			$params[':user_id'] = (int) ($user['id'] ?? 0);
		}
		$sql .= ' GROUP BY category';

		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);
		$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

		$spent = ['ticket' => 0, 'daily' => 0, 'project_dailies' => 0];
		foreach ($rows as $r) {
			$name = trim((string)($r['category'] ?? ''));
			$total = (int)($r['total'] ?? 0);
			if ($total <= 0) {
				continue;
			}
			if ($name === 'Ticket') {
				$spent['ticket'] += $total;
			} elseif ($name === 'Diária' || $name === 'Uso Geral') {
				$spent['daily'] += $total;
			} elseif ($name === 'Projeto') {
				$spent['project_dailies'] += $total;
			}
		}

		// Comprados: histórico de créditos (lançamentos positivos, pool de usuários finais)
		// Consumidos: soma real de QTD nos chamados por categoria
		$purchased = ['ticket' => 0, 'daily' => 0, 'project_dailies' => 0];
		$spentHistory = ['ticket' => 0, 'daily' => 0, 'project_dailies' => 0];
		foreach (array_keys($purchased) as $creditType) {
			try {
				$historySummary = \App\Models\CreditHistory::getGlobalSummary($creditType, 'user');
				$purchased[$creditType] = (int) ($historySummary['purchased'] ?? 0);
				$spentHistory[$creditType] = (int) ($historySummary['spent'] ?? 0);
			} catch (\Throwable $e) {
			}
		}

		$importedPurchased = $this->loadPurchasedDailiesParsed();
		if ($importedPurchased !== null) {
			$purchased['daily'] = (int) ($importedPurchased['summary']['daily_purchased'] ?? $purchased['daily']);
			$purchased['project_dailies'] = (int) ($importedPurchased['summary']['project_purchased'] ?? $purchased['project_dailies']);
		}

		$summary = [
			'ticket' => [
				'purchased' => $purchased['ticket'],
				'spent' => (int) $spent['ticket'],
				'available' => $purchased['ticket'] - (int) $spent['ticket'],
				'spent_history' => $spentHistory['ticket'],
			],
			'daily' => [
				'purchased' => $purchased['daily'],
				'spent' => (int) $spent['daily'],
				'available' => $purchased['daily'] - (int) $spent['daily'],
				'spent_history' => $spentHistory['daily'],
			],
			'project_dailies' => [
				'purchased' => $purchased['project_dailies'],
				'spent' => (int) $spent['project_dailies'],
				'available' => $purchased['project_dailies'] - (int) $spent['project_dailies'],
				'spent_history' => $spentHistory['project_dailies'],
			],
			'total_used_dailies' => (int) $spent['daily'] + (int) $spent['project_dailies'],
		];

		$this->json(['success' => true, 'summary' => $summary]);
	}

	public function inventoryStats(): void
	{
		$this->requireAuth([]);

		$xlsxPath = $this->getGlobalInventoryXlsxPath();
		if ($xlsxPath === '') {
			$xlsxPath = (string) ($_SESSION['inventory_xlsx_path'] ?? '');
		}
		if ($xlsxPath === '') {
			$xlsxPath = (string) (getenv('INVENTORY_XLSX_PATH') ?: '/Users/douglas/Downloads/PLANILHA PARA DASBOARD 13.04.xlsx');
		}
		if (!is_file($xlsxPath) || !is_readable($xlsxPath)) {
			$this->json([
				'success' => false,
				'message' => 'Planilha de inventário não encontrada ou sem permissão de leitura',
				'path' => $xlsxPath,
			], 500);
		}

		try {
			$rows = $this->readXlsxRows($xlsxPath);
		} catch (\Throwable $e) {
			$this->json([
				'success' => false,
				'message' => 'Erro ao processar planilha de inventário',
				'details' => $e->getMessage(),
			], 500);
		}

		if (count($rows) < 2) {
			$this->json([
				'success' => true,
				'labels' => ['Sem dados'],
				'data' => [0],
				'total_items' => 0,
				'source' => $xlsxPath,
			]);
		}

		$header = array_map(static fn($v) => strtoupper(trim((string) $v)), $rows[0]);
		$targetColumns = ['SETUP', 'ROLLOUT', 'HEXAPADS', 'DEFEITO', 'SUPORTE_INSTALADO', 'SUPORTE_PENDENTE'];
		$counts = array_fill_keys($targetColumns, 0);
		$availableStores = [];
		$filteredStores = [];
		$locationsByCategory = [];
		$sampleRows = [];
		$summaryMetrics = [
			'stores' => 0,
			'previsto' => 0,
			'executado' => 0,
			'diarias_consumidas' => 0,
			'setup' => 0,
			'rollout' => 0,
			'formatacao' => 0,
			'troca_memoria' => 0,
			'pdvs' => 0,
			'ocorrencias' => 0,
			'suporte_instalado' => 0,
			'suporte_pendente' => 0,
		];

		$indexByHeader = [];
		foreach ($header as $idx => $name) {
			if ($name !== '') {
				$indexByHeader[$name] = $idx;
			}
		}
		$normalizeHeaderKey = static function (string $text): string {
			$text = strtoupper(trim($text));
			$from = ['Á', 'À', 'Â', 'Ã', 'Ä', 'É', 'È', 'Ê', 'Ë', 'Í', 'Ì', 'Î', 'Ï', 'Ó', 'Ò', 'Ô', 'Õ', 'Ö', 'Ú', 'Ù', 'Û', 'Ü', 'Ç'];
			$to =   ['A', 'A', 'A', 'A', 'A', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'C'];
			$text = str_replace($from, $to, $text);
			$text = preg_replace('/[^A-Z0-9]/', '', $text) ?? $text;
			return $text;
		};
		$normalizedHeaderIndex = [];
		foreach ($indexByHeader as $name => $idx) {
			$normalizedHeaderIndex[$normalizeHeaderKey((string) $name)] = (int) $idx;
		}
		$findHeaderIdxLike = static function (array $normalizedMap, string $needle) use ($normalizeHeaderKey): ?int {
			$needleNorm = $normalizeHeaderKey($needle);
			foreach ($normalizedMap as $key => $idx) {
				if ($key === $needleNorm || strpos($key, $needleNorm) !== false) {
					return (int) $idx;
				}
			}
			return null;
		};
		$ocorrenciasHeaderIdx = $indexByHeader['OCORRENCIAS'] ?? ($indexByHeader['OCORRÊNCIAS'] ?? null);
		$pdvHeaderIdx = $indexByHeader['HEXAPADS']
			?? ($indexByHeader['XPAD'] ?? ($indexByHeader['XPADS'] ?? null));
		$suporteHeaderIdx = $indexByHeader['SUPORTE'] ?? $findHeaderIdxLike($normalizedHeaderIndex, 'SUPORTE');
		$suporteInstaladoHeaderIdx = $indexByHeader['SUPORTE INSTALADO']
			?? ($indexByHeader['SUPORTE INSTALADO '] ?? $findHeaderIdxLike($normalizedHeaderIndex, 'SUPORTEINSTALADO'));
		$suportePendenteHeaderIdx = $indexByHeader['SUPORTE PENDENTE']
			?? ($indexByHeader['SUPORTE PENDENTE '] ?? $findHeaderIdxLike($normalizedHeaderIndex, 'SUPORTEPENDENTE'));

		$storeFilter = strtoupper(trim((string) ($_GET['store'] ?? '')));
		$supportStatusFilter = strtolower(trim((string) ($_GET['support_status'] ?? '')));
		$startDateFilter = trim((string) ($_GET['start_date'] ?? ''));
		$endDateFilter = trim((string) ($_GET['end_date'] ?? ''));
		$startDateTs = $startDateFilter !== '' ? strtotime($startDateFilter . ' 00:00:00') : null;
		$endDateTs = $endDateFilter !== '' ? strtotime($endDateFilter . ' 23:59:59') : null;

		$dataRows = array_slice($rows, 1);
		foreach ($dataRows as $row) {
			$storeValue = '';
			if (isset($indexByHeader['LOJA'])) {
				$storeRaw = $row[$indexByHeader['LOJA']] ?? '';
				$storeValue = strtoupper(trim((string) $storeRaw));
				if ($storeValue !== '' && $storeValue !== '-') {
					$availableStores[$storeValue] = true;
				}
			}
			if ($storeFilter !== '' && $storeValue !== $storeFilter) {
				continue;
			}

			if (isset($indexByHeader['DATA'])) {
				$dateRaw = $row[$indexByHeader['DATA']] ?? '';
				$dateYmd = $this->normalizeSpreadsheetDateToYmd($dateRaw);
				if ($dateYmd !== null) {
					$dateTs = strtotime($dateYmd . ' 12:00:00');
					if ($startDateTs !== null && $dateTs !== false && $dateTs < $startDateTs) {
						continue;
					}
					if ($endDateTs !== null && $dateTs !== false && $dateTs > $endDateTs) {
						continue;
					}
				}
			}

			$rowSupportStatus = '';
			if ($suporteInstaladoHeaderIdx !== null || $suportePendenteHeaderIdx !== null) {
				$installedValue = 0.0;
				$pendingValue = 0.0;
				if ($suporteInstaladoHeaderIdx !== null) {
					$instRaw = $row[$suporteInstaladoHeaderIdx] ?? null;
					$instNorm = strtoupper(trim((string) $instRaw));
					if ($instNorm !== '' && $instNorm !== '-' && $instNorm !== '0') {
						$installedValue = is_numeric($instRaw) ? max(0, (float) $instRaw) : 1.0;
					}
				}
				if ($suportePendenteHeaderIdx !== null) {
					$pendRaw = $row[$suportePendenteHeaderIdx] ?? null;
					$pendNorm = strtoupper(trim((string) $pendRaw));
					if ($pendNorm !== '' && $pendNorm !== '-' && $pendNorm !== '0') {
						$pendingValue = is_numeric($pendRaw) ? max(0, (float) $pendRaw) : 1.0;
					}
				}
				if ($pendingValue > 0) {
					$rowSupportStatus = 'pending';
				} elseif ($installedValue > 0) {
					$rowSupportStatus = 'installed';
				}
			} else {
				$suporteIdx = $suporteHeaderIdx;
				if ($suporteIdx !== null) {
					$suporteRaw = $row[$suporteIdx] ?? null;
					$suporteNormalized = strtoupper(trim((string) $suporteRaw));
					$rowSupportStatus = ($suporteNormalized === '' || $suporteNormalized === '-' || $suporteNormalized === '0')
						? 'pending'
						: 'installed';
				}
			}
			if (($supportStatusFilter === 'pending' || $supportStatusFilter === 'installed') && $rowSupportStatus !== $supportStatusFilter) {
				continue;
			}

			if ($storeValue !== '' && $storeValue !== '-') {
				$filteredStores[$storeValue] = true;
			}

			$previstoIdx = $indexByHeader['PREVISTO'] ?? null;
			if ($previstoIdx !== null) {
				$previstoRaw = $row[$previstoIdx] ?? null;
				$previstoNorm = strtoupper(trim((string) $previstoRaw));
				if ($previstoNorm !== '' && $previstoNorm !== '-' && is_numeric($previstoRaw)) {
					$summaryMetrics['previsto'] += max(0, (float) $previstoRaw);
				}
			}

			$executadoIdx = $indexByHeader['EXECUTADO'] ?? null;
			if ($executadoIdx !== null) {
				$executadoRaw = $row[$executadoIdx] ?? null;
				$executadoNorm = strtoupper(trim((string) $executadoRaw));
				if ($executadoNorm !== '' && $executadoNorm !== '-' && is_numeric($executadoRaw)) {
					$summaryMetrics['executado'] += max(0, (float) $executadoRaw);
				}
			}

			$diariasIdx = $indexByHeader['DIARIAS CONSUMIDAS'] ?? null;
			if ($diariasIdx !== null) {
				$diariasRaw = $row[$diariasIdx] ?? null;
				$diariasNorm = strtoupper(trim((string) $diariasRaw));
				if ($diariasNorm !== '' && $diariasNorm !== '-' && is_numeric($diariasRaw)) {
					$summaryMetrics['diarias_consumidas'] += max(0, (float) $diariasRaw);
				}
			}

			$setupIdx = $indexByHeader['SETUP'] ?? null;
			if ($setupIdx !== null) {
				$setupRaw = $row[$setupIdx] ?? null;
				$setupNormalized = strtoupper(trim((string) $setupRaw));
				if ($setupNormalized !== '' && $setupNormalized !== '-' && $setupNormalized !== '0') {
					$summaryMetrics['setup']++;
					// Regra de negócio: setup e formatação representam o mesmo conjunto.
					$summaryMetrics['formatacao']++;
				}
			}
			$rolloutIdx = $indexByHeader['ROLLOUT'] ?? null;
			if ($rolloutIdx !== null) {
				$rolloutRaw = $row[$rolloutIdx] ?? null;
				$rolloutNormalized = strtoupper(trim((string) $rolloutRaw));
				if ($rolloutNormalized !== '' && $rolloutNormalized !== '-' && $rolloutNormalized !== '0') {
					$summaryMetrics['rollout']++;
				}
			}

			$evidenciasIdx = $indexByHeader['EVIDENCIAS'] ?? null;
			if ($evidenciasIdx !== null) {
				$evidencias = strtoupper(trim((string) ($row[$evidenciasIdx] ?? '')));
				if ($evidencias !== '' && $evidencias !== '-') {
					if (strpos($evidencias, 'MEMOR') !== false) {
						$summaryMetrics['troca_memoria']++;
					}
				}
			}

			$pdvIdx = $pdvHeaderIdx;
			if ($pdvIdx !== null) {
				$pdvRaw = $row[$pdvIdx] ?? null;
				$pdvNormalized = strtoupper(trim((string) $pdvRaw));
				if ($pdvNormalized !== '' && $pdvNormalized !== '-' && $pdvNormalized !== '0') {
					if (is_numeric($pdvRaw)) {
						$summaryMetrics['pdvs'] += max(0, (int) ((float) $pdvRaw));
					} else {
						$summaryMetrics['pdvs']++;
					}
				}
			}

			$ocorrenciasIdx = $indexByHeader['OCORRENCIAS'] ?? ($indexByHeader['OCORRÊNCIAS'] ?? ($indexByHeader['DEFEITO'] ?? null));
			if ($ocorrenciasIdx !== null) {
				$ocorrenciasRaw = $row[$ocorrenciasIdx] ?? null;
				$ocorrenciasNormalized = strtoupper(trim((string) $ocorrenciasRaw));
				if ($ocorrenciasNormalized !== '' && $ocorrenciasNormalized !== '-' && $ocorrenciasNormalized !== '0') {
					// Ocorrências = quantidade de linhas com ocorrência registrada.
					$summaryMetrics['ocorrencias']++;
				}
			}
			// Preferir colunas dedicadas de suporte quando existirem (I/J).
			if ($suporteInstaladoHeaderIdx !== null || $suportePendenteHeaderIdx !== null) {
				if ($suporteInstaladoHeaderIdx !== null) {
					$instRaw = $row[$suporteInstaladoHeaderIdx] ?? null;
					$instNorm = strtoupper(trim((string) $instRaw));
					if ($instNorm !== '' && $instNorm !== '-' && $instNorm !== '0') {
						$summaryMetrics['suporte_instalado'] += is_numeric($instRaw)
							? max(0, (float) $instRaw)
							: 1;
					}
				}
				if ($suportePendenteHeaderIdx !== null) {
					$pendRaw = $row[$suportePendenteHeaderIdx] ?? null;
					$pendNorm = strtoupper(trim((string) $pendRaw));
					if ($pendNorm !== '' && $pendNorm !== '-' && $pendNorm !== '0') {
						$summaryMetrics['suporte_pendente'] += is_numeric($pendRaw)
							? max(0, (float) $pendRaw)
							: 1;
					}
				}
			} else {
				$suporteIdx = $suporteHeaderIdx;
				if ($suporteIdx !== null) {
					$suporteRaw = $row[$suporteIdx] ?? null;
					$suporteNormalized = strtoupper(trim((string) $suporteRaw));
					if ($suporteNormalized === '' || $suporteNormalized === '-' || $suporteNormalized === '0') {
						$summaryMetrics['suporte_pendente']++;
					} else {
						$isInstalled = is_numeric($suporteRaw)
							? ((float) $suporteRaw) > 0
							: true;
						if ($isInstalled) {
							$summaryMetrics['suporte_instalado']++;
						} else {
							$summaryMetrics['suporte_pendente']++;
						}
					}
				}
			}

			foreach ($targetColumns as $columnName) {
				$colIdx = $indexByHeader[$columnName] ?? null;
				// A categoria "DEFEITO" no gráfico é exibida como "OCORRÊNCIAS";
				// portanto deve ler preferencialmente da coluna de ocorrências.
				if ($columnName === 'DEFEITO' && $ocorrenciasHeaderIdx !== null) {
					$colIdx = $ocorrenciasHeaderIdx;
				}
				// A categoria de PDV pode vir como HEXAPADS, XPAD ou XPADS na planilha.
				if ($columnName === 'HEXAPADS' && $pdvHeaderIdx !== null) {
					$colIdx = $pdvHeaderIdx;
				}
				if ($columnName === 'SUPORTE_INSTALADO' || $columnName === 'SUPORTE_PENDENTE') {
					$storeKey = $storeValue !== '' && $storeValue !== '-' ? $storeValue : 'SEM LOJA';
					if (!isset($locationsByCategory[$columnName])) {
						$locationsByCategory[$columnName] = [];
					}

					$supportValue = 0.0;
					if ($columnName === 'SUPORTE_INSTALADO') {
						if ($suporteInstaladoHeaderIdx !== null) {
							$raw = $row[$suporteInstaladoHeaderIdx] ?? null;
							$norm = strtoupper(trim((string) $raw));
							if ($norm !== '' && $norm !== '-' && $norm !== '0') {
								$supportValue = is_numeric($raw) ? max(0, (float) $raw) : 1.0;
							}
						} elseif ($suporteHeaderIdx !== null) {
							$raw = $row[$suporteHeaderIdx] ?? null;
							$norm = strtoupper(trim((string) $raw));
							if ($norm !== '' && $norm !== '-' && $norm !== '0') {
								$supportValue = is_numeric($raw) ? max(0, (float) $raw) : 1.0;
							}
						}
					} else {
						if ($suportePendenteHeaderIdx !== null) {
							$raw = $row[$suportePendenteHeaderIdx] ?? null;
							$norm = strtoupper(trim((string) $raw));
							if ($norm !== '' && $norm !== '-' && $norm !== '0') {
								$supportValue = is_numeric($raw) ? max(0, (float) $raw) : 1.0;
							}
						} elseif ($suporteHeaderIdx !== null) {
							$raw = $row[$suporteHeaderIdx] ?? null;
							$norm = strtoupper(trim((string) $raw));
							if ($norm === '' || $norm === '-' || $norm === '0') {
								$supportValue = 1.0;
							}
						}
					}

					if ($supportValue > 0) {
						$counts[$columnName] += $supportValue;
						$locationsByCategory[$columnName][$storeKey] = ($locationsByCategory[$columnName][$storeKey] ?? 0) + $supportValue;
					}
					continue;
				}
				if ($colIdx === null) {
					continue;
				}
				$value = $row[$colIdx] ?? null;
				$normalized = strtoupper(trim((string) $value));
				if ($normalized === '' || $normalized === '-' || $normalized === '0') {
					continue;
				}
				$storeKey = $storeValue !== '' && $storeValue !== '-' ? $storeValue : 'SEM LOJA';
				if (!isset($locationsByCategory[$columnName])) {
					$locationsByCategory[$columnName] = [];
				}

				$isOcorrenciasCategory = ($columnName === 'DEFEITO' && $ocorrenciasHeaderIdx !== null);
				if ($isOcorrenciasCategory) {
					$counts[$columnName] += 1;
					$locationsByCategory[$columnName][$storeKey] = ($locationsByCategory[$columnName][$storeKey] ?? 0) + 1;
				} elseif (is_numeric($value)) {
					$increment = max(0, (float) $value);
					$counts[$columnName] += $increment;
					$locationsByCategory[$columnName][$storeKey] = ($locationsByCategory[$columnName][$storeKey] ?? 0) + $increment;
				} else {
					$counts[$columnName] += 1;
					$locationsByCategory[$columnName][$storeKey] = ($locationsByCategory[$columnName][$storeKey] ?? 0) + 1;
				}
			}

			if (count($sampleRows) < 5) {
				$sample = [];
				foreach ($targetColumns as $columnName) {
					$colIdx = $indexByHeader[$columnName] ?? null;
					$sample[$columnName] = $colIdx !== null ? (string) ($row[$colIdx] ?? '') : '';
				}
				$sample['LOJA'] = isset($indexByHeader['LOJA']) ? (string) ($row[$indexByHeader['LOJA']] ?? '') : '';
				$sample['DATA'] = isset($indexByHeader['DATA']) ? (string) ($row[$indexByHeader['DATA']] ?? '') : '';
				$sampleRows[] = $sample;
			}
		}

		$labels = [];
		$data = [];
		foreach ($counts as $label => $total) {
			$labels[] = $label;
			$data[] = (float) $total;
		}

		$totalItems = array_sum($data);
		$summaryMetrics['stores'] = count($filteredStores);
		$locationsPayload = [];
		foreach ($locationsByCategory as $category => $storeTotals) {
			arsort($storeTotals);
			$locationsPayload[$category] = [];
			foreach ($storeTotals as $store => $total) {
				$locationsPayload[$category][] = [
					'store' => (string) $store,
					'total' => (float) $total,
				];
			}
		}
		if ($totalItems <= 0) {
			$labels = ['Sem dados'];
			$data = [0];
		}

		$this->json([
			'success' => true,
			'labels' => $labels,
			'data' => $data,
			'total_items' => $totalItems,
			'summary_metrics' => $summaryMetrics,
			'locations_by_category' => $locationsPayload,
			'stores' => array_keys($availableStores),
			'source' => $xlsxPath,
			'debug' => [
				'rows_total' => count($rows),
				'rows_data' => count($dataRows),
				'headers_detected' => array_values(array_filter($header, static fn($h) => $h !== '')),
				'target_columns_found' => array_values(array_intersect($targetColumns, array_keys($indexByHeader))),
				'target_columns_missing' => array_values(array_diff($targetColumns, array_keys($indexByHeader))),
				'sample_rows' => $sampleRows,
				'counts' => $counts,
				'filters' => [
					'store' => $storeFilter,
					'support_status' => $supportStatusFilter,
					'start_date' => $startDateFilter,
					'end_date' => $endDateFilter,
				],
			],
		]);
	}

	public function uploadInventoryFile(): void
	{
		$this->requireAuth([]);

		if (!isset($_FILES['file'])) {
			$this->json([
				'success' => false,
				'message' => 'Arquivo não enviado',
			], 400);
		}

		$file = $_FILES['file'];
		if (!is_array($file) || (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)) {
			$this->json([
				'success' => false,
				'message' => 'Falha no upload da planilha',
			], 400);
		}

		$originalName = (string) ($file['name'] ?? 'planilha.xlsx');
		$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
		$allowed = ['xlsx', 'xlsm', 'xltx', 'xltm'];
		if (!in_array($ext, $allowed, true)) {
			$this->json([
				'success' => false,
				'message' => 'Formato inválido. Envie um arquivo XLSX.',
			], 400);
		}

		$storageDir = BASE_PATH . '/storage/uploads/inventory';
		if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
			$this->json([
				'success' => false,
				'message' => 'Não foi possível preparar a pasta de upload',
			], 500);
		}

		$targetPath = $storageDir . '/inventory_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.xlsx';
		$tmpPath = (string) ($file['tmp_name'] ?? '');
		if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
			$this->json([
				'success' => false,
				'message' => 'Arquivo temporário inválido',
			], 400);
		}

		if (!move_uploaded_file($tmpPath, $targetPath)) {
			$this->json([
				'success' => false,
				'message' => 'Não foi possível salvar a planilha',
			], 500);
		}

		$_SESSION['inventory_xlsx_path'] = $targetPath;
		$this->setGlobalInventoryXlsxPath($targetPath);
		$this->json([
			'success' => true,
			'message' => 'Planilha importada com sucesso',
			'source' => $targetPath,
		]);
	}

	public function downloadInventoryFile(): void
	{
		$this->requireAuth([]);

		$xlsxPath = $this->getGlobalInventoryXlsxPath();
		if ($xlsxPath === '') {
			$xlsxPath = (string) ($_SESSION['inventory_xlsx_path'] ?? '');
		}
		if ($xlsxPath === '') {
			$xlsxPath = (string) (getenv('INVENTORY_XLSX_PATH') ?: '');
		}
		if ($xlsxPath === '' || !is_file($xlsxPath) || !is_readable($xlsxPath)) {
			http_response_code(404);
			header('Content-Type: text/plain; charset=utf-8');
			echo 'Planilha não encontrada para download';
			exit;
		}

		$downloadName = 'inventario_atual.xlsx';
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment; filename="' . $downloadName . '"');
		header('Content-Length: ' . (string) filesize($xlsxPath));
		header('Cache-Control: private, max-age=0, must-revalidate');
		readfile($xlsxPath);
		exit;
	}

	private function getGlobalInventoryXlsxPath(): string
	{
		$pointerPath = BASE_PATH . '/storage/uploads/inventory/current_path.txt';
		if (!is_file($pointerPath) || !is_readable($pointerPath)) {
			return '';
		}
		$path = trim((string) file_get_contents($pointerPath));
		if ($path === '' || !is_file($path) || !is_readable($path)) {
			return '';
		}
		return $path;
	}

	private function setGlobalInventoryXlsxPath(string $path): void
	{
		$pointerPath = BASE_PATH . '/storage/uploads/inventory/current_path.txt';
		@file_put_contents($pointerPath, $path);
	}

	public function purchasedDailiesStats(): void
	{
		$this->requireAuth(['support', 'admin']);

		$parsed = $this->loadPurchasedDailiesParsed();
		if ($parsed === null) {
			$this->json([
				'success' => true,
				'rows' => [],
				'summary' => [
					'total_rows' => 0,
					'daily_purchased' => 0,
					'project_purchased' => 0,
					'total_purchased' => 0,
				],
				'source' => null,
			]);
			return;
		}

		$path = PurchasedDailies::getCurrentFilePath();
		$this->json([
			'success' => true,
			'rows' => $parsed['rows'],
			'summary' => $parsed['summary'],
			'source' => [
				'file' => basename($path),
				'imported_at' => date('d/m/Y H:i', (int) filemtime($path)),
			],
		]);
	}

	public function uploadPurchasedDailiesFile(): void
	{
		$this->requireAuth(['support', 'admin']);

		if (!isset($_FILES['file'])) {
			$this->json(['success' => false, 'message' => 'Arquivo não enviado'], 400);
			return;
		}

		$file = $_FILES['file'];
		if (!is_array($file) || (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)) {
			$this->json(['success' => false, 'message' => 'Falha no upload da planilha'], 400);
			return;
		}

		$originalName = (string) ($file['name'] ?? 'diarias_compradas.xlsx');
		$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
		$allowed = ['xlsx', 'xlsm', 'xltx', 'xltm', 'csv'];
		if (!in_array($ext, $allowed, true)) {
			$this->json(['success' => false, 'message' => 'Formato inválido. Envie XLSX ou CSV.'], 400);
			return;
		}

		$storageDir = PurchasedDailies::storageDir();
		if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
			$this->json(['success' => false, 'message' => 'Não foi possível preparar a pasta de upload'], 500);
			return;
		}

		$targetPath = $storageDir . '/purchased_dailies_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
		$tmpPath = (string) ($file['tmp_name'] ?? '');
		if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
			$this->json(['success' => false, 'message' => 'Arquivo temporário inválido'], 400);
			return;
		}

		if (!move_uploaded_file($tmpPath, $targetPath)) {
			$this->json(['success' => false, 'message' => 'Não foi possível salvar a planilha'], 500);
			return;
		}

		try {
			$parsed = $this->parsePurchasedDailiesFile($targetPath);
		} catch (\Throwable $e) {
			@unlink($targetPath);
			$this->json(['success' => false, 'message' => 'Erro ao ler planilha: ' . $e->getMessage()], 422);
			return;
		}

		if ((int) ($parsed['summary']['total_rows'] ?? 0) === 0) {
			@unlink($targetPath);
			$detected = $parsed['summary']['detected_headers'] ?? [];
			$headersHint = is_array($detected) && count($detected) > 0
				? ' Colunas encontradas: ' . implode(', ', array_slice($detected, 0, 12)) . '.'
				: '';
			$this->json([
				'success' => false,
				'message' => 'Nenhum registro válido encontrado. Verifique colunas Data, Loja e Quantidade (ou Diárias compradas / Qtd / Previsto).' . $headersHint,
			], 422);
			return;
		}

		PurchasedDailies::setCurrentFilePath($targetPath);
		$this->json([
			'success' => true,
			'message' => 'Planilha importada com sucesso',
			'summary' => $parsed['summary'],
			'source' => basename($targetPath),
		]);
	}

	public function downloadPurchasedDailiesFile(): void
	{
		$this->requireAuth(['support', 'admin']);

		$path = PurchasedDailies::getCurrentFilePath();
		if ($path === '' || !is_file($path) || !is_readable($path)) {
			http_response_code(404);
			header('Content-Type: text/plain; charset=utf-8');
			echo 'Planilha de diárias compradas não encontrada';
			exit;
		}

		$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
		$contentType = $ext === 'csv'
			? 'text/csv; charset=utf-8'
			: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
		$downloadName = 'diarias_compradas.' . ($ext !== '' ? $ext : 'xlsx');

		header('Content-Type: ' . $contentType);
		header('Content-Disposition: attachment; filename="' . $downloadName . '"');
		header('Content-Length: ' . (string) filesize($path));
		header('Cache-Control: private, max-age=0, must-revalidate');
		readfile($path);
		exit;
	}

	/**
	 * @return array{rows: array<int, array<string, mixed>>, summary: array<string, int>}|null
	 */
	private function loadPurchasedDailiesParsed(): ?array
	{
		$path = PurchasedDailies::getCurrentFilePath();
		if ($path === '') {
			return null;
		}
		try {
			return $this->parsePurchasedDailiesFile($path);
		} catch (\Throwable $e) {
			error_log('loadPurchasedDailiesParsed: ' . $e->getMessage());
			return null;
		}
	}

	/**
	 * @return array{rows: array<int, array<string, mixed>>, summary: array<string, int>}
	 */
	private function parsePurchasedDailiesFile(string $path): array
	{
		$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
		if ($ext === 'csv') {
			$rows = $this->readCsvRows($path);
		} else {
			$rows = $this->readXlsxRows($path, [
				'DATA', 'LOJA', 'COMPRAD', 'QUANT', 'QTD', 'DIARIA', 'TIPO', 'PREVISTO', 'UNIDADE', 'SIGLA', 'ATIVIDADE',
			]);
		}
		return PurchasedDailies::parseRows($rows);
	}

	/**
	 * @return array<int, array<int, string>>
	 */
	private function readCsvRows(string $csvPath): array
	{
		$handle = fopen($csvPath, 'rb');
		if ($handle === false) {
			throw new \RuntimeException('Não foi possível abrir o arquivo CSV.');
		}

		$rows = [];
		while (($data = fgetcsv($handle, 0, ';')) !== false) {
			if (count($data) === 1 && isset($data[0]) && str_contains((string) $data[0], ',')) {
				$data = str_getcsv((string) $data[0], ',');
			}
			$rows[] = array_map(static fn($v) => trim((string) $v), $data);
		}
		fclose($handle);
		return $rows;
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

	/**
	 * Lê linhas de uma planilha XLSX sem dependências externas.
	 *
	 * @return array<int, array<int, string>>
	 */
	/**
	 * @param array<int, string>|null $preferredHeaders
	 * @return array<int, array<int, string>>
	 */
	private function readXlsxRows(string $xlsxPath, ?array $preferredHeaders = null): array
	{
		if (!class_exists(\ZipArchive::class)) {
			throw new \RuntimeException('Extensão ZipArchive não está disponível no PHP.');
		}

		$zip = new \ZipArchive();
		if ($zip->open($xlsxPath) !== true) {
			throw new \RuntimeException('Não foi possível abrir o arquivo XLSX.');
		}

		$sharedStrings = [];
		$sharedXml = $zip->getFromName('xl/sharedStrings.xml');
		if (is_string($sharedXml) && $sharedXml !== '') {
			$sharedDoc = @simplexml_load_string($sharedXml);
			if ($sharedDoc !== false) {
				$siNodes = $sharedDoc->xpath('/*[local-name()="sst"]/*[local-name()="si"]');
				if (!is_array($siNodes)) {
					$siNodes = [];
				}
				foreach ($siNodes as $si) {
					$text = '';
					$tNodes = $si->xpath('./*[local-name()="t"]');
					if (is_array($tNodes) && count($tNodes) > 0) {
						$text = (string) ($tNodes[0] ?? '');
					} else {
						$rNodes = $si->xpath('./*[local-name()="r"]');
						if (!is_array($rNodes)) {
							$rNodes = [];
						}
						foreach ($rNodes as $run) {
							$runTNodes = $run->xpath('./*[local-name()="t"]');
							if (is_array($runTNodes) && count($runTNodes) > 0) {
								$text .= (string) ($runTNodes[0] ?? '');
							}
						}
					}
					$sharedStrings[] = $text;
				}
			}
		}

		$sheetXml = null;
		$sheetCandidates = [];
		$workbookXml = $zip->getFromName('xl/workbook.xml');
		$relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
		if (is_string($workbookXml) && $workbookXml !== '' && is_string($relsXml) && $relsXml !== '') {
			$workbookDoc = @simplexml_load_string($workbookXml);
			$relsDoc = @simplexml_load_string($relsXml);
			if ($workbookDoc !== false && $relsDoc !== false) {
				$workbookDoc->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
				$relsDoc->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');
				$sheetNodes = $workbookDoc->xpath('//x:sheets/x:sheet');
				$relNodes = $relsDoc->xpath('//r:Relationship');
				$relById = [];
				if (is_array($relNodes)) {
					foreach ($relNodes as $relNode) {
						$rid = (string) ($relNode['Id'] ?? '');
						$target = (string) ($relNode['Target'] ?? '');
						if ($rid !== '' && $target !== '') {
							$relById[$rid] = $target;
						}
					}
				}
				if (is_array($sheetNodes) && count($sheetNodes) > 0) {
					foreach ($sheetNodes as $sheetNode) {
						$sheetName = (string) ($sheetNode['name'] ?? '');
						$rid = (string) ($sheetNode->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] ?? '');
						if ($rid === '' || !isset($relById[$rid])) {
							continue;
						}
						$target = (string) $relById[$rid];
						$sheetRawXml = null;
						foreach ($this->xlsxResolveEntryCandidates($target) as $sheetPathCandidate) {
							$candidateXml = $zip->getFromName($sheetPathCandidate);
							if (is_string($candidateXml) && $candidateXml !== '') {
								$sheetRawXml = $candidateXml;
								break;
							}
						}
						if (!is_string($sheetRawXml) || $sheetRawXml === '') {
							continue;
						}
						$sheetCandidates[] = [
							'name' => $sheetName,
							'xml' => $sheetRawXml,
						];
					}
				}
			}
		}
		if (count($sheetCandidates) === 0) {
			$fallbackXml = $zip->getFromName('xl/worksheets/sheet1.xml');
			if (is_string($fallbackXml) && $fallbackXml !== '') {
				$sheetCandidates[] = [
					'name' => 'sheet1',
					'xml' => $fallbackXml,
				];
			}
		}
		if (count($sheetCandidates) === 0) {
			// Fallback robusto: listar qualquer aba física existente no ZIP.
			$sheetCandidates = $this->xlsxCollectWorksheetCandidates($zip);
		}
		if (count($sheetCandidates) > 0) {
			// Escolher automaticamente a aba com maior aderência ao cabeçalho esperado
			// para evitar "importação vazia" quando a primeira aba for Dashboard/Resumo.
			$requiredHeaders = $preferredHeaders ?? ['DATA', 'LOJA', 'SETUP', 'ROLLOUT', 'HEXAPADS', 'DEFEITO', 'SUPORTE'];
			$normalizeSheetHeader = static function (string $text): string {
				$text = strtoupper(trim($text));
				$from = ['Á', 'À', 'Â', 'Ã', 'Ä', 'É', 'È', 'Ê', 'Ë', 'Í', 'Ì', 'Î', 'Ï', 'Ó', 'Ò', 'Ô', 'Õ', 'Ö', 'Ú', 'Ù', 'Û', 'Ü', 'Ç'];
				$to =   ['A', 'A', 'A', 'A', 'A', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'C'];
				$text = str_replace($from, $to, $text);
				return preg_replace('/[^A-Z0-9]/', '', $text) ?? $text;
			};
			$bestScore = -1;
			foreach ($sheetCandidates as $candidate) {
				$rows = $this->extractRowsFromSheetXml((string) $candidate['xml'], $sharedStrings);
				if (count($rows) === 0) {
					continue;
				}
				$headerLimit = min(count($rows), 8);
				$bestRowScore = -1;
				for ($headerRowIdx = 0; $headerRowIdx < $headerLimit; $headerRowIdx++) {
					$headerKeys = array_map(
						static fn($v) => $normalizeSheetHeader((string) $v),
						$rows[$headerRowIdx]
					);
					$headerKeys = array_values(array_filter($headerKeys, static fn($h) => $h !== ''));
					$rowScore = 0;
					foreach ($requiredHeaders as $requiredHeader) {
						$needle = $normalizeSheetHeader($requiredHeader);
						if ($needle === '') {
							continue;
						}
						foreach ($headerKeys as $headerKey) {
							if ($headerKey === $needle || str_contains($headerKey, $needle) || str_contains($needle, $headerKey)) {
								$rowScore++;
								break;
							}
						}
					}
					if ($rowScore > $bestRowScore) {
						$bestRowScore = $rowScore;
					}
				}
				if ($bestRowScore > $bestScore) {
					$bestScore = $bestRowScore;
					$sheetXml = (string) $candidate['xml'];
				}
			}
		}
		if (!is_string($sheetXml) || $sheetXml === '') {
			$entries = [];
			$entryTotal = (int) $zip->numFiles;
			for ($i = 0; $i < min($entryTotal, 30); $i++) {
				$name = (string) $zip->getNameIndex($i);
				if ($name !== '') {
					$entries[] = $name;
				}
			}
			$hasWorkbook = is_string($workbookXml) && $workbookXml !== '';
			$hasRels = is_string($relsXml) && $relsXml !== '';
			$zip->close();
			throw new \RuntimeException(
				'Aba principal da planilha não foi encontrada. ' .
				'debug: entries=' . $entryTotal .
				', workbook=' . ($hasWorkbook ? 'ok' : 'nao') .
				', rels=' . ($hasRels ? 'ok' : 'nao') .
				', candidatos=' . count($sheetCandidates) .
				', amostra=[' . implode(', ', $entries) . ']'
			);
		}
		$zip->close();
		return $this->extractRowsFromSheetXml($sheetXml, $sharedStrings);
	}

	/**
	 * @return array<int, array<int, string>>
	 */
	private function extractRowsFromSheetXml(string $sheetXml, array $sharedStrings): array
	{
		$sheetDoc = @simplexml_load_string($sheetXml);
		if ($sheetDoc === false) {
			throw new \RuntimeException('Falha ao interpretar XML da planilha.');
		}

		$rows = [];
		$rowNodes = $sheetDoc->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]');
		if (!is_array($rowNodes)) {
			return [];
		}

		foreach ($rowNodes as $rowNode) {
			$row = [];
			$cells = $rowNode->xpath('./*[local-name()="c"]');
			if (!is_array($cells)) {
				continue;
			}

			$fallbackColIndex = 0;
			foreach ($cells as $cell) {
				$ref = (string) ($cell['r'] ?? '');
				$colIndex = $this->xlsxColumnIndexFromRef($ref);
				if ($colIndex < 0) {
					// Alguns geradores não incluem referência (r="A1") em todas as células.
					// Nesses casos, usar sequência posicional na linha.
					$colIndex = $fallbackColIndex;
				}
				$value = $this->xlsxExtractCellValue($cell, $sharedStrings);
				if ($colIndex >= 0) {
					$row[$colIndex] = $value;
					$fallbackColIndex = $colIndex + 1;
				}
			}

			if (!empty($row)) {
				ksort($row);
				$rows[] = $row;
			}
		}

		return $rows;
	}

	private function normalizeSpreadsheetDateToYmd($raw): ?string
	{
		$value = trim((string) $raw);
		if ($value === '' || $value === '-') {
			return null;
		}

		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
			return $value;
		}

		if (is_numeric($value)) {
			$serial = (float) $value;
			if ($serial > 20000) {
				$unix = (int) round(($serial - 25569) * 86400);
				return gmdate('Y-m-d', $unix);
			}
		}

		$ts = strtotime($value);
		if ($ts === false) {
			return null;
		}
		return date('Y-m-d', $ts);
	}

	private function xlsxExtractCellValue(\SimpleXMLElement $cell, array $sharedStrings): string
	{
		$attrs = $cell->attributes();
		$type = (string) ($attrs['t'] ?? '');

		if ($type === 'inlineStr') {
			$text = '';
			$isNodes = $cell->xpath('./*[local-name()="is"]');
			if (is_array($isNodes) && count($isNodes) > 0) {
				$inline = $isNodes[0];
				$tNodes = $inline->xpath('./*[local-name()="t"]');
				if (is_array($tNodes) && count($tNodes) > 0) {
					$text = (string) ($tNodes[0] ?? '');
				} else {
					$rNodes = $inline->xpath('./*[local-name()="r"]');
					if (!is_array($rNodes)) {
						$rNodes = [];
					}
					foreach ($rNodes as $run) {
						$runTNodes = $run->xpath('./*[local-name()="t"]');
						if (is_array($runTNodes) && count($runTNodes) > 0) {
							$text .= (string) ($runTNodes[0] ?? '');
						}
					}
				}
			}
			return $text;
		}

		$vNodes = $cell->xpath('./*[local-name()="v"]');
		if (!is_array($vNodes) || count($vNodes) === 0) {
			return '';
		}
		$raw = (string) ($vNodes[0] ?? '');
		if ($type === 's') {
			$sharedIndex = (int) $raw;
			return (string) ($sharedStrings[$sharedIndex] ?? '');
		}
		if ($type === 'b') {
			return $raw === '1' ? '1' : '0';
		}
		return $raw;
	}

	private function xlsxColumnIndexFromRef(string $ref): int
	{
		if ($ref === '') {
			return -1;
		}
		if (!preg_match('/^([A-Z]+)/i', $ref, $m)) {
			return -1;
		}
		$letters = strtoupper($m[1]);
		$index = 0;
		$len = strlen($letters);
		for ($i = 0; $i < $len; $i++) {
			$index = ($index * 26) + (ord($letters[$i]) - 64);
		}
		return $index - 1;
	}

	/**
	 * Normaliza caminhos de entries de XLSX vindos de workbook rels.
	 *
	 * @return array<int, string>
	 */
	private function xlsxResolveEntryCandidates(string $target): array
	{
		$value = trim($target);
		if ($value === '') {
			return [];
		}

		$value = str_replace('\\', '/', $value);
		$value = ltrim($value, '/');
		$normalized = preg_replace('#/\./#', '/', $value) ?? $value;
		while (strpos($normalized, '../') === 0) {
			$normalized = substr($normalized, 3);
		}

		$candidates = [$normalized];
		if (strpos($normalized, 'xl/') !== 0) {
			$candidates[] = 'xl/' . ltrim($normalized, '/');
		}
		if (strpos($normalized, 'worksheets/') === 0) {
			$candidates[] = 'xl/' . $normalized;
		}

		return array_values(array_unique(array_filter($candidates, static fn($v) => $v !== '')));
	}

	/**
	 * @return array<int, array{name:string, xml:string}>
	 */
	private function xlsxCollectWorksheetCandidates(\ZipArchive $zip): array
	{
		$candidates = [];
		for ($i = 0; $i < $zip->numFiles; $i++) {
			$entryName = (string) $zip->getNameIndex($i);
			if ($entryName === '') {
				continue;
			}
			if (!preg_match('#^xl/worksheets/[^/]+\.xml$#i', $entryName)) {
				continue;
			}
			$xml = $zip->getFromIndex($i);
			if (!is_string($xml) || $xml === '') {
				continue;
			}
			$candidates[] = [
				'name' => basename($entryName, '.xml'),
				'xml' => $xml,
			];
		}
		return $candidates;
	}
}


