<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Models\CreditHistory;
use App\Services\Auth;
use App\Services\TicketAccess;
use App\Services\TicketNotification;

final class TicketController extends Controller
{
	public function index(): void
	{
		$this->requireAuth([]);
		header('Location: /');
	}

	public function show(): void
	{
		$this->requireAuth([]);
		$user = Auth::instance()->user();
		$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
		$ticket = $this->findAuthorizedTicket($id, $user);
		if (!$ticket) {
			$this->json(['success' => false, 'message' => 'Chamado não encontrado'], 404);
			return;
		}
		$this->json(['success' => true, 'ticket' => $ticket]);
	}

	public function create(): void
	{
		try {
			// Garantir que não há output antes
			if (ob_get_level() > 0) {
				ob_clean();
			}

			$this->requireAuth([]);
			$user = Auth::instance()->user();
			if (!$user) {
				$this->json(['success' => false, 'message' => 'Usuário não autenticado'], 401);
				return;
			}

			$data = [
				'title' => trim($_POST['title'] ?? ''),
				'priority' => (string) ($_POST['priority'] ?? ''),
				'category' => (string) ($_POST['category'] ?? ''),
				'qtd' => isset($_POST['qtd']) ? (int) $_POST['qtd'] : 1,
				'name' => (string) ($_POST['name'] ?? ''),
				'registration' => (string) ($_POST['registration'] ?? ''),
				'unit' => (string) ($_POST['unit'] ?? ''),
				'cep' => (string) ($_POST['cep'] ?? ''),
				'address' => (string) ($_POST['address'] ?? ''),
				'address_number' => (string) ($_POST['address_number'] ?? ''),
				'city' => (string) ($_POST['city'] ?? ''),
				'uf' => (string) ($_POST['uf'] ?? ''),
				'description' => (string) ($_POST['description'] ?? ''),
				'technician_name' => !empty($_POST['technician_name']) ? (string) $_POST['technician_name'] : null,
				'technician_rg' => !empty($_POST['technician_rg']) ? (string) $_POST['technician_rg'] : null,
				'technician_cpf' => !empty($_POST['technician_cpf']) ? (string) $_POST['technician_cpf'] : null,
				'internal_order' => !empty($_POST['internal_order']) ? (string) $_POST['internal_order'] : null,
				'invoice' => !empty($_POST['invoice']) ? (string) $_POST['invoice'] : null,
				'project_name' => !empty($_POST['project_name']) ? (string) $_POST['project_name'] : null,
				'project_type' => !empty($_POST['project_type']) ? (string) $_POST['project_type'] : null,
				'daily_destination' => !empty($_POST['daily_destination']) ? (string) $_POST['daily_destination'] : null,
				'service_date' => !empty($_POST['service_date']) ? (string) $_POST['service_date'] : null,
				'service_time' => !empty($_POST['service_time']) ? (string) $_POST['service_time'] : null,
				'daily_rates' => !empty($_POST['daily_rates']) ? (string) $_POST['daily_rates'] : null,
				'external_ticket' => !empty($_POST['external_ticket']) ? (string) $_POST['external_ticket'] : null,
				'assigned_to' => null,
				'logo_path' => null,
				'status' => 'Aberto',
				'user_id' => (int) $user['id'],
			];
			$this->logTicketCreateDebug('request_received', [
				'user_id' => (int) ($user['id'] ?? 0),
				'user_role' => (string) ($user['role'] ?? ''),
				'data' => $data,
			]);

			if ($data['qtd'] < 0) {
				$data['qtd'] = 0;
			}

			if (
				$data['title'] === '' ||
				$data['priority'] === '' ||
				$data['category'] === '' ||
				$data['name'] === '' ||
				$data['unit'] === '' ||
				$data['cep'] === '' ||
				$data['address'] === '' ||
				$data['description'] === ''
			) {
				$this->logTicketCreateDebug('validation_failed_required', [
					'user_id' => (int) ($user['id'] ?? 0),
					'fields' => [
						'title_empty' => $data['title'] === '',
						'priority_empty' => $data['priority'] === '',
						'category_empty' => $data['category'] === '',
						'name_empty' => $data['name'] === '',
						'unit_empty' => $data['unit'] === '',
						'cep_empty' => $data['cep'] === '',
						'address_empty' => $data['address'] === '',
						'description_empty' => $data['description'] === '',
					],
				]);
				$this->json(['success' => false, 'message' => 'Campos obrigatórios faltando'], 422);
				return;
			}

			// Debitar créditos de acordo com a categoria/modalidade do chamado
			$costs = $this->calculateCreditsCost($data);
			$this->logTicketCreateDebug('credits_cost_calculated', [
				'user_id' => (int) ($user['id'] ?? 0),
				'user_role' => (string) ($user['role'] ?? ''),
				'category' => $data['category'],
				'qtd' => $data['qtd'],
				'costs' => $costs,
			]);
			$remainingTicketCredits = null;
			$remainingDailyCredits = null;
			$remainingProjectDailiesCredits = null;
			$role = (string) ($user['role'] ?? 'user');
			// Normalizar papéis vindos do banco/sessão para valores internos
			if ($role === 'usuario') {
				$role = 'user';
			} elseif (in_array($role, ['suporte', 'gerente'], true)) {
				$role = 'support';
			} elseif ($role === 'superadmin') {
				$role = 'admin';
			}
			$usePool = in_array($role, ['user', 'support', 'admin'], true);

			try {
					// Para user/support/admin, debitar do pool para TODOS os tipos (user, admin, support)
					if ($usePool) {
						$rolesPool = ['user', 'admin', 'support'];
						// Créditos de ticket (inclui Ticket e Uso Geral)
						if (!empty($costs['ticket']) && $costs['ticket'] > 0) {
							$this->logTicketCreateDebug('debit_attempt_user_pool_ticket_all_roles', [
								'user_id' => (int) ($user['id'] ?? 0),
								'amount' => $costs['ticket'],
							]);
							$byUser = User::adjustCreditsForRoles($rolesPool, -$costs['ticket']);
							if (empty($byUser) || !isset($byUser[(int) $user['id']])) {
								$this->logTicketCreateDebug('debit_failed_user_pool_ticket_all_roles_no_user', [
									'user_id' => (int) ($user['id'] ?? 0),
									'amount' => $costs['ticket'],
								]);
								$this->json(['success' => false, 'message' => 'Nenhum usuário encontrado para débito de créditos de ticket'], 404);
								return;
							}
							foreach (array_keys($byUser) as $uid) {
								CreditHistory::record(
									$uid,
									'ticket',
									-$costs['ticket'],
									'Crédito utilizado - Ticket criado',
									null,
									'ticket_creation'
								);
							}
							$remainingTicketCredits = $byUser[(int) $user['id']];
							$this->logTicketCreateDebug('debit_success_user_pool_ticket_all_roles', [
								'user_id' => (int) ($user['id'] ?? 0),
								'amount' => $costs['ticket'],
								'new_balance_current_user' => $remainingTicketCredits,
							]);
						}
						// Créditos de diária
						if (!empty($costs['daily']) && $costs['daily'] > 0) {
							$this->logTicketCreateDebug('debit_attempt_user_pool_daily_all_roles', [
								'user_id' => (int) ($user['id'] ?? 0),
								'amount' => $costs['daily'],
							]);
							$byUser = User::adjustDailyCreditsForRoles($rolesPool, -$costs['daily']);
							if (empty($byUser) || !isset($byUser[(int) $user['id']])) {
								$this->logTicketCreateDebug('debit_failed_user_pool_daily_all_roles_no_user', [
									'user_id' => (int) ($user['id'] ?? 0),
									'amount' => $costs['daily'],
								]);
								$this->json(['success' => false, 'message' => 'Nenhum usuário encontrado para débito de créditos de diária'], 404);
								return;
							}
							foreach (array_keys($byUser) as $uid) {
								CreditHistory::record(
									$uid,
									'daily',
									-$costs['daily'],
									'Crédito utilizado - Diária criada',
									null,
									'ticket_creation'
								);
							}
							$remainingDailyCredits = $byUser[(int) $user['id']];
							$this->logTicketCreateDebug('debit_success_user_pool_daily_all_roles', [
								'user_id' => (int) ($user['id'] ?? 0),
								'amount' => $costs['daily'],
								'new_balance_current_user' => $remainingDailyCredits,
							]);
						}
						// Créditos de diárias projeto
						if (!empty($costs['project_dailies']) && $costs['project_dailies'] > 0) {
							$this->logTicketCreateDebug('debit_attempt_user_pool_project_dailies_all_roles', [
								'user_id' => (int) ($user['id'] ?? 0),
								'amount' => $costs['project_dailies'],
							]);
							$byUser = User::adjustProjectDailiesCreditsForRoles($rolesPool, -$costs['project_dailies']);
							if (empty($byUser) || !isset($byUser[(int) $user['id']])) {
								$this->logTicketCreateDebug('debit_failed_user_pool_project_dailies_all_roles_no_user', [
									'user_id' => (int) ($user['id'] ?? 0),
									'amount' => $costs['project_dailies'],
								]);
								$this->json(['success' => false, 'message' => 'Nenhum usuário encontrado para débito de créditos de diárias projeto'], 404);
								return;
							}
							foreach (array_keys($byUser) as $uid) {
								CreditHistory::record(
									$uid,
									'project_dailies',
									-$costs['project_dailies'],
									'Crédito utilizado - Diária Projeto criada',
									null,
									'ticket_creation'
								);
							}
							$remainingProjectDailiesCredits = $byUser[(int) $user['id']];
							$this->logTicketCreateDebug('debit_success_user_pool_project_dailies_all_roles', [
								'user_id' => (int) ($user['id'] ?? 0),
								'amount' => $costs['project_dailies'],
								'new_balance_current_user' => $remainingProjectDailiesCredits,
							]);
						}
					} else {
						// Comportamento antigo: debitar apenas do usuário atual (support, etc.)
						// Créditos de ticket (inclui Ticket e Uso Geral)
						if (!empty($costs['ticket']) && $costs['ticket'] > 0) {
							$this->logTicketCreateDebug('debit_attempt_single_user_ticket', [
								'user_id' => (int) ($user['id'] ?? 0),
								'amount' => $costs['ticket'],
							]);
							$remainingTicketCredits = User::adjustCredits((int) $user['id'], -$costs['ticket']);
							if ($remainingTicketCredits === null) {
								$this->logTicketCreateDebug('debit_failed_single_user_ticket_user_not_found', [
									'user_id' => (int) ($user['id'] ?? 0),
									'amount' => $costs['ticket'],
								]);
								$this->json(['success' => false, 'message' => 'Usuário não encontrado para débito de créditos de ticket'], 404);
								return;
							}
							// Registrar no histórico
							CreditHistory::record(
								(int) $user['id'],
								'ticket',
								-$costs['ticket'],
								'Crédito utilizado - Ticket criado',
								null,
								'ticket_creation'
							);
							$this->logTicketCreateDebug('debit_success_single_user_ticket', [
								'user_id' => (int) ($user['id'] ?? 0),
								'amount' => $costs['ticket'],
								'new_balance' => $remainingTicketCredits,
							]);
						}
						// Créditos de diária
						if (!empty($costs['daily']) && $costs['daily'] > 0) {
							$this->logTicketCreateDebug('debit_attempt_single_user_daily', [
								'user_id' => (int) ($user['id'] ?? 0),
								'amount' => $costs['daily'],
							]);
							$remainingDailyCredits = User::adjustDailyCredits((int) $user['id'], -$costs['daily']);
							if ($remainingDailyCredits === null) {
								$this->logTicketCreateDebug('debit_failed_single_user_daily_user_not_found', [
									'user_id' => (int) ($user['id'] ?? 0),
									'amount' => $costs['daily'],
								]);
								$this->json(['success' => false, 'message' => 'Usuário não encontrado para débito de créditos de diária'], 404);
								return;
							}
							// Registrar no histórico
							CreditHistory::record(
								(int) $user['id'],
								'daily',
								-$costs['daily'],
								'Crédito utilizado - Diária criada',
								null,
								'ticket_creation'
							);
							$this->logTicketCreateDebug('debit_success_single_user_daily', [
								'user_id' => (int) ($user['id'] ?? 0),
								'amount' => $costs['daily'],
								'new_balance' => $remainingDailyCredits,
							]);
						}
						// Créditos de diárias projeto
						if (!empty($costs['project_dailies']) && $costs['project_dailies'] > 0) {
							$this->logTicketCreateDebug('debit_attempt_single_user_project_dailies', [
								'user_id' => (int) ($user['id'] ?? 0),
								'amount' => $costs['project_dailies'],
							]);
							$remainingProjectDailiesCredits = User::adjustProjectDailiesCredits((int) $user['id'], -$costs['project_dailies']);
							if ($remainingProjectDailiesCredits === null) {
								$this->logTicketCreateDebug('debit_failed_single_user_project_dailies_user_not_found', [
									'user_id' => (int) ($user['id'] ?? 0),
									'amount' => $costs['project_dailies'],
								]);
								$this->json(['success' => false, 'message' => 'Usuário não encontrado para débito de créditos de diárias projeto'], 404);
								return;
							}
							// Registrar no histórico
							CreditHistory::record(
								(int) $user['id'],
								'project_dailies',
								-$costs['project_dailies'],
								'Crédito utilizado - Diária Projeto criada',
								null,
								'ticket_creation'
							);
							$this->logTicketCreateDebug('debit_success_single_user_project_dailies', [
								'user_id' => (int) ($user['id'] ?? 0),
								'amount' => $costs['project_dailies'],
								'new_balance' => $remainingProjectDailiesCredits,
							]);
						}
					}
				} catch (\RuntimeException $e) {
					$this->logTicketCreateDebug('debit_runtime_exception', [
						'user_id' => (int) ($user['id'] ?? 0),
						'category' => $data['category'] ?? null,
						'qtd' => $data['qtd'] ?? null,
						'costs' => $costs ?? null,
						'exception_message' => $e->getMessage(),
						'exception_class' => get_class($e),
					]);
					$this->json(['success' => false, 'message' => $e->getMessage()], 422);
					return;
			} catch (\Throwable $e) {
				error_log('Erro ao debitar créditos: ' . $e->getMessage());
				$this->logTicketCreateDebug('debit_unexpected_exception', [
					'user_id' => (int) ($user['id'] ?? 0),
					'category' => $data['category'] ?? null,
					'qtd' => $data['qtd'] ?? null,
					'costs' => $costs ?? null,
					'exception_message' => $e->getMessage(),
					'exception_class' => get_class($e),
				]);
				$this->json(['success' => false, 'message' => 'Erro ao debitar créditos'], 500);
				return;
			}

			$id = Ticket::create($data);
			$this->logTicketCreateDebug('ticket_created', [
				'user_id' => (int) ($user['id'] ?? 0),
				'ticket_id' => (int) $id,
				'category' => $data['category'],
				'qtd' => $data['qtd'],
				'costs' => $costs,
				'remaining_ticket_credits' => $remainingTicketCredits,
				'remaining_daily_credits' => $remainingDailyCredits,
				'remaining_project_dailies_credits' => $remainingProjectDailiesCredits,
			]);
			// Processar anexos enviados na abertura do chamado (attachments[])
			$this->handleTicketAttachmentsUpload((int) $id, 'attachments');
			try {
				TicketNotification::notifyTicketOpened((int) $id, $data, $user);
			} catch (\Throwable $e) {
				error_log('Erro ao notificar abertura de chamado por e-mail: ' . $e->getMessage());
			}
			$this->json([
				'success' => true,
				'message' => 'Chamado aberto',
				'id' => $id,
				'remaining_ticket_credits' => $remainingTicketCredits,
				'remaining_daily_credits' => $remainingDailyCredits,
			]);
		} catch (\Throwable $e) {
			error_log('Erro ao criar chamado: ' . $e->getMessage());
			$this->logTicketCreateDebug('create_unexpected_exception', [
				'user_id' => isset($user['id']) ? (int) $user['id'] : null,
				'exception_message' => $e->getMessage(),
				'exception_class' => get_class($e),
			]);
			$this->json(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()], 500);
		}
	}

	public function update(): void
	{
		try {
			// Garantir que não há output antes
			if (ob_get_level() > 0) {
				ob_clean();
			}

			$this->requireAuth([]);
			$user = Auth::instance()->user();
			if (!$user) {
				$this->logTicketUpdateDebug('request_received_unauthenticated', [
					'post' => $_POST,
				]);
				$this->json(['success' => false, 'message' => 'Usuário não autenticado'], 401);
				return;
			}

			$this->logTicketUpdateDebug('request_received', [
				'user_id' => (int) ($user['id'] ?? 0),
				'user_role' => (string) ($user['role'] ?? ''),
				'post' => $_POST,
			]);

			$ticketId = (int) ($_POST['ticket_id'] ?? $_POST['id'] ?? 0);
			if ($ticketId <= 0) {
				$this->logTicketUpdateDebug('validation_failed_id', [
					'user_id' => (int) ($user['id'] ?? 0),
					'user_role' => (string) ($user['role'] ?? ''),
					'post' => $_POST,
				]);
				$this->json(['success' => false, 'message' => 'ID do chamado inválido'], 422);
				return;
			}
			
			$existing = $this->findAuthorizedTicket($ticketId, $user);
			if (!$existing) {
				$this->logTicketUpdateDebug('ticket_not_found', [
					'ticket_id' => $ticketId,
					'user_id' => (int) ($user['id'] ?? 0),
					'user_role' => (string) ($user['role'] ?? ''),
				]);
				$this->json(['success' => false, 'message' => 'Chamado não encontrado'], 404);
				return;
			}

			$role = TicketAccess::normalizeRole((string) ($user['role'] ?? 'user'));
			$isOwner = (int)($existing['user_id'] ?? 0) === (int)$user['id'];
			if ($role === 'user' && !$isOwner) {
				$this->logTicketUpdateDebug('permission_denied', [
					'ticket_id' => $ticketId,
					'user_id' => (int) ($user['id'] ?? 0),
					'user_role' => $role,
					'ticket_user_id' => (int) ($existing['user_id'] ?? 0),
				]);
				$this->json(['success' => false, 'message' => 'Você não tem permissão para editar este chamado'], 403);
				return;
			}

			$hasQtdColumn = Ticket::hasQtdColumn();
			$existingQtd = 1;
			if ($hasQtdColumn && isset($existing['qtd'])) {
				$existingQtd = (int) $existing['qtd'];
				if ($existingQtd < 0) {
					$existingQtd = 0;
				}
			}

			$data = [
				'title' => trim($_POST['title'] ?? ''),
				'priority' => (string) ($_POST['priority'] ?? ''),
				'category' => (string) ($_POST['category'] ?? ''),
				'name' => (string) ($_POST['name'] ?? ''),
				'registration' => (string) ($_POST['registration'] ?? ''),
				'unit' => (string) ($_POST['unit'] ?? ''),
				'cep' => (string) ($_POST['cep'] ?? ''),
				'address' => (string) ($_POST['address'] ?? ''),
				'address_number' => (string) ($_POST['address_number'] ?? ''),
				'city' => (string) ($_POST['city'] ?? ''),
				'uf' => (string) ($_POST['uf'] ?? ''),
				'description' => (string) ($_POST['description'] ?? ''),
				'technician_name' => !empty($_POST['technician_name']) ? (string) $_POST['technician_name'] : null,
				'technician_rg' => !empty($_POST['technician_rg']) ? (string) $_POST['technician_rg'] : null,
				'technician_cpf' => !empty($_POST['technician_cpf']) ? (string) $_POST['technician_cpf'] : null,
				'internal_order' => !empty($_POST['internal_order']) ? (string) $_POST['internal_order'] : null,
				'invoice' => !empty($_POST['invoice']) ? (string) $_POST['invoice'] : null,
				'project_name' => !empty($_POST['project_name']) ? (string) $_POST['project_name'] : null,
				'project_type' => !empty($_POST['project_type']) ? (string) $_POST['project_type'] : null,
				'daily_destination' => !empty($_POST['daily_destination']) ? (string) $_POST['daily_destination'] : null,
				'service_date' => !empty($_POST['service_date']) ? (string) $_POST['service_date'] : null,
				'service_time' => !empty($_POST['service_time']) ? (string) $_POST['service_time'] : null,
				'daily_rates' => !empty($_POST['daily_rates']) ? (string) $_POST['daily_rates'] : null,
				'external_ticket' => !empty($_POST['external_ticket']) ? (string) $_POST['external_ticket'] : null,
			];

			if ($hasQtdColumn) {
				$qtd = isset($_POST['qtd']) ? (int) $_POST['qtd'] : $existingQtd;
				if ($qtd < 0) {
					$qtd = 0;
				}
				if ($qtd < $existingQtd) {
					$qtd = $existingQtd;
				}
				$data['qtd'] = $qtd;
			}

			$this->logTicketUpdateDebug('prepared_update_data', [
				'ticket_id' => $ticketId,
				'user_id' => (int) ($user['id'] ?? 0),
				'user_role' => $role,
				'has_qtd_column' => $hasQtdColumn,
				'existing_qtd' => $existingQtd,
				'new_qtd' => $hasQtdColumn ? ($data['qtd'] ?? null) : null,
				'data' => $data,
			]);
			
			if (
				$data['title'] === '' ||
				$data['priority'] === '' ||
				$data['category'] === '' ||
				$data['name'] === '' ||
				$data['unit'] === '' ||
				$data['cep'] === '' ||
				$data['address'] === '' ||
				$data['description'] === ''
			) {
				$this->logTicketUpdateDebug('validation_failed_required', [
					'ticket_id' => $ticketId,
					'user_id' => (int) ($user['id'] ?? 0),
					'fields' => [
						'title_empty' => $data['title'] === '',
						'priority_empty' => $data['priority'] === '',
						'category_empty' => $data['category'] === '',
						'name_empty' => $data['name'] === '',
						'unit_empty' => $data['unit'] === '',
						'cep_empty' => $data['cep'] === '',
						'address_empty' => $data['address'] === '',
						'description_empty' => $data['description'] === '',
					],
				]);
				$this->json(['success' => false, 'message' => 'Campos obrigatórios faltando'], 422);
				return;
			}

			if ($hasQtdColumn) {
				$deltaQtd = max(0, (int) ($data['qtd'] ?? $existingQtd) - $existingQtd);
				// Sempre que a QTD aumentar, independentemente do papel (inclusive admin),
				// devemos debitar créditos proporcionais ao aumento.
				if ($deltaQtd > 0) {
					$costs = $this->calculateCreditsCost([
						'category' => $data['category'],
						'qtd' => $deltaQtd,
					]);
					$this->logTicketUpdateDebug('credits_cost_calculated_update', [
						'ticket_id' => $ticketId,
						'user_id' => (int) ($user['id'] ?? 0),
						'user_role' => $role,
						'category' => $data['category'],
						'delta_qtd' => $deltaQtd,
						'costs' => $costs,
					]);

					try {
						if ($role === 'user') {
							$rolesPool = ['user', 'admin', 'support'];
							if (!empty($costs['ticket']) && $costs['ticket'] > 0) {
								$byUser = User::adjustCreditsForRoles($rolesPool, -$costs['ticket']);
								if (empty($byUser) || !isset($byUser[(int) $user['id']])) {
									$this->json(['success' => false, 'message' => 'Nenhum usuário encontrado para débito de créditos de ticket'], 404);
									return;
								}
								foreach (array_keys($byUser) as $uid) {
									CreditHistory::record(
										$uid,
										'ticket',
										-$costs['ticket'],
										'Crédito utilizado - Aumento de QTD em chamado',
										$ticketId,
										'ticket_qtd_increase'
									);
								}
							}
							if (!empty($costs['daily']) && $costs['daily'] > 0) {
								$byUser = User::adjustDailyCreditsForRoles($rolesPool, -$costs['daily']);
								if (empty($byUser) || !isset($byUser[(int) $user['id']])) {
									$this->json(['success' => false, 'message' => 'Nenhum usuário encontrado para débito de créditos de diária'], 404);
									return;
								}
								foreach (array_keys($byUser) as $uid) {
									CreditHistory::record(
										$uid,
										'daily',
										-$costs['daily'],
										'Crédito utilizado - Aumento de QTD de Diária em chamado',
										$ticketId,
										'ticket_qtd_increase'
									);
								}
							}
							if (!empty($costs['project_dailies']) && $costs['project_dailies'] > 0) {
								$byUser = User::adjustProjectDailiesCreditsForRoles($rolesPool, -$costs['project_dailies']);
								if (empty($byUser) || !isset($byUser[(int) $user['id']])) {
									$this->json(['success' => false, 'message' => 'Nenhum usuário encontrado para débito de créditos de diárias projeto'], 404);
									return;
								}
								foreach (array_keys($byUser) as $uid) {
									CreditHistory::record(
										$uid,
										'project_dailies',
										-$costs['project_dailies'],
										'Crédito utilizado - Aumento de QTD de Diária Projeto em chamado',
										$ticketId,
										'ticket_qtd_increase'
									);
								}
							}
						} else {
							if (!empty($costs['ticket']) && $costs['ticket'] > 0) {
								$remainingTicketCredits = User::adjustCredits((int) $user['id'], -$costs['ticket']);
								if ($remainingTicketCredits === null) {
									$this->json(['success' => false, 'message' => 'Usuário não encontrado para débito de créditos de ticket'], 404);
									return;
								}
								CreditHistory::record(
									(int) $user['id'],
									'ticket',
									-$costs['ticket'],
									'Crédito utilizado - Aumento de QTD em chamado',
									$ticketId,
									'ticket_qtd_increase'
								);
							}
							if (!empty($costs['daily']) && $costs['daily'] > 0) {
								$remainingDailyCredits = User::adjustDailyCredits((int) $user['id'], -$costs['daily']);
								if ($remainingDailyCredits === null) {
									$this->json(['success' => false, 'message' => 'Usuário não encontrado para débito de créditos de diária'], 404);
									return;
								}
								CreditHistory::record(
									(int) $user['id'],
									'daily',
									-$costs['daily'],
									'Crédito utilizado - Aumento de QTD de Diária em chamado',
									$ticketId,
									'ticket_qtd_increase'
								);
							}
							if (!empty($costs['project_dailies']) && $costs['project_dailies'] > 0) {
								$remainingProjectDailiesCredits = User::adjustProjectDailiesCredits((int) $user['id'], -$costs['project_dailies']);
								if ($remainingProjectDailiesCredits === null) {
									$this->json(['success' => false, 'message' => 'Usuário não encontrado para débito de créditos de diárias projeto'], 404);
									return;
								}
								CreditHistory::record(
									(int) $user['id'],
									'project_dailies',
									-$costs['project_dailies'],
									'Crédito utilizado - Aumento de QTD de Diária Projeto em chamado',
									$ticketId,
									'ticket_qtd_increase'
								);
							}
						}
					} catch (\RuntimeException $e) {
						$this->logTicketUpdateDebug('debit_runtime_exception_update', [
							'ticket_id' => $ticketId,
							'user_id' => (int) ($user['id'] ?? 0),
							'user_role' => $role,
							'category' => $data['category'] ?? null,
							'delta_qtd' => $deltaQtd,
							'costs' => $costs ?? null,
							'exception_message' => $e->getMessage(),
							'exception_class' => get_class($e),
						]);
						$this->json(['success' => false, 'message' => $e->getMessage()], 422);
						return;
					} catch (\Throwable $e) {
						error_log('Erro ao debitar créditos em edição de chamado: ' . $e->getMessage());
						$this->logTicketUpdateDebug('debit_unexpected_exception_update', [
							'ticket_id' => $ticketId,
							'user_id' => (int) ($user['id'] ?? 0),
							'user_role' => $role,
							'category' => $data['category'] ?? null,
							'delta_qtd' => $deltaQtd,
							'costs' => $costs ?? null,
							'exception_message' => $e->getMessage(),
							'exception_class' => get_class($e),
						]);
						$this->json(['success' => false, 'message' => 'Erro ao debitar créditos na edição do chamado'], 500);
						return;
					}
				}
			}
			
			$ok = Ticket::updateTicket($ticketId, $data);
			$this->logTicketUpdateDebug('update_persist_result', [
				'ticket_id' => $ticketId,
				'user_id' => (int) ($user['id'] ?? 0),
				'ok' => $ok,
			]);
			if (!$ok) {
				$this->json(['success' => false, 'message' => 'Nenhuma alteração realizada no chamado'], 422);
				return;
			}
			// Processar anexos adicionais enviados na edição (attachments[])
			$this->handleTicketAttachmentsUpload($ticketId, 'attachments');

			$this->json([
				'success' => true,
				'message' => 'Chamado atualizado com sucesso',
				'id' => $ticketId,
			]);
		} catch (\Throwable $e) {
			error_log('Erro ao atualizar chamado: ' . $e->getMessage());
			$this->logTicketUpdateDebug('update_unexpected_exception', [
				'user_id' => (isset($user) && isset($user['id'])) ? (int) $user['id'] : null,
				'exception_message' => $e->getMessage(),
				'exception_class' => get_class($e),
			]);
			$this->json(['success' => false, 'message' => 'Erro ao atualizar chamado'], 500);
		}
	}

	/**
	 * Clonar um chamado existente, debitando créditos como se fosse um novo chamado.
	 *
	 * Endpoint esperado (pela interface atual): GET /tickets/clone?id=123
	 */
	public function cloneTicket(): void
	{
		$this->requireAuth(['support', 'admin']);
		$user = Auth::instance()->user();
		if (!$user) {
			$this->json(['success' => false, 'message' => 'Usuário não autenticado'], 401);
			return;
		}

		$id = (int) ($_GET['id'] ?? 0);
		if ($id <= 0) {
			$this->json(['success' => false, 'message' => 'ID do chamado inválido'], 422);
			return;
		}

		$existing = $this->findAuthorizedTicket($id, $user);
		if (!$existing) {
			$this->json(['success' => false, 'message' => 'Chamado não encontrado'], 404);
			return;
		}

		// Montar os dados para o novo chamado reutilizando os campos principais.
		// Regra pedida: ao clonar, SEMPRE debitar do crédito de TICKET (mesmo sem saldo, ficando negativo).
		$qtd = (int) ($existing['qtd'] ?? 1);
		if ($qtd <= 0) {
			$qtd = 1;
		}
		$rawTitle = 'Chamado Clonado - ' . ($existing['title'] ?? '');
		$title = mb_substr($rawTitle, 0, 250);
		$data = [
			'title' => $title,
			'description' => $existing['description'] ?? '',
			'priority' => $existing['priority'] ?? 'Média',
			'category' => 'Ticket',
			'name' => $existing['name'] ?? ($existing['user_name'] ?? ''),
			'registration' => $existing['registration'] ?? '',
			'unit' => $existing['unit'] ?? '',
			'cep' => $existing['cep'] ?? '',
			'address' => $existing['address'] ?? '',
			'address_number' => $existing['address_number'] ?? '',
			'city' => $existing['city'] ?? '',
			'uf' => $existing['uf'] ?? '',
			'internal_order' => $existing['internal_order'] ?? '',
			'invoice' => $existing['invoice'] ?? '',
			'daily_destination' => $existing['daily_destination'] ?? '',
			'project_name' => $existing['project_name'] ?? '',
			'project_type' => $existing['project_type'] ?? '',
			'technician_name' => $existing['technician_name'] ?? '',
			'technician_rg' => $existing['technician_rg'] ?? '',
			'technician_cpf' => $existing['technician_cpf'] ?? '',
			'service_date' => $existing['service_date'] ?? null,
			'service_time' => $existing['service_time'] ?? null,
			'qtd' => $qtd,
			'user_id' => (int) ($user['id'] ?? 0),
			'status' => 'Aberto',
		];

		$pdo = \App\Services\Database::pdo();
		$pdo->beginTransaction();
		try {
			$newId = Ticket::create($data);

			$costs = $this->calculateCreditsCost(['category' => 'Ticket', 'qtd' => $qtd]);
			$rolesPool = ['user', 'admin', 'support'];
			$byUser = \App\Models\User::adjustCreditsForRolesAllowNegative($rolesPool, -$costs['ticket']);

			foreach (array_keys($byUser) as $uid) {
				\App\Models\CreditHistory::record(
					(int) $uid,
					'ticket',
					-$costs['ticket'],
					'Crédito utilizado - Chamado clonado',
					(int) $newId,
					'ticket_clone',
					isset($user['id']) ? (int) $user['id'] : null
				);
			}

			$pdo->commit();
			$this->json([
				'success' => true,
				'message' => 'Chamado clonado',
				'id' => $newId,
			]);
		} catch (\Throwable $e) {
			if ($pdo->inTransaction()) {
				$pdo->rollBack();
			}
			$errorMsg = $e->getMessage();
			error_log('Erro ao clonar chamado: ' . $errorMsg);
			$this->json([
				'success' => false,
				'message' => APP_DEBUG ? ('Erro ao clonar chamado: ' . $errorMsg) : 'Erro ao clonar chamado',
			], 500);
		}
	}

	public function updateStatus(): void
	{
		$this->requireAuth(['support', 'admin']);
		$id = (int) ($_POST['id'] ?? 0);
		$status = (string) ($_POST['status'] ?? '');
		if (!$id || !in_array($status, ['Aberto', 'Em andamento', 'Fechado'], true)) {
			$this->json(['success' => false, 'message' => 'Parâmetros inválidos'], 422);
			return;
		}
		$ok = Ticket::updateStatus($id, $status);
		$this->json(['success' => $ok, 'message' => $ok ? 'Status atualizado' : 'Falha ao atualizar']);
	}

	public function assignToMe(): void
	{
		$this->requireAuth(['support', 'admin']);
		$user = Auth::instance()->user();
		$id = (int) ($_POST['id'] ?? 0);
		if (!$id) {
			$this->json(['success' => false, 'message' => 'Parâmetros inválidos'], 422);
			return;
		}
		$ok = Ticket::assignTo((int) $id, (int) $user['id']);
		$this->json(['success' => $ok, 'message' => $ok ? 'Chamado atribuído' : 'Falha ao atribuir']);
	}

	public function saveResponse(): void
	{
		$this->requireAuth(['support', 'admin']);
		$id = (int) ($_POST['id'] ?? 0);
		$response = trim($_POST['response'] ?? '');
		
		if (!$id) {
			$this->json(['success' => false, 'message' => 'ID inválido'], 422);
			return;
		}

		$responseUpdated = Ticket::updateResponse($id, $response);
		
		// Processar upload de anexos da resposta (imagem/PDF)
		$uploadedAttachments = [];
		if (!empty($_FILES['images']) && is_array($_FILES['images']['name'])) {
			$uploadDir = BASE_PATH . '/public/uploads/tickets/';
			if (!is_dir($uploadDir)) {
				mkdir($uploadDir, 0755, true);
			}
			
			$user = Auth::instance()->user();
			$files = $_FILES['images'];
			$fileCount = count($files['name']);
			
			for ($i = 0; $i < $fileCount; $i++) {
				if ($files['error'][$i] !== UPLOAD_ERR_OK) {
					continue;
				}
				
				$fileName = $files['name'][$i];
				$fileTmp = $files['tmp_name'][$i];
				$fileType = $files['type'][$i];
				$fileSize = $files['size'][$i];
				$ext = strtolower((string) pathinfo((string) $fileName, PATHINFO_EXTENSION));
				$allowedImageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
				$isImage = strpos((string) $fileType, 'image/') === 0 || in_array($ext, $allowedImageExts, true);
				$isPdf = $fileType === 'application/pdf' || $ext === 'pdf';
				if (!$isImage && !$isPdf) {
					continue;
				}
				if ($ext === '') {
					$ext = $isPdf ? 'pdf' : 'bin';
				}

				$resolvedType = (string) $fileType;
				if ($resolvedType === '') {
					if ($isPdf) {
						$resolvedType = 'application/pdf';
					} elseif ($isImage) {
						if (in_array($ext, ['jpg', 'jpeg'], true)) {
							$resolvedType = 'image/jpeg';
						} elseif ($ext === 'png') {
							$resolvedType = 'image/png';
						} elseif ($ext === 'gif') {
							$resolvedType = 'image/gif';
						} elseif ($ext === 'webp') {
							$resolvedType = 'image/webp';
						} else {
							$resolvedType = 'image/*';
						}
					} else {
						$resolvedType = 'application/octet-stream';
					}
				}
				
				$newFileName = 'ticket_' . $id . '_' . time() . '_' . $i . '.' . $ext;
				$filePath = $uploadDir . $newFileName;
				
				if (move_uploaded_file($fileTmp, $filePath)) {
					$attachmentId = TicketAttachment::create([
						'ticket_id' => $id,
						'file_path' => '/uploads/tickets/' . $newFileName,
						'file_name' => $fileName,
						'file_type' => $resolvedType,
						'file_size' => $fileSize,
						'uploaded_by' => (int) $user['id'],
					]);
					$uploadedAttachments[] = [
						'id' => $attachmentId,
						'file_path' => '/uploads/tickets/' . $newFileName,
						'file_name' => $fileName,
					];
				}
			}
		}
		
		$hasUploadedAttachments = count($uploadedAttachments) > 0;
		$success = $responseUpdated || $hasUploadedAttachments;
		$message = $success ? 'Resposta salva com sucesso' : 'Falha ao salvar resposta';
		if (!$responseUpdated && $hasUploadedAttachments) {
			$message = 'Anexos salvos com sucesso';
		}
		
		$this->json([
			'success' => $success,
			'message' => $message,
			'attachments' => $uploadedAttachments
		]);
	}

	public function updateTechnician(): void
	{
		$this->requireAuth(['support', 'admin']);
		$id = (int) ($_POST['id'] ?? 0);
		if ($id <= 0) {
			$this->json(['success' => false, 'message' => 'ID do chamado inválido'], 422);
			return;
		}

		$user = Auth::instance()->user();
		$ticket = $this->findAuthorizedTicket($id, $user);
		if (!$ticket) {
			$this->json(['success' => false, 'message' => 'Chamado não encontrado'], 404);
			return;
		}

		$data = [
			'technician_name' => isset($_POST['technician_name']) && $_POST['technician_name'] !== '' ? (string) $_POST['technician_name'] : null,
			'technician_rg' => isset($_POST['technician_rg']) && $_POST['technician_rg'] !== '' ? (string) $_POST['technician_rg'] : null,
			'technician_cpf' => isset($_POST['technician_cpf']) && $_POST['technician_cpf'] !== '' ? (string) $_POST['technician_cpf'] : null,
		];

		$ok = Ticket::updateTicket($id, $data);
		if (!$ok) {
			$this->json(['success' => false, 'message' => 'Nenhuma alteração realizada nos dados do técnico'], 422);
			return;
		}

		$this->json([
			'success' => true,
			'message' => 'Dados do técnico atualizados com sucesso',
			'id' => $id,
		]);
	}

	public function attachments(): void
	{
		$this->requireAuth([]);
		$user = Auth::instance()->user();
		$id = (int) ($_GET['id'] ?? 0);
		if (!$id) {
			$this->json(['success' => false, 'message' => 'ID inválido'], 422);
			return;
		}
		if (!$this->findAuthorizedTicket($id, $user)) {
			$this->json(['success' => false, 'message' => 'Chamado não encontrado'], 404);
			return;
		}

		$attachments = TicketAttachment::findByTicket($id);
		foreach ($attachments as &$att) {
			$att['download_url'] = '/tickets/attachment-download?id=' . (int) ($att['id'] ?? 0);
		}
		unset($att);
		$this->json(['success' => true, 'attachments' => $attachments]);
	}

	public function downloadAttachment(): void
	{
		$this->requireAuth([]);
		$user = Auth::instance()->user();
		$attachmentId = (int) ($_GET['id'] ?? 0);
		if ($attachmentId <= 0) {
			http_response_code(422);
			echo 'Anexo inválido';
			return;
		}

		$attachment = TicketAttachment::find($attachmentId);
		if (!$attachment) {
			http_response_code(404);
			echo 'Anexo não encontrado';
			return;
		}

		$ticketId = (int) ($attachment['ticket_id'] ?? 0);
		if (!$this->findAuthorizedTicket($ticketId, $user)) {
			http_response_code(403);
			echo 'Acesso negado';
			return;
		}

		$webPath = (string) ($attachment['file_path'] ?? '');
		$basePath = BASE_PATH . '/public';
		$fsPath = $webPath !== '' && $webPath[0] === '/'
			? $basePath . $webPath
			: $basePath . '/' . ltrim($webPath, '/');

		if (!is_file($fsPath) || !is_readable($fsPath)) {
			http_response_code(404);
			echo 'Arquivo não encontrado';
			return;
		}

		$mime = (string) ($attachment['file_type'] ?? '');
		if ($mime === '' || $mime === 'application/octet-stream') {
			$mime = mime_content_type($fsPath) ?: 'application/octet-stream';
		}

		header('Content-Type: ' . $mime);
		header('Content-Disposition: inline; filename="' . basename((string) ($attachment['file_name'] ?? 'anexo')) . '"');
		header('Content-Length: ' . (string) filesize($fsPath));
		header('Cache-Control: private, no-store');
		readfile($fsPath);
		exit;
	}

	public function deleteAttachment(): void
	{
		$this->requireAuth([]);
		$user = Auth::instance()->user();
		if (!$user) {
			$this->json(['success' => false, 'message' => 'Usuário não autenticado'], 401);
			return;
		}
		$attachmentId = (int) ($_POST['id'] ?? 0);
		if ($attachmentId <= 0) {
			$this->json(['success' => false, 'message' => 'ID de anexo inválido'], 422);
			return;
		}
		$attachment = TicketAttachment::find($attachmentId);
		if (!$attachment) {
			$this->json(['success' => false, 'message' => 'Anexo não encontrado'], 404);
			return;
		}
		$ticketId = (int) ($attachment['ticket_id'] ?? 0);
		if ($ticketId <= 0) {
			$this->json(['success' => false, 'message' => 'Chamado associado ao anexo não encontrado'], 404);
			return;
		}
		$ticket = $this->findAuthorizedTicket($ticketId, $user);
		if (!$ticket) {
			$this->json(['success' => false, 'message' => 'Chamado associado ao anexo não encontrado'], 404);
			return;
		}
		$normalizedRole = TicketAccess::normalizeRole((string) ($user['role'] ?? ''));
		$isOwner = (int) ($ticket['user_id'] ?? 0) === (int) ($user['id'] ?? 0);
		if (!$isOwner && !in_array($normalizedRole, ['support', 'admin'], true)) {
			$this->json(['success' => false, 'message' => 'Você não tem permissão para excluir este anexo'], 403);
			return;
		}
		$ok = TicketAttachment::delete($attachmentId);
		$this->json([
			'success' => $ok,
			'message' => $ok ? 'Anexo excluído com sucesso' : 'Falha ao excluir anexo',
			'id' => $attachmentId,
		]);
	}

	public function delete(): void
	{
		$this->requireAuth(['support', 'admin']);
		$id = (int) ($_POST['id'] ?? 0);
		if (!$id) {
			$this->json(['success' => false, 'message' => 'ID inválido'], 422);
			return;
		}

		try {
			$ok = Ticket::delete($id);
			$this->json([
				'success' => $ok,
				'message' => $ok ? 'Chamado excluído com sucesso' : 'Falha ao excluir chamado',
			]);
		} catch (\Throwable $e) {
			error_log('Erro ao excluir ticket: ' . $e->getMessage());
			$this->json(['success' => false, 'message' => 'Erro ao excluir chamado'], 500);
		}
	}

	private function logTicketCreateDebug(string $stage, array $data = []): void
	{
		try {
			$logDir = BASE_PATH . '/storage/logs';
			if (!is_dir($logDir)) {
				@mkdir($logDir, 0775, true);
			}
			$logFile = $logDir . '/ticket_create.log';

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

	private function logTicketUpdateDebug(string $stage, array $data = []): void
	{
		try {
			$logDir = BASE_PATH . '/storage/logs';
			if (!is_dir($logDir)) {
				@mkdir($logDir, 0775, true);
			}
			$logFile = $logDir . '/ticket_update.log';

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

	/**
	 * Processa upload de anexos de chamado (imagens/PDF) para um ticket específico.
	 */
	private function handleTicketAttachmentsUpload(int $ticketId, string $filesKey): void
	{
		if (empty($_FILES[$filesKey]) || !is_array($_FILES[$filesKey]['name'])) {
			return;
		}
		$files = $_FILES[$filesKey];
		$uploadDir = BASE_PATH . '/public/uploads/tickets/';
		if (!is_dir($uploadDir)) {
			@mkdir($uploadDir, 0755, true);
		}
		$user = Auth::instance()->user();
		$maxFiles = 20;
		$maxSize = 40 * 1024 * 1024; // 40MB
		$fileCount = count($files['name']);
		$processed = 0;
		for ($i = 0; $i < $fileCount && $processed < $maxFiles; $i++) {
			if ($files['error'][$i] !== UPLOAD_ERR_OK) {
				continue;
			}
			$fileName = (string) $files['name'][$i];
			$fileTmp = (string) $files['tmp_name'][$i];
			$fileType = (string) ($files['type'][$i] ?? '');
			$fileSize = (int) ($files['size'][$i] ?? 0);
			if ($fileSize <= 0 || $fileSize > $maxSize) {
				continue;
			}
			if (function_exists('finfo_open')) {
				$finfo = finfo_open(FILEINFO_MIME_TYPE);
				$detectedMime = $finfo ? (string) finfo_file($finfo, $fileTmp) : '';
				if ($finfo) {
					finfo_close($finfo);
				}
				$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
				if ($detectedMime !== '' && !in_array($detectedMime, $allowedMimes, true)) {
					continue;
				}
			}
			$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
			$imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
			$isImage = strpos($fileType, 'image/') === 0 || in_array($ext, $imageExts, true);
			$isPdf = $fileType === 'application/pdf' || $ext === 'pdf';
			if (!$isImage && !$isPdf) {
				continue;
			}
			if ($ext === '') {
				$ext = $isPdf ? 'pdf' : 'bin';
			}
			$resolvedType = $fileType;
			if ($resolvedType === '') {
				if ($isPdf) {
					$resolvedType = 'application/pdf';
				} elseif ($isImage) {
					if (in_array($ext, ['jpg', 'jpeg'], true)) {
						$resolvedType = 'image/jpeg';
					} elseif ($ext === 'png') {
						$resolvedType = 'image/png';
					} elseif ($ext === 'gif') {
						$resolvedType = 'image/gif';
					} elseif ($ext === 'webp') {
						$resolvedType = 'image/webp';
					} else {
						$resolvedType = 'image/*';
					}
				} else {
					$resolvedType = 'application/octet-stream';
				}
			}
			$newFileName = 'ticket_' . $ticketId . '_' . time() . '_' . $i . '.' . $ext;
			$filePath = $uploadDir . $newFileName;
			if (!@move_uploaded_file($fileTmp, $filePath)) {
				continue;
			}
			try {
				TicketAttachment::create([
					'ticket_id' => $ticketId,
					'file_path' => '/uploads/tickets/' . $newFileName,
					'file_name' => $fileName,
					'file_type' => $resolvedType,
					'file_size' => $fileSize,
					'uploaded_by' => (int) ($user['id'] ?? 0),
				]);
				$processed++;
			} catch (\Throwable $e) {
				error_log('Erro ao salvar anexo de ticket: ' . $e->getMessage());
			}
		}
	}

	private function findAuthorizedTicket(int $id, ?array $user): ?array
	{
		if ($id <= 0 || !$user) {
			return null;
		}
		$ticket = Ticket::find($id, $user);
		if (!$ticket || !TicketAccess::canAccess($user, $ticket)) {
			return null;
		}

		return $ticket;
	}

	private function calculateCreditsCost(array $data): array
	{
		$category = trim((string)($data['category'] ?? ''));
		$quantity = isset($data['qtd']) ? (int) $data['qtd'] : 1;
		if ($quantity < 0) {
			$quantity = 0;
		}
		$costs = [
			'ticket' => 0,
			'daily' => 0,
			'project_dailies' => 0,
		];

		if ($quantity === 0) {
			return $costs;
		}

		if ($category === 'Diária' || $category === 'Uso Geral') {
			// Chamados de Diária e Uso Geral debitam do saldo de diárias
			$costs['daily'] = $quantity;
		} elseif ($category === 'Projeto') {
			// Chamados de projeto debitam do saldo de diárias projeto
			$costs['project_dailies'] = $quantity;
		} elseif ($category === 'Ticket') {
			// Chamados de Ticket debitam do saldo de tickets
			$costs['ticket'] = $quantity;
		}

		return $costs;
	}
}


