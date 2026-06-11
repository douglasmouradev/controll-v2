<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;
use App\Models\CreditHistory;
use App\Services\Auth;
use App\Services\TicketAccess;

final class UserController extends Controller
{
	public function index(): void
	{
		$this->requireAuth(['support', 'admin']);
		
		// Se houver ID, retorna apenas esse usuário
		if (!empty($_GET['id'])) {
			$id = (int) $_GET['id'];
			$user = User::findById($id);
			if ($user) {
				$this->json(['success' => true, 'users' => [$user]]);
			} else {
				$this->json(['success' => false, 'message' => 'Usuário não encontrado'], 404);
			}
			return;
		}
		
		$users = User::listAll();
		$this->json(['success' => true, 'users' => $users]);
	}

	public function create(): void
	{
		$this->requireAuth(['support', 'admin']);
		
		$name = trim($_POST['name'] ?? '');
		$email = trim($_POST['email'] ?? '');
		$password = $_POST['password'] ?? '';
		$role = $_POST['role'] ?? 'usuario';
		$actor = Auth::instance()->user();
		if (!$this->canAssignRole($actor, (string) $role)) {
			$this->json(['success' => false, 'message' => 'Você não pode atribuir este perfil'], 403);
			return;
		}

		$this->logUserCreateDebug('request_received', [
			'name' => $name,
			'email' => $email,
			'role' => $role,
		]);
		
		if ($name === '' || $email === '' || $password === '') {
			$this->logUserCreateDebug('validation_failed_required', [
				'email' => $email,
				'name_empty' => $name === '',
				'email_empty' => $email === '',
				'password_empty' => $password === '',
			]);
			$this->json(['success' => false, 'message' => 'Dados obrigatórios faltando'], 422);
			return;
		}

		// Evitar erro de banco por e-mail duplicado e retornar mensagem mais clara para o usuário
		$existing = User::findByEmail($email);
		if ($existing) {
			$this->logUserCreateDebug('email_already_exists', [
				'email' => $email,
				'existing' => [
					'id' => $existing['id'] ?? null,
					'email' => $existing['email'] ?? null,
					'username' => $existing['username'] ?? null,
					'role' => $existing['role'] ?? null,
					'active' => $existing['active'] ?? null,
				],
			]);
			$this->json([
				'success' => false,
				'message' => 'E-mail já cadastrado para outro usuário',
			], 422);
			return;
		}
		
		try {
			$id = User::create([
				'name' => $name,
				'email' => $email,
				'password' => $password,
				'role' => $role,
			]);
			$this->logUserCreateDebug('user_created', [
				'id' => $id,
				'email' => $email,
				'role' => $role,
			]);
			$this->json(['success' => true, 'message' => 'Usuário criado', 'id' => $id]);
		} catch (\Throwable $e) {
			$this->logUserCreateDebug('exception', [
				'email' => $email,
				'role' => $role,
				'exception_message' => $e->getMessage(),
				'exception_code' => $e->getCode(),
			]);
			error_log('Erro ao criar usuário: ' . $e->getMessage());
			$this->json(['success' => false, 'message' => 'Erro ao criar usuário'], 500);
		}
	}

	public function update(): void
	{
		$this->requireAuth(['support', 'admin']);
		
		$id = (int) ($_POST['id'] ?? 0);
		if (!$id) {
			$this->json(['success' => false, 'message' => 'ID inválido'], 422);
			return;
		}
		
		$data = [];
		if (isset($_POST['name'])) $data['name'] = trim($_POST['name']);
		if (isset($_POST['email'])) $data['email'] = trim($_POST['email']);
		if (isset($_POST['password']) && !empty($_POST['password'])) $data['password'] = $_POST['password'];
		if (isset($_POST['role'])) {
			$actor = Auth::instance()->user();
			if (!$this->canAssignRole($actor, (string) $_POST['role'])) {
				$this->json(['success' => false, 'message' => 'Você não pode atribuir este perfil'], 403);
				return;
			}
			$data['role'] = $_POST['role'];
		}
		
		if (empty($data)) {
			$this->json(['success' => false, 'message' => 'Nenhum dado para atualizar'], 422);
			return;
		}
		
		$ok = User::update($id, $data);
		$this->json(['success' => $ok, 'message' => $ok ? 'Usuário atualizado' : 'Erro ao atualizar']);
	}

