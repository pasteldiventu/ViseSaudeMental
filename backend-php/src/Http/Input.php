<?php

declare(strict_types=1);

namespace Vise\Http;

use Vise\Support\Str;

/** Validação do corpo JSON da API (equivalente aos schemas Pydantic). */
final class Input
{
    /** @param array<string, mixed> $data */
    public static function int(array $data, string $key, bool $required = true, ?int $min = null): ?int
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            if ($required) {
                throw new HttpError(422, "Campo obrigatório: $key.");
            }
            return null;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', trim($value))) {
            $value = (int) trim($value);
        } elseif (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }
        if (!is_int($value)) {
            throw new HttpError(422, "Campo $key deve ser um número inteiro.");
        }
        if ($min !== null && $value < $min) {
            throw new HttpError(422, "Campo $key deve ser maior ou igual a $min.");
        }
        return $value;
    }

    /** @param array<string, mixed> $data */
    public static function str(array $data, string $key, bool $required = true, int $min = 0, ?int $max = null): ?string
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            if ($required) {
                throw new HttpError(422, "Campo obrigatório: $key.");
            }
            return null;
        }
        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }
        if (!is_string($value)) {
            throw new HttpError(422, "Campo $key deve ser texto.");
        }
        $length = Str::len($value);
        if ($length < $min) {
            throw new HttpError(422, "Campo $key deve ter pelo menos $min caractere(s).");
        }
        if ($max !== null && $length > $max) {
            throw new HttpError(422, "Campo $key deve ter no máximo $max caractere(s).");
        }
        return $value;
    }

    /** Aceita "AAAA-MM-DD". */
    public static function date(array $data, string $key): string
    {
        $value = self::str($data, $key);
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            throw new HttpError(422, "Campo $key deve ser uma data no formato AAAA-MM-DD.");
        }
        return $value;
    }

    /** ISO 8601 (com ou sem fuso) → "Y-m-d H:i:s" em UTC. Sem fuso = UTC. */
    public static function datetimeUtc(array $data, string $key): ?string
    {
        $value = self::str($data, $key, false);
        if ($value === null) {
            return null;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?)?(Z|[+-]\d{2}:?\d{2})?$/i', trim($value))) {
            throw new HttpError(422, "Campo $key deve ser uma data/hora ISO 8601.");
        }
        try {
            $parsed = new \DateTimeImmutable(trim($value), new \DateTimeZone('UTC'));
        } catch (\Exception) {
            throw new HttpError(422, "Campo $key deve ser uma data/hora ISO 8601.");
        }
        return $parsed->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public static function uuid(array $data, string $key): ?string
    {
        $value = self::str($data, $key, false);
        if ($value === null) {
            return null;
        }
        $hex = strtolower(str_replace(['-', '{', '}'], '', trim($value)));
        if (!preg_match('/^[0-9a-f]{32}$/', $hex)) {
            throw new HttpError(422, "Campo $key deve ser um UUID.");
        }
        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
}
