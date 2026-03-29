<?php

declare(strict_types=1);

/**
 * JWT HS256 minimal (sans dépendance Composer).
 */
final class Jwt
{
    public static function encode(array $payload, string $secret, int $ttlSeconds): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $now = time();
        $payload['iat'] = $now;
        $payload['exp'] = $now + $ttlSeconds;

        $segments = [
            self::b64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR)),
            self::b64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR)),
        ];
        $signing = implode('.', $segments);
        $sig = hash_hmac('sha256', $signing, $secret, true);
        $segments[] = self::b64UrlEncode($sig);

        return implode('.', $segments);
    }

    /**
     * @return array<string, mixed>
     */
    public static function decode(string $token, string $secret): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new RuntimeException('Invalid token');
        }
        [$h64, $p64, $s64] = $parts;
        $signing = $h64 . '.' . $p64;
        $expected = self::b64UrlEncode(hash_hmac('sha256', $signing, $secret, true));
        if (!hash_equals($expected, $s64)) {
            throw new RuntimeException('Invalid signature');
        }
        $payload = json_decode(self::b64UrlDecode($p64), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            throw new RuntimeException('Invalid payload');
        }
        if (isset($payload['exp']) && (int) $payload['exp'] < time()) {
            throw new RuntimeException('Token expired');
        }
        return $payload;
    }

    private static function b64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64UrlDecode(string $data): string
    {
        $pad = 4 - (strlen($data) % 4);
        if ($pad < 4) {
            $data .= str_repeat('=', $pad);
        }
        $b64 = strtr($data, '-_', '+/');
        $raw = base64_decode($b64, true);
        if ($raw === false) {
            throw new RuntimeException('Invalid base64');
        }
        return $raw;
    }
}
