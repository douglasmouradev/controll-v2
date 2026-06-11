<?php

namespace App\Models;

use App\Services\Database;

class CreditHistory
{
    /**
     * Registra uma transação de crédito
     */
    public static function record(
        int $userId,
        string $type, // 'ticket' ou 'daily'
        int $amount, // positivo para compra, negativo para uso
        string $description,
        ?int $referenceId = null,
        ?string $referenceType = null,
        ?int $createdBy = null
    ): bool {
        try {
            $pdo = Database::pdo();
            $stmt = $pdo->prepare('
                INSERT INTO credit_history 
                (user_id, type, amount, description, reference_id, reference_type, created_by)
                VALUES (:user_id, :type, :amount, :description, :reference_id, :reference_type, :created_by)
            ');

            return $stmt->execute([
                ':user_id' => $userId,
                ':type' => $type,
                ':amount' => $amount,
                ':description' => $description,
                ':reference_id' => $referenceId,
                ':reference_type' => $referenceType,
                ':created_by' => $createdBy,
            ]);
        } catch (\Exception $e) {
            error_log('Erro ao registrar histórico de créditos: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Conta usuários por role (user_type)
     */
    public static function countUsersByRole(?string $role = 'user'): int
    {
        try {
            $pdo = Database::pdo();
            $sql = 'SELECT COUNT(*) as c FROM users';
            $params = [];
            if ($role) {
                $sql .= ' WHERE user_type = :role AND active = 1';
                $params[':role'] = $role;
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: ['c' => 0];
            return (int)($row['c'] ?? 0);
        } catch (\Exception $e) {
            error_log('Erro ao contar usuários por role: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Resumo global normalizado por usuário (per-user), para o tipo informado.
     */
    public static function getGlobalSummaryPerUser(string $type, ?string $role = 'user'): array
    {
        $sum = self::getGlobalSummary($type, $role);
        $count = self::countUsersByRole($role);
        if ($count <= 0) {
            return ['purchased' => 0, 'spent' => 0, 'available' => 0];
        }
        $purchased = (int) floor(($sum['purchased'] ?? 0) / $count);
        $spent = (int) floor(($sum['spent'] ?? 0) / $count);
        return [
            'purchased' => $purchased,
            'spent' => $spent,
            'available' => $purchased - $spent,
        ];
    }

    /**
     * Histórico global normalizado por usuário: agrupa lançamentos iguais (por segundo)
     * e retorna apenas um registro com o valor por usuário (não multiplicado pelos usuários).
     */
    public static function getAllHistoryNormalizedForRole(?string $type = null, int $limit = 100, ?string $role = 'user'): array
    {
        try {
            $pdo = Database::pdo();
            $sql = '
                SELECT 
                    DATE_FORMAT(ch.created_at, "%Y-%m-%d %H:%i:%s") as created_at,
                    ch.type,
                    ch.description,
                    ch.reference_type,
                    ch.created_by,
                    ch.amount as amount,
                    COUNT(*) as occurrences
                FROM credit_history ch
                JOIN users u ON u.id = ch.user_id
            ';
            $clauses = [];
            $params = [];
            if ($type) {
                $clauses[] = 'ch.type = :type';
                $params[':type'] = $type;
            }
            if ($role) {
                $clauses[] = 'u.user_type = :role';
                $params[':role'] = $role;
            }
            if (!empty($clauses)) {
                $sql .= ' WHERE ' . implode(' AND ', $clauses);
            }
            $sql .= ' GROUP BY DATE_FORMAT(ch.created_at, "%Y-%m-%d %H:%i:%s"), ch.type, ch.description, ch.reference_type, ch.created_by, ch.amount';
            $sql .= ' ORDER BY ch.created_at DESC LIMIT :limit';
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            error_log('Erro ao obter histórico global normalizado: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtém o histórico de créditos de um usuário
     */
    public static function getHistory(int $userId, string $type = null, int $limit = 50): array
    {
        try {
            $pdo = Database::pdo();
            $sql = 'SELECT * FROM credit_history WHERE user_id = :user_id';
            $params = [':user_id' => $userId];

            if ($type) {
                $sql .= ' AND type = :type';
                $params[':type'] = $type;
            }

            $sql .= ' ORDER BY created_at DESC LIMIT :limit';
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            error_log('Erro ao obter histórico de créditos: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtém resumo de créditos (total gasto e disponível)
     */
    public static function getSummary(int $userId, string $type): array
    {
        try {
            $pdo = Database::pdo();
            
            // Total gasto (soma de valores negativos)
            $stmt = $pdo->prepare('
                SELECT 
                    COALESCE(SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END), 0) as spent,
                    COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) as purchased
                FROM credit_history 
                WHERE user_id = :user_id AND type = :type
            ');
            $stmt->execute([
                ':user_id' => $userId,
                ':type' => $type,
            ]);
            
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            return [
                'purchased' => (int)($result['purchased'] ?? 0),
                'spent' => (int)($result['spent'] ?? 0),
                'available' => (int)(($result['purchased'] ?? 0) - ($result['spent'] ?? 0)),
            ];
        } catch (\Exception $e) {
            error_log('Erro ao obter resumo de créditos: ' . $e->getMessage());
            return ['purchased' => 0, 'spent' => 0, 'available' => 0];
        }
    }

    /**
     * Obtém histórico de todos os usuários (para admin)
     */
    public static function getAllHistory(string $type = null, int $limit = 100): array
    {
        try {
            $pdo = Database::pdo();
            $sql = '
                SELECT ch.*, u.name as user_name
                FROM credit_history ch
                JOIN users u ON u.id = ch.user_id
            ';
            $params = [];

            if ($type) {
                $sql .= ' WHERE ch.type = :type';
                $params[':type'] = $type;
            }

            $sql .= ' ORDER BY ch.created_at DESC LIMIT :limit';
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            error_log('Erro ao obter histórico geral de créditos: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtém o histórico agregado filtrando por role (ex.: apenas usuários finais 'user')
     */
    public static function getAllHistoryForRole(?string $type = null, int $limit = 100, ?string $role = 'user'): array
    {
        try {
            $pdo = Database::pdo();
            $sql = '
                SELECT ch.*, u.name as user_name, u.user_type as user_role
                FROM credit_history ch
                JOIN users u ON u.id = ch.user_id
            ';
            $clauses = [];
            $params = [];

            if ($type) {
                $clauses[] = 'ch.type = :type';
                $params[':type'] = $type;
            }
            if ($role) {
                $clauses[] = 'u.user_type = :role';
                $params[':role'] = $role;
            }

            if (!empty($clauses)) {
                $sql .= ' WHERE ' . implode(' AND ', $clauses);
            }

            $sql .= ' ORDER BY ch.created_at DESC LIMIT :limit';
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            error_log('Erro ao obter histórico por role: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtém o resumo global (comprados, consumidos, disponível) para um tipo de crédito,
     * opcionalmente filtrando por role do usuário (ex.: 'user').
     */
    public static function getGlobalSummary(string $type, ?string $role = 'user'): array
    {
        try {
            $pdo = Database::pdo();
            $sql = '
                SELECT 
                    COALESCE(SUM(CASE WHEN ch.amount < 0 THEN ABS(ch.amount) ELSE 0 END), 0) as spent,
                    COALESCE(SUM(CASE WHEN ch.amount > 0 THEN ch.amount ELSE 0 END), 0) as purchased
                FROM credit_history ch
                JOIN users u ON u.id = ch.user_id
                WHERE ch.type = :type
            ';
            $params = [':type' => $type];
            if ($role) {
                $sql .= ' AND u.user_type = :role';
                $params[':role'] = $role;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC) ?: ['purchased' => 0, 'spent' => 0];
            $purchased = (int)($result['purchased'] ?? 0);
            $spent = (int)($result['spent'] ?? 0);
            return [
                'purchased' => $purchased,
                'spent' => $spent,
                'available' => $purchased - $spent,
            ];
        } catch (\Exception $e) {
            error_log('Erro ao obter resumo global de créditos: ' . $e->getMessage());
            return ['purchased' => 0, 'spent' => 0, 'available' => 0];
        }
    }
}
