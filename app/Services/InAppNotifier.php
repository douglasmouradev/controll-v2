<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Notification;

final class InAppNotifier
{
	public static function ticketOpened(int $ticketId, array $ticket, array $openedBy): void
	{
		$assignedTo = (int) ($ticket['assigned_to'] ?? 0);
		if ($assignedTo > 0) {
			self::notifyUser(
				$assignedTo,
				'ticket_opened',
				'Novo chamado #' . $ticketId,
				(string) ($openedBy['name'] ?? 'Usuário') . ' abriu: ' . (string) ($ticket['title'] ?? ''),
				$ticketId
			);
		}
	}

	public static function ticketStatusChanged(int $ticketId, array $ticket, string $newStatus, array $actor): void
	{
		$ownerId = (int) ($ticket['user_id'] ?? 0);
		$actorId = (int) ($actor['id'] ?? 0);
		if ($ownerId <= 0 || $ownerId === $actorId) {
			return;
		}

		self::notifyUser(
			$ownerId,
			'ticket_status',
			'Chamado #' . $ticketId . ' atualizado',
			'Status alterado para: ' . $newStatus,
			$ticketId,
			$newStatus === 'Fechado' ? 'high' : 'normal'
		);
	}

	public static function ticketSupportResponse(int $ticketId, array $ticket, array $actor): void
	{
		$ownerId = (int) ($ticket['user_id'] ?? 0);
		$actorId = (int) ($actor['id'] ?? 0);
		if ($ownerId <= 0 || $ownerId === $actorId) {
			return;
		}

		self::notifyUser(
			$ownerId,
			'ticket_response',
			'Resposta no chamado #' . $ticketId,
			(string) ($actor['name'] ?? 'Suporte') . ' respondeu ao seu chamado.',
			$ticketId,
			'high'
		);
	}

	private static function notifyUser(
		int $userId,
		string $type,
		string $title,
		string $message,
		?int $ticketId = null,
		string $priority = 'normal'
	): void {
		Notification::create($userId, $type, $title, $message, $ticketId, $priority);
	}
}
