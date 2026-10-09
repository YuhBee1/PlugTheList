<?php
declare(strict_types=1);

namespace PTL;

   
                           
                                                                                                               
                                                                                           
                                                                                            
   
final class DB
{
    private static ?\PDO $pdo = null;
    private static int $depth = 0;
                                    
    private static array $after = [];

    public static function pdo(): \PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }
        $dsn = Env::get('DB_DSN');
        if (!$dsn) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                Env::get('DB_HOST', '127.0.0.1'),
                Env::get('DB_PORT', '3306'),
                Env::must('DB_NAME')
            );
        }
        self::$pdo = new \PDO($dsn, Env::get('DB_USER'), Env::get('DB_PASS'), [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        if (self::$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql') {
            self::$pdo->exec("SET NAMES utf8mb4, time_zone = '+01:00', sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'");
        }
        return self::$pdo;
    }

    public static function driver(): string
    {
        return (string)self::pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

                                                                                                                
    public static function forUpdate(): string
    {
        return self::driver() === 'mysql' ? ' FOR UPDATE' : '';
    }

       
                                                                                                  
                                     
       
    public static function insertIgnore(string $table, array $d, bool $stamp = true): bool
    {
        self::ident($table);
        if ($stamp && !array_key_exists('created_at', $d)) {
            $d['created_at'] = now();
        }
        $cols = array_map([self::class, 'ident'], array_keys($d));
        $verb = self::driver() === 'mysql' ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
        $sql = $verb . ' INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
        return self::run($sql, array_values($d)) === 1;
    }

                       
    public static function reset(): void
    {
        self::$pdo = null;
        self::$depth = 0;
        self::$after = [];
    }

    private static function ident(string $s): string
    {
        if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $s)) {
            throw new \InvalidArgumentException('Bad identifier');
        }
        return $s;
    }

                                             
    private static function bind(array $p): array
    {
        return array_map(static fn($v) => is_bool($v) ? (int)$v : $v, array_values($p));
    }

                                             
    public static function run(string $sql, array $p = []): int
    {
        $st = self::pdo()->prepare($sql);
        $st->execute(self::bind($p));
        return $st->rowCount();
    }

                                             
    public static function one(string $sql, array $p = []): ?array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute(self::bind($p));
        $r = $st->fetch();
        return $r === false ? null : $r;
    }

                                             
    public static function all(string $sql, array $p = []): array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute(self::bind($p));
        return $st->fetchAll();
    }

                                             
    public static function val(string $sql, array $p = []): mixed
    {
        $st = self::pdo()->prepare($sql);
        $st->execute(self::bind($p));
        $r = $st->fetchColumn();
        return $r === false ? null : $r;
    }

                                         
    public static function insert(string $table, array $d, bool $stamp = true): int
    {
        self::ident($table);
        if ($stamp && !array_key_exists('created_at', $d)) {
            $d['created_at'] = now();
        }
        $cols = array_map([self::class, 'ident'], array_keys($d));
        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
        self::run($sql, array_values($d));
        return (int)self::pdo()->lastInsertId();
    }

       
                                     
                                              
       
    public static function update(string $table, array $d, string $where, array $params = [], bool $stamp = true): int
    {
        self::ident($table);
        if ($stamp && !array_key_exists('updated_at', $d)) {
            $d['updated_at'] = now();
        }
        $sets = [];
        foreach (array_keys($d) as $c) {
            $sets[] = '`' . self::ident($c) . '` = ?';
        }
        return self::run('UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE ' . $where, array_merge(array_values($d), array_values($params)));
    }

    public static function inTx(): bool
    {
        return self::$depth > 0;
    }

    public static function tx(callable $fn): mixed
    {
        $pdo = self::pdo();
        if (self::$depth === 0) {
            $pdo->beginTransaction();
        }
        self::$depth++;
        try {
            $r = $fn();
            self::$depth--;
            if (self::$depth === 0) {
                $pdo->commit();
                $cbs = self::$after;
                self::$after = [];
                foreach ($cbs as $cb) {
                    try {
                        $cb();
                    } catch (\Throwable $e) {
                        error_log('[afterCommit] ' . $e->getMessage());
                    }
                }
            }
            return $r;
        } catch (\Throwable $e) {
            self::$depth--;
            if (self::$depth === 0) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                self::$after = [];
            }
            throw $e;
        }
    }

                                                                                                                  
    public static function afterCommit(callable $cb): void
    {
        if (self::$depth === 0) {
            try {
                $cb();
            } catch (\Throwable $e) {
                error_log('[afterCommit] ' . $e->getMessage());
            }
            return;
        }
        self::$after[] = $cb;
    }
}
