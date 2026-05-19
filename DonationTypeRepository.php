<?php
/**
 * Репозиторий для работы с типами донаций
 */

namespace App\Repositories;

use App\Exceptions\RepositoryException;

class DonationTypeRepository extends AbstractRepository
{
    protected string $table = 'donation_types';
    protected string $primaryKey = 'type_id';
    
    /**
     * Создание нового типа донации
     */
    public function create(array $data): int
    {
        try {
            $sql = "INSERT INTO `{$this->table}` 
                    (`name`, `description`, `duration`, `preparation_required`) 
                    VALUES 
                    (:name, :description, :duration, :preparation_required)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'duration' => $data['duration'],
                'preparation_required' => $data['preparation_required'] ?? null
            ]);
            
            return (int)$this->pdo->lastInsertId();
            
        } catch (\PDOException $e) {
            throw RepositoryException::fromPDOException($e);
        }
    }
    
    /**
     * Обновление типа донации
     */
    public function updateType(int $id, array $data): int
    {
        try {
            $allowedFields = ['name', 'description', 'duration', 'preparation_required', 'is_active'];
            
            $updates = [];
            $params = ['id' => $id];
            
            foreach ($data as $field => $value) {
                if (in_array($field, $allowedFields)) {
                    $updates[] = "`$field` = :$field";
                    $params[$field] = $value;
                }
            }
            
            if (empty($updates)) {
                throw new RepositoryException("Нет данных для обновления");
            }
            
            $sql = "UPDATE `{$this->table}` SET " . implode(', ', $updates) . 
                   " WHERE `{$this->primaryKey}` = :id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->rowCount();
            
        } catch (\PDOException $e) {
            throw RepositoryException::fromPDOException($e);
        }
    }
    
    /**
     * Получение статистики по типам донаций
     */
    public function getDonationTypesStats(string $startDate, string $endDate): array
    {
        try {
            $sql = "SELECT dt.*,
                    COUNT(a.appointment_id) as total_appointments,
                    SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_donations
                    FROM `{$this->table}` dt
                    LEFT JOIN `appointments` a ON dt.type_id = a.donation_type_id
                        AND a.appointment_date BETWEEN :start_date AND :end_date
                    WHERE dt.is_active = TRUE
                    GROUP BY dt.type_id
                    ORDER BY completed_donations DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            
            return $stmt->fetchAll();
            
        } catch (\PDOException $e) {
            throw RepositoryException::fromPDOException($e);
        }
    }
}
