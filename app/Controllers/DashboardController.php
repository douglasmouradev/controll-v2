<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\SdwanAccessLink;
use App\Models\SdwanEntry;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AuditLock;
use App\Services\Auth;
use App\Services\Cache;
use App\Services\DashboardCache;
use App\Services\DashboardStatsService;
use App\Services\Database;
use App\Services\DatabaseSchema;
use App\Services\SdwanEntryService;
use App\Services\SdwanExportService;
use App\Services\SdwanImageService;
use App\Services\SdwanImportService;
use App\Services\SdwanCleanupService;
use App\Services\SdwanAudit;
use App\Services\SdwanPermission;
use App\Services\SdwanQrService;
use App\Services\SdwanSettings;
use App\Services\StoreAddressService;
use App\Services\PurchasedDailies;
use App\Services\AuditLog;
use App\Services\InventoryService;
use App\Services\TicketAccess;
use App\Services\XlsxReaderService;

final class DashboardController extends Controller
{
	public function index(): void
	{
		$this->requireAuth([]);
		Ticket::bootstrapStatuses();
		$sessionUser = Auth::instance()->user();
		// Carrega dados completos do usuário (incluindo créditos) quando possível
		$fullUser = $sessionUser && isset($sessionUser['id'])
			? User::findById((int) $sessionUser['id'])
			: null;
		$user = $fullUser ?: $sessionUser;

		if ($fullUser && empty($fullUser['password_changed_at'])) {
			header('Location: /change-password-first');
			return;
		}

		$perPage = 50;
		$page = max(1, (int) ($_GET['page'] ?? 1));
		$filters = [
			'id' => $_GET['id'] ?? null,
			'status' => $_GET['status'] ?? null,
			'priority' => $_GET['priority'] ?? null,
			'user' => $_GET['user'] ?? null,
			'sigla' => $_GET['sigla'] ?? null,
			'cidade' => $_GET['cidade'] ?? null,
			'estado' => $_GET['estado'] ?? null,
			'limit' => $perPage,
			'offset' => ($page - 1) * $perPage,
		];
		$tickets = Ticket::listForUser($user, $filters);
		$ticketTotal = Ticket::countForUser($user, $filters);
		$ticketPages = max(1, (int) ceil($ticketTotal / $perPage));
		if ($page > $ticketPages) {
			$page = $ticketPages;
		}

		$closedPerPage = 50;
		$closedPage = max(1, (int) ($_GET['closed_page'] ?? 1));
		$closedFilters = [
			'id' => $_GET['closed_id'] ?? null,
			'status' => 'Fechado',
			'period' => $_GET['closed_period'] ?? null,
			'user' => $_GET['closed_user'] ?? null,
			'limit' => $closedPerPage,
			'offset' => ($closedPage - 1) * $closedPerPage,
		];
		$closedTickets = Ticket::listClosed($user, $closedFilters);
		$closedTotal = Ticket::countClosed($user, $closedFilters);
		$closedPages = max(1, (int) ceil($closedTotal / $closedPerPage));
		if ($closedPage > $closedPages) {
			$closedPage = $closedPages;
		}

		// Estatísticas para os cards (reutiliza cache do summary)
		try {
			$cacheKey = DashboardCache::statsKey('dashboard:summary', $user);
			$cached = Cache::get($cacheKey);
			if (is_array($cached) && isset($cached['stats']) && is_array($cached['stats'])) {
				$stats = $cached['stats'];
			} else {
				$stats = $this->getStats($user);
				Cache::set($cacheKey, ['success' => true, 'stats' => $stats], 30);
			}
		} catch (\Throwable $e) {
			error_log('Erro ao obter estatísticas: ' . $e->getMessage());
			$stats = [
				'total_tickets' => 0,
				'open_tickets' => 0,
				'in_progress_tickets' => 0,
				'scheduled_tickets' => 0,
				'closed_tickets' => 0,
				'total_users' => 0,
				'support_agents' => 0,
				'avg_resolution_hours' => 0,
			];
		}

		// Lista de usuários (para admin/suporte) — primeira página apenas no SSR
		$users = [];
		$usersPagination = ['page' => 1, 'pages' => 1, 'total' => 0, 'per_page' => 50];
		if (Auth::instance()->isSupport()) {
			try {
				$usersPerPage = 50;
				$usersTotal = User::countAll();
				$usersPages = max(1, (int) ceil($usersTotal / $usersPerPage));
				$users = User::listPaginated($usersPerPage, 0);
				$usersPagination = [
					'page' => 1,
					'pages' => $usersPages,
					'total' => $usersTotal,
					'per_page' => $usersPerPage,
				];
			} catch (\Throwable $e) {
				error_log('Erro ao listar usuários: ' . $e->getMessage());
				$users = [];
			}
		}

		$accessLogs = Auth::instance()->isAdmin() ? AuditLog::recent(100) : [];

		$this->view('dashboard/index', [
			'layout' => 'dashboard',
			'user' => $user,
			'tickets' => $tickets,
			'closed_tickets' => $closedTickets,
			'filters' => $filters,
			'closed_filters' => $closedFilters,
			'stats' => $stats,
			'users' => $users,
			'users_pagination' => $usersPagination,
			'maintenance_mode' => AuditLock::isMaintenanceEnabled(),
			'ticket_pagination' => [
				'page' => $page,
				'per_page' => $perPage,
				'total' => $ticketTotal,
				'pages' => $ticketPages,
			],
			'closed_pagination' => [
				'page' => $closedPage,
				'per_page' => $closedPerPage,
				'total' => $closedTotal,
				'pages' => $closedPages,
			],
			'access_logs' => $accessLogs,
		]);
	}

	private function getStats(array $user): array
	{
		return DashboardStatsService::getStats($user);
	}

	public function dailyStats(): void
	{
		$this->requireAuth([]);
		$user = Auth::instance()->user();
		$cacheKey = DashboardCache::statsKey('stats:dailies', $user);
		$cached = Cache::get($cacheKey);
		if (is_array($cached)) {
			$this->json($cached);
			return;
		}

		$payload = $this->buildDailyStatsPayload($user);
		Cache::set($cacheKey, $payload, 45);
		$this->json($payload);
	}

	public function dailyDestinationStats(): void
	{
		$this->requireAuth([]);
		$user = Auth::instance()->user();
		$cacheKey = DashboardCache::statsKey('stats:daily_dest', $user);
		$cached = Cache::get($cacheKey);
		if (is_array($cached)) {
			$this->json($cached);
			return;
		}

		$payload = DashboardStatsService::dailyDestinationStats($user);
		Cache::set($cacheKey, $payload, 60);
		$this->json($payload);
	}

	public function statusStats(): void
	{
		$this->requireAuth([]);
		$user = Auth::instance()->user();
		$cacheKey = DashboardCache::statsKey('stats:status', $user);
		$cached = Cache::get($cacheKey);
		if (is_array($cached)) {
			$this->json($cached);
			return;
		}

		$payload = $this->buildStatusStatsPayload($user);
		Cache::set($cacheKey, $payload, 45);
		$this->json($payload);
	}

	public function summaryStats(): void
	{
		$this->requireAuth([]);
		$user = Auth::instance()->user();
		$cacheKey = DashboardCache::statsKey('dashboard:summary', $user);
		$cached = Cache::get($cacheKey);
		if (is_array($cached)) {
			$this->json($cached);
			return;
		}

		$payload = [
			'success' => true,
			'stats' => DashboardStatsService::getStats($user),
		];
		Cache::set($cacheKey, $payload, 30);
		$this->json($payload);
	}

