<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\Database;
use PDO;

final class Ticket
{
	public static function create(array $data): int
	{
		// Valores padrão
		$priorityId = 1; // ID padrão para prioridade 'Média'
		$statusId = 1;    // ID padrão para status 'Aberto'
		$categoryId = 1;  // ID padrão para categoria 'Geral'

		// Se existir a tabela de prioridades, busca o ID correto
		try {
			$pdo = Database::pdo();
			
			// Verifica se a tabela de prioridades existe (preferir ticket_priorities)
			$stmt = $pdo->query("SHOW TABLES LIKE 'ticket_priorities'");
			if ($stmt->rowCount() > 0) {
				$priorityName = $data['priority'] ?? 'Média';
				$stmt = $pdo->prepare("SELECT id FROM ticket_priorities WHERE name = ? LIMIT 1");
				$stmt->execute([$priorityName]);
				$result = $stmt->fetch(PDO::FETCH_ASSOC);
				if ($result) {
					$priorityId = (int) $result['id'];
				}
			} else {
				$stmt = $pdo->query("SHOW TABLES LIKE 'priorities'");
				if ($stmt->rowCount() > 0) {
					$priorityName = $data['priority'] ?? 'Média';
					$stmt = $pdo->prepare("SELECT id FROM priorities WHERE name = ? LIMIT 1");
					$stmt->execute([$priorityName]);
					$result = $stmt->fetch(PDO::FETCH_ASSOC);
					if ($result) {
						$priorityId = (int) $result['id'];
					}
				}
			}

			// Verifica se a tabela de status existe (preferir ticket_statuses)
			$stmt = $pdo->query("SHOW TABLES LIKE 'ticket_statuses'");
			if ($stmt->rowCount() > 0) {
				$statusName = $data['status'] ?? 'Aberto';
				$stmt = $pdo->prepare("SELECT id FROM ticket_statuses WHERE name = ? LIMIT 1");
				$stmt->execute([$statusName]);
				$result = $stmt->fetch(PDO::FETCH_ASSOC);
				if ($result) {
					$statusId = (int) $result['id'];
				}
			} else {
				$stmt = $pdo->query("SHOW TABLES LIKE 'statuses'");
				if ($stmt->rowCount() > 0) {
					$statusName = $data['status'] ?? 'Aberto';
					$stmt = $pdo->prepare("SELECT id FROM statuses WHERE name = ? LIMIT 1");
					$stmt->execute([$statusName]);
					$result = $stmt->fetch(PDO::FETCH_ASSOC);
					if ($result) {
						$statusId = (int) $result['id'];
					}
				}
			}

			// Verifica se a tabela de categorias existe (preferir ticket_categories)
			$stmt = $pdo->query("SHOW TABLES LIKE 'ticket_categories'");
			if ($stmt->rowCount() > 0) {
				$categoryName = $data['category'] ?? 'Geral';
				$stmt = $pdo->prepare("SELECT id FROM ticket_categories WHERE name = ? LIMIT 1");
				$stmt->execute([$categoryName]);
				$result = $stmt->fetch(PDO::FETCH_ASSOC);
				if ($result) {
					$categoryId = (int) $result['id'];
				}
			} else {
				$stmt = $pdo->query("SHOW TABLES LIKE 'categories'");
				if ($stmt->rowCount() > 0) {
					$categoryName = $data['category'] ?? 'Geral';
					$stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? LIMIT 1");
					$stmt->execute([$categoryName]);
					$result = $stmt->fetch(PDO::FETCH_ASSOC);
					if ($result) {
						$categoryId = (int) $result['id'];
					}
				}
			}

			// Prepara os dados para inserção
			$insertData = [
				'title' => $data['title'],
				'description' => $data['description'] ?? '',
				'category_id' => $categoryId,
				'priority_id' => $priorityId,
				'status_id' => $statusId,
				'user_id' => (int) $data['user_id'],
				'assigned_to' => $data['assigned_to'] ?? null,
				'department_id' => $data['department_id'] ?? null,
				'name' => $data['name'] ?? null,
				'registration' => $data['registration'] ?? null,
				'unit' => $data['unit'] ?? null,
				'cep' => $data['cep'] ?? null,
				'address' => $data['address'] ?? null,
				'address_number' => $data['address_number'] ?? null,
				'city' => $data['city'] ?? null,
				'uf' => $data['uf'] ?? null,
				'internal_order' => $data['internal_order'] ?? null,
				'invoice' => $data['invoice'] ?? null,
				'daily_destination' => $data['daily_destination'] ?? null,
				'daily_rates' => $data['daily_rates'] ?? null,
				'external_ticket' => $data['external_ticket'] ?? null,
				'logo_path' => $data['logo_path'] ?? null,
			];

			// Remove chaves com valor null
			$insertData = array_filter($insertData, function($value) {
				return $value !== null;
			});

			// Monta a query dinamicamente
			$columns = array_keys($insertData);
			$placeholders = array_map(fn($col) => ":$col", $columns);
			
			$sql = sprintf(
				'INSERT INTO tickets (%s) VALUES (%s)',
				implode(', ', $columns),
				implode(', ', $placeholders)
			);

			$stmt = $pdo->prepare($sql);
			$stmt->execute($insertData);
			
			return (int) $pdo->lastInsertId();

		} catch (\PDOException $e) {
			error_log('Erro ao criar ticket: ' . $e->getMessage());
			throw $e;
		}
	}
	