	public function adjustCredits(): void
	{
		$this->requireAuth(['admin']);
		$id = (int) ($_POST['id'] ?? 0);
		$delta = (int) ($_POST['delta'] ?? 0);
		$type = isset($_POST['type']) ? (string) $_POST['type'] : 'ticket';
		
		if ($delta === 0) {
			$this->json(['success' => false, 'message' => 'Parâmetros inválidos'], 422);
			return;
		}
		
		if (!in_array($type, ['ticket', 'daily', 'project_dailies'], true)) {
			$this->json(['success' => false, 'message' => 'Tipo de crédito inválido'], 422);
			return;
		}
		
		try {
			$currentUser = Auth::instance()->user();
			$description = $delta > 0 ? 'Créditos comprados' : 'Créditos ajustados';

			// Pool global: aplicar ajuste para todos os tipos (user, admin, support) quando id = 0
			if ($id === 0) {
				$rolesPool = ['user', 'admin', 'support'];
				$creditsByUser = [];
				if ($type === 'daily') {
					$creditsByUser = User::adjustDailyCreditsForRoles($rolesPool, $delta);
				} elseif ($type === 'project_dailies') {
					$creditsByUser = User::adjustProjectDailiesCreditsForRoles($rolesPool, $delta);
				} else {
					$creditsByUser = User::adjustCreditsForRoles($rolesPool, $delta);
				}
				
				if (empty($creditsByUser)) {
					$this->json(['success' => false, 'message' => 'Nenhum usuário do tipo usuario encontrado'], 404);
					return;
				}

				// Registrar no historico para cada usuario afetado
				foreach (array_keys($creditsByUser) as $userId) {
					CreditHistory::record(
						$userId,
						$type,
						$delta,
						$description,
						null,
						'manual',
						$currentUser ? (int) $currentUser['id'] : null
					);
				}

				$this->json([
					'success' => true,
					'message' => 'Creditos atualizados para todos os usuarios',
					'type' => $type,
					'credits' => reset($creditsByUser),
				]);
				return;
			}

			// Ajuste individual para um usuario especifico
			if (!$id) {
				$this->json(['success' => false, 'message' => 'ID de usuario invalido'], 422);
				return;
			}

			$targetUser = User::findById($id);
			if (!$targetUser) {
				$this->json(['success' => false, 'message' => 'Usuario nao encontrado'], 404);
				return;
			}
			
			$targetRole = (string) ($targetUser['role'] ?? 'user');

			// Se for usuario final (user), aplicar o ajuste para TODOS os tipos (user, admin, support)
			if ($targetRole === 'user') {
				$rolesPool = ['user', 'admin', 'support'];
				$creditsByUser = [];
				if ($type === 'daily') {
					$creditsByUser = User::adjustDailyCreditsForRoles($rolesPool, $delta);
				} elseif ($type === 'project_dailies') {
					$creditsByUser = User::adjustProjectDailiesCreditsForRoles($rolesPool, $delta);
				} else {
					$creditsByUser = User::adjustCreditsForRoles($rolesPool, $delta);
				}
				
				if (empty($creditsByUser)) {
					$this->json(['success' => false, 'message' => 'Nenhum usuario do tipo usuario encontrado para ajuste de creditos'], 404);
					return;
				}

				// Registrar no historico para cada usuario afetado
				foreach (array_keys($creditsByUser) as $userId) {
					CreditHistory::record(
						$userId,
						$type,
						$delta,
						$description,
						null,
						'manual',
						$currentUser ? (int) $currentUser['id'] : null
					);
				}

				$newForTarget = $creditsByUser[$id] ?? null;
				$this->json([
					'success' => true,
					'message' => 'Creditos atualizados para todos os usuarios',
					'type' => $type,
					'credits' => $newForTarget,
				]);
				return;
			}

			// Para outros perfis (admin/support), manter ajuste individual
			if ($type === 'daily') {
				$new = User::adjustDailyCredits($id, $delta);
			} elseif ($type === 'project_dailies') {
				$new = User::adjustProjectDailiesCredits($id, $delta);
			} else {
				$new = User::adjustCredits($id, $delta);
			}
			
			if ($new === null) {
				$this->json(['success' => false, 'message' => 'Usuario nao encontrado'], 404);
				return;
			}

			CreditHistory::record(
				$id,
				$type,
				$delta,
				$description,
				null,
				'manual',
				$currentUser ? (int) $currentUser['id'] : null
			);

			$this->json([
				'success' => true,
				'message' => 'Creditos atualizados',
				'type' => $type,
				'credits' => $new,
			]);
		} catch (\RuntimeException $e) {
			$this->json(['success' => false, 'message' => $e->getMessage()], 422);
		} catch (\Throwable $e) {
			error_log('Erro ao ajustar creditos: ' . $e->getMessage());
			$this->json(['success' => false, 'message' => 'Erro ao ajustar creditos'], 500);
		}
	}

