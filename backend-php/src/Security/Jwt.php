<?php

declare(strict_types=1);

namespace Vise\Security;

use Vise\Config;

/** JWT HS256 (mesmo formato do python-jose usado antes: tokens antigos continuam válidos). */
final class Jwt
{
    public static function encode(array $claims): string
    {
        $header = self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = self::b64((string) json_encode($claims));
        $signature = self::b64(hash_hmac('sha256', "$header.$payload", Config::secretKey(), true));
        return "$header.$payload.$signature";
    }

    /** @return array<string, mixed> */
    public static function decode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \UnexpectedValueException('Token malformado.');
        }
        [$header, $payload, $signature] = $parts;
        $headerData = json_decode(self::unb64($header), true);
        if (!is_array($headerData) || ($headerData['alg'] ?? '') !== 'HS256') {
            throw new \UnexpectedValueException('Algoritmo inválido.');
        }
        $expected = self::b64(hash_hmac('sha256', "$header.$payload", Config::secretKey(), true));
        if (!hash_equals($expected, $signature)) {
            throw new \UnexpectedValueException('Assinatura inválida.');
        }
        $claims = json_decode(self::unb64($payload), true);
        if (!is_array($claims)) {
            throw new \UnexpectedValueException('Payload inválido.');
        }
        if (isset($claims['exp']) && (int) $claims['exp'] <= time()) {
            throw new \UnexpectedValueException('Token expirado.');
        }
        return $claims;
    }

    public static function forUser(int $userId): string
    {
        $minutes = Config::int('ACCESS_TOKEN_EXPIRE_MINUTES', 60 * 24 * 7);
        return self::encode(['sub' => (string) $userId, 'exp' => time() + $minutes * 60]);
    }

    private static function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function unb64(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }
}
