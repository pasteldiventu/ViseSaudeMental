<?php

declare(strict_types=1);

namespace Vise\Support;

final class Str
{
    public static function digits(mixed $value): string
    {
        return (string) preg_replace('/\D+/', '', (string) ($value ?? ''));
    }

    public static function len(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : (int) preg_match_all('/./us', $value);
    }

    public static function sub(string $value, int $start, int $length): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, $start, $length, 'UTF-8');
        }
        preg_match_all('/./us', $value, $chars);
        return implode('', array_slice($chars[0], $start, $length));
    }

    public static function lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    public static function upper(string $value): string
    {
        return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
    }

    /** Resume o texto para listagens (com reticências). */
    public static function limit(mixed $value, int $limit = 80): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return '—';
        }
        return self::len($text) > $limit ? self::sub($text, 0, $limit - 1) . '…' : $text;
    }

    public static function orNull(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }

    public static function cpf(mixed $value): string
    {
        $digits = self::digits($value);
        if (strlen($digits) !== 11) {
            return trim((string) ($value ?? '')) ?: '—';
        }
        return sprintf('%s.%s.%s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6, 3), substr($digits, 9));
    }

    /** "2026-01-02 10:00:00" → "2026-01-02T10:00:00" (formato do contrato da API). */
    public static function iso(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return str_replace(' ', 'T', (string) $value);
    }
}