	public function delete(): void
	{
		$this->requireAuth(['admin']);
		
		$id = (int) ($_POST['id'] ?? 0);
		if (!$id) {
			$this->json(['success' => false, 'message' => 'ID invalido'], 422);
			return;
		}

		// Nao permitir deletar a si mesmo
		$currentUser = Auth::instance()->user();
		if ($currentUser && (int) $currentUser['id'] === $id) {
			$this->json(['success' => false, 'message' => 'Nao e possivel deletar seu proprio usuario'], 422);
			return;
		}

		try {
			$ok = User::delete($id);
			if (!$ok) {
				$this->json([
					'success' => false,
					'message' => 'Nao foi possivel excluir: e necessario existir pelo menos outro usuario no sistema para reatribuir chamados vinculados a este perfil.',
				], 422);
				return;
			}
			$this->json(['success' => true, 'message' => 'Usuario deletado']);
		} catch (\PDOException $e) {
			error_log('Erro ao deletar usuario: ' . $e->getMessage());
			$msg = 'Erro ao deletar usuario';
			if (str_contains($e->getMessage(), '1451') || str_contains($e->getMessage(), 'Integrity constraint')) {
				$msg = 'Nao foi possivel excluir: ainda existem registros vinculados a este usuario no banco de dados.';
			}
			$this->json(['success' => false, 'message' => $msg], 422);
		} catch (\Throwable $e) {
			error_log('Erro ao deletar usuario: ' . $e->getMessage());
			$this->json(['success' => false, 'message' => 'Erro ao deletar usuario'], 500);
		}
	}

	public function creditHistory(): void
	{
		$this->requireAuth([]);
		// Aceitar tanto 'user_id' quanto 'id' como parametro
		$userId = (int) ($_GET['user_id'] ?? $_GET['id'] ?? 0);
		$type = isset($_GET['type']) ? (string) $_GET['type'] : null;

		if ($userId === 0) {
			$currentUser = Auth::instance()->user();
			if (!$currentUser || TicketAccess::normalizeRole((string) ($currentUser['role'] ?? '')) !== 'admin') {
				$this->json(['success' => false, 'message' => 'Acesso negado'], 403);
				return;
			}
            try {
                if ($type) {
                    // Histórico global normalizado (um registro por operação, não multiplicado por número de usuários)
                    $history = CreditHistory::getAllHistoryNormalizedForRole($type, 200, 'user');
                    // Resumo global TOTAL (somatório real)
                    $summary = CreditHistory::getGlobalSummary($type, 'user');
                } else {
                    $history = CreditHistory::getAllHistoryNormalizedForRole(null, 200, 'user');
                    $summary = [
                        'ticket' => CreditHistory::getGlobalSummary('ticket', 'user'),
                        'daily' => CreditHistory::getGlobalSummary('daily', 'user'),
                        'project_dailies' => CreditHistory::getGlobalSummary('project_dailies', 'user'),
                    ];
                }
                $this->json(['success' => true, 'history' => $history, 'summary' => $summary]);
            } catch (\Throwable $e) {
                error_log('Erro ao obter historico global de creditos: ' . $e->getMessage());
				$this->json(['success' => false, 'message' => 'Erro ao obter historico global'], 500);
			}
			return;
		}

		if (!$userId) {
			$this->json(['success' => false, 'message' => 'user_id invalido'], 422);
			return;
		}

		// Verificar permissao: usuario so pode ver seu proprio historico, admin pode ver de qualquer um
		$currentUser = Auth::instance()->user();
		if ($currentUser && (int) $currentUser['id'] !== $userId && (string) $currentUser['role'] !== 'admin') {
			$this->json(['success' => false, 'message' => 'Acesso negado'], 403);
			return;
		}

		try {
			$history = CreditHistory::getHistory($userId, $type);
			$summary = [];
			
			if ($type) {
				$summary = CreditHistory::getSummary($userId, $type);
			} else {
				// Se nao especificar tipo, retornar resumo de todos
				$summary = [
					'ticket' => CreditHistory::getSummary($userId, 'ticket'),
					'daily' => CreditHistory::getSummary($userId, 'daily'),
					'project_dailies' => CreditHistory::getSummary($userId, 'project_dailies'),
				];
			}

			$this->json([
				'success' => true,
				'history' => $history,
				'summary' => $summary,
			]);
		} catch (\Throwable $e) {
			error_log('Erro ao obter historico de creditos: ' . $e->getMessage());
			$this->json(['success' => false, 'message' => 'Erro ao obter historico'], 500);
		}
	}

