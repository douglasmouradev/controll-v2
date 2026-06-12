<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\CreditHistory;
use App\Models\User;

final class TicketCreditService
{
	/** @return array{ticket: int, daily: int, project_dailies: int} */
	public static function calculateCost(array $data): array
	{
		$category = trim((string) ($data['category'] ?? ''));
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
			$costs['daily'] = $quantity;
		} elseif ($category === 'Projeto') {
			$costs['project_dailies'] = $quantity;
		} elseif ($category === 'Ticket') {
			$costs['ticket'] = $quantity;
		}

		return $costs;
	}

	/**
	 * @return array{
	 *   success: bool,
	 *   message?: string,
	 *   code?: int,
	 *   remaining_ticket?: int|null,
	 *   remaining_daily?: int|null,
	 *   remaining_project_dailies?: int|null
	 * }
	 */
	public static function debitForCreation(array $user, array $costs): array
	{
		$role = TicketAccess::normalizeRole((string) ($user['role'] ?? ''));
		$userId = (int) ($user['id'] ?? 0);
		$usePool = in_array($role, ['user', 'support', 'admin'], true);

		try {
			if ($usePool) {
				return self::debitPool($userId, $costs, self::creationMessages(), 'ticket_creation', null);
			}

			return self::debitSingle($userId, $costs, self::creationMessages(), 'ticket_creation', null);
		} catch (\RuntimeException $e) {
			return ['success' => false, 'message' => $e->getMessage(), 'code' => 422];
		}
	}

	/**
	 * @return array{
	 *   success: bool,
	 *   message?: string,
	 *   code?: int,
	 *   remaining_ticket?: int|null,
	 *   remaining_daily?: int|null,
	 *   remaining_project_dailies?: int|null
	 * }
	 */
	public static function debitForQtdIncrease(array $user, array $costs, int $ticketId, string $role): array
	{
		$normalized = TicketAccess::normalizeRole($role);
		$userId = (int) ($user['id'] ?? 0);

		try {
			if ($normalized === 'user') {
				return self::debitPool($userId, $costs, self::qtdIncreaseMessages(), 'ticket_qtd_increase', $ticketId);
			}

			return self::debitSingle($userId, $costs, self::qtdIncreaseMessages(), 'ticket_qtd_increase', $ticketId);
		} catch (\RuntimeException $e) {
			return ['success' => false, 'message' => $e->getMessage(), 'code' => 422];
		}
	}

	/** @return array{ticket: string, daily: string, project_dailies: string} */
	private static function creationMessages(): array
	{
		return [
			'ticket' => 'Crédito utilizado - Ticket criado',
			'daily' => 'Crédito utilizado - Diária criada',
			'project_dailies' => 'Crédito utilizado - Diária Projeto criada',
		];
	}

	/** @return array{ticket: string, daily: string, project_dailies: string} */
	private static function qtdIncreaseMessages(): array
	{
		return [
			'ticket' => 'Crédito utilizado - Aumento de QTD em chamado',
			'daily' => 'Crédito utilizado - Aumento de QTD de Diária em chamado',
			'project_dailies' => 'Crédito utilizado - Aumento de QTD de Diária Projeto em chamado',
		];
	}

	/**
	 * @param array{ticket: string, daily: string, project_dailies: string} $messages
	 * @return array{success: bool, message?: string, code?: int, remaining_ticket?: int|null, remaining_daily?: int|null, remaining_project_dailies?: int|null}
	 */
	private static function debitPool(
		int $userId,
		array $costs,
		array $messages,
		string $source,
		?int $ticketId
	): array {
		$rolesPool = ['user', 'admin', 'support'];
		$remainingTicket = null;
		$remainingDaily = null;
		$remainingProject = null;

		if (!empty($costs['ticket']) && $costs['ticket'] > 0) {
			$byUser = User::adjustCreditsForRoles($rolesPool, -$costs['ticket']);
			if (empty($byUser) || !isset($byUser[$userId])) {
				return ['success' => false, 'message' => 'Nenhum usuário encontrado para débito de créditos de ticket', 'code' => 404];
			}
			foreach (array_keys($byUser) as $uid) {
				CreditHistory::recordOrFail($uid, 'ticket', -$costs['ticket'], $messages['ticket'], $ticketId, $source);
			}
			$remainingTicket = $byUser[$userId];
		}

		if (!empty($costs['daily']) && $costs['daily'] > 0) {
			$byUser = User::adjustDailyCreditsForRoles($rolesPool, -$costs['daily']);
			if (empty($byUser) || !isset($byUser[$userId])) {
				return ['success' => false, 'message' => 'Nenhum usuário encontrado para débito de créditos de diária', 'code' => 404];
			}
			foreach (array_keys($byUser) as $uid) {
				CreditHistory::recordOrFail($uid, 'daily', -$costs['daily'], $messages['daily'], $ticketId, $source);
			}
			$remainingDaily = $byUser[$userId];
		}

		if (!empty($costs['project_dailies']) && $costs['project_dailies'] > 0) {
			$byUser = User::adjustProjectDailiesCreditsForRoles($rolesPool, -$costs['project_dailies']);
			if (empty($byUser) || !isset($byUser[$userId])) {
				return ['success' => false, 'message' => 'Nenhum usuário encontrado para débito de créditos de diárias projeto', 'code' => 404];
			}
			foreach (array_keys($byUser) as $uid) {
				CreditHistory::recordOrFail($uid, 'project_dailies', -$costs['project_dailies'], $messages['project_dailies'], $ticketId, $source);
			}
			$remainingProject = $byUser[$userId];
		}

		return [
			'success' => true,
			'remaining_ticket' => $remainingTicket,
			'remaining_daily' => $remainingDaily,
			'remaining_project_dailies' => $remainingProject,
		];
	}

	/**
	 * @param array{ticket: string, daily: string, project_dailies: string} $messages
	 * @return array{success: bool, message?: string, code?: int, remaining_ticket?: int|null, remaining_daily?: int|null, remaining_project_dailies?: int|null}
	 */
	private static function debitSingle(
		int $userId,
		array $costs,
		array $messages,
		string $source,
		?int $ticketId
	): array {
		$remainingTicket = null;
		$remainingDaily = null;
		$remainingProject = null;

		if (!empty($costs['ticket']) && $costs['ticket'] > 0) {
			$remainingTicket = User::adjustCredits($userId, -$costs['ticket']);
			if ($remainingTicket === null) {
				return ['success' => false, 'message' => 'Usuário não encontrado para débito de créditos de ticket', 'code' => 404];
			}
			CreditHistory::recordOrFail($userId, 'ticket', -$costs['ticket'], $messages['ticket'], $ticketId, $source);
		}

		if (!empty($costs['daily']) && $costs['daily'] > 0) {
			$remainingDaily = User::adjustDailyCredits($userId, -$costs['daily']);
			if ($remainingDaily === null) {
				return ['success' => false, 'message' => 'Usuário não encontrado para débito de créditos de diária', 'code' => 404];
			}
			CreditHistory::recordOrFail($userId, 'daily', -$costs['daily'], $messages['daily'], $ticketId, $source);
		}

		if (!empty($costs['project_dailies']) && $costs['project_dailies'] > 0) {
			$remainingProject = User::adjustProjectDailiesCredits($userId, -$costs['project_dailies']);
			if ($remainingProject === null) {
				return ['success' => false, 'message' => 'Usuário não encontrado para débito de créditos de diárias projeto', 'code' => 404];
			}
			CreditHistory::recordOrFail($userId, 'project_dailies', -$costs['project_dailies'], $messages['project_dailies'], $ticketId, $source);
		}

		return [
			'success' => true,
			'remaining_ticket' => $remainingTicket,
			'remaining_daily' => $remainingDaily,
			'remaining_project_dailies' => $remainingProject,
		];
	}
}
