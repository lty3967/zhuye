<?php
class DBSessionHandler implements SessionHandlerInterface {
    private $pdo;
    private $gc_maxlifetime;
    private $table_name;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->gc_maxlifetime = ini_get('session.gc_maxlifetime');
        
        // 使用配置文件中的表前缀
        $this->table_name = defined('TABLE_PREFIX') ? TABLE_PREFIX . 'sessions' : 'sessions';
        
        // 自动创建会话表（首次运行时）
        $this->createSessionTable();
    }

    private function createSessionTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->table_name}` (
            `id` VARCHAR(128) NOT NULL PRIMARY KEY,
            `data` TEXT,
            `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci";
        
        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("Session table creation failed: " . $e->getMessage());
        }
    }

    public function open($savePath, $sessionName): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read($sessionId): string {
        try {
            $stmt = $this->pdo->prepare("
                SELECT data 
                FROM `{$this->table_name}` 
                WHERE id = ? 
            ");
            
            $stmt->execute([$sessionId]);
            $data = $stmt->fetchColumn();
            
            return $data ?: '';
        } catch (PDOException $e) {
            error_log("Session read error: " . $e->getMessage());
            return '';
        }
    }

    public function write($sessionId, $data): bool {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO `{$this->table_name}` 
                (id, data) 
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE
                    data = VALUES(data),
                    timestamp = CURRENT_TIMESTAMP
            ");
            
            return $stmt->execute([
                $sessionId,
                $data
            ]);
        } catch (PDOException $e) {
            error_log("Session write error: " . $e->getMessage());
            return false;
        }
    }

    public function destroy($sessionId): bool {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM `{$this->table_name}` 
                WHERE id = ?
            ");
            return $stmt->execute([$sessionId]);
        } catch (PDOException $e) {
            error_log("Session destroy error: " . $e->getMessage());
            return false;
        }
    }

    public function gc($maxlifetime): int|false {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM `{$this->table_name}` 
                WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$maxlifetime]);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Session GC error: " . $e->getMessage());
            return false;
        }
    }
}