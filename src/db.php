<?php
declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

class DB
{
    private static ?PDO $pdo = null;

    public static function connect(array $cfg): void
    {
        if (self::$pdo !== null) return;

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'], $cfg['port'] ?? 3306, $cfg['name'], $cfg['charset']
        );

        try {
            self::$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
        } catch (PDOException $e) {
            error_log('[RentOps] DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            die('Database connection failed. Check your .env configuration.');
        }
    }

    public static function get(): PDO          { return self::$pdo; }

    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function row(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row ?: null;
    }

    public static function rows(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function scalar(string $sql, array $params = []): mixed
    {
        $val = self::query($sql, $params)->fetchColumn();
        return $val === false ? null : $val;
    }

    /**
     * Insert a row and return the last insert ID.
     *
     * FIX B15: For tables with CHAR(36) UUID primary keys, PDO::lastInsertId()
     * returns "0" (not the UUID), because MySQL only tracks auto-increment IDs.
     * This return value must NOT be used as the record ID when the PK is a UUID.
     * Always include the UUID in $data['id'] and reference that variable directly
     * after calling insert(). The return value is only meaningful for auto-increment
     * integer PK tables (none currently exist in this schema).
     *
     * @return string Last auto-increment ID ("0" for UUID-keyed tables — do not use)
     */
    public static function insert(string $table, array $data): string
    {
        $cols    = implode(',', array_map(fn($k) => "`{$k}`", array_keys($data)));
        $holders = implode(',', array_fill(0, count($data), '?'));
        self::query("INSERT INTO `{$table}` ({$cols}) VALUES ({$holders})", array_values($data));
        return self::$pdo->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set  = implode(',', array_map(fn($k) => "`{$k}`=?", array_keys($data)));
        $stmt = self::query(
            "UPDATE `{$table}` SET {$set} WHERE {$where}",
            [...array_values($data), ...$whereParams]
        );
        return $stmt->rowCount();
    }

    public static function beginTransaction(): void  { self::$pdo->beginTransaction(); }
    public static function commit(): void            { self::$pdo->commit(); }
    public static function rollback(): void          { if (self::$pdo->inTransaction()) self::$pdo->rollBack(); }
    public static function lastId(): string          { return self::$pdo->lastInsertId(); }
    public static function inTransaction(): bool     { return self::$pdo->inTransaction(); }
}

// Bootstrap connection
global $config;
\App\DB::connect($config['db']);
