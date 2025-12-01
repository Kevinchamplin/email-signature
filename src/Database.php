<?php
namespace Ironcrest\Signature;

use PDO;
use PDOException;

/**
 * Database Connection Handler
 */
class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct($config) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['name'],
            $config['charset']
        );
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        try {
            $this->pdo = new PDO($dsn, $config['username'], $config['password'], $options);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new \Exception('Database connection failed');
        }
    }
    
    public static function getInstance($config = null) {
        if (self::$instance === null) {
            if ($config === null) {
                throw new \Exception('Database config required for first initialization');
            }
            self::$instance = new self($config);
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function insert($table, $data) {
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $fields),
            implode(', ', $placeholders)
        );
        
        $this->query($sql, array_values($data));
        return $this->pdo->lastInsertId();
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        $sets = [];
        $params = [];
        
        foreach ($data as $field => $value) {
            if ($value instanceof DatabaseRaw) {
                $sets[] = "$field = " . $value->getValue();
            } else {
                $sets[] = "$field = ?";
                $params[] = $value;
            }
        }
        
        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode(', ', $sets),
            $where
        );
        
        $params = array_merge($params, $whereParams);
        return $this->query($sql, $params);
    }
    
    public static function raw($value) {
        return new DatabaseRaw($value);
    }
    
    public function delete($table, $where, $params = []) {
        $sql = sprintf('DELETE FROM %s WHERE %s', $table, $where);
        return $this->query($sql, $params);
    }
    
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    public function commit() {
        return $this->pdo->commit();
    }
    
    public function rollback() {
        return $this->pdo->rollBack();
    }
}

/**
 * DatabaseRaw class for raw SQL expressions
 */
class DatabaseRaw {
    private $value;
    
    public function __construct($value) {
        $this->value = $value;
    }
    
    public function getValue() {
        return $this->value;
    }
}
