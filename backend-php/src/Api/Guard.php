<?php

declare(strict_types=1);

namespace Vise\Api;

use Vise\Db;
use Vise\Http\HttpError;
use Vise\Http\Request;
use Vise\Security\AlunoTokens;
use Vise\Security\Jwt;

final class Guard
{
    /** @return array<string, mixed> */
    public static function aluno(Request $request): array
    {
        $aluno = AlunoTokens::find(self::token($request));
        if ($aluno === null) {
            throw HttpError::unauthorized('Token inválido ou expirado.');
        }
        return $aluno;
    }

    /** @return array<string, mixed> */
    public static function user(Request $request): array
    {
        try {
            $claims = Jwt::decode(self::token($request));
            $userId = (int) ($claims['sub'] ?? 0);
        } catch (\UnexpectedValueException) {
            throw HttpError::unauthorized('Token inválido ou expirado.');
        }
        $user = Db::one('SELECT * FROM users WHERE id = ? AND deleted_at IS NULL', [$userId]);
        if ($user === null) {
            throw HttpError::unauthorized('Usuário não encontrado.');
        }
        return $user;
    }

    private static function token(Request $request): string
    {
        $token = $request->bearerToken();
        if ($token === null) {
            throw HttpError::unauthorized('Token de autenticação não informado.');
        }
        return $token;
    }
}
