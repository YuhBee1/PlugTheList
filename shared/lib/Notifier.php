<?php
declare(strict_types=1);

namespace PTL;

                                                                            
final class Notifier
{
    public static function to(int $userId, string $title, string $body, string $url = '', bool $email = true): void
    {
        DB::insert('notifications', [
            'user_id' => $userId,
            'title' => mb_substr($title, 0, 150),
            'body' => mb_substr($body, 0, 500),
            'url' => mb_substr($url, 0, 300),
        ]);
        if ($email) {
            $addr = (string)DB::val('SELECT email FROM users WHERE id = ? AND status = ?', [$userId, 'active']);
            if ($addr !== '') {
                DB::afterCommit(static function () use ($addr, $title, $body, $url): void {
                    Mailer::send($addr, $title, $body, $url ?: null, 'Open on PlugTheList');
                });
            }
        }
    }

    public static function admins(string $title, string $body, string $url = ''): void
    {
        foreach (DB::all('SELECT id FROM users WHERE role = ? AND status = ?', ['admin', 'active']) as $a) {
            self::to((int)$a['id'], $title, $body, $url, true);
        }
    }

    public static function unread(int $userId): int
    {
        return (int)DB::val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [$userId]);
    }
}
