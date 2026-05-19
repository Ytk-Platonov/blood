<?php
/**
 * Класс для управления подключением к базе данных
 * Реализует паттерн Singleton для единственного соединения
 */

namespace App\Database;

use App\Exceptions\RepositoryException;

class Database
{
    private static $instance = null;
    private $connection;
    
    private function __construct()
    {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            
            $this->connection = new \PDO(
                $dsn,
                DB_USER,
                DB_PASS,
                DB_OPTIONS
            );
            
        } catch (\PDOException $e) {
            throw RepositoryException::fromPDOException($e);
        }
    }
    
    private function __clone() {}
    
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    public function getConnection(): \PDO
    {
        return $this->connection;
    }
}
