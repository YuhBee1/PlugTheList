<?php
declare(strict_types=1);

namespace PTL;

   
                                                                                    
                                                                 
   
final class RateLimiter
{
    private static function key(string $bucket, string $id): string
    {
        return hash('sha256', $bucket . '|' . strtolower($id));
    }

                                                                    
    public static function hit(string $bucket, string $id, int $windowSec): array
    {
        $k = self::key($bucket, $id);
        $now = time();
        for ($i = 0; $i < 3; $i++) {
                                                 
            if (DB::run('UPDATE rate_limits SET hits = hits + 1 WHERE k = ? AND window_start > ?', [$k, $now - $windowSec]) === 1) {
                break;
            }
                                                           
            if (DB::run('UPDATE rate_limits SET hits = 1, window_start = ? WHERE k = ? AND window_start <= ?', [$now, $k, $now - $windowSec]) === 1) {
                break;
            }
                                                                                                        
            if (DB::insertIgnore('rate_limits', ['k' => $k, 'hits' => 1, 'window_start' => $now], false)) {
                break;
            }
        }
        $row = DB::one('SELECT hits, window_start FROM rate_limits WHERE k = ?', [$k]);
        $hits = (int)($row['hits'] ?? 1);
        $reset = max(1, (int)($row['window_start'] ?? $now) + $windowSec - $now);
        return [$hits, $reset];
    }

                                                                                                                   
    public static function over(string $bucket, string $id, int $max, int $windowSec): bool
    {
        $row = DB::one('SELECT hits FROM rate_limits WHERE k = ? AND window_start > ?', [self::key($bucket, $id), time() - $windowSec]);
        return $row !== null && (int)$row['hits'] >= $max;
    }

                                                                        
    public static function allow(string $bucket, string $id, int $max, int $windowSec): bool
    {
        [$hits] = self::hit($bucket, $id, $windowSec);
        return $hits <= $max;
    }

                                                                       
    public static function enforce(string $bucket, string $id, int $max, int $windowSec): void
    {
        [$hits, $reset] = self::hit($bucket, $id, $windowSec);
        if ($hits > $max) {
            if (!headers_sent()) {
                header('Retry-After: ' . $reset);
            }
            abort(429, 'Too many requests. Please wait ' . max(1, (int)ceil($reset / 60)) . ' minute(s) and try again.');
        }
    }

    public static function reset(string $bucket, string $id): void
    {
        DB::run('DELETE FROM rate_limits WHERE k = ?', [self::key($bucket, $id)]);
    }

    public static function purge(): int
    {
        return DB::run('DELETE FROM rate_limits WHERE window_start < ?', [time() - 86400]);
    }
}
