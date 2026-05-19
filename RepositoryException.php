<?php
/**
 * Пользовательское исключение для репозиториев
 */

namespace App\Exceptions;

class RepositoryException extends \Exception
{
    protected $sqlState;
    protected $errorCode;
    
    public function __construct($message = "", $code = 0, $sqlState = null, $errorCode = null)
    {
        parent::__construct($message, $code);
        $this->sqlState = $sqlState;
        $this->errorCode = $errorCode;
    }
    
    public function getSqlState()
    {
        return $this->sqlState;
    }
    
    public function getErrorCode()
    {
        return $this->errorCode;
    }
    
    public static function fromPDOException(\PDOException $e)
    {
        $message = "Ошибка базы данных: " . $e->getMessage();
        return new self($message, $e->getCode(), $e->errorInfo[0] ?? null, $e->errorInfo[1] ?? null);
    }
}
