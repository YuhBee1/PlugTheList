<?php
declare(strict_types=1);

namespace PTL;

   
                                                                                                                                  
   
final class Paystack
{
    private const BASE = 'https://api.paystack.co';

    public static function mock(): bool
    {
        return Env::bool('PAYSTACK_MOCK') && Env::get('APP_ENV', 'production') !== 'production';
    }

                                                                            
    private static function call(string $method, string $path, ?array $body = null): array
    {
        $key = Env::must('PAYSTACK_SECRET');
        $ch = curl_init(self::BASE . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new \RuntimeException('Paystack unreachable: ' . $err);
        }
        $j = json_decode((string)$raw, true);
        if (!is_array($j)) {
            throw new \RuntimeException('Paystack bad response (' . $code . ')');
        }
        $j['_http'] = $code;
        return $j;
    }

                                                     
    public static function initialize(string $email, int $kobo, string $reference, string $callback, array $meta = []): array
    {
        if (self::mock()) {
            return ['url' => $callback . (str_contains($callback, '?') ? '&' : '?') . 'reference=' . rawurlencode($reference), 'reference' => $reference];
        }
        $r = self::call('POST', '/transaction/initialize', [
            'email' => $email, 'amount' => $kobo, 'currency' => 'NGN', 'reference' => $reference,
            'callback_url' => $callback, 'metadata' => $meta,
        ]);
        if (empty($r['status']) || empty($r['data']['authorization_url'])) {
            throw new \RuntimeException('Paystack init failed: ' . ($r['message'] ?? '?'));
        }
        return ['url' => (string)$r['data']['authorization_url'], 'reference' => $reference];
    }

                                                                                      
    public static function verify(string $reference): ?array
    {
        if (self::mock()) {
            $p = DB::one('SELECT amount_kobo FROM payments WHERE reference = ?', [$reference]);
            return $p ? ['status' => 'success', 'amount' => (int)$p['amount_kobo'], 'currency' => 'NGN', 'channel' => 'mock'] : null;
        }
        $r = self::call('GET', '/transaction/verify/' . rawurlencode($reference));
        if (empty($r['status']) || !isset($r['data']['status'])) {
            return null;
        }
        return [
            'status' => (string)$r['data']['status'],
            'amount' => (int)($r['data']['amount'] ?? 0),
            'currency' => (string)($r['data']['currency'] ?? ''),
            'channel' => (string)($r['data']['channel'] ?? ''),
        ];
    }

    public static function refund(string $reference, int $kobo): bool
    {
        if (self::mock()) {
            return true;
        }
        $r = self::call('POST', '/refund', ['transaction' => $reference, 'amount' => $kobo]);
        return !empty($r['status']);
    }

                                                             
    public static function banks(): array
    {
        $cache = PTL_STORAGE . '/cache-banks.json';
        if (is_file($cache) && filemtime($cache) > time() - 86400) {
            $j = json_decode((string)file_get_contents($cache), true);
            if (is_array($j) && $j) {
                return $j;
            }
        }
        if (self::mock()) {
            return [['name' => 'Access Bank', 'code' => '044'], ['name' => 'GTBank', 'code' => '058'], ['name' => 'Zenith Bank', 'code' => '057'], ['name' => 'Kuda', 'code' => '50211'], ['name' => 'OPay', 'code' => '999992']];
        }
        $r = self::call('GET', '/bank?country=nigeria&perPage=200&currency=NGN');
        $out = [];
        foreach (($r['data'] ?? []) as $b) {
            if (!empty($b['code']) && !empty($b['name']) && empty($b['is_deleted'])) {
                $out[] = ['name' => (string)$b['name'], 'code' => (string)$b['code']];
            }
        }
        usort($out, static fn($a, $b) => strcmp($a['name'], $b['name']));
        if ($out) {
            @file_put_contents($cache, json_encode($out), LOCK_EX);
        }
        return $out;
    }

    public static function resolveAccount(string $number, string $bankCode): ?string
    {
        if (self::mock()) {
            return 'MOCK ACCOUNT HOLDER';
        }
        $r = self::call('GET', '/bank/resolve?account_number=' . rawurlencode($number) . '&bank_code=' . rawurlencode($bankCode));
        return !empty($r['status']) ? (string)($r['data']['account_name'] ?? '') : null;
    }

    public static function createRecipient(string $name, string $number, string $bankCode): ?string
    {
        if (self::mock()) {
            return 'RCP_mock_' . substr(sha1($number . $bankCode), 0, 10);
        }
        $r = self::call('POST', '/transferrecipient', ['type' => 'nuban', 'name' => $name, 'account_number' => $number, 'bank_code' => $bankCode, 'currency' => 'NGN']);
        return !empty($r['status']) ? (string)$r['data']['recipient_code'] : null;
    }

                                                           
    public static function transfer(int $kobo, string $recipient, string $reference, string $reason): array
    {
        if (self::mock()) {
            return ['ok' => true, 'code' => 'TRF_mock', 'status' => 'success'];
        }
        $r = self::call('POST', '/transfer', ['source' => 'balance', 'amount' => $kobo, 'recipient' => $recipient, 'reference' => $reference, 'reason' => $reason, 'currency' => 'NGN']);
        return ['ok' => !empty($r['status']), 'code' => (string)($r['data']['transfer_code'] ?? ''), 'status' => (string)($r['data']['status'] ?? 'failed')];
    }

    public static function signatureValid(string $rawBody, string $header): bool
    {
        $secret = (string)Env::get('PAYSTACK_SECRET', '');
        if ($secret === '' || $header === '') {
            return false;
        }
        return hash_equals(hash_hmac('sha512', $rawBody, $secret), strtolower($header));
    }
}
