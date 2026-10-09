<?php
declare(strict_types=1);

namespace PTL;

final class Migrator
{
                                               
    public static function run(): array
    {
        $log = [];
        $schema = require PTL_ROOT . '/database/schema.php';
        $mysql = DB::driver() === 'mysql';
        $pk = $mysql ? 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $engine = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        foreach ($schema['tables'] as $name => $sql) {
            $sql = str_replace(['{PK}', '{ENGINE}'], [$pk, $engine], $sql);
            if (!$mysql) {
                $sql = preg_replace('/\bINT UNSIGNED\b/', 'INT', $sql) ?? $sql;
            }
            DB::pdo()->exec($sql);
            $log[] = "table $name ok";
        }
        foreach ($schema['columns'] as $table => $cols) {
            foreach ($cols as $col => $def) {
                if (self::ensureColumn($table, $col, $def)) {
                    $log[] = "added $table.$col";
                }
            }
        }
        foreach ($schema['indexes'] as [$name, $table, $cols, $unique]) {
            try {
                DB::pdo()->exec(($unique ? 'CREATE UNIQUE INDEX ' : 'CREATE INDEX ') . $name . ' ON ' . $table . ' (' . $cols . ')');
                $log[] = "index $name created";
            } catch (\Throwable $e) {
                                 
            }
        }
        foreach (Settings::DEFAULTS as $k => $v) {
            DB::insertIgnore('settings', ['k' => $k, 'v' => $v, 'updated_at' => now()], false);
        }
        DB::insertIgnore('migrations', ['version' => 1, 'applied_at' => now()], false);
        Settings::flush();
        return $log;
    }

    public static function ensureColumn(string $table, string $col, string $def): bool
    {
        if (!preg_match('/^[a-z_]+$/', $table . $col)) {
            throw new \InvalidArgumentException('bad identifier');
        }
        if (DB::driver() === 'mysql') {
            $has = DB::one('SHOW COLUMNS FROM `' . $table . '` LIKE ?', [$col]) !== null;
        } else {
            $has = false;
            foreach (DB::all('PRAGMA table_info(' . $table . ')') as $c) {
                if ($c['name'] === $col) {
                    $has = true;
                }
            }
        }
        if (!$has) {
            DB::pdo()->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $col . '` ' . $def);
        }
        return !$has;
    }
}