	public static function listForUser(array $authUser, array $filters = []): array
	{
		$pdo = Database::pdo();
		$hasTP = self::tableExists('ticket_priorities');
		$hasP  = !$hasTP && self::tableExists('priorities');
		$hasTS = self::tableExists('ticket_statuses');
		$hasS  = !$hasTS && self::tableExists('statuses');
		$hasTC = self::tableExists('ticket_categories');
		$hasC  = !$hasTC && self::tableExists('categories');

		$select = [
			't.id', 't.title', 't.description', 't.user_id', 't.assigned_to', 't.created_at', 't.updated_at',
			'u.name AS user_name', 'a.name AS assigned_name',
			self::columnExists('tickets', 'support_response') ? 't.support_response' : "'' AS support_response"
		];
		$joins = [
			'LEFT JOIN users u ON u.id = t.user_id',
			'LEFT JOIN users a ON a.id = t.assigned_to'
		];

		if ($hasTC) { $select[] = 'tc.name AS category'; $joins[] = 'LEFT JOIN ticket_categories tc ON tc.id = t.category_id'; }
		elseif ($hasC) { $select[] = 'c.name AS category'; $joins[] = 'LEFT JOIN categories c ON c.id = t.category_id'; }
		elseif (self::columnExists('tickets', 'category')) { $select[] = 't.category AS category'; }
		else { $select[] = "'' AS category"; }

		if ($hasTP) { $select[] = 'tp.name AS priority'; $joins[] = 'LEFT JOIN ticket_priorities tp ON tp.id = t.priority_id'; }
		elseif ($hasP) { $select[] = 'p.name AS priority'; $joins[] = 'LEFT JOIN priorities p ON p.id = t.priority_id'; }
		elseif (self::columnExists('tickets', 'priority')) { $select[] = 't.priority AS priority'; }
		else { $select[] = "'' AS priority"; }

		if ($hasTS) { $select[] = 'ts.name AS status'; $joins[] = 'LEFT JOIN ticket_statuses ts ON ts.id = t.status_id'; }
		elseif ($hasS) { $select[] = 's.name AS status'; $joins[] = 'LEFT JOIN statuses s ON s.id = t.status_id'; }
		elseif (self::columnExists('tickets', 'status')) { $select[] = 't.status AS status'; }
		else { $select[] = "'' AS status"; }

		$select[] = self::columnExists('tickets', 'name') ? 't.name' : "'' AS name";
		$select[] = self::columnExists('tickets', 'registration') ? 't.registration' : "'' AS registration";
		$select[] = self::columnExists('tickets', 'unit') ? 't.unit' : "'' AS unit";
		$select[] = self::columnExists('tickets', 'cep') ? 't.cep' : "'' AS cep";
		$select[] = self::columnExists('tickets', 'address') ? 't.address' : "'' AS address";
		$select[] = self::columnExists('tickets', 'address_number') ? 't.address_number' : "'' AS address_number";
		$select[] = self::columnExists('tickets', 'city') ? 't.city' : "'' AS city";
		$select[] = self::columnExists('tickets', 'uf') ? 't.uf' : "'' AS uf";
		$select[] = self::columnExists('tickets', 'internal_order') ? 't.internal_order' : "'' AS internal_order";
		$select[] = self::columnExists('tickets', 'invoice') ? 't.invoice' : "'' AS invoice";
		$select[] = self::columnExists('tickets', 'external_ticket') ? 't.external_ticket' : "'' AS external_ticket";

		$where = [];
		$params = [];
		// Restringir visualização: apenas admin vê todos os chamados
		if ($authUser['role'] !== 'admin') {
			$where[] = 't.user_id = :me';
			$params[':me'] = (int)$authUser['id'];
		}
		if (!empty($filters['status'])) {
			if ($hasTS) { $where[] = 'ts.name = :status'; }
			elseif ($hasS) { $where[] = 's.name = :status'; }
			elseif (self::columnExists('tickets', 'status')) { $where[] = 't.status = :status'; }
			$params[':status'] = $filters['status'];
		}
		if (!empty($filters['priority'])) {
			if ($hasTP) { $where[] = 'tp.name = :priority'; }
			elseif ($hasP) { $where[] = 'p.name = :priority'; }
			elseif (self::columnExists('tickets', 'priority')) { $where[] = 't.priority = :priority'; }
			$params[':priority'] = $filters['priority'];
		}
		if (!empty($filters['user'])) { 
			$userFilter = $filters['user'];
			if (is_numeric($userFilter)) {
				$where[] = 't.user_id = :user'; 
				$params[':user'] = (int)$userFilter;
			} else {
				$where[] = 'u.name LIKE :user'; 
				$params[':user'] = '%' . $userFilter . '%';
			}
		}
		if (!empty($filters['id'])) { $where[] = 't.id = :id'; $params[':id'] = (int)$filters['id']; }
		$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

		$sql = 'SELECT ' . implode(', ', $select) . ' FROM tickets t ' . implode(' ', $joins) . ' ' . $whereSql . ' ORDER BY t.created_at DESC LIMIT 200';
		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public static function find(int $id): ?array
	{
		$rows = self::listForUser(['role' => 'admin', 'id' => 0], []);
		foreach ($rows as $r) { if ((int)$r['id'] === (int)$id) { return $r; } }
		return null;
	}

	public static function listClosed(array $authUser, array $filters = []): array
	{
		$pdo = Database::pdo();
		$hasTS = self::tableExists('ticket_statuses');
		$hasS  = !$hasTS && self::tableExists('statuses');
		$hasTC = self::tableExists('ticket_categories');
		$hasC  = !$hasTC && self::tableExists('categories');

		$select = [
			't.id', 't.title', 't.description', 't.user_id', 't.assigned_to', 't.created_at', 't.updated_at',
			'u.name AS user_name', 'a.name AS assigned_name',
			self::columnExists('tickets', 'support_response') ? 't.support_response' : "'' AS support_response"
		];
		$joins = [
			'LEFT JOIN users u ON u.id = t.user_id',
			'LEFT JOIN users a ON a.id = t.assigned_to'
		];

		if ($hasTC) { $select[] = 'tc.name AS category'; $joins[] = 'LEFT JOIN ticket_categories tc ON tc.id = t.category_id'; }
		elseif ($hasC) { $select[] = 'c.name AS category'; $joins[] = 'LEFT JOIN categories c ON c.id = t.category_id'; }
		elseif (self::columnExists('tickets', 'category')) { $select[] = 't.category AS category'; }
		else { $select[] = "'' AS category"; }

		if (self::tableExists('ticket_priorities')) { $select[] = 'tp.name AS priority'; $joins[] = 'LEFT JOIN ticket_priorities tp ON tp.id = t.priority_id'; }
		elseif (self::columnExists('tickets', 'priority')) { $select[] = 't.priority AS priority'; }
		else { $select[] = "'' AS priority"; }

		if ($hasTS) { $select[] = 'ts.name AS status'; $joins[] = 'LEFT JOIN ticket_statuses ts ON ts.id = t.status_id'; }
		elseif ($hasS) { $select[] = 's.name AS status'; $joins[] = 'LEFT JOIN statuses s ON s.id = t.status_id'; }
		elseif (self::columnExists('tickets', 'status')) { $select[] = 't.status AS status'; }
		else { $select[] = "'' AS status"; }

		$select[] = self::columnExists('tickets', 'name') ? 't.name' : "'' AS name";
		$select[] = self::columnExists('tickets', 'registration') ? 't.registration' : "'' AS registration";
		$select[] = self::columnExists('tickets', 'unit') ? 't.unit' : "'' AS unit";

		$where = [];
		$params = [];
		
		// Restringir visualização: apenas admin vê todos os chamados fechados
		if ($authUser['role'] !== 'admin') {
			$where[] = 't.user_id = :me';
			$params[':me'] = (int)$authUser['id'];
		}
		
		// Filtrar por status fechado
		if ($hasTS) { $where[] = 'ts.name = :status'; }
		elseif ($hasS) { $where[] = 's.name = :status'; }
		elseif (self::columnExists('tickets', 'status')) { $where[] = 't.status = :status'; }
		$params[':status'] = 'Fechado';
		
		// Filtro de período
		if (!empty($filters['period'])) {
			$days = (int)$filters['period'];
			$where[] = 't.updated_at >= DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)';
		}
		
		if (!empty($filters['id'])) { $where[] = 't.id = :id'; $params[':id'] = (int)$filters['id']; }
		if (!empty($filters['user'])) { 
			$userFilter = $filters['user'];
			if (is_numeric($userFilter)) {
				$where[] = 't.user_id = :user'; 
				$params[':user'] = (int)$userFilter;
			} else {
				$where[] = 'u.name LIKE :user'; 
				$params[':user'] = '%' . $userFilter . '%';
			}
		}
		
		$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
		$sql = 'SELECT ' . implode(', ', $select) . ' FROM tickets t ' . implode(' ', $joins) . ' ' . $whereSql . ' ORDER BY t.updated_at DESC LIMIT 200';
		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public static function updateStatus(int $id, string $status): bool
	{
		$pdo = Database::pdo();
		if (self::tableExists('ticket_statuses')) {
			$stId = self::getIdByName('ticket_statuses', $status);
			if ($stId) {
				$stmt = $pdo->prepare('UPDATE tickets SET status_id = :sid, updated_at = NOW() WHERE id = :id');
				return $stmt->execute([':sid' => $stId, ':id' => $id]);
			}
		}
		if (self::tableExists('statuses')) {
			$stId = self::getIdByName('statuses', $status);
			if ($stId) {
				$stmt = $pdo->prepare('UPDATE tickets SET status_id = :sid, updated_at = NOW() WHERE id = :id');
				return $stmt->execute([':sid' => $stId, ':id' => $id]);
			}
		}
		if (self::columnExists('tickets', 'status')) {
			$stmt = $pdo->prepare('UPDATE tickets SET status = :status, updated_at = NOW() WHERE id = :id');
			return $stmt->execute([':status' => $status, ':id' => $id]);
		}
		return false;
	}

	public static function assignTo(int $id, int $userId): bool
	{
		$stmt = Database::pdo()->prepare('UPDATE tickets SET assigned_to = :assigned_to, updated_at = NOW() WHERE id = :id');
		$ok = $stmt->execute([':assigned_to' => $userId, ':id' => $id]);
		if ($ok) { self::updateStatus($id, 'Em andamento'); }
		return $ok;
	}

	public static function updateResponse(int $id, string $response): bool
	{
		$sql = "UPDATE tickets SET support_response = :response, updated_at = NOW() WHERE id = :id";
		$stmt = Database::pdo()->prepare($sql);
		return $stmt->execute([':response' => $response ?: null, ':id' => $id]);
	}

	private static function tableExists(string $table): bool
	{
		$pdo = Database::pdo();
		$stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
		return (bool)$stmt->rowCount();
	}

	private static function columnExists(string $table, string $column): bool
	{
		$pdo = Database::pdo();
		$stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
		return (bool)$stmt->rowCount();
	}

	private static function getIdByName(string $table, string $name): ?int
	{
		$stmt = Database::pdo()->prepare("SELECT id FROM `{$table}` WHERE name = ? LIMIT 1");
		$stmt->execute([$name]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ? (int)$row['id'] : null;
	}
}


