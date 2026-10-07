<?php

declare(strict_types=1);

namespace Vise\Api;

use Vise\Db;
use Vise\Http\HttpError;
use Vise\Http\Input;
use Vise\Http\Request;
use Vise\Http\Response;
use Vise\Security\AlunoTokens;
use Vise\Support\Turmas;

final class AplicacoesController
{
    public static function normalizarCodigo(string $codigo): string
    {
        return (string) preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($codigo)));
    }

    /**
     * Aplicações cujo público inclui o aluno (escola toda, a turma dele ou ele próprio).
     *
     * @param array<string, mixed> $aluno
     * @return array{0: string, 1: list<mixed>}
     */
    private static function alvoDoAluno(array $aluno): array
    {
        return [
            "(a.alvo_tipo = 'escola'"
            . " OR (a.alvo_tipo = 'turma' AND a.turma_id = ?)"
            . " OR (a.alvo_tipo = 'aluno' AND a.aluno_id = ?))",
            [$aluno['turma_id'], $aluno['id']],
        ];
    }

    /**
     * Aplicação ativa, da escola do aluno e com o aluno no público-alvo (404 caso contrário).
     *
     * @param array<string, mixed> $aluno
     * @return array<string, mixed>
     */
    public static function paraAluno(array $aluno, int $aplicacaoId): array
    {
        [$alvo, $params] = self::alvoDoAluno($aluno);
        $aplicacao = Db::one(
            "SELECT a.* FROM aplicacoes_questionario a
             WHERE a.id = ? AND a.escola_id = ? AND a.status = 'ativa' AND a.deleted_at IS NULL AND $alvo",
            array_merge([$aplicacaoId, $aluno['escola_id']], $params)
        );
        if ($aplicacao === null) {
            throw new HttpError(404, 'Aplicação não encontrada.');
        }
        return $aplicacao;
    }

    public static function listar(Request $request): Response
    {
        $aluno = Guard::aluno($request);
        [$alvo, $params] = self::alvoDoAluno($aluno);
        $aplicacoes = Db::all(
            "SELECT a.id, a.questionario_id, q.nome AS questionario_nome, q.versao AS questionario_versao
             FROM aplicacoes_questionario a
             JOIN questionarios q ON q.id = a.questionario_id
             WHERE a.escola_id = ? AND a.status = 'ativa' AND a.deleted_at IS NULL AND $alvo
             ORDER BY a.id",
            array_merge([$aluno['escola_id']], $params)
        );

        $data = [];
        foreach ($aplicacoes as $aplicacao) {
            $total = (int) Db::value(
                'SELECT COUNT(p.id) FROM perguntas p
                 JOIN categorias c ON c.id = p.categoria_id
                 WHERE c.questionario_id = ? AND p.deleted_at IS NULL AND c.deleted_at IS NULL',
                [$aplicacao['questionario_id']]
            );
            $respondidas = (int) Db::value(
                'SELECT COUNT(id) FROM respostas WHERE aplicacao_id = ? AND aluno_id = ?',
                [$aplicacao['id'], $aluno['id']]
            );
            $concluido = Db::value(
                'SELECT id FROM resultados WHERE aplicacao_id = ? AND aluno_id = ? LIMIT 1',
                [$aplicacao['id'], $aluno['id']]
            ) !== null;
            $data[] = [
                'id' => (int) $aplicacao['id'],
                'questionario' => [
                    'id' => (int) $aplicacao['questionario_id'],
                    'nome' => (string) $aplicacao['questionario_nome'],
                    'versao' => (int) $aplicacao['questionario_versao'],
                ],
                'respondidas' => $respondidas,
                'total' => $total,
                'concluido' => $concluido,
            ];
        }
        return Response::json(['data' => $data]);
    }

    /** @param array<string, string> $params */
    public static function salaPublica(Request $request, array $params): Response
    {
        $codigo = self::normalizarCodigo($params['codigo']);
        $aplicacao = Db::one(
            "SELECT a.codigo_sala, a.alvo_tipo, a.status, q.nome AS questionario_nome, e.nome AS escola_nome
             FROM aplicacoes_questionario a
             LEFT JOIN questionarios q ON q.id = a.questionario_id
             LEFT JOIN escolas e ON e.id = a.escola_id
             WHERE a.codigo_sala = ? AND a.status = 'ativa' AND a.deleted_at IS NULL
             LIMIT 1",
            [$codigo]
        );
        if ($aplicacao === null) {
            throw new HttpError(404, 'Sala não encontrada.');
        }
        return Response::json([
            'codigo' => (string) ($aplicacao['codigo_sala'] ?: $codigo),
            'questionario_nome' => (string) ($aplicacao['questionario_nome'] ?? 'Questionário'),
            'escola_nome' => (string) ($aplicacao['escola_nome'] ?? 'Escola'),
            'alvo_tipo' => (string) $aplicacao['alvo_tipo'],
            'status' => (string) $aplicacao['status'],
        ]);
    }

    public static function entrarComCodigo(Request $request): Response
    {
        $aluno = Guard::aluno($request);
        $codigo = self::normalizarCodigo((string) Input::str($request->json(), 'codigo', true, 4, 12));
        [$alvo, $params] = self::alvoDoAluno($aluno);
        $aplicacao = Db::one(
            "SELECT a.id, a.codigo_sala, q.nome AS questionario_nome
             FROM aplicacoes_questionario a
             LEFT JOIN questionarios q ON q.id = a.questionario_id
             WHERE a.codigo_sala = ? AND a.status = 'ativa' AND a.deleted_at IS NULL
               AND a.escola_id = ? AND $alvo
             LIMIT 1",
            array_merge([$codigo, $aluno['escola_id']], $params)
        );
        if ($aplicacao !== null) {
            return Response::json([
                'aplicacao_id' => (int) $aplicacao['id'],
                'questionario_nome' => (string) ($aplicacao['questionario_nome'] ?? 'Questionário'),
                'codigo' => (string) ($aplicacao['codigo_sala'] ?: $codigo),
            ]);
        }
        return self::salaForaDoPublico($aluno, $codigo);
    }

    /**
     * O código não serve para este cadastro: explica o motivo ou, se outro cadastro da mesma pessoa
     * (mesmo CPF e nascimento) faz parte da sala, entra com ele e devolve a nova sessão.
     *
     * @param array<string, mixed> $aluno
     */
    private static function salaForaDoPublico(array $aluno, string $codigo): Response
    {
        $sala = Db::one(
            'SELECT a.*, q.nome AS questionario_nome, e.nome AS escola_nome, ' . Turmas::sql('t') . ' AS turma_nome
             FROM aplicacoes_questionario a
             LEFT JOIN questionarios q ON q.id = a.questionario_id
             LEFT JOIN escolas e ON e.id = a.escola_id
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE a.codigo_sala = ? AND a.deleted_at IS NULL',
            [$codigo]
        );
        if ($sala === null) {
            throw new HttpError(404, "Não existe sala com o código $codigo. Confira as letras e os números com o professor.");
        }
        if ($sala['status'] !== 'ativa') {
            throw new HttpError(404, "A sala $codigo já foi encerrada. Peça um novo código ao professor.");
        }

        $outro = Db::one(
            "SELECT al.* FROM alunos al
             WHERE al.cpf = ? AND al.data_nascimento = ? AND al.id <> ? AND al.deleted_at IS NULL AND al.escola_id = ?
               AND (? = 'escola' OR (? = 'turma' AND al.turma_id = ?) OR (? = 'aluno' AND al.id = ?))
             ORDER BY al.id DESC LIMIT 1",
            [
                $aluno['cpf'], $aluno['data_nascimento'], $aluno['id'], $sala['escola_id'],
                $sala['alvo_tipo'], $sala['alvo_tipo'], $sala['turma_id'], $sala['alvo_tipo'], $sala['aluno_id'],
            ]
        );
        if ($outro !== null) {
            return Response::json([
                'aplicacao_id' => (int) $sala['id'],
                'questionario_nome' => (string) ($sala['questionario_nome'] ?? 'Questionário'),
                'codigo' => $codigo,
                'token' => AlunoTokens::create((int) $outro['id']),
                'aluno' => AuthController::alunoMe($outro),
            ]);
        }

        $escolaAluno = (string) (Db::value('SELECT nome FROM escolas WHERE id = ?', [$aluno['escola_id']]) ?? 'outra escola');
        if ((int) $sala['escola_id'] !== (int) $aluno['escola_id']) {
            throw new HttpError(404, "A sala $codigo é da escola {$sala['escola_nome']}, mas o seu cadastro está na escola $escolaAluno. "
                . 'Peça à escola para conferir o seu cadastro.');
        }
        if ($sala['alvo_tipo'] === 'turma') {
            $turmaAluno = $aluno['turma_id'] === null ? null : Db::value(
                "SELECT CONCAT_WS(' ', s.descricao, t.nome) FROM turmas t LEFT JOIN series s ON s.id = t.serie_id WHERE t.id = ?",
                [$aluno['turma_id']]
            );
            throw new HttpError(404, "A sala $codigo é só para a turma {$sala['turma_nome']}. "
                . ($turmaAluno === null ? 'O seu cadastro está sem turma.' : "O seu cadastro está na turma $turmaAluno.")
                . ' Peça à escola para conferir o seu cadastro.');
        }
        throw new HttpError(404, "A sala $codigo foi criada para outro aluno.");
    }

    /** @param array<string, string> $params */
    public static function categorias(Request $request, array $params): Response
    {
        $aluno = Guard::aluno($request);
        $aplicacao = self::paraAluno($aluno, (int) $params['id']);
        $categorias = Db::all(
            'SELECT id, nome, ordem, cor, mensagem_avatar, imagem_apoio FROM categorias
             WHERE questionario_id = ? AND deleted_at IS NULL
             ORDER BY ordem, id',
            [$aplicacao['questionario_id']]
        );
        return Response::json(['data' => array_map(static fn (array $c) => [
            'id' => (int) $c['id'],
            'nome' => (string) $c['nome'],
            'ordem' => (int) $c['ordem'],
            'cor' => $c['cor'],
            'mensagem_avatar' => $c['mensagem_avatar'],
            'imagem_apoio' => $c['imagem_apoio'],
        ], $categorias)]);
    }

    /**
     * Perguntas e opções da categoria. A pontuação das opções nunca é exposta ao aluno.
     *
     * @param array<string, string> $params
     */
    public static function perguntas(Request $request, array $params): Response
    {
        $aluno = Guard::aluno($request);
        $aplicacao = self::paraAluno($aluno, (int) $params['id']);
        $categoriaId = Db::value(
            'SELECT id FROM categorias WHERE id = ? AND questionario_id = ? AND deleted_at IS NULL',
            [(int) $params['categoria'], $aplicacao['questionario_id']]
        );
        if ($categoriaId === null) {
            throw new HttpError(404, 'Categoria não encontrada.');
        }
        $perguntas = Db::all(
            'SELECT id, categoria_id, subcategoria_id, tipo, texto, ordem, obrigatoria, imagem
             FROM perguntas WHERE categoria_id = ? AND deleted_at IS NULL
             ORDER BY ordem, id',
            [$categoriaId]
        );
        $ids = array_map(static fn (array $p) => (int) $p['id'], $perguntas);
        $opcoesPorPergunta = [];
        if ($ids !== []) {
            $opcoes = Db::all(
                'SELECT id, pergunta_id, descricao, ordem, cor, emoji FROM opcoes_resposta
                 WHERE pergunta_id IN (' . Db::in($ids) . ') AND deleted_at IS NULL
                 ORDER BY ordem, id',
                $ids
            );
            foreach ($opcoes as $opcao) {
                $opcoesPorPergunta[(int) $opcao['pergunta_id']][] = [
                    'id' => (int) $opcao['id'],
                    'descricao' => (string) $opcao['descricao'],
                    'ordem' => (int) $opcao['ordem'],
                    'cor' => $opcao['cor'],
                    'emoji' => $opcao['emoji'],
                ];
            }
        }
        return Response::json(['data' => array_map(static fn (array $p) => [
            'id' => (int) $p['id'],
            'categoria_id' => (int) $p['categoria_id'],
            'subcategoria_id' => $p['subcategoria_id'] === null ? null : (int) $p['subcategoria_id'],
            'tipo' => (string) $p['tipo'],
            'texto' => (string) $p['texto'],
            'ordem' => (int) $p['ordem'],
            'obrigatoria' => (bool) $p['obrigatoria'],
            'imagem' => $p['imagem'],
            'opcoes' => $opcoesPorPergunta[(int) $p['id']] ?? [],
        ], $perguntas)]);
    }
}
