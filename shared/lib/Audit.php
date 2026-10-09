<?php
declare(strict_types=1);

namespace PTL;

                                                                                                                             
final class Audit
{
                                            
    public static function log(?int $userId, string $action, string $entity = '', ?int $entityId = null, array $meta = []): void
    {
        try {
            DB::insert('audit_logs', [
                'user_id' => $userId,
                'action' => mb_substr($action, 0, 60),
                'entity' => mb_substr($entity, 0, 40),
                'entity_id' => $entityId,
                'ip' => PHP_SAPI === 'cli' ? 'cli' : Security::ip(),
                'meta' => $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
            error_log('[audit] ' . $e->getMessage());
        }
    }
}
