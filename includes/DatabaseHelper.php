<?php
/**
 * DatabaseHelper - Safe database operations dengan prepared statements
 * Prevents SQL injection dan provides consistent query interface
 */

class DatabaseHelper {
    private $conn;
    private $lastError;
    
    public function __construct($connection) {
        $this->conn = $connection;
        $this->lastError = null;
    }
    
    /**
     * Get last error message
     */
    public function getLastError() {
        return $this->lastError;
    }
    
    /**
     * Execute SELECT query dengan prepared statement
     * @param string $query SQL query dengan ? placeholders
     * @param array $params Array parameter untuk binding
     * @param string $types String tipe parameter (s=string, i=integer, d=double, b=blob)
     * @return array|false Array hasil atau false jika error
     */
    public function select($query, $params = [], $types = '') {
        try {
            $stmt = $this->conn->prepare($query);
            
            if (!$stmt) {
                $this->lastError = $this->conn->error;
                return false;
            }
            
            if (!empty($params)) {
                if (empty($types)) {
                    $types = str_repeat('s', count($params));
                }
                
                if (!$stmt->bind_param($types, ...$params)) {
                    $this->lastError = $stmt->error;
                    $stmt->close();
                    return false;
                }
            }
            
            if (!$stmt->execute()) {
                $this->lastError = $stmt->error;
                $stmt->close();
                return false;
            }
            
            $result = $stmt->get_result();
            $data = [];
            
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            
            $stmt->close();
            return $data;
            
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }
    
    /**
     * Execute SELECT query dan return single row
     */
    public function selectOne($query, $params = [], $types = '') {
        $result = $this->select($query, $params, $types);
        return ($result && count($result) > 0) ? $result[0] : null;
    }
    
    /**
     * Execute INSERT query
     * @return int|false Last insert ID atau false jika error
     */
    public function insert($query, $params = [], $types = '') {
        try {
            $stmt = $this->conn->prepare($query);
            
            if (!$stmt) {
                $this->lastError = $this->conn->error;
                return false;
            }
            
            if (!empty($params)) {
                if (empty($types)) {
                    $types = str_repeat('s', count($params));
                }
                
                if (!$stmt->bind_param($types, ...$params)) {
                    $this->lastError = $stmt->error;
                    $stmt->close();
                    return false;
                }
            }
            
            if (!$stmt->execute()) {
                $this->lastError = $stmt->error;
                $stmt->close();
                return false;
            }
            
            $lastId = $stmt->insert_id;
            $stmt->close();
            return $lastId;
            
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }
    
    /**
     * Execute UPDATE query
     * @return bool|int Affected rows atau false jika error
     */
    public function update($query, $params = [], $types = '') {
        try {
            $stmt = $this->conn->prepare($query);
            
            if (!$stmt) {
                $this->lastError = $this->conn->error;
                return false;
            }
            
            if (!empty($params)) {
                if (empty($types)) {
                    $types = str_repeat('s', count($params));
                }
                
                if (!$stmt->bind_param($types, ...$params)) {
                    $this->lastError = $stmt->error;
                    $stmt->close();
                    return false;
                }
            }
            
            if (!$stmt->execute()) {
                $this->lastError = $stmt->error;
                $stmt->close();
                return false;
            }
            
            $affectedRows = $stmt->affected_rows;
            $stmt->close();
            return $affectedRows;
            
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }
    
    /**
     * Execute DELETE query
     * @return bool|int Affected rows atau false jika error
     */
    public function delete($query, $params = [], $types = '') {
        return $this->update($query, $params, $types);
    }
    
    /**
     * Execute INSERT/UPDATE/DELETE tanpa return value
     */
    public function execute($query, $params = [], $types = '') {
        try {
            $stmt = $this->conn->prepare($query);
            
            if (!$stmt) {
                $this->lastError = $this->conn->error;
                return false;
            }
            
            if (!empty($params)) {
                if (empty($types)) {
                    $types = str_repeat('s', count($params));
                }
                
                if (!$stmt->bind_param($types, ...$params)) {
                    $this->lastError = $stmt->error;
                    $stmt->close();
                    return false;
                }
            }
            
            if (!$stmt->execute()) {
                $this->lastError = $stmt->error;
                $stmt->close();
                return false;
            }
            
            $stmt->close();
            return true;
            
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }
    
    /**
     * Count rows
     */
    public function count($query, $params = [], $types = '') {
        $result = $this->selectOne($query, $params, $types);
        return $result ? (int)array_values($result)[0] : 0;
    }
    
    /**
     * Start transaction
     */
    public function beginTransaction() {
        return $this->conn->begin_transaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->conn->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->conn->rollback();
    }
}
?>
