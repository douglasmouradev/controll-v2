<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\Database;
use PDO;

final class User
{
	public const ROLES = ['superadmin', 'admin', 'suporte', 'gerente', 'usuario'];
	private const ROLE_MAP = [
		'superadmin' => 'admin',
		'admin' => 'admin',
		'suporte' => 'support',
		'gerente' => 'support',
		'usuario' => 'user',
	];

	public static function findByEmail(string $email): ?array
	{
		// Permite login tanto com "admin" quanto com "admin@local"
		$sql = 'SELECT id, name, email, password_hash as password, user_type as role, username, active, password_changed_at 
		        FROM users 
		        WHERE email = ? 
		           OR email = CONCAT(?, \'@local\')
		        LIMIT 1';
		$stmt = Database::pdo()->prepare($sql);
		$stmt->execute([$email, $email]);
		$user = $stmt->fetch(PDO::FETCH_ASSOC);
		
		// Verifica se o usuário está ativo
		if ($user && isset($user['active']) && !$user['active']) {
		    return null;
		}
		
		return $user ?: null;
	}

	public static function findById(int $id): ?array
	{
		$sql = 'SELECT id, name, email, user_type as role, username, active, credits, daily_credits, project_dailies_credits, password_changed_at FROM users WHERE id = :id LIMIT 1';
		$stmt = Database::pdo()->prepare($sql);
		$stmt->execute([':id' => $id]);
		$user = $stmt->fetch(PDO::FETCH_ASSOC);
		
		// Verifica se o usuário está ativo
		if ($user && isset($user['active']) && !$user['active']) {
		    return null;
		}
		
		return $user ?: null;
	}

	public static function listAll(): array
	{
		$sql = 'SELECT id, name, email, user_type as role, username, active, credits, daily_credits, project_dailies_credits, created_at FROM users ORDER BY created_at DESC';
		$stmt = Database::pdo()->query($sql);
		$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
		return $result ?: [];
	}

	public static function adjustCredits(int $id, int $delta): ?int
	{
		$pdo = Database::pdo();
		$pdo->beginTransaction();
		try {
			$stmt = $pdo->prepare('SELECT credits FROM users WHERE id = :id FOR UPDATE');
			$stmt->execute([':id' => $id]);
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			if (!$row) {
				$pdo->rollBack();
				return null;
			}
			$current = (int) $row['credits'];
			$new = $current + $delta;
			if ($new < 0) {
				$pdo->rollBack();
				throw new \RuntimeException('Saldo de créditos insuficiente');
			}
			$stmt = $pdo->prepare('UPDATE users SET credits = :credits WHERE id = :id');
			$stmt->execute([':credits' => $new, ':id' => $id]);
			$pdo->commit();
			return $new;
		} catch (\Throwable $e) {
			if ($pdo->inTransaction()) {
				$pdo->rollBack();
			}
			throw $e;
		}
	}

	public static function adjustDailyCredits(int $id, int $delta): ?int
	{
		$pdo = Database::pdo();
		$pdo->beginTransaction();
		try {
			$stmt = $pdo->prepare('SELECT daily_credits FROM users WHERE id = :id FOR UPDATE');
			$stmt->execute([':id' => $id]);
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			if (!$row) {
				$pdo->rollBack();
				return null;
			}
			$current = (int) $row['daily_credits'];
			$new = $current + $delta;
			if ($new < 0) {
				$pdo->rollBack();
				throw new \RuntimeException('Saldo de créditos insuficiente');
			}
			$stmt = $pdo->prepare('UPDATE users SET daily_credits = :credits WHERE id = :id');
			$stmt->execute([':credits' => $new, ':id' => $id]);
			$pdo->commit();
			return $new;
		} catch (\Throwable $e) {
			if ($pdo->inTransaction()) {
				$pdo->rollBack();
			}
			throw $e;
		}
	}

	public static function adjustProjectDailiesCredits(int $id, int $delta): ?int
	{
		$pdo = Database::pdo();
		$pdo->beginTransaction();
		try {
			$stmt = $pdo->prepare('SELECT project_dailies_credits FROM users WHERE id = :id FOR UPDATE');
			$stmt->execute([':id' => $id]);
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			if (!$row) {
				$pdo->rollBack();
				return null;
			}
			$current = (int) $row['project_dailies_credits'];
			$new = $current + $delta;
			if ($new < 0) {
				$pdo->rollBack();
				throw new \RuntimeException('Saldo de créditos insuficiente');
			}
			$stmt = $pdo->prepare('UPDATE users SET project_dailies_credits = :credits WHERE id = :id');
			$stmt->execute([':credits' => $new, ':id' => $id]);
			$pdo->commit();
			return $new;
		} catch (\Throwable $e) {
			if ($pdo->inTransaction()) {
				$pdo->rollBack();
			}
			throw $e;
		}
	}

	/**
	 * Ajusta créditos de Ticket para todos os usuários de um determinado tipo.
	 * Retorna um array [user_id => novo_saldo].
	 */
	public static function adjustCreditsForUserType(string $userType, int $delta): array
	{
		return self::adjustCreditsForUserTypeInternal($userType, $delta, false);
	}

	/**
	 * Ajuste de créditos de Ticket permitindo saldo negativo (usado em clonagem).
	 * Retorna um array [user_id => novo_saldo].
	 */
	public static function adjustCreditsForUserTypeAllowNegative(string $userType, int $delta): array
	{
		return self::adjustCreditsForUserTypeInternal($userType, $delta, true);
	}

	private static function adjustCreditsForUserTypeInternal(string $userType, int $delta, bool $allowNegative): array
	{
		$pdo = Database::pdo();
		$startedTx = false;
		if (!$pdo->inTransaction()) {
			$pdo->beginTransaction();
			$startedTx = true;
		}
		try {
			$stmt = $pdo->prepare('SELECT id, credits FROM users WHERE user_type = :user_type AND active = 1 FOR UPDATE');
			$stmt->execute([':user_type' => $userType]);
			$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
			if (!$rows) {
				if ($startedTx) {
					$pdo->rollBack();
				}
				return [];
			}

			$newById = [];
			foreach ($rows as $row) {
				$current = (int) $row['credits'];
				$new = $current + $delta;
				if (!$allowNegative && $new < 0) {
					if ($startedTx) {
						$pdo->rollBack();
					}
					throw new \RuntimeException('Saldo de créditos insuficiente');
				}
				$newById[(int) $row['id']] = $new;
			}

			$stmtUpdate = $pdo->prepare('UPDATE users SET credits = :credits WHERE id = :id');
			foreach ($newById as $userId => $newCredits) {
				$stmtUpdate->execute([
					':credits' => $newCredits,
					':id' => $userId,
				]);
			}

			if ($startedTx) {
				$pdo->commit();
			}
			return $newById;
		} catch (\Throwable $e) {
			if ($startedTx && $pdo->inTransaction()) {
				$pdo->rollBack();
			}
			throw $e;
		}
	}

	/**
	 * Ajusta créditos de Ticket para múltiplos tipos de usuário (pool entre user/admin/support).
	 * Retorna array [user_id => novo_saldo].
	 */
	public static function adjustCreditsForRoles(array $userTypes, int $delta): array
	{
		$result = [];
		$userTypes = array_values(array_unique($userTypes));
		foreach ($userTypes as $type) {
			$byType = self::adjustCreditsForUserType($type, $delta);
			if (!empty($byType)) {
				foreach ($byType as $userId => $credits) {
					$result[$userId] = $credits;
				}
			}
		}
		return $result;
	}

	/**
	 * Ajusta créditos de Ticket para múltiplos tipos de usuário permitindo saldo negativo.
	 * Retorna array [user_id => novo_saldo].
	 */
	public static function adjustCreditsForRolesAllowNegative(array $userTypes, int $delta): array
	{
		$result = [];
		$userTypes = array_values(array_unique($userTypes));
		foreach ($userTypes as $type) {
			$byType = self::adjustCreditsForUserTypeAllowNegative($type, $delta);
			if (!empty($byType)) {
				foreach ($byType as $userId => $credits) {
					$result[$userId] = $credits;
				}
			}
		}
		return $result;
	}

	/**
	 * Ajusta créditos de Diária para múltiplos tipos de usuário (pool entre user/admin/support).
	 * Retorna array [user_id => novo_saldo].
	 */
	public static function adjustDailyCreditsForRoles(array $userTypes, int $delta): array
	{
		$result = [];
		$userTypes = array_values(array_unique($userTypes));
		foreach ($userTypes as $type) {
			$byType = self::adjustDailyCreditsForUserType($type, $delta);
			if (!empty($byType)) {
				foreach ($byType as $userId => $credits) {
					$result[$userId] = $credits;
				}
			}
		}
		return $result;
	}

	/**
	 * Ajusta créditos de Diárias Projeto para múltiplos tipos de usuário (pool entre user/admin/support).
	 * Retorna array [user_id => novo_saldo].
	 */
	public static function adjustProjectDailiesCreditsForRoles(array $userTypes, int $delta): array
	{
		$result = [];
		$userTypes = array_values(array_unique($userTypes));
		foreach ($userTypes as $type) {
			$byType = self::adjustProjectDailiesCreditsForUserType($type, $delta);
			if (!empty($byType)) {
				foreach ($byType as $userId => $credits) {
					$result[$userId] = $credits;
				}
			}
		}
		return $result;
	}

	/**
	 * Ajusta créditos de Diária para todos os usuários de um determinado tipo.
	 * Retorna um array [user_id => novo_saldo].
	 */
	public static function adjustDailyCreditsForUserType(string $userType, int $delta): array
	{
		$pdo = Database::pdo();
		$pdo->beginTransaction();
		try {
			$stmt = $pdo->prepare('SELECT id, daily_credits FROM users WHERE user_type = :user_type AND active = 1 FOR UPDATE');
			$stmt->execute([':user_type' => $userType]);
			$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
			if (!$rows) {
				$pdo->rollBack();
				return [];
			}

			$newById = [];
			foreach ($rows as $row) {
				$current = (int) $row['daily_credits'];
				$new = $current + $delta;
				if ($new < 0) {
					$pdo->rollBack();
					throw new \RuntimeException('Saldo de créditos insuficiente');
				}
				$newById[(int) $row['id']] = $new;
			}

			$stmtUpdate = $pdo->prepare('UPDATE users SET daily_credits = :credits WHERE id = :id');
			foreach ($newById as $userId => $newCredits) {
				$stmtUpdate->execute([
					':credits' => $newCredits,
					':id' => $userId,
				]);
			}

			$pdo->commit();
			return $newById;
		} catch (\Throwable $e) {
			if ($pdo->inTransaction()) {
				$pdo->rollBack();
			}
			throw $e;
		}
	}

	/**
	 * Ajusta créditos de Diárias Projeto para todos os usuários de um determinado tipo.
	 * Retorna um array [user_id => novo_saldo].
	 */
	public static function adjustProjectDailiesCreditsForUserType(string $userType, int $delta): array
	{
		$pdo = Database::pdo();
		$pdo->beginTransaction();
		try {
			$stmt = $pdo->prepare('SELECT id, project_dailies_credits FROM users WHERE user_type = :user_type AND active = 1 FOR UPDATE');
			$stmt->execute([':user_type' => $userType]);
			$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
			if (!$rows) {
				$pdo->rollBack();
				return [];
			}

			$newById = [];
			foreach ($rows as $row) {
				$current = (int) $row['project_dailies_credits'];
				$new = $current + $delta;
				if ($new < 0) {
					$pdo->rollBack();
					throw new \RuntimeException('Saldo de créditos insuficiente');
				}
				$newById[(int) $row['id']] = $new;
			}

			$stmtUpdate = $pdo->prepare('UPDATE users SET project_dailies_credits = :credits WHERE id = :id');
			foreach ($newById as $userId => $newCredits) {
				$stmtUpdate->execute([
					':credits' => $newCredits,
					':id' => $userId,
				]);
			}

			$pdo->commit();
			return $newById;
		} catch (\Throwable $e) {
			if ($pdo->inTransaction()) {
				$pdo->rollBack();
			}
			throw $e;
		}
	}

	public static function create(array $data): int
	{
		$username = $data['username'] ?? strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $data['name']));
		$username = substr($username, 0, 50); // Garante que não ultrapasse o tamanho do campo
		
