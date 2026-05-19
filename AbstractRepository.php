<?php
/**
 * Абстрактный класс репозитория с базовыми CRUD операциями
 */

namespace App\Repositories;

use App\Exceptions\RepositoryException;

abstract class AbstractRepository
{
    protected \PDO $pdo;
    protected string $table;
    protected string $primaryKey = 'id';
    
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    /**
     * Получение всех записей с фильтрацией и сортировкой
     * 
     * @param string|null $where
     * @param array $params
     * @param string|null $orderBy
     * @param int|null $limit
     * @param int|null $offset
     * @return array
     * @throws RepositoryException
     */
    public function findAll(
        ?string $where = null,
        array $params = [],
        ?string $orderBy = null,
        ?int $limit = null,
        ?int $offset = null
    ): array {
        try {
            $sql = "SELECT * FROM `{$this->table}`";
            
            if ($where !== null) {
                $sql .= " WHERE $where";
            }
            
            if ($orderBy !== null) {
                $this->validateOrderBy($orderBy);
                $sql .= " ORDER BY $orderBy";
            }
            
            if ($limit !== null) {
                $sql .= " LIMIT " . (int)$limit;
                
                if ($offset !== null) {
                    $sql .= " OFFSET " . (int)$offset;
                }
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
            
        } catch (\PDOException $e) {
            throw RepositoryException::fromPDOException($e);
        }
    }
    
    /**
     * Поиск записи по первичному ключу
     * 
     * @param mixed $id
     * @return array|null
     */
    public function findById($id): ?array
    {
        try {
            $sql = "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $id]);
            
            $result = $stmt->fetch();
            return $result ?: null;
            
        } catch (\PDOException $e) {
            throw RepositoryException::fromPDOException($e);
        }
    }
    
    /**
     * Удаление записи
     * 
     * @param mixed $id
     * @return int
     */
    public function delete($id): int
    {
        try {
            $sql = "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $id]);
            
            return $stmt->rowCount();
            
        } catch (\PDOException $e) {
            throw RepositoryException::fromPDOException($e);
        }
    }
    
    /**
     * Подсчет количества записей
     */
    public function count(?string $where = null, array $params = []): int
    {
        try {
            $sql = "SELECT COUNT(*) FROM `{$this->table}`";
            
            if ($where !== null) {
                $sql .= " WHERE $where";
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return (int)$stmt->fetchColumn();
            
        } catch (\PDOException $e) {
            throw RepositoryException::fromPDOException($e);
        }
    }
    
    /**
     * Проверка существования записи
     */
    public function exists($id): bool
    {
        return $this->findById($id) !== null;
    }
    
    /**
     * Валидация сортировки по белому списку
     */
    protected function validateOrderBy(string $orderBy): void
    {
        $parts = explode(' ', trim($orderBy));
        $field = $parts[0];
        
        $allowedFields = $this->getTableColumns();
        
        if (!in_array($field, $allowedFields)) {
            throw new RepositoryException(
                "Недопустимое поле для сортировки: {$field}"
            );
        }
    }
    
    /**
     * Получение списка колонок таблицы
     */
    protected function getTableColumns(): array
    {
        try {
            $sql = "SHOW COLUMNS FROM `{$this->table}`";
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
        } catch (\PDOException $e) {
            throw RepositoryException::fromPDOException($e);
        }
    }
}
