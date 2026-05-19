<?php
/**
 * Репозиторий для работы с медицинскими центрами
 */

namespace App\Repositories;

use App\Exceptions\RepositoryException;

class MedicalCenterRepository extends AbstractRepository
{
    protected string $table = 'medical_centers';
    protected string $primaryKey = 'center_id';
    
    /**
     * Создание нового центра
     */
    public function create(array $data): int
    {
        try {
            $sql = "INSERT INTO `{$this->table}` 
                    (`name`, `address`, `phone`, `email`, `working_hours`, `capacity`) 
                    VALUES 
                    (:name, :address, :phone, :email, :working_hours, :capacity)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'name' => $data['name'],
                'address' => $data['address'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'working_hours' => $data['working_hours'] ?? null,
                'capacity' => $data['capacity'] ?? 50
            ]);
            
            return (int)$this->pdo->lastInsertId();
            
        } catch (\PDOException $e) {
            throw RepositoryException::fromPDOException($e);
        }
    }
    
    /**
     * Обновление данных центра
     */
    public function updateCenter(int $id, array $data): int
    {
        try {
            $allowedFields = ['name', 'address', 'phone', 'email', 'working_hours', 'capacity', 'is_active'];
            
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
     * Получение загруженности центров на дату
     */
    public function getCentersWorkload(string $date): array
    {
        try {
            $sql = "SELECT mc.*,
                    COUNT(a.appointment_id) as appointments_count,
                    (mc.capacity - COUNT(a.appointment_id)) as free_slots
                    FROM `{$this->table}` mc
                    LEFT JOIN `appointments` a ON mc.center_id = a.center_id 
                        AND a.appointment_date = :date 
                        AND a.status IN ('scheduled', 'confirmed')
                    WHERE mc.is_active = TRUE
                    GROUP BY mc.center_id
                    ORDER BY free_slots DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['date' => $date]);
            
            return $stmt->fetchAll();
            
        } catch (\PDOException $e) {
            throw RepositoryException::fromPDOException($e);
        }
    }
    
    /**
     * Поиск центров, где есть свободные слоты
     */
    public function findAvailableCenters(string $date, string $time): array
    {
        try {
            $sql = "SELECT mc.*,
                    (mc.capacity - COUNT(a.appointment_id)) as available_slots
                    FROM `{$this->table}` mc
                    LEFT JOIN `appointments` a ON mc.center_id = a.center_id 
                        AND a.appointment_date = :date 
                        AND a.appointment_time = :time
                        AND a.status IN ('scheduled', 'confirmed')
                    WHERE mc.is_active = TRUE
                    GROUP BY mc.center_id
                    HAVING available_slots > 0
                    ORDER BY available_slots DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'date' => $date,
                'time' => $time
            ]);
            
            return $stmt->fetchAll();
            
        } catch (\PDOException $e) {
            throw RepositoryException::fromPDOException($e);
        }
    }
}