		$role = $data['role'] ?? 'usuario';
		$userType = self::ROLE_MAP[$role] ?? 'user';
		
		$pdo = Database::pdo();
		
		// Para usuários finais (user_type = 'user'), herdamos o saldo atual do pool
		if (in_array($userType, ['user', 'admin', 'support'], true)) {
			$baseCredits = 0;
			$baseDailyCredits = 0;
			$baseProjectDailiesCredits = 0;
			
			$stmtBase = $pdo->prepare('SELECT credits, daily_credits, project_dailies_credits FROM users WHERE user_type = :user_type AND active = 1 ORDER BY id ASC LIMIT 1');
			$stmtBase->execute([':user_type' => 'user']);
			$baseUser = $stmtBase->fetch(PDO::FETCH_ASSOC);
			if ($baseUser) {
				$baseCredits = (int) ($baseUser['credits'] ?? 0);
				$baseDailyCredits = (int) ($baseUser['daily_credits'] ?? 0);
				$baseProjectDailiesCredits = (int) ($baseUser['project_dailies_credits'] ?? 0);
			}
			
			$sql = 'INSERT INTO users (name, email, username, password_hash, user_type, active, credits, daily_credits, project_dailies_credits) 
			        VALUES (:name, :email, :username, :password_hash, :user_type, :active, :credits, :daily_credits, :project_dailies_credits)';
			
			$stmt = $pdo->prepare($sql);
			$stmt->execute([
				':name' => $data['name'],
				':email' => $data['email'],
				':username' => $username,
				':password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
				':user_type' => $userType,
				':active' => 1,
				':credits' => $baseCredits,
				':daily_credits' => $baseDailyCredits,
				':project_dailies_credits' => $baseProjectDailiesCredits,
			]);
		} else {
			$sql = 'INSERT INTO users (name, email, username, password_hash, user_type, active) 
			        VALUES (:name, :email, :username, :password_hash, :user_type, :active)';
			
			$stmt = $pdo->prepare($sql);
			$stmt->execute([
				':name' => $data['name'],
				':email' => $data['email'],
				':username' => $username,
				':password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
				':user_type' => $userType,
				':active' => 1,
			]);
		}
		
