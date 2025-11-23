<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Models\CreditHistory;
use App\Services\Auth;

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
		$id = (int) ($_GET['id'] ?? 0);
		$ticket = $id > 0 ? Ticket::find($id) : null;
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
				'name' => (string) ($_POST['name'] ?? ''),
				'registration' => (string) ($_POST['registration'] ?? ''),
				'unit' => (string) ($_POST['unit'] ?? ''),
				'cep' => (string) ($_POST['cep'] ?? ''),
				'address' => (string) ($_POST['address'] ?? ''),
				'address_number' => (string) ($_POST['address_number'] ?? ''),
				'city' => (string) ($_POST['city'] ?? ''),
				'uf' => (string) ($_POST['uf'] ?? ''),
				'description' => (string) ($_POST['description'] ?? ''),
				'internal_order' => !empty($_POST['internal_order']) ? (string) $_POST['internal_order'] : null,
				'invoice' => !empty($_POST['invoice']) ? (string) $_POST['invoice'] : null,
				'daily_destination' => !empty($_POST['daily_destination']) ? (string) $_POST['daily_destination'] : null,
				'daily_rates' => !empty($_POST['daily_rates']) ? (string) $_POST['daily_rates'] : null,
				'external_ticket' => !empty($_POST['external_ticket']) ? (string) $_POST['external_ticket'] : null,
				'assigned_to' => null,
				'logo_path' => null,
				'status' => 'Aberto',
				'user_id' => (int) $user['id'],
			];

			if ($data['title'] === '' || $data['priority'] === '' || $data['category'] === '' || $data['name'] === '' || $data['registration'] === '' || $data['unit'] === '' || $data['cep'] === '' || $data['address'] === '' || $data['description'] === '') {
				$this->json(['success' => false, 'message' => 'Campos obrigatórios faltando'], 422);
				return;
			}
			
			// Debitar créditos de acordo com a categoria/modalidade do chamado
			$costs = $this->calculateCreditsCost($data);
			$remainingTicketCredits = null;
			$remainingDailyCredits = null;
			$remainingProjectDailiesCredits = null;
			
			try {
				$role = (string) ($user['role'] ?? 'user');
				// Se for usuário final (user), debitar de TODOS os usuários do tipo 'user'
				if ($role === 'user') {
					// Créditos de ticket (inclui Ticket e Uso Geral)
					if (!empty($costs['ticket']) && $costs['ticket'] > 0) {
						$byUser = User::adjustCreditsForUserType('user', -$costs['ticket']);
						if (empty($byUser) || !isset($byUser[(int) $user['id']])) {
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
					}
					// Créditos de diária
					if (!empty($costs['daily']) && $costs['daily'] > 0) {
						$byUser = User::adjustDailyCreditsForUserType('user', -$costs['daily']);
						if (empty($byUser) || !isset($byUser[(int) $user['id']])) {
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
					}
					// Créditos de diárias projeto
					if (!empty($costs['project_dailies']) && $costs['project_dailies'] > 0) {
						$byUser = User::adjustProjectDailiesCreditsForUserType('user', -$costs['project_dailies']);
						if (empty($byUser) || !isset($byUser[(int) $user['id']])) {
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
					}
				} else {
					// Comportamento antigo: debitar apenas do usuário atual
					// Créditos de ticket (inclui Ticket e Uso Geral)
					if (!empty($costs['ticket']) && $costs['ticket'] > 0) {
						$remainingTicketCredits = User::adjustCredits((int) $user['id'], -$costs['ticket']);
						if ($remainingTicketCredits === null) {
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
					}
					// Créditos de diária
					if (!empty($costs['daily']) && $costs['daily'] > 0) {
						$remainingDailyCredits = User::adjustDailyCredits((int) $user['id'], -$costs['daily']);
						if ($remainingDailyCredits === null) {
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
					}
					// Créditos de diárias projeto
					if (!empty($costs['project_dailies']) && $costs['project_dailies'] > 0) {
						$remainingProjectDailiesCredits = User::adjustProjectDailiesCredits((int) $user['id'], -$costs['project_dailies']);
						if ($remainingProjectDailiesCredits === null) {
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
					}
				}
			} catch (\RuntimeException $e) {
				$this->json(['success' => false, 'message' => $e->getMessage()], 422);
				return;
			} catch (\Throwable $e) {
				error_log('Erro ao debitar créditos: ' . $e->getMessage());
				$this->json(['success' => false, 'message' => 'Erro ao debitar créditos'], 500);
				return;
			}
			
			$id = Ticket::create($data);
			$this->json([
				'success' => true,
				'message' => 'Chamado aberto',
				'id' => $id,
				'remaining_ticket_credits' => $remainingTicketCredits,
				'remaining_daily_credits' => $remainingDailyCredits,
			]);
		} catch (\Throwable $e) {
			error_log('Erro ao criar chamado: ' . $e->getMessage());
			$this->json(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()], 500);
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

		$ok = Ticket::updateResponse($id, $response);
		
		// Processar upload de imagens
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
				
				if (strpos($fileType, 'image/') === 0) {
					$ext = pathinfo($fileName, PATHINFO_EXTENSION);
					$allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
					if (!in_array(strtolower($ext), $allowedExts, true)) {
						continue;
					}
					
					$newFileName = 'ticket_' . $id . '_' . time() . '_' . $i . '.' . $ext;
					$filePath = $uploadDir . $newFileName;
					
					if (move_uploaded_file($fileTmp, $filePath)) {
						$attachmentId = TicketAttachment::create([
							'ticket_id' => $id,
							'file_path' => '/uploads/tickets/' . $newFileName,
							'file_name' => $fileName,
							'file_type' => $fileType,
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
		}
		
		$this->json([
			'success' => $ok, 
			'message' => $ok ? 'Resposta salva com sucesso' : 'Falha ao salvar resposta',
			'attachments' => $uploadedAttachments
		]);
	}

	public function attachments(): void
	{
		$this->requireAuth([]);
		$id = (int) ($_GET['id'] ?? 0);
		if (!$id) {
			$this->json(['success' => false, 'message' => 'ID inválido'], 422);
			return;
		}
		
		$attachments = TicketAttachment::findByTicket($id);
		$this->json(['success' => true, 'attachments' => $attachments]);
	}

	private function calculateCreditsCost(array $data): array
	{
		$category = trim((string)($data['category'] ?? ''));
		$costs = [
			'ticket' => 0,
			'daily' => 0,
			'project_dailies' => 0,
		];

		if ($category === 'Diária') {
			// Chamados de diária debitam do saldo de diárias
			$costs['daily'] = 1;
		} elseif ($category === 'Projeto') {
			// Chamados de projeto debitam do saldo de diárias projeto
			$costs['project_dailies'] = 1;
		} elseif ($category === 'Ticket' || $category === 'Uso Geral') {
			// Tickets e Uso Geral debitam do saldo de tickets
			$costs['ticket'] = 1;
		}

		return $costs;
	}
}


