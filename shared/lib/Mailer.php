<?php
declare(strict_types=1);

namespace PTL;

   
                                                                                          
                                                                                                           
   
final class Mailer
{
    public static function send(string $to, string $subject, string $text, ?string $ctaUrl = null, ?string $ctaLabel = null): bool
    {
        $to = str_replace(["\r", "\n"], '', $to);
        $subject = str_replace(["\r", "\n"], ' ', $subject);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $from = Env::get('MAIL_FROM', 'no-reply@' . base_domain());
        $fromName = str_replace(["\r", "\n", '"'], '', (string)Env::get('MAIL_FROM_NAME', 'PlugTheList'));
        $plain = $text . ($ctaUrl ? "\n\n" . ($ctaLabel ?? 'Open') . ': ' . $ctaUrl : '')
            . "\n\n--\nPlugTheList, Paramount Digital Services, Uyo, Nigeria\nYou get this because you have an account at " . base_domain() . ".";
        $html = self::html($subject, $text, $ctaUrl, $ctaLabel);
        $boundary = 'b' . bin2hex(random_bytes(12));
        $headers = [
            'Date: ' . date('r'),
            'From: "' . $fromName . '" <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'MIME-Version: 1.0',
            'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . base_domain() . '>',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'Auto-Submitted: auto-generated',
        ];
        $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($plain))
            . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html))
            . "--$boundary--\r\n";
        $driver = (string)Env::get('MAIL_DRIVER', 'log');
        try {
            if ($driver === 'smtp') {
                return self::smtp($from, $to, implode("\r\n", $headers) . "\r\n\r\n" . $body);
            }
            if ($driver === 'mail') {
                return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", array_slice($headers, 1)), '-f' . $from);
            }
            @file_put_contents(PTL_STORAGE . '/logs/mail.log', "=== " . now() . " to=$to subject=$subject\n$plain\n\n", FILE_APPEND | LOCK_EX);
            return true;
        } catch (\Throwable $e) {
            error_log('[mail] ' . $e->getMessage());
            return false;
        }
    }

    private static function html(string $subject, string $text, ?string $url, ?string $label): string
    {
        $paras = '';
        foreach (preg_split('/\n{2,}/', trim($text)) ?: [] as $p) {
            $paras .= '<p style="margin:0 0 14px;line-height:1.55">' . nl2br(e($p)) . '</p>';
        }
        $btn = $url ? '<p style="margin:22px 0"><a href="' . e($url) . '" style="background:#FFC400;color:#12130F;padding:12px 20px;text-decoration:none;font-weight:700;border-radius:4px;display:inline-block">' . e($label ?? 'Open') . '</a></p><p style="font-size:12px;color:#666;word-break:break-all">' . e($url) . '</p>' : '';
        return '<!doctype html><html><body style="margin:0;background:#F1F2EE;font-family:Arial,Helvetica,sans-serif;color:#12130F"><div style="max-width:560px;margin:0 auto;padding:24px"><div style="height:8px;background:repeating-linear-gradient(90deg,#FFC400 0 24px,#12130F 24px 48px)"></div><div style="background:#fff;padding:26px"><h1 style="font-size:20px;margin:0 0 16px">' . e($subject) . '</h1>' . $paras . $btn . '</div><p style="font-size:12px;color:#666">PlugTheList, Paramount Digital Services, Uyo, Nigeria</p></div></body></html>';
    }

    private static function smtp(string $from, string $to, string $data): bool
    {
        $host = Env::must('MAIL_HOST');
        $port = (int)Env::get('MAIL_PORT', '587');
        $ssl = $port === 465;
        $fp = @stream_socket_client(($ssl ? 'ssl://' : '') . $host . ':' . $port, $en, $es, 12, STREAM_CLIENT_CONNECT, stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]));
        if (!$fp) {
            throw new \RuntimeException("SMTP connect failed: $es");
        }
        stream_set_timeout($fp, 15);
        $read = static function () use ($fp): string {
            $out = '';
            while (($l = fgets($fp, 515)) !== false) {
                $out .= $l;
                if (strlen($l) < 4 || $l[3] === ' ') {
                    break;
                }
            }
            return $out;
        };
        $cmd = static function (string $c, array $ok) use ($fp, $read): string {
            fwrite($fp, $c . "\r\n");
            $r = $read();
            if (!in_array((int)substr($r, 0, 3), $ok, true)) {
                throw new \RuntimeException('SMTP error: ' . trim($r));
            }
            return $r;
        };
        $read();
        $name = base_domain();
        $cmd('EHLO ' . $name, [250]);
        if (!$ssl) {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('SMTP STARTTLS failed');
            }
            $cmd('EHLO ' . $name, [250]);
        }
        $user = Env::get('MAIL_USER');
        if ($user) {
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode($user), [334]);
            $cmd(base64_encode((string)Env::get('MAIL_PASS')), [235]);
        }
        $cmd('MAIL FROM:<' . $from . '>', [250]);
        $cmd('RCPT TO:<' . $to . '>', [250, 251]);
        $cmd('DATA', [354]);
        $data = preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], "\r\n", $data));
        fwrite($fp, $data . "\r\n.\r\n");
        $r = $read();
        if ((int)substr($r, 0, 3) !== 250) {
            throw new \RuntimeException('SMTP data rejected: ' . trim($r));
        }
        fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return true;
    }
}
