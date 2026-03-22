<?php
class DBSessionHandler implements SessionHandlerInterface {
    private $pdo;
    private $gc_maxlifetime;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->gc_maxlifetime = (int)ini_get('session.gc_maxlifetime') ?: 1440;
        $this->createSessionTable();
    }

    private function createSessionTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `sessions` (
            `session_id` VARCHAR(128) NOT NULL PRIMARY KEY,
            `data` TEXT NOT NULL,
            `user_agent` VARCHAR(255) NOT NULL,
            `ip_address` VARCHAR(45) NOT NULL,
            `expires` INT UNSIGNED NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (`expires`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
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
                FROM sessions 
                WHERE session_id = ? 
                AND expires > ?
                AND user_agent = ?
                AND ip_address = ?
            ");
            
            $stmt->execute([
                $sessionId,
                time(),
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
            
            return $stmt->fetchColumn() ?: '';
        } catch (PDOException $e) {
            error_log("Session read error: " . $e->getMessage());
            return '';
        }
    }

    public function write($sessionId, $data): bool {
        try {
            $expires = time() + $this->gc_maxlifetime;
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

            $stmt = $this->pdo->prepare("
                INSERT INTO sessions 
                (session_id, data, user_agent, ip_address, expires)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                data = VALUES(data),
                expires = VALUES(expires),
                user_agent = VALUES(user_agent),
                ip_address = VALUES(ip_address)
            ");
            
            return $stmt->execute([
                $sessionId,
                $data,
                $userAgent,
                $ipAddress,
                $expires
            ]);
        } catch (PDOException $e) {
            error_log("Session write error: " . $e->getMessage());
            return false;
        }
    }

    public function destroy($sessionId): bool {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE session_id = ?");
            return $stmt->execute([$sessionId]);
        } catch (PDOException $e) {
            error_log("Session destroy error: " . $e->getMessage());
            return false;
        }
    }

    public function gc($maxlifetime) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE expires < ?");
            $stmt->execute([time()]);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Session GC error: " . $e->getMessage());
            return false;
        }
    }
}