		return (int) $pdo->lastInsertId();
	}

	public static function update(int $id, array $data): bool
	{
		$updates = [];
		$params = [':id' => $id];
		
		if (isset($data['name'])) {
		    $updates[] = 'name = :name';
		    $params[':name'] = $data['name'];
		}
		if (isset($data['email'])) {
		    $updates[] = 'email = :email';
		    $params[':email'] = $data['email'];
		}
		if (isset($data['username'])) {
		    $updates[] = 'username = :username';
		    $params[':username'] = $data['username'];
		}
		if (isset($data['password'])) {
		    $updates[] = 'password_hash = :password_hash';
		    $params[':password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
		}
		if (isset($data['role'])) {
	    $updates[] = 'user_type = :user_type';
	    $params[':user_type'] = self::ROLE_MAP[$data['role']] ?? 'user';
		}
		if (isset($data['active'])) {
		    $updates[] = 'active = :active';
		    $params[':active'] = (bool)$data['active'] ? 1 : 0;
		}
		
		if (empty($updates)) {
		    return false;
		}
		
		$sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = :id';
		$stmt = Database::pdo()->prepare($sql);
		return $stmt->execute($params);
	}

	public static function delete(int $id): bool
	{
		$pdo = Database::pdo();
		$pdo->beginTransaction();
		try {
			$stmt = $pdo->prepare(
				'SELECT id FROM users WHERE id != :id ORDER BY (user_type = \'admin\') DESC, (user_type = \'support\') DESC, id ASC LIMIT 1'
			);
			$stmt->execute([':id' => $id]);
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			if (!$row) {
				$pdo->rollBack();
				return false;
			}
			$fallbackId = (int) $row['id'];

			if (self::dbTableExists($pdo, 'tickets')) {
				if (self::dbColumnExists($pdo, 'tickets', 'user_id')) {
					$u = $pdo->prepare('UPDATE tickets SET user_id = :fallback WHERE user_id = :id');
					$u->execute([':fallback' => $fallbackId, ':id' => $id]);
				}
				if (self::dbColumnExists($pdo, 'tickets', 'assigned_to')) {
					$u = $pdo->prepare('UPDATE tickets SET assigned_to = NULL WHERE assigned_to = :id');
					$u->execute([':id' => $id]);
				}
			}

			self::reassignUserFkColumn($pdo, 'ticket_comments', 'user_id', $id, $fallbackId);
			self::reassignUserFkColumn($pdo, 'ticket_history', 'user_id', $id, $fallbackId);
			self::reassignUserFkColumn($pdo, 'credit_history', 'user_id', $id, $fallbackId);
			self::reassignUserFkColumn($pdo, 'credit_history', 'created_by', $id, $fallbackId);

			if (self::dbTableExists($pdo, 'ticket_attachments') && self::dbColumnExists($pdo, 'ticket_attachments', 'uploaded_by')) {
				$u = $pdo->prepare('UPDATE ticket_attachments SET uploaded_by = :fallback WHERE uploaded_by = :id');
				$u->execute([':fallback' => $fallbackId, ':id' => $id]);
			}

			if (self::dbTableExists($pdo, 'tickets_backup') && self::dbColumnExists($pdo, 'tickets_backup', 'user_id')) {
				$u = $pdo->prepare('UPDATE tickets_backup SET user_id = :fallback WHERE user_id = :id');
				$u->execute([':fallback' => $fallbackId, ':id' => $id]);
			}
			if (self::dbTableExists($pdo, 'tickets_backup') && self::dbColumnExists($pdo, 'tickets_backup', 'assigned_to')) {
				$u = $pdo->prepare('UPDATE tickets_backup SET assigned_to = NULL WHERE assigned_to = :id');
				$u->execute([':id' => $id]);
			}

			if (self::dbTableExists($pdo, 'departments') && self::dbColumnExists($pdo, 'departments', 'manager_id')) {
				$u = $pdo->prepare('UPDATE departments SET manager_id = NULL WHERE manager_id = :id');
				$u->execute([':id' => $id]);
			}

			$del = $pdo->prepare('DELETE FROM users WHERE id = :id');
			$del->execute([':id' => $id]);
			if ($del->rowCount() === 0) {
				$pdo->rollBack();
				return false;
			}
			$pdo->commit();
			return true;
		} catch (\Throwable $e) {
			if ($pdo->inTransaction()) {
				$pdo->rollBack();
			}
			throw $e;
		}
	}

	private static function dbTableExists(PDO $pdo, string $table): bool
	{
		if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
			return false;
		}
		$stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));
		return $stmt !== false && $stmt->rowCount() > 0;
	}

	private static function dbColumnExists(PDO $pdo, string $table, string $column): bool
	{
		if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
			return false;
		}
		$stmt = $pdo->query('SHOW COLUMNS FROM `' . $table . '` LIKE ' . $pdo->quote($column));
		return $stmt !== false && $stmt->rowCount() > 0;
	}

	private static function reassignUserFkColumn(PDO $pdo, string $table, string $column, int $fromUserId, int $toUserId): void
	{
		if (!self::dbTableExists($pdo, $table) || !self::dbColumnExists($pdo, $table, $column)) {
			return;
		}
		$stmt = $pdo->prepare('UPDATE `' . $table . '` SET `' . $column . '` = :to WHERE `' . $column . '` = :from');
		$stmt->execute([':to' => $toUserId, ':from' => $fromUserId]);
	}

	public static function updatePasswordChanged(int $id, string $newPassword): bool
	{
		$sql = 'UPDATE users SET password_hash = :password_hash, password_changed_at = NOW() WHERE id = :id';
		$stmt = Database::pdo()->prepare($sql);
		$ok = $stmt->execute([
			':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
			':id' => $id,
		]);

		return $ok && $stmt->rowCount() > 0;
	}
}


