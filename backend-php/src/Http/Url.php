<?php

declare(strict_types=1);

namespace Vise\Http;

/** Gera URLs respeitando a subpasta onde o backend foi instalado. */
final class Url
{
    private static string $base = '';

    public static function setBase(string $base): void
    {
        self::$base = rtrim($base, '/');
    }

    public static function base(): string
    {
        return self::$base;
    }

    /** @param array<string, mixed> $query */
    public static function to(string $path, array $query = []): string
    {
        $url = self::$base . '/' . ltrim($path, '/');
        $query = array_filter($query, static fn ($value) => $value !== null && $value !== '');
        return $query === [] ? $url : $url . '?' . http_build_query($query);
    }
}
