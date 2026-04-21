<?php
// ============================================================
//  includes/db.php — PDO singleton, PHP 7.1+ compatible
// ============================================================
require_once __DIR__ . '/../config.php';

class DB {
    private static $pdo = null;

    public static function get(): PDO {
        if (self::$pdo === null) {
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => true,
            ];
            if (defined('PDO::MYSQL_ATTR_MAX_BUFFER_SIZE')) {
                $options[PDO::MYSQL_ATTR_MAX_BUFFER_SIZE] = 128 * 1024 * 1024;
            }
            try {
                self::$pdo = new PDO(
                    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
                    DB_USER, DB_PASS, $options
                );
            } catch (PDOException $e) {
                error_log('DB Connection Error: ' . $e->getMessage());
                die('<div style="font-family:sans-serif;padding:40px;color:#c0392b;">'
                  . '<h2>Database Connection Failed</h2>'
                  . '<p>Check credentials in <code>config.php</code>.</p>'
                  . '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>'
                  . '</div>');
            }
        }
        return self::$pdo;
    }

    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Special insert for rows containing BLOB data.
     * Binds each param individually so PDO handles binary correctly.
     * Returns the new row's ID.
     */
    public static function insertWithBlobs(string $sql, array $params): string {
        $pdo  = self::get();
        $stmt = $pdo->prepare($sql);
        foreach ($params as $i => $val) {
            $pos = $i + 1;
            if (is_null($val)) {
                $stmt->bindValue($pos, null, PDO::PARAM_NULL);
            } elseif (is_int($val)) {
                $stmt->bindValue($pos, $val, PDO::PARAM_INT);
            } elseif (is_string($val) && strlen($val) > 1000) {
                // Treat long strings as binary/LOB
                $stmt->bindValue($pos, $val, PDO::PARAM_STR);
            } else {
                $stmt->bindValue($pos, $val, PDO::PARAM_STR);
            }
        }
        $stmt->execute();
        return (string)$pdo->lastInsertId();
    }

    /**
     * Special update for rows containing BLOB data.
     */
    public static function updateWithBlobs(string $sql, array $params): bool {
        $pdo  = self::get();
        $stmt = $pdo->prepare($sql);
        foreach ($params as $i => $val) {
            $pos = $i + 1;
            if (is_null($val)) {
                $stmt->bindValue($pos, null, PDO::PARAM_NULL);
            } elseif (is_int($val)) {
                $stmt->bindValue($pos, $val, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($pos, $val, PDO::PARAM_STR);
            }
        }
        return $stmt->execute();
    }

    public static function row(string $sql, array $params = []): ?array {
        $r = self::query($sql, $params)->fetch();
        return ($r !== false) ? $r : null;
    }

    public static function rows(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll();
    }

    public static function val(string $sql, array $params = []) {
        $r = self::query($sql, $params)->fetchColumn();
        return ($r !== false) ? $r : null;
    }

    public static function lastId(): string {
        return (string)self::get()->lastInsertId();
    }
}
