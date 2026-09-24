<?php

declare(strict_types=1);

namespace Vise\Security;

final class Password
{
    public static function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT);
    }

    /** Aceita hashes $2y$ (PHP) e $2a$/$2b$ (gerados pelo backend Python). */
    public static function verify(string $plain, string $hash): bool
    {
        if (str_starts_with($hash, '$2b$') || str_starts_with($hash, '$2a$')) {
            $hash = '$2y$' . substr($hash, 4);
        }
        return password_verify($plain, $hash);
    }

    public static function isHash(string $value): bool
    {
        return (bool) preg_match('/^\$2[aby]\$\d{2}\$.{53}$/', $value);
    }
}
