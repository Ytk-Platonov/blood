<?php
/**
 * Демонстрационный скрипт работы DAL
 * Система онлайн-записи на донорство крови
 */

// Автозагрузка классов
spl_autoload_register(function ($className) {
    $className = str_replace('App\\', '', $className);
    $className = str_replace('\\', '/', $className);
    $file = __DIR__ . '/src/' . $className . '.php';
    
    if (file_exists($file)) {
        require_once $file;
    }
});

// Подключаем конфигурацию
require_once __DIR__ . '/config.php';

use App\Database\Database;
use App\Repositories\DonorRepository;
use App\Repositories\MedicalCenterRepository;
use App\Repositories\DonationTypeRepository;
use App\Repositories\AppointmentRepository;
use App\Exceptions\RepositoryException;

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Демонстрация DAL - Донорство крови</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #c41e3a;
            border-bottom: 2px solid #c41e3a;
            padding-bottom: 10px;
        }
        h2 {
            color: #555;
            margin-top: 30px;
        }
        .success {
            color: #155724;
            background-color: #d4edda;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .error {
            color: #721c24;
            background-color: #f8d7da;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        pre {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #c41e3a;
            overflow-x: auto;
        }
        .demo-section {
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🩸 Демонстрация уровня доступа к данным</h1>
        <p>Система онлайн-записи на донорство крови</p>
        
        <?php
        try {
            // Инициализация подключения к БД
            $database = Database::getInstance();
            $pdo = $database->getConnection();
            echo "<div class='success'>✓ Подключение к базе данных установлено</div>";
            
            // Создание репозиториев
            $donorRepo = new DonorRepository($pdo);
            $centerRepo = new MedicalCenterRepository($pdo);
            $typeRepo = new DonationTypeRepository($pdo);
            $appointmentRepo = new AppointmentRepository($pdo);
            
            echo "<div class='success'>✓ Все репозитории созданы</div>";
            
            // 1. Работа с донорами
            echo "<div class='demo-section'>";
            echo "<h2>1. Регистрация нового донора</h2>";
            
            $donorId = $donorRepo->register([
                'first_name' => 'Иван',
                'last_name' => 'Петров',
                'patronymic' => 'Сергеевич',
                'birth_date' => '1990-05-15',
                'gender' => 'male',
                'blood_type' => 'II',
                'rh_factor' => 'positive',
                'phone' => '+7(999)123-45-67',
                'email' => 'ivan@example.com',
                'address' => 'г. Москва, ул. Примерная, д. 1'
            ]);
            
            echo "<div class='success'>✓ Зарегистрирован новый донор с ID: {$donorId}</div>";
            
            // Получение данных донора
            $donor = $donorRepo->findById($donorId);
            echo "<pre>" . print_r($donor, true) . "</pre>";
            echo "</div>";
            
            // 2. Поиск доноров по группе крови
            echo "<div class='demo-section'>";
            echo "<h2>2. Поиск доноров с кровью II+</h2>";
            
            $donors = $donorRepo->findByBloodType('II', 'positive');
            echo "<pre>Найдено доноров: " . count($donors) . "\n" . print_r($donors, true) . "</pre>";
            echo "</div>";
            
            // 3. Работа с медицинскими центрами
            echo "<div class='demo-section'>";
            echo "<h2>3. Создание медицинского центра</h2>";
            
            $centerId = $centerRepo->create([
                'name' => 'Центр крови №1',
                'address' => 'г. Москва, ул. Донорская, д. 10',
                'phone' => '+7(495)111-22-33',
                'email' => 'center1@blood.ru',
                'working_hours' => 'Пн-Пт: 8:00-20:00, Сб: 9:00-15:00',
                'capacity' => 100
            ]);
            
            echo "<div class='success'>✓ Создан медицинский центр с ID: {$centerId}</div>";
            echo "</div>";
            
            // 4. Создание типа донации
            echo "<div class='demo-section'>";
            echo "<h2>4. Создание типа донации</h2>";
            
            $typeId = $typeRepo->create([
                'name' => 'Цельная кровь',
                'description' => 'Стандартная донация цельной крови',
                'duration' => 30,
                'preparation_required' => 'Не есть жирную пищу за 24 часа, выспаться'
            ]);
            
            echo "<div class='success'>✓ Создан тип донации с ID: {$typeId}</div>";
            echo "</div>";
            
            // 5. Создание записи на донацию
            echo "<div class='demo-section'>";
            echo "<h2>5. Запись на донацию</h2>";
            
            $appointmentId = $appointmentRepo->createAppointment([
                'donor_id' => $donorId,
                'center_id' => $centerId,
                'donation_type_id' => $typeId,
                'appointment_date' => date('Y-m-d', strtotime('+3 days')),
                'appointment_time' => '10:00',
                'notes' => 'Первый раз сдает кровь'
            ]);
            
            echo "<div class='success'>✓ Создана запись на донацию с ID: {$appointmentId}</div>";
            
            // Получение записи
            $appointment = $appointmentRepo->findById($appointmentId);
            echo "<pre>" . print_r($appointment, true) . "</pre>";
            echo "</div>";
            
            // 6. Обновление статуса записи
            echo "<div class='demo-section'>";
            echo "<h2>6. Подтверждение записи</h2>";
            
            $updated = $appointmentRepo->updateStatus($appointmentId, 'confirmed', 'Запись подтверждена по телефону');
            echo "<div class='success'>✓ Статус записи обновлен. Затронуто строк: {$updated}</div>";
            
            $appointment = $appointmentRepo->findById($appointmentId);
            echo "<pre>" . print_r($appointment, true) . "</pre>";
            echo "</div>";
            
            // 7. Получение записей на дату
            echo "<div class='demo-section'>";
            echo "<h2>7. Записи на " . date('Y-m-d', strtotime('+3 days')) . "</h2>";
            
            $appointments = $appointmentRepo->getAppointmentsByDate(date('Y-m-d', strtotime('+3 days')));
            echo "<pre>Найдено записей: " . count($appointments) . "\n" . print_r($appointments, true) . "</pre>";
            echo "</div>";
            
            // 8. Статистика донора
            echo "<div class='demo-section'>";
            echo "<h2>8. Статистика донора</h2>";
            
            $stats = $donorRepo->getDonorStatistics($donorId);
            echo "<pre>" . print_r($stats, true) . "</pre>";
            echo "</div>";
            
            // 9. Загруженность центров
            echo "<div class='demo-section'>";
            echo "<h2>9. Загруженность центров</h2>";
            
            $workload = $centerRepo->getCentersWorkload(date('Y-m-d', strtotime('+3 days')));
            echo "<pre>" . print_r($workload, true) . "</pre>";
            echo "</div>";
            
            // 10. Удаление тестовой записи
            echo "<div class='demo-section'>";
            echo "<h2>10. Удаление тестовой записи</h2>";
            
            $deleted = $appointmentRepo->delete($appointmentId);
            echo "<div class='success'>✓ Запись удалена. Затронуто строк: {$deleted}</div>";
            echo "</div>";
            
            echo "<div class='success'>";
            echo "<h3>🎉 Демонстрация успешно завершена!</h3>";
            echo "<p>Все операции CRUD выполнены корректно.</p>";
            echo "</div>";
            
        } catch (RepositoryException $e) {
            echo "<div class='error'>";
            echo "<h3>❌ Ошибка репозитория:</h3>";
            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
            if ($e->getSqlState()) {
                echo "<p>SQL State: " . htmlspecialchars($e->getSqlState()) . "</p>";
            }
            echo "</div>";
            
        } catch (\Exception $e) {
            echo "<div class='error'>";
            echo "<h3>❌ Общая ошибка:</h3>";
            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
            echo "</div>";
        }
        ?>
    </div>
</body>
</html>
