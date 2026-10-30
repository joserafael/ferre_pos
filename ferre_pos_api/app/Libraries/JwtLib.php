<?php

namespace App\Libraries;

use RuntimeException;

class JwtLib
{
    private string $secret;
    private int $ttl;

    public function __construct()
    {
        $this->secret = (string) (env('JWT_SECRET') ?: 'ferre_pos_super_secret_jwt_key_2026_bimonetario_usd_ves');
        $this->ttl    = (int) (env('JWT_TTL') ?: 86400);
    }

    public function encode(array $payload): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $now    = time();

        $payload['iat'] = $payload['iat'] ?? $now;
        $payload['exp'] = $payload['exp'] ?? ($now + $this->ttl);

        $segments   = [];
        $segments[] = $this->base64UrlEncode(json_encode($header));
        $segments[] = $this->base64UrlEncode(json_encode($payload));

        $signingInput = implode('.', $segments);
        $signature    = hash_hmac('sha256', $signingInput, $this->secret, true);
        $segments[]   = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    public function decode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new RuntimeException('Formato de token JWT inválido.');
        }

        [$headb64, $bodyb64, $cryptob64] = $parts;

        $header  = json_decode($this->base64UrlDecode($headb64), true);
        $payload = json_decode($this->base64UrlDecode($bodyb64), true);
        $sig     = $this->base64UrlDecode($cryptob64);

        if (!is_array($header) || !is_array($payload)) {
            throw new RuntimeException('Token JWT malformado.');
        }

        if (($header['alg'] ?? '') !== 'HS256') {
            throw new RuntimeException('Algoritmo JWT no soportado.');
        }

        $expectedSig = hash_hmac('sha256', $headb64 . '.' . $bodyb64, $this->secret, true);
        if (!hash_equals($expectedSig, $sig)) {
            throw new RuntimeException('Firma de token JWT inválida.');
        }

        if (isset($payload['exp']) && time() >= (int) $payload['exp']) {
            throw new RuntimeException('El token JWT ha expirado.');
        }

        return $payload;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return (string) base64_decode(strtr($data, '-_', '+/'));
    }
}
