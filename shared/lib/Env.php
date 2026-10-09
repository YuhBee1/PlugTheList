<?php
declare(strict_types=1);

namespace PTL;

                                                                                                      
final class Env
{
    private static array $v = [];

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            return;
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$k, $val] = explode('=', $line, 2);
            $k = trim($k);
            $val = trim($val);
            if (strlen($val) >= 2 && ($val[0] === '"' || $val[0] === "'") && substr($val, -1) === $val[0]) {
                $val = substr($val, 1, -1);
            }
            self::$v[$k] = $val;
        }
    }

    public static function get(string $k, ?string $default = null): ?string
    {
        if (array_key_exists($k, self::$v)) {
            return self::$v[$k];
        }
        $e = getenv($k);
        return $e !== false ? $e : $default;
    }

    public static function must(string $k): string
    {
        $v = self::get($k);
        if ($v === null || $v === '') {
            throw new \RuntimeException("Missing required setting: $k (see secrets/.env)");
        }
        return $v;
    }

    public static function bool(string $k, bool $default = false): bool
    {
        $v = self::get($k);
        if ($v === null) {
            return $default;
        }
        return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }

                            
    public static function set(string $k, string $v): void
    {
        self::$v[$k] = $v;
    }
}
