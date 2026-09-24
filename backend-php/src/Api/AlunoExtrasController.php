<?php

declare(strict_types=1);

namespace Vise\Api;

use Vise\Db;
use Vise\Http\Input;
use Vise\Http\Request;
use Vise\Http\Response;

/** Avatar e aceite do termo de participação. */
final class AlunoExtrasController
{
    public static function avatar(Request $request): Response
    {
        $aluno = Guard::aluno($request);
        $avatar = Db::one(
            'SELECT id, nome, imagem_path, mensagem_padrao FROM avatars
             WHERE escola_id = ? OR escola_id IS NULL
             ORDER BY CASE WHEN escola_id = ? THEN 0 ELSE 1 END, id
             LIMIT 1',
            [$aluno['escola_id'], $aluno['escola_id']]
        );
        return Response::json(['data' => $avatar === null ? null : [
            'id' => (int) $avatar['id'],
            'nome' => (string) $avatar['nome'],
            'imagem_path' => (string) $avatar['imagem_path'],
            'mensagem_padrao' => $avatar['mensagem_padrao'],
        ]]);
    }

    public static function aceitarTermo(Request $request): Response
    {
        $aluno = Guard::aluno($request);
        $data = $request->json();
        $versao = (string) Input::str($data, 'versao', true, 0, 50);
        $hash = (string) Input::str($data, 'texto_hash', true, 64, 64);
        $valores = [
            'texto_hash' => $hash,
            'ip' => $request->ip,
            'user_agent' => $request->header('user-agent'),
            'accepted_at' => Db::now(),
        ];
        $termoId = Db::value(
            'SELECT id FROM termos_aceite WHERE aluno_id = ? AND versao = ?',
            [$aluno['id'], $versao]
        );
        if ($termoId === null) {
            Db::insert('termos_aceite', $valores + ['aluno_id' => $aluno['id'], 'versao' => $versao]);
        } else {
            Db::update('termos_aceite', $valores, (int) $termoId);
        }
        return Response::json(['status' => 'aceito'], 201);
    }
}
