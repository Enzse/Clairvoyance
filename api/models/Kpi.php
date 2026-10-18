<?php

namespace Models;

use PDO;
use PDOException;

class Kpi
{
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    // CRUD Operations
    public function create(array $data, int $userId): array
    {
        try {
            $stmt = $this->db->prepare('
                INSERT INTO kpis (name, direction, target, rag_red, rag_amber, format_prefix, format_suffix, user_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $data['name'], 
                $data['direction'], 
                $data['target'], 
                $data['rag_red'], 
                $data['rag_amber'], 
                $data['format_prefix'], 
                $data['format_suffix'], 
                $userId
            ]);
            return ['success' => true, 'id' => $this->db->lastInsertId()];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function findById(int $id): ?array
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM kpis WHERE id = ?');
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }
    
    public function findByUserId(int $userId): array
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM kpis WHERE user_id = ? ORDER BY created_at DESC');
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function findAll(): array
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM kpis ORDER BY created_at DESC');
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function update(int $id, array $data): bool
    {
        try {
            $fields = [];
            $params = [];
            
            $allowedFields = ['name', 'direction', 'target', 'rag_red', 'rag_amber', 'format_prefix', 'format_suffix'];
            
            foreach ($data as $key => $value) {
                if (in_array($key, $allowedFields)) {
                    $fields[] = "$key = ?";
                    $params[] = $value;
                }
            }
            
            if (empty($fields)) {
                return false;
            }
            
            $params[] = $id;
            $sql = 'UPDATE kpis SET ' . implode(', ', $fields) . ', updated_at = CURRENT_TIMESTAMP WHERE id = ?';
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public function delete(int $id): bool
    {
        try {
            $this->db->beginTransaction();
            
            // Delete all entries for this KPI first to maintain foreign key constraints
            $stmt = $this->db->prepare('DELETE FROM kpi_entries WHERE kpi_id = ?');
            $stmt->execute([$id]);

            // Delete the KPI itself
            $stmt = $this->db->prepare('DELETE FROM kpis WHERE id = ?');
            $stmt->execute([$id]);

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            return false;
        }
    }
    
    // Data Validation Methods
    public function validateName(string $name): bool
    {
        return !empty(trim($name)) && strlen($name) <= 255;
    }
    
    public function validateDirection(string $direction): bool
    {
        return in_array($direction, ['higher_is_better', 'lower_is_better']);
    }
    
    public function validateTarget(float $target): bool
    {
        return is_numeric($target) && $target >= 0;
    }
    
    public function validateRagThresholds(float $ragRed, float $ragAmber): bool
    {
        return $ragRed != $ragAmber && $ragRed >= 0 && $ragAmber >= 0;
    }
    
    public function validateFormatPrefix(string $prefix): bool
    {
        return strlen($prefix) <= 10;
    }
    
    public function validateFormatSuffix(string $suffix): bool
    {
        return strlen($suffix) <= 10;
    }
    
    // RAG Status Calculation (Data Logic Only)
    public function calculateRagStatus(float $value, string $direction, float $ragRed, float $ragAmber): string
    {
        if ($direction === 'higher_is_better') {
            if ($value >= $ragRed) return 'green';
            if ($value >= $ragAmber) return 'amber';
            return 'red';
        } else {
            if ($value <= $ragRed) return 'green';
            if ($value <= $ragAmber) return 'amber';
            return 'red';
        }
    }
    
    public function formatValue(float $value, string $prefix = '', string $suffix = ''): string
    {
        return $prefix . number_format($value, 2) . $suffix;
    }
    
    // Utility Methods
    public function exists(int $id): bool
    {
        try {
            $stmt = $this->db->prepare('SELECT 1 FROM kpis WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public function getOwner(int $kpiId): ?array
    {
        try {
            $stmt = $this->db->prepare('
                SELECT u.id, u.email, u.role 
                FROM kpis k 
                JOIN users u ON k.user_id = u.id 
                WHERE k.id = ?
            ');
            $stmt->execute([$kpiId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Highest permission a user holds on a dashboard that shows this KPI.
     * Only dashboards owned by the KPI's owner count, so nobody can gain access
     * to someone else's KPI by putting its id into their own dashboard layout.
     */
    public function getDashboardPermissionLevel(int $kpiId, int $userId): ?string
    {
        try {
            $stmt = $this->db->prepare('
                SELECT d.layout, da.permission_level
                FROM kpis k
                JOIN dashboards d ON d.user_id = k.user_id
                JOIN dashboard_access da ON da.dashboard_id = d.id AND da.user_id = ?
                WHERE k.id = ?
            ');
            $stmt->execute([$userId, $kpiId]);

            $levels = ['viewer' => 1, 'editor' => 2, 'owner' => 3];
            $best = null;
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $layout = json_decode($row['layout'], true);
                if (!is_array($layout)) {
                    continue;
                }
                foreach ($layout as $widget) {
                    if (isset($widget['kpi_id']) && (int)$widget['kpi_id'] === $kpiId) {
                        $level = $row['permission_level'];
                        if ($best === null || ($levels[$level] ?? 0) > $levels[$best]) {
                            $best = $level;
                        }
                        break;
                    }
                }
            }
            return $best;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function getStats(int $userId): array
    {
        try {
            $stmt = $this->db->prepare('
                SELECT
                    COUNT(*) as total_kpis,
                    COUNT(CASE WHEN direction = "higher_is_better" THEN 1 END) as higher_is_better_count,
                    COUNT(CASE WHEN direction = "lower_is_better" THEN 1 END) as lower_is_better_count,
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as recent_kpis
                FROM kpis 
                WHERE user_id = ?
            ');
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function searchByName(string $name, int $userId): array
    {
        try {
            $stmt = $this->db->prepare('
                SELECT * FROM kpis 
                WHERE user_id = ? AND name LIKE ? 
                ORDER BY name ASC
            ');
            $stmt->execute([$userId, "%{$name}%"]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function getKpisByDirection(string $direction, int $userId): array
    {
        try {
            $stmt = $this->db->prepare('
                SELECT * FROM kpis 
                WHERE user_id = ? AND direction = ? 
                ORDER BY created_at DESC
            ');
            $stmt->execute([$userId, $direction]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
} 