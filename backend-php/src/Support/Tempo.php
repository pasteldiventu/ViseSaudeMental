<?php

declare(strict_types=1);

namespace Vise\Support;

use Vise\Config;

/** O banco grava em UTC; indicadores e relatórios são exibidos no fuso APP_TIMEZONE. */
final class Tempo
{
    private static ?\DateTimeZone $tz = null;

    public static function tz(): \DateTimeZone
    {
        if (self::$tz === null) {
            try {
                self::$tz = new \DateTimeZone(Config::get('APP_TIMEZONE', 'America/Cuiaba'));
            } catch (\Exception) {
                self::$tz = new \DateTimeZone('UTC');
            }
        }
        return self::$tz;
    }

    /** "2026-01-02 15:00:00" (UTC) → texto no fuso local. */
    public static function local(mixed $utc, string $formato = 'd/m/Y H:i'): string
    {
        if ($utc === null || $utc === '') {
            return '';
        }
        $data = new \DateTimeImmutable((string) $utc, new \DateTimeZone('UTC'));
        return $data->setTimezone(self::tz())->format($formato);
    }

    public static function hoje(): string
    {
        return (new \DateTimeImmutable('now', self::tz()))->format('Y-m-d');
    }

    /** Data local (AAAA-MM-DD) → início ou fim do dia em UTC, para filtrar colunas UTC. */
    public static function limiteUtc(string $data, bool $fimDoDia = false): string
    {
        $local = new \DateTimeImmutable($data . ($fimDoDia ? ' 23:59:59' : ' 00:00:00'), self::tz());
        return $local->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /** Deslocamento atual do fuso local para CONVERT_TZ (ex.: "-04:00"). */
    public static function offsetSql(): string
    {
        return (new \DateTimeImmutable('now', self::tz()))->format('P');
    }

    public static function dataValida(mixed $valor): ?string
    {
        if (!is_string($valor) || $valor === '') {
            return null;
        }
        $data = \DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
        return ($data !== false && $data->format('Y-m-d') === $valor) ? $valor : null;
    }
}
