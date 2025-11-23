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
		$sql = 'SELECT id, name, email, user_type as role, username, active, credits, daily_credits, project_dailies_credits FROM users WHERE id = :id LIMIT 1';
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
		$pdo = Database::pdo();
		$pdo->beginTransaction();
		try {
			$stmt = $pdo->prepare('SELECT id, credits FROM users WHERE user_type = :user_type AND active = 1 FOR UPDATE');
			$stmt->execute([':user_type' => $userType]);
			$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
			if (!$rows) {
				$pdo->rollBack();
				return [];
			}

			$newById = [];
			foreach ($rows as $row) {
				$current = (int) $row['credits'];
				$new = $current + $delta;
				if ($new < 0) {
					$pdo->rollBack();
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
		$sql = 'INSERT INTO users (name, email, username, password_hash, user_type, active) 
		        VALUES (:name, :email, :username, :password_hash, :user_type, :active)';
		        
		$username = $data['username'] ?? strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $data['name']));
		$username = substr($username, 0, 50); // Garante que não ultrapasse o tamanho do campo
		
		$role = $data['role'] ?? 'usuario';
		$userType = self::ROLE_MAP[$role] ?? 'user';
		
		$stmt = Database::pdo()->prepare($sql);
		$stmt->execute([
			':name' => $data['name'],
			':email' => $data['email'],
			':username' => $username,
			':password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
			':user_type' => $userType,
			':active' => 1
		]);
		
		return (int) Database::pdo()->lastInsertId();
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
		$sql = 'DELETE FROM users WHERE id = :id';
		$stmt = Database::pdo()->prepare($sql);
		return $stmt->execute([':id' => $id]);
	}

	public static function updatePasswordChanged(int $id, string $newPassword): bool
	{
		$sql = 'UPDATE users SET password_hash = :password_hash, password_changed_at = NOW() WHERE id = :id';
		$stmt = Database::pdo()->prepare($sql);
		return $stmt->execute([
			':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
			':id' => $id
		]);
	}
}