	public function creditUsageStats(): void
	{
		$this->requireAuth([]);
		$user = Auth::instance()->user();
		$cacheKey = DashboardCache::statsKey('stats:credit_usage', $user);
		$cached = Cache::get($cacheKey);
		if (is_array($cached)) {
			$this->json($cached);
			return;
		}

		$pdo = Database::pdo();

		$hasQtd = DatabaseSchema::columnExists($pdo, 'tickets', 'qtd');
		$hasTicketCategories = DatabaseSchema::tableExists($pdo, 'ticket_categories');
		$hasCategories = !$hasTicketCategories && DatabaseSchema::tableExists($pdo, 'categories');
		$hasCategoryColumn = DatabaseSchema::columnExists($pdo, 'tickets', 'category');

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
		if (TicketAccess::normalizeRole((string) ($user['role'] ?? '')) === 'user') {
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

		$payload = ['success' => true, 'summary' => $summary];
		Cache::set($cacheKey, $payload, 45);
		$this->json($payload);
	}

	public function chartsBundle(): void
	{
		$this->requireAuth([]);
		$user = Auth::instance()->user();
		$cacheKey = DashboardCache::statsKey('dashboard:charts_bundle', $user);
		$cached = Cache::get($cacheKey);
		if (is_array($cached)) {
			$this->json($cached);
			return;
		}

		$payload = [
			'success' => true,
			'dailies' => $this->buildDailyStatsPayload($user),
			'status' => $this->buildStatusStatsPayload($user),
			'daily_destinations' => DashboardStatsService::dailyDestinationStats($user),
		];
		Cache::set($cacheKey, $payload, 45);
		$this->json($payload);
	}

	public function ticketsOpen(): void
	{
		$this->requireAuth([]);
		$user = Auth::instance()->user();
		$perPage = 50;
		$page = max(1, (int) ($_GET['page'] ?? 1));
		$filters = [
			'id' => $_GET['id'] ?? null,
			'status' => $_GET['status'] ?? null,
			'priority' => $_GET['priority'] ?? null,
			'user' => $_GET['user'] ?? null,
			'sigla' => $_GET['sigla'] ?? null,
			'cidade' => $_GET['cidade'] ?? null,
			'estado' => $_GET['estado'] ?? null,
			'limit' => $perPage,
			'offset' => ($page - 1) * $perPage,
		];
		$tickets = Ticket::listForUser($user, $filters);
		$total = Ticket::countForUser($user, $filters);
		$pages = max(1, (int) ceil($total / $perPage));
		$this->json([
			'success' => true,
			'tickets' => $tickets,
			'pagination' => [
				'page' => min($page, $pages),
				'pages' => $pages,
				'total' => $total,
				'per_page' => $perPage,
			],
			'is_support' => Auth::instance()->isSupport(),
			'current_user_id' => (int) ($user['id'] ?? 0),
		]);
	}

	public function inventoryStats(): void
	{
		$this->requireAuth([]);

		$xlsxPath = InventoryService::resolvePath((string) ($_SESSION['inventory_xlsx_path'] ?? ''));
		if ($xlsxPath === '') {
			$this->json([
				'success' => false,
				'message' => 'Planilha de inventário não encontrada ou sem permissão de leitura. Envie uma planilha ou configure INVENTORY_XLSX_PATH.',
			], 500);
			return;
		}

		$mtime = (int) (@filemtime($xlsxPath) ?: 0);
		$filterKey = md5(json_encode([
			'store' => (string) ($_GET['store'] ?? ''),
			'support_status' => (string) ($_GET['support_status'] ?? ''),
			'start_date' => (string) ($_GET['start_date'] ?? ''),
			'end_date' => (string) ($_GET['end_date'] ?? ''),
		], JSON_UNESCAPED_UNICODE));
		$cacheKey = 'inventory:stats:' . md5($xlsxPath) . ':' . $mtime . ':' . $filterKey;
		$cached = Cache::get($cacheKey);
		if (is_array($cached)) {
			$this->json($cached);
			return;
		}

		try {
			$rows = (new XlsxReaderService())->readRows($xlsxPath);
			$payload = InventoryService::buildStatsPayload($rows, $_GET, $xlsxPath);
			Cache::set($cacheKey, $payload, 300);
			$this->json($payload);
		} catch (\Throwable $e) {
			error_log('Erro ao processar planilha de inventário: ' . $e->getMessage());
			$response = [
				'success' => false,
				'message' => 'Erro ao processar planilha de inventário',
			];
			if (defined('APP_DEBUG') && APP_DEBUG) {
				$response['details'] = $e->getMessage();
			}
			$this->json($response, 500);
		}
	}

	public function uploadInventoryFile(): void
	{
		$this->requireAuth(['support', 'admin']);

		if (!isset($_FILES['file'])) {
			$this->json([
				'success' => false,
				'message' => 'Arquivo não enviado',
			], 400);
			return;
		}

		$file = $_FILES['file'];
		if (!is_array($file) || (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)) {
			$this->json([
				'success' => false,
				'message' => 'Falha no upload da planilha',
			], 400);
			return;
		}

		try {
			$targetPath = InventoryService::saveUploadedFile($file);
		} catch (\InvalidArgumentException $e) {
			$this->json(['success' => false, 'message' => $e->getMessage()], 400);
			return;
		} catch (\Throwable $e) {
			error_log('Erro no upload de inventário: ' . $e->getMessage());
			$this->json(['success' => false, 'message' => 'Erro ao importar planilha de inventário'], 500);
			return;
		}

		$_SESSION['inventory_xlsx_path'] = $targetPath;
		$this->json([
			'success' => true,
			'message' => 'Planilha importada com sucesso',
		]);
	}
	public function downloadInventoryFile(): void
	{
		$this->requireAuth(['support', 'admin']);

		$xlsxPath = InventoryService::resolvePath((string) ($_SESSION['inventory_xlsx_path'] ?? ''));
		if ($xlsxPath === '') {
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
			$cacheDir = BASE_PATH . '/storage/cache/purchased_dailies';
			if (!is_dir($cacheDir)) {
				@mkdir($cacheDir, 0775, true);
			}
			$mtime = (int) @filemtime($path);
			$cacheFile = $cacheDir . '/' . md5($path . '|' . $mtime) . '.json';
			if (is_file($cacheFile) && is_readable($cacheFile)) {
				$cached = json_decode((string) file_get_contents($cacheFile), true);
				if (is_array($cached) && isset($cached['rows'], $cached['summary'])) {
					return $cached;
				}
			}
			$parsed = $this->parsePurchasedDailiesFile($path);
			@file_put_contents($cacheFile, json_encode($parsed, JSON_UNESCAPED_UNICODE));
			return $parsed;
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
			$rows = (new XlsxReaderService())->readRows($path, [
				'DATA', 'LOJA', 'COMPRAD', 'QUANT', 'QTD', 'DIARIA', 'TIPO', 'PREVISTO', 'UNIDADE', 'SIGLA', 'ATIVIDADE', 'PEDIDO', 'NUMERO',
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
			return;
		}

		$mtime = (int) (@filemtime($path) ?: 0);
		$cacheKey = 'store:addresses:' . $mtime;
		$cached = Cache::get($cacheKey);
		if (is_array($cached)) {
			$this->json($cached);
			return;
		}

		$raw = trim((string) file_get_contents($path));
		if ($raw === '') {
			$payload = ['success' => true, 'data' => []];
			Cache::set($cacheKey, $payload, 3600);
			$this->json($payload);
			return;
		}

		$json = '[' . rtrim($raw, ", \n\r\t") . ']';
		$data = json_decode($json, true);
		if (!is_array($data)) {
			$this->json([
				'success' => false,
				'message' => 'Erro ao ler arquivo de endereços',
			], 500);
			return;
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

		$payload = ['success' => true, 'data' => $result];
		Cache::set($cacheKey, $payload, 3600);
		$this->json($payload);
	}

	private function buildDailyStatsPayload(array $user): array
	{
		$pdo = Database::pdo();
		$hasQtd = DatabaseSchema::columnExists($pdo, 'tickets', 'qtd');
		$hasTicketCategories = DatabaseSchema::tableExists($pdo, 'ticket_categories');
		$hasCategories = !$hasTicketCategories && DatabaseSchema::tableExists($pdo, 'categories');

		$qtdExpr = $hasQtd
			? 'CASE WHEN t.qtd IS NULL OR t.qtd = 0 THEN 1 ELSE t.qtd END'
			: '1';

		$sql = "
			SELECT DATE(t.created_at) AS dia,
			       SUM($qtdExpr) AS total
			FROM tickets t
		";

		if ($hasTicketCategories) {
			$sql .= ' LEFT JOIN ticket_categories tc ON tc.id = t.category_id';
		} elseif ($hasCategories) {
			$sql .= ' LEFT JOIN categories c ON c.id = t.category_id';
		}

		$sql .= ' WHERE 1=1';

		if ($hasTicketCategories) {
			$sql .= " AND tc.name = 'Diária'";
		} elseif ($hasCategories) {
			$sql .= " AND c.name = 'Diária'";
		} elseif (DatabaseSchema::columnExists($pdo, 'tickets', 'category')) {
			$sql .= " AND t.category = 'Diária'";
		}

		$params = [];
		if (TicketAccess::normalizeRole((string) ($user['role'] ?? '')) === 'user') {
			$sql .= ' AND t.user_id = :user_id';
			$params[':user_id'] = (int) $user['id'];
		}

		$sql .= ' GROUP BY DATE(t.created_at) ORDER BY dia ASC LIMIT 60';

		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);
		$rows = $stmt->fetchAll();
		$labels = [];
		$data = [];
		foreach ($rows as $r) {
			$labels[] = $r['dia'];
			$data[] = (int) $r['total'];
		}

		return [
			'success' => true,
			'labels' => $labels,
			'data' => $data,
		];
	}

	private function buildStatusStatsPayload(array $user): array
	{
		$pdo = Database::pdo();
		$sql = '
			SELECT ts.name, COUNT(t.id) as total
			FROM tickets t
			LEFT JOIN ticket_statuses ts ON t.status_id = ts.id
			WHERE 1=1
		';
		$params = [];
		if (TicketAccess::normalizeRole((string) ($user['role'] ?? '')) === 'user') {
			$sql .= ' AND t.user_id = :user_id';
			$params[':user_id'] = (int) $user['id'];
		}
		$sql .= ' GROUP BY ts.id, ts.name ORDER BY ts.id ASC';

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

		return [
			'success' => true,
			'labels' => $labels,
			'data' => $data,
		];
	}

	public function sdwanEntries(): void
	{
		$this->requireAuth([]);
		if (!SdwanPermission::canView()) {
			$this->json(['success' => false, 'message' => 'Sem permissão'], 403);
			return;
		}

		if (!SdwanEntry::tableReady()) {
			$this->json([
				'success' => false,
				'message' => 'Tabela ACUPAD não configurada. Execute as migrations do banco.',
			], 503);
			return;
		}

		$filters = SdwanEntry::filtersFromRequest();
		$list = SdwanEntry::listFiltered($filters);

		$this->json([
			'success' => true,
			'entries' => $list['entries'],
			'pagination' => $list['pagination'],
			'summary' => SdwanEntry::summary($filters),
			'chart' => SdwanEntry::pieChartByStore($filters),
			'progress' => SdwanEntry::progressChart($filters),
			'store_panel' => SdwanEntry::storePanel($filters),
			'inconsistencies' => SdwanEntry::inconsistencies($filters),
			'settings' => SdwanSettings::apiPayload(),
			'can_manage' => SdwanPermission::canManage(),
			'can_admin' => SdwanPermission::canAdmin(),
		]);
	}

	public function sdwanChartStats(): void
	{
		$this->requireAuth([]);

		if (!SdwanEntry::tableReady()) {
			$this->json([
				'success' => false,
				'message' => 'Tabela ACUPAD não configurada. Execute as migrations do banco.',
			], 503);
			return;
		}

		$filters = SdwanEntry::filtersFromRequest();
		$this->json(SdwanEntry::chartPayload($filters));
	}

	public function sdwanExportPdf(): void
	{
		$this->requireAuth([]);

		if (!SdwanEntry::tableReady()) {
			http_response_code(503);
			echo 'Tabela ACUPAD não configurada';
			return;
		}

		try {
			SdwanExportService::exportPdf();
		} catch (\Throwable $e) {
			error_log('Erro ao exportar PDF SDWAN: ' . $e->getMessage());
			http_response_code(500);
			echo 'Erro ao gerar PDF';
		}
		exit;
	}

	public function sdwanExportXlsx(): void
	{
		$this->requireAuth([]);

		if (!SdwanEntry::tableReady()) {
			http_response_code(503);
			echo 'Tabela ACUPAD não configurada';
			return;
		}

		try {
			SdwanExportService::exportXlsx();
		} catch (\Throwable $e) {
			error_log('Erro ao exportar Excel SDWAN: ' . $e->getMessage());
			http_response_code(500);
			echo 'Erro ao gerar Excel';
		}
		exit;
	}

	public function sdwanEntryCreate(): void
	{
		$this->requireAuth([]);
		if (!SdwanPermission::canManage()) {
			$this->json(['success' => false, 'message' => 'Sem permissão para cadastrar no Projeto ACUPAD'], 403);
			return;
		}

		$validation = SdwanEntry::validateInput($_POST);
		if (!$validation['success']) {
			$this->json(['success' => false, 'message' => $validation['message'] ?? 'Dados inválidos'], 422);
			return;
		}

		$id = 0;
		try {
			$user = Auth::instance()->user();
			$data = $validation['data'];
			$id = SdwanEntry::create($data, isset($user['id']) ? (int) $user['id'] : null, ['entry_source' => 'dashboard']);
			SdwanEntryService::applyImageUpload($id, $_POST, $_FILES);
			$entry = SdwanEntry::findById($id);
			$response = [
				'success' => true,
				'message' => 'Registro ACUPAD salvo com sucesso',
				'entry' => $entry,
			];
			if (!empty($validation['warning'])) {
				$response['warning'] = $validation['warning'];
			}
			SdwanAudit::record('create', 'entry:' . $id);
			$this->json($response);
		} catch (\InvalidArgumentException $e) {
			if (!empty($id) && $id > 0) {
				SdwanEntry::delete((int) $id);
			}
			$this->json(['success' => false, 'message' => $e->getMessage()], 422);
		} catch (\Throwable $e) {
			error_log('Erro ao criar registro ACUPAD: ' . $e->getMessage());
			$this->json(['success' => false, 'message' => 'Erro ao salvar registro ACUPAD'], 500);
		}
	}

	public function sdwanEntryUpdate(): void
	{
		$this->requireAuth([]);
		if (!SdwanPermission::canManage()) {
			$this->json(['success' => false, 'message' => 'Sem permissão para editar no Projeto ACUPAD'], 403);
			return;
		}

		$id = (int) ($_POST['id'] ?? 0);
		$existing = SdwanEntry::findRawById($id);
		if ($id <= 0 || !$existing) {
			$this->json(['success' => false, 'message' => 'Registro não encontrado'], 404);
			return;
		}

		$validation = SdwanEntry::validateInput($_POST, $id);
		if (!$validation['success']) {
			$this->json(['success' => false, 'message' => $validation['message'] ?? 'Dados inválidos'], 422);
			return;
		}

		try {
			$data = $validation['data'];
			if (SdwanEntry::hasImageColumns()) {
				$data['image_path'] = $existing['image_path'] ?? null;
				$data['image_name'] = $existing['image_name'] ?? null;
				$data['image_type'] = $existing['image_type'] ?? null;
				$data['image_size'] = $existing['image_size'] ?? null;
			}

			if (!SdwanEntry::update($id, $data)) {
				$this->json(['success' => false, 'message' => 'Erro ao atualizar registro ACUPAD'], 500);
				return;
			}

			SdwanEntryService::applyImageUpload($id, $_POST, $_FILES);
			$response = [
				'success' => true,
				'message' => 'Registro ACUPAD atualizado com sucesso',
				'entry' => SdwanEntry::findById($id),
			];
			if (!empty($validation['warning'])) {
				$response['warning'] = $validation['warning'];
			}
			SdwanAudit::record('update', 'entry:' . $id);
			$this->json($response);
		} catch (\InvalidArgumentException $e) {
			$this->json(['success' => false, 'message' => $e->getMessage()], 422);
		} catch (\Throwable $e) {
			error_log('Erro ao atualizar registro ACUPAD: ' . $e->getMessage());
			$this->json(['success' => false, 'message' => 'Erro ao atualizar registro ACUPAD'], 500);
		}
	}

	public function sdwanEntryDelete(): void
	{
		$this->requireAuth([]);
		if (!SdwanPermission::canManage()) {
			$this->json(['success' => false, 'message' => 'Sem permissão para excluir no Projeto ACUPAD'], 403);
			return;
		}

		$id = (int) ($_POST['id'] ?? 0);
		if ($id <= 0 || !SdwanEntry::findRawById($id)) {
			$this->json(['success' => false, 'message' => 'Registro não encontrado'], 404);
			return;
		}

		if (!SdwanEntry::delete($id)) {
			$this->json(['success' => false, 'message' => 'Erro ao excluir registro ACUPAD'], 500);
			return;
		}

		SdwanAudit::record('delete', 'entry:' . $id);

		$this->json([
			'success' => true,
			'message' => 'Registro ACUPAD excluído com sucesso',
		]);
	}

	public function sdwanEntryImage(): void
	{
		$this->requireAuth([]);

		$id = (int) ($_GET['id'] ?? 0);
		$entry = SdwanEntry::findRawById($id);
		if ($id <= 0 || !$entry || empty($entry['image_path'])) {
			http_response_code(404);
			echo 'Imagem não encontrada';
			return;
		}

		$fsPath = SdwanImageService::resolveFilesystemPath((string) $entry['image_path']);
		if ($fsPath === null) {
			http_response_code(404);
			echo 'Arquivo não encontrado';
			return;
		}

		$mime = (string) ($entry['image_type'] ?? '');
		if ($mime === '' || $mime === 'application/octet-stream') {
			$mime = mime_content_type($fsPath) ?: 'application/octet-stream';
		}

		header('Content-Type: ' . $mime);
		header('Content-Disposition: inline; filename="' . basename((string) ($entry['image_name'] ?? 'imagem')) . '"');
		header('Content-Length: ' . (string) filesize($fsPath));
		header('Cache-Control: private, max-age=3600');
		readfile($fsPath);
		exit;
	}

	public function sdwanAccessLinkQr(): void
	{
		$this->requireAuth([]);

		$id = (int) ($_GET['id'] ?? 0);
		$row = SdwanAccessLink::findById($id);
		if ($row === null || strtotime((string) ($row['expires_at'] ?? '')) <= time()) {
			http_response_code(404);
			echo 'QR Code não disponível';
			return;
		}

		$code = SdwanAccessLink::normalizeCode((string) ($row['code'] ?? ''));
		if ($code === '') {
			http_response_code(404);
			echo 'QR Code não disponível';
			return;
		}

		$image = SdwanQrService::render(SdwanAccessLink::buildPublicUrl($code));
		if ($image === null) {
			http_response_code(502);
			echo 'Não foi possível gerar o QR Code';
			return;
		}

		header('Content-Type: ' . $image['content_type']);
		header('Cache-Control: private, max-age=3600');
		header('Content-Length: ' . (string) strlen($image['body']));
		echo $image['body'];
		exit;
	}

	public function sdwanAccessLinkStatus(): void
	{
		$this->requireAuth([]);

		if (!SdwanAccessLink::tableReady()) {
			$this->json([
				'success' => false,
				'message' => 'Tabela de links ACUPAD não configurada. Execute as migrations.',
			], 503);
			return;
		}

		$user = Auth::instance()->user();
		$userId = isset($user['id']) ? (int) $user['id'] : 0;
		$link = $userId > 0 ? SdwanAccessLink::getLatestActiveForUser($userId) : null;
		$links = $userId > 0 ? SdwanAccessLink::listActive($userId) : SdwanAccessLink::listActive();

		$this->json([
			'success' => true,
			'link' => $link,
			'links' => $links,
		]);
	}

	public function sdwanAccessLinkRevoke(): void
	{
		$this->requireAuth([]);
		if (!SdwanPermission::canManage()) {
			$this->json(['success' => false, 'message' => 'Sem permissão'], 403);
			return;
		}

		$id = (int) ($_POST['id'] ?? 0);
		if ($id <= 0) {
			$this->json(['success' => false, 'message' => 'Link inválido'], 422);
			return;
		}

		$user = Auth::instance()->user();
		$userId = isset($user['id']) ? (int) $user['id'] : 0;
		if (!SdwanAccessLink::revoke($id, $userId > 0 ? $userId : null)) {
			$this->json(['success' => false, 'message' => 'Não foi possível revogar o link'], 404);
			return;
		}

		SdwanAudit::record('link_revoke', 'link:' . $id);

		$this->json([
			'success' => true,
			'message' => 'Link revogado com sucesso',
			'links' => $userId > 0 ? SdwanAccessLink::listActive($userId) : SdwanAccessLink::listActive(),
		]);
	}

	public function sdwanAccessLinkGenerate(): void
	{
		$this->requireAuth([]);
		if (!SdwanPermission::canManage()) {
			$this->json(['success' => false, 'message' => 'Sem permissão'], 403);
			return;
		}

		$user = Auth::instance()->user();
		$userId = isset($user['id']) ? (int) $user['id'] : null;
		$result = SdwanAccessLink::generate($userId);

		if (!$result['success']) {
			$this->json(['success' => false, 'message' => $result['message'] ?? 'Erro ao gerar link'], 500);
			return;
		}

		SdwanAudit::record('link_generate', 'user:' . ($userId ?? 0));

		$this->json([
			'success' => true,
			'message' => 'Link gerado com sucesso. Válido por ' . SdwanSettings::linkTtlHours() . ' hora(s).',
			'link' => $result['link'] ?? null,
			'links' => $userId ? SdwanAccessLink::listActive($userId) : SdwanAccessLink::listActive(),
		]);
	}

	public function sdwanSettingsUpdate(): void
	{
		$this->requireAuth([]);
		if (!SdwanPermission::canManage()) {
			$this->json(['success' => false, 'message' => 'Sem permissão'], 403);
			return;
		}

		$goal = max(0, (int) ($_POST['xpads_goal'] ?? 0));
		$maxSub = max(1, min(500, (int) ($_POST['link_max_submissions'] ?? 50)));
		$ttlHours = max(1, min(168, (int) ($_POST['link_ttl_hours'] ?? 24)));
		SdwanSettings::setXpadsGoal($goal);
		SdwanSettings::setLinkMaxSubmissions($maxSub);
		SdwanSettings::setLinkTtlHours($ttlHours);
		SdwanAudit::record('settings_update', 'goal:' . $goal);

		$this->json([
			'success' => true,
			'message' => 'Configurações ACUPAD salvas',
			'settings' => SdwanSettings::apiPayload(),
		]);
	}

	public function sdwanImportCsv(): void
	{
		$this->requireAuth([]);
		if (!SdwanPermission::canManage()) {
			$this->json(['success' => false, 'message' => 'Sem permissão'], 403);
			return;
		}

		if (!isset($_FILES['file'])) {
			$this->json(['success' => false, 'message' => 'Arquivo não enviado'], 400);
			return;
		}

		$user = Auth::instance()->user();
		$userId = isset($user['id']) ? (int) $user['id'] : null;
		$result = SdwanImportService::importCsvFile($_FILES['file'], $userId);
		$this->json($result, ($result['success'] ?? false) ? 200 : 422);
	}

	public function sdwanImportPreview(): void
	{
		$this->requireAuth([]);
		if (!SdwanPermission::canManage()) {
			$this->json(['success' => false, 'message' => 'Sem permissão'], 403);
			return;
		}

		if (!isset($_FILES['file'])) {
			$this->json(['success' => false, 'message' => 'Arquivo não enviado'], 400);
			return;
		}

		$result = SdwanImportService::previewCsvFile($_FILES['file']);
		$this->json($result, ($result['success'] ?? false) ? 200 : 422);
	}

	public function sdwanUploadStores(): void
	{
		$this->requireAuth([]);
		if (!SdwanPermission::canManage()) {
			$this->json(['success' => false, 'message' => 'Sem permissão'], 403);
			return;
		}

		if (!isset($_FILES['file'])) {
			$this->json(['success' => false, 'message' => 'Arquivo não enviado'], 400);
			return;
		}

		$result = StoreAddressService::uploadAddressesFile($_FILES['file']);
		if ($result['success'] ?? false) {
			SdwanAudit::record('stores_upload', 'total:' . ($result['total'] ?? 0));
		}
		$this->json($result, ($result['success'] ?? false) ? 200 : 422);
	}

	public function sdwanCleanup(): void
	{
		$this->requireAuth(['admin']);
		$result = SdwanCleanupService::run();
		$this->json([
			'success' => true,
			'message' => sprintf('Limpeza concluída: %d imagem(ns) órfã(s), %d link(s) antigo(s) removido(s).', $result['orphan_images'], $result['expired_links']),
			'result' => $result,
		]);
	}

	public function sdwanAuditLogs(): void
	{
		$this->requireAuth([]);
		if (!SdwanPermission::canAdmin()) {
			$this->json(['success' => false, 'message' => 'Sem permissão'], 403);
			return;
		}

		$logs = SdwanAudit::recent(50);
		$items = array_map(static function (array $row): array {
			$action = (string) ($row['action'] ?? '');

			return [
				'id' => (int) ($row['id'] ?? 0),
				'action' => $action,
				'action_label' => SdwanAudit::actionLabel($action),
				'resource' => (string) ($row['resource'] ?? ''),
				'user_name' => (string) ($row['user_name'] ?? 'Sistema'),
				'success' => (int) ($row['success'] ?? 0) === 1,
				'created_at' => (string) ($row['created_at'] ?? ''),
			];
		}, $logs);

		$this->json(['success' => true, 'logs' => $items]);
	}

	public function sdwanImportTemplate(): void
	{
		$this->requireAuth([]);
		if (!SdwanPermission::canManage()) {
			http_response_code(403);
			echo 'Sem permissão';
			return;
		}

		header('Content-Type: text/csv; charset=UTF-8');
		header('Content-Disposition: attachment; filename="modelo-importacao-acupad.csv"');
		echo "loja;xpads_previsto;quantidade_localizada;pdv_numero;pdv_serie\n";
		echo "ABC;10;8;001;\n";
		exit;
	}
}


