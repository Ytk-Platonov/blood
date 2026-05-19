<?php
/**
 * Репозиторий для работы с записями на донацию крови
 */

namespace App\Repositories;

use App\Exceptions\RepositoryException;

class AppointmentRepository extends AbstractRepository
{
    protected string $table = 'appointments';
    protected string $primaryKey = 'appointment_id';
    
    /**
     * Получение записей на определенную дату
     */
    public function getAppointmentsByDate(string $date, ?string $status = null): array
    {
        try {
            $sql = "SELECT a.*, 
                    CONCAT(d.first_name, ' ', d.last_name, 
                        CASE WHEN d.patronymic IS NOT NULL THEN CONCAT(' ', d.patronymic) ELSE '' END
                    ) as donor_full_name,
                    d.blood_type, d.rh_factor,
                    mc.name as center_name,
                    dt.name as donation_type_name
                    FROM `{$this->table}` a
                    JOIN `donors` d ON a.donor_id = d.donor_id
                    JOIN `medical_centers` mc ON a.center_id = mc.center_id
                    JOIN `donation_types` dt ON a.donation_type_id = dt.type_id
                    WHERE a.appointment_date = :date";
            
            $params = ['date' => $date];
            
            if ($status !== null) {
                $sql .= " AND a.status = :status";
                $params['status'] = $status;
            }
            
            $sql .= " ORDER BY a.appointment_time";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
            
        } catch (\PDOException $e) {
            throw RepositoryException::fromPDOException($e);
        }
    }
    
    /**
     * Создание новой записи на донацию с транзакцией
     */
    public function createAppointment(array $data): int
    {
        try {
            $this->pdo->beginTransaction();
            
            // Проверяем существование донора, центра и типа донации
            $donorRepo = new DonorRepository($this->pdo);
            $donor = $donorRepo->findById($data['donor_id']);
            if (!$donor) {
                throw new RepositoryException("Донор не найден");
            }
            
            // Проверяем, не записан ли донор уже на эту дату
            $existingAppointment = $this->findDonorAppointmentByDate(
                $data['donor_id'], 
                $data['appointment_date']
            );
            
            if ($existingAppointment) {
                throw new RepositoryException("Донор уже записан на эту дату");
            }
            
            // Проверяем доступность слота в центре
            $centerRepo = new MedicalCenterRepository($this->pdo);
            $availableCenters = $centerRepo->findAvailableCenters(
                $data['appointment_date'],
                $data['appointment_time']
            );
            
            $centerAvailable = false;
            foreach ($availableCenters as $center) {
                if ($center['center_id'] == $data['center_id']) {
                    $centerAvailable = true;
                    break;
                }
            }
            
            if (!$centerAvailable) {
                throw new RepositoryException("Нет свободных мест в выбранном центре на это время");
            }
            
            // Проверяем медицинские ограничения
            if ($donor['medical_restrictions']) {
                throw new RepositoryException(
                    "Донор имеет медицинские ограничения: " . $donor['medical_restrictions']
                );
            }
            
            // Создаем запись
            $sql = "INSERT INTO `{$this->table}` 
                    (`donor_id`, `center_id`, `donation_type_id`, `appointment_date`, 
                     `appointment_time`, `status`, `notes`) 
                    VALUES 
                    (:donor_id, :center_id, :donation_type_id, :appointment_date, 
                     :appointment_time, :status, :notes)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'donor_id' => $data['donor_id'],
                'center_id' => $data['center_id'],
                'donation_type_id' => $data['donation_type_id'],
                'appointment_date' => $data['appointment_date'],
                'appointment_time' => $data['appointment_time'],
                'status' => $data['status'] ?? 'scheduled',
                'notes' => $data['notes'] ?? null
            ]);
            
            $appointmentId = (int)$this->pdo->lastInsertId();
            
            $this->pdo->commit();
            
            return $appointmentId;
            
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw RepositoryException::fromPDOException($e);
        } catch (RepositoryException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Обновление статуса записи
     */
    public function updateStatus(int $id, string $status, ?string $notes = null): int
    {
        try {
            $allowedStatuses = ['scheduled', 'confirmed', 'completed', 'cancelled', 'no_show', 'deferred'];
            
            if (!in_array($status, $allowedStatuses)) {
                throw new RepositoryException("Недопустимый статус: {$status}");
            }
            
            $sql = "UPDATE `{$this->table}` 
                    SET `status` = :status";
            
            $params = ['status' => $status, 'id' => $id];
            
            if ($notes !== null) {
                $sql .= ", `notes` = CONCAT(IFNULL(`notes`, ''), :notes)";
                $params['notes'] = "\n" . date('Y-m-d H:i:s') . ": " . $notes;
            }
            
            $sql .= " WHERE `{$this->primaryKey}` = :id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->rowCount();
            
        } catch (\PDOException $e) {
            throw RepositoryException::fromPDOException($e);
        }
    }
    
    /**
     * Получение записей донора
     */
    public function getDonorAppointments(int $donorId, ?string $status = null): array
    {
        try {
            $sql = "SELECT a.*,
                    mc.name as center_name,
                    mc.address as center_address,
                    dt.name as donation_type_name,
                    dt.duration as donation_duration
                    FROM `{$this->table}` a
                    JOIN `medical_centers` mc ON a.center_id = mc.center_id
                    JOIN `donation_types` dt ON a.donation_type_id = dt.type_id
                    WHERE a.donor_id = :donor_id";
            
            $params = ['donor_id' => $donorId];
            
            if ($status !== null) {
                $sql .= " AND a.status = :status";
                $params['status'] = $status;
            }
            
            $sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
            
        } catch (\PDOException $e) {
            throw RepositoryException::fromPDOException($e);
        }
    }
    
    /**
     * Поиск записи донора на дату
     */
    private function findDonorAppointmentByDate(int $donorId, string $date): ?array
    {
        try {
            $sql = "SELECT * FROM `{$this->table}` 
                    WHERE `donor_id` = :donor_id 
                    AND `appointment_date` = :date 
                    AND `status` NOT IN ('cancelled', 'no_show')
                    LIMIT 1";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'donor_id' => $donorId,
                'date' => $date
            ]);
            
            return $stmt->fetch() ?: null;
            
        } catch (\PDOException $e) {
            throw RepositoryException::fromPDOException($e);
        }
    }
    
    /**
     * Получение статистики донаций за период
     */
    public function getStatistics(string $startDate, string $endDate): array
    {
        try {
            $sql = "SELECT 
                    COUNT(*) as total_appointments,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                    SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show,
                    SUM(CASE WHEN status = 'deferred' THEN 1 ELSE 0 END) as deferred,
                    COUNT(DISTINCT donor_id) as unique_donors,
                    COUNT(DISTINCT center_id) as active_centers,
                    GROUP_CONCAT(DISTINCT d.blood_type, ' ', d.rh_factor) as blood_types_collected
                    FROM `{$this->table}` a
                    LEFT JOIN `donors` d ON a.donor_id = d.donor_id
                    WHERE a.appointment_date BETWEEN :start_date AND :end_date";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            
           
