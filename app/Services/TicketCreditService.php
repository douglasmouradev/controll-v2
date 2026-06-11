<?php
declare(strict_types=1);

namespace App\Services;

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
}
