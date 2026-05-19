<?php
/**
 * Репозиторий для работы с донорами
 */

namespace App\Repositories;

use App\Exceptions\RepositoryException;

class DonorRepository extends AbstractRepository
{
    protected string $table = 'donors';
    protected string $primaryKey = 'donor_id';
    
    /**
     * Поиск донора по телефону
     */
    public function findByPhone(string $phone): ?array
    {
        try {
            $sql = "SELECT * FROM `{$this->table}` WHERE `phone` = :phone";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['phone' => $phone]);
            
            return $stmt->fetch() ?: null;
            
        } catch (\PDOException $e) {
            throw RepositoryException::fromPDOException($e);
        }
    }
    
    /**
     * Поиск донора по email
     */
    public function findByEmail(string $email): ?array
    {
        try {
            $sql = "SELECT * FROM `{$this->table}` WHERE `email` = :email";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['email' => $email]);
            
            return $stmt->fetch() ?: null;
            
        } catch (\PDOException $e) {
            throw RepositoryException::fromPDOException($e);
        }
    }
    
    /**
     * Регистрация нового донора
     */
    public function register(array $data): int
    {
        try {
            // Проверка на дубликаты
            if ($this->findByPhone($data['phone'])) {
                throw new RepositoryException("Донор с таким телефоном уже зарегистрирован");
            }
            
            if (isset($data['email']) && $this->findByEmail($data['email'])) {
                throw new RepositoryException("Донор с таким email уже зарегистрирован");
            }
            
            $sql = "INSERT INTO `{$this->table}` 
                    (`first_name`, `last_name`, `patronymic`, `birth_date`, `gender`, 
                     `blood_type`, `rh_factor`, `phone`, `email`, `address`, `medical_restrictions`) 
                    VALUES 
                    (:first_name, :last_name, :patronymic, :birth_date, :gender,
                     :blood_type, :rh_factor, :phone, :email, :address, :medical_restrictions)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'patronymic' => $data['patronymic'] ?? null,
                'birth_date' => $data['birth_date'],
                'gender' => $data['gender'],
                'blood_type' => $data['blood_type'],
                'rh_factor' => $data['rh_factor'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'medical_restrictions' => $data['medical_restrictions'] ?? null
            ]);
            
            return (int)$this->pdo->lastInsertId();
            
        } catch (\PDOException $e) {
            throw RepositoryException::fromPDOException($e);
        }
    }
    
    /**
     * Обновление данных донора
     */
    public function updateDonor(int $id, array $data): int
    {
        try {
            $allowedFields = [
                'first_name', 'last_name', 'patronymic', 'phone', 'email',
                'address', 'medical_restrictions', 'is_active'
            ];
            
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
     * Поиск доноров по группе крови
     */
    public function findByBloodType(string $bloodType, string $rhFactor): array
    {
        try {
            $sql = "SELECT * FROM `{$this->table}` 
                    WHERE `blood_type` = :blood_type 
                    AND `rh_factor` = :rh_factor 
                    AND `is_active` = TRUE";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'blood_type' => $bloodType,
                'rh_factor' => $rhFactor
            ]);
            
            return $stmt->fetchAll();
            
        } catch (\PDOException $e) {
            throw RepositoryException::fromPDOException($e);
        }
    }
    
    /**
     * Получение доноров, которые могут сдать кровь сегодня
     * (проверка, что прошло не менее 60 дней с последней донации)
     */
    public function getEligibleDonors(): array
    {
        try {
            $sql = "SELECT d.*, 
                    MAX(a.appointment_date) as last_donation_date,
                    DATEDIFF(CURRENT_DATE, MAX(a.appointment_date)) as days_since_last_donation
                    FROM `{$this->table}` d
                    LEFT JOIN `appointments` a ON d.donor_id = a.donor_id 
                        AND a.status = 'completed'
                    WHERE d.is_active = TRUE
                    GROUP BY d.donor_id
                    HAVING days_since_last_donation >= 60 OR days_since_last_donation IS NULL
                    ORDER BY d.blood_type, d.rh_factor";
            
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll();
            
        } catch (\PDOException $e) {
            throw RepositoryException::fromPDOException($e);
        }
    }
    
    /**
     * Получение статистики донора
     */
    public function getDonorStatistics(int $donorId): ?array
    {
        try {
            $sql = "SELECT 
                    d.*,
                    COUNT(a.appointment_id) as total_donations,
                    SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_donations,
                    SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_donations,
                    MAX(CASE WHEN a.status = 'completed' THEN a.appointment_date END) as last_donation,
                    dt.name as last_donation_type
                    FROM `{$this->table}` d
                    LEFT JOIN `appointments` a ON d.donor_id = a.donor_id
                    LEFT JOIN `donation_types` dt ON a.donation_type_id = dt.type_id
                        AND a.appointment_date = (
                            SELECT MAX(appointment_date) 
                            FROM appointments 
                            WHERE donor_id = :donor_id AND status = 'completed'
                        )
                    WHERE d.donor_id = :donor_id
                    GROUP BY d.donor_id, dt.name";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['donor_id' => $donorId]);
            
            return $stmt->fetch() ?: null;
            
        } catch (\PDOException $e) {
            throw RepositoryException::fromPDOException($e);
        }
    }
}
