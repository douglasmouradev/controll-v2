<?php
declare(strict_types=1);

use App\Services\TicketCreditService;
use PHPUnit\Framework\TestCase;

final class TicketCreditServiceTest extends TestCase
{
	public function testTicketCategoryCost(): void
	{
		$costs = TicketCreditService::calculateCost(['category' => 'Ticket', 'qtd' => 3]);
		$this->assertSame(3, $costs['ticket']);
		$this->assertSame(0, $costs['daily']);
	}

	public function testDailyCategoryCost(): void
	{
		$costs = TicketCreditService::calculateCost(['category' => 'Diária', 'qtd' => 2]);
		$this->assertSame(2, $costs['daily']);
	}

	public function testZeroQuantity(): void
	{
		$costs = TicketCreditService::calculateCost(['category' => 'Ticket', 'qtd' => 0]);
		$this->assertSame(0, $costs['ticket']);
	}
}
