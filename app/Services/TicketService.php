<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;

final class TicketService
{
	/** @return array{success: bool, message: string} */
	public static function updateStatus(int $id, string $status, array $actor): array
	{
		$ticket = Ticket::find($id, $actor);
		if (!$ticket) {
			return ['success' => false, 'message' => 'Chamado não encontrado'];
		}

		$previousStatus = (string) ($ticket['status'] ?? '');
		$ok = Ticket::updateStatus($id, $status);
		if (!$ok) {
			return ['success' => false, 'message' => 'Falha ao atualizar'];
		}

		if ($previousStatus !== $status) {
			AuditLog::record('ticket_status_update', 'ticket:' . $id . ':' . $status);
			TicketNotification::notifyStatusChanged($id, $ticket, $status, $actor);
			InAppNotifier::ticketStatusChanged($id, $ticket, $status, $actor);
		}

		return ['success' => true, 'message' => 'Status atualizado'];
	}

	/** @return array{success: bool, message: string} */
	public static function saveSupportResponse(int $id, string $response, array $actor): array
	{
		$ticket = Ticket::find($id, $actor);
		if (!$ticket) {
			return ['success' => false, 'message' => 'Chamado não encontrado'];
		}

		$ok = Ticket::updateResponse($id, $response);
		if (!$ok) {
			return ['success' => false, 'message' => 'Falha ao salvar resposta'];
		}

		if ($response !== '') {
			AuditLog::record('ticket_support_response', 'ticket:' . $id);
			TicketNotification::notifySupportResponse($id, $ticket, $response, $actor);
			InAppNotifier::ticketSupportResponse($id, $ticket, $actor);
		}

		return ['success' => true, 'message' => 'Resposta salva'];
	}

	public static function ticketOwnerEmail(array $ticket): string
	{
		$userId = (int) ($ticket['user_id'] ?? 0);
		if ($userId <= 0) {
			return '';
		}

		$user = User::findById($userId);

		return trim((string) ($user['email'] ?? ''));
	}
}
