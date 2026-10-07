<?php

declare(strict_types=1);

namespace Vise\Api;

use Vise\Db;
use Vise\Http\HttpError;
use Vise\Http\Input;
use Vise\Http\Request;
use Vise\Http\Response;
use Vise\Security\AlunoTokens;
use Vise\Support\Str;

final class AuthController
{
    public static function login(Request $request): Response
    {
        $data = $request->json();
        $cpf = Str::digits(Input::str($data, 'cpf'));
        if (strlen($cpf) !== 11) {
            throw new HttpError(422, 'CPF deve conter 11 dígitos.');
        }
        $nascimento = Input::date($data, 'data_nascimento');

        $aluno = Db::one(
            'SELECT * FROM alunos WHERE cpf = ? AND data_nascimento = ? AND deleted_at IS NULL LIMIT 1',
            [$cpf, $nascimento]
        );
        if ($aluno === null) {
            throw new HttpError(422, 'Credenciais inválidas.');
        }
        return Response::json([
            'token' => AlunoTokens::create((int) $aluno['id']),
            'aluno' => self::alunoMe($aluno),
        ]);
    }

    public static function logout(Request $request): Response
    {
        Guard::aluno($request);
        AlunoTokens::revoke((string) $request->bearerToken());
        return Response::json(['status' => 'ok']);
    }

    public static function me(Request $request): Response
    {
        return Response::json(self::alunoMe(Guard::aluno($request)));
    }

    /** @param array<string, mixed> $aluno */
    public static function alunoMe(array $aluno): array
    {
        return [
            'id' => (int) $aluno['id'],
            'nome' => (string) $aluno['nome'],
            'escola_id' => (int) $aluno['escola_id'],
        ];
    }
}