	private function logUserCreateDebug(string $stage, array $data = []): void
	{
		try {
			$logDir = BASE_PATH . '/storage/logs';
			if (!is_dir($logDir)) {
				@mkdir($logDir, 0775, true);
			}
			$logFile = $logDir . '/user_create.log';

			unset($data['password'], $data['password_hash']);

			$entry = [
				'time' => date('c'),
				'stage' => $stage,
				'data' => $data,
				'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
			];
			file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
		} catch (\Throwable $e) {
		}
	}

	public function resetCredits(): void
	{
		$this->requireAuth(['admin']);
		$type = isset($_POST['type']) ? (string) $_POST['type'] : '';
		if (!in_array($type, ['ticket', 'daily', 'project_dailies'], true)) {
			$this->json(['success' => false, 'message' => 'Tipo de credito invalido'], 422);
			return;
		}

		try {
			$pdo = \App\Services\Database::pdo();
			$pdo->beginTransaction();

			// Zerar saldos na tabela users para todos os usuarios ativos
			if ($type === 'ticket') {
				$stmt = $pdo->prepare('UPDATE users SET credits = 0 WHERE active = 1');
			} elseif ($type === 'daily') {
				$stmt = $pdo->prepare('UPDATE users SET daily_credits = 0 WHERE active = 1');
			} else {
				$stmt = $pdo->prepare('UPDATE users SET project_dailies_credits = 0 WHERE active = 1');
			}
			$stmt->execute();

			// Limpar historico de creditos para o tipo informado
			$stmtDel = $pdo->prepare('DELETE FROM credit_history WHERE type = :type');
			$stmtDel->execute([':type' => $type]);

			$pdo->commit();
			$this->json([
				'success' => true,
				'message' => 'Creditos e historico resetados para o tipo ' . $type,
			]);
		} catch (\Throwable $e) {
			if (isset($pdo) && $pdo->inTransaction()) {
				$pdo->rollBack();
			}
			error_log('Erro ao resetar creditos: ' . $e->getMessage());
			$this->json(['success' => false, 'message' => 'Erro ao resetar creditos'], 500);
		}
	}

	public function clearCreditHistory(): void
	{
		$this->requireAuth(['admin']);

		$type = isset($_POST['type']) ? (string) $_POST['type'] : '';
		if ($type !== '' && !in_array($type, ['ticket', 'daily', 'project_dailies'], true)) {
			$this->json(['success' => false, 'message' => 'Tipo de crédito inválido'], 422);
			return;
		}

		try {
			$pdo = \App\Services\Database::pdo();
			$pdo->beginTransaction();

			if ($type === '') {
				$pdo->exec('DELETE FROM credit_history');
			} else {
				$stmtDel = $pdo->prepare('DELETE FROM credit_history WHERE type = :type');
				$stmtDel->execute([':type' => $type]);
			}

			$pdo->commit();
			$this->json([
				'success' => true,
				'message' => $type === '' ? 'Histórico apagado' : ('Histórico apagado para o tipo ' . $type),
			]);
		} catch (\Throwable $e) {
			if (isset($pdo) && $pdo->inTransaction()) {
				$pdo->rollBack();
			}
			error_log('Erro ao apagar histórico de créditos: ' . $e->getMessage());
			$this->json(['success' => false, 'message' => 'Erro ao apagar histórico'], 500);
		}
	}

	private function canAssignRole(?array $actor, string $requestedRole): bool
	{
		if (!$actor) {
			return false;
		}
		$actorRole = TicketAccess::normalizeRole((string) ($actor['role'] ?? ''));
		$targetRole = TicketAccess::normalizeRole($requestedRole);
		if ($actorRole === 'admin') {
			return true;
		}
		if ($actorRole === 'support') {
			return $targetRole !== 'admin';
		}

		return false;
	}
}
