<?php

declare(strict_types=1);

namespace Vise\Services;

use Vise\Db;
use Vise\Http\HttpError;

final class CodigoSala
{
    private const ALFABETO = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    public static function gerar(): string
    {
        for ($tentativa = 0; $tentativa < 40; $tentativa++) {
            $codigo = '';
            for ($i = 0; $i < 6; $i++) {
                $codigo .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
            }
            if (Db::value('SELECT id FROM aplicacoes_questionario WHERE codigo_sala = ?', [$codigo]) === null) {
                return $codigo;
            }
        }
        throw new HttpError(500, 'Não foi possível gerar código da sala.');
    }
}
