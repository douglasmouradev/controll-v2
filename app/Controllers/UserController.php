<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;
use App\Models\CreditHistory;
use App\Services\Auth;

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
		
		if (!$name || !$email || !$password) {
			$this->json(['success' => false, 'message' => 'Dados obrigatórios faltando'], 422);
			return;
		}
		
		try {
			$id = User::create([
				'name' => $name,
				'email' => $email,
				'password' => $password,
				'role' => $role,
			]);
			$this->json(['success' => true, 'message' => 'Usuário criado', 'id' => $id]);
		} catch (\Throwable $e) {
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
		if (isset($_POST['role'])) $data['role'] = $_POST['role'];
		
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

			// Se id = 0, aplicar ajuste global para TODOS os usuários do tipo 'user'
			if ($id === 0) {
				$creditsByUser = [];
				if ($type === 'daily') {
					$creditsByUser = User::adjustDailyCreditsForUserType('user', $delta);
				} elseif ($type === 'project_dailies') {
					$creditsByUser = User::adjustProjectDailiesCreditsForUserType('user', $delta);
				} else {
					$creditsByUser = User::adjustCreditsForUserType('user', $delta);
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

			// Se for usuario final (user), aplicar o ajuste para TODOS os usuarios desse tipo
			if ($targetRole === 'user') {
				$creditsByUser = [];
				if ($type === 'daily') {
					$creditsByUser = User::adjustDailyCreditsForUserType('user', $delta);
				} elseif ($type === 'project_dailies') {
					$creditsByUser = User::adjustProjectDailiesCreditsForUserType('user', $delta);
				} else {
					$creditsByUser = User::adjustCreditsForUserType('user', $delta);
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

		$ok = User::delete($id);
		$this->json(['success' => $ok, 'message' => $ok ? 'Usuario deletado' : 'Erro ao deletar']);
	}

	public function creditHistory(): void
	{
		$this->requireAuth([]);
		// Aceitar tanto 'user_id' quanto 'id' como parametro
		$userId = (int) ($_GET['user_id'] ?? $_GET['id'] ?? 0);
		$type = isset($_GET['type']) ? (string) $_GET['type'] : null;

		        // id=0 => modo GLOBAL (todos veem o mesmo resumo/historico per-user para o pool de usuários finais)
        if ($userId === 0) {
            try {
                if ($type) {
                    // Histórico global normalizado (um registro por operação, não multiplicado por número de usuários)
                    $history = CreditHistory::getAllHistoryNormalizedForRole($type, 200, 'user');
                    // Resumo global por usuário (não multiplicado pelo número de usuários)
                    $summary = CreditHistory::getGlobalSummaryPerUser($type, 'user');
                } else {
                    $history = CreditHistory::getAllHistoryNormalizedForRole(null, 200, 'user');
                    $summary = [
                        'ticket' => CreditHistory::getGlobalSummaryPerUser('ticket', 'user'),
                        'daily' => CreditHistory::getGlobalSummaryPerUser('daily', 'user'),
                        'project_dailies' => CreditHistory::getGlobalSummaryPerUser('project_dailies', 'user'),
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
}
