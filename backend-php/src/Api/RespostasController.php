<?php

declare(strict_types=1);

namespace Vise\Api;

use Vise\Db;
use Vise\Http\HttpError;
use Vise\Http\Input;
use Vise\Http\Request;
use Vise\Http\Response;
use Vise\Services\ResultadoService;

final class RespostasController
{
    private const LOTE_MAXIMO = 500;

    /** @param array<string, string> $params */
    public static function salvar(Request $request, array $params): Response
    {
        $aluno = Guard::aluno($request);
        $entrada = self::entrada($request->json());
        $id = Db::transaction(static fn () => self::persistir((int) $params['id'], $aluno, $entrada));
        return Response::json(['status' => 'salvo', 'id' => $id]);
    }

    /** @param array<string, string> $params */
    public static function lote(Request $request, array $params): Response
    {
        $aluno = Guard::aluno($request);
        $respostas = $request->json()['respostas'] ?? null;
        if (!is_array($respostas) || ($respostas !== [] && array_keys($respostas) !== range(0, count($respostas) - 1))) {
            throw new HttpError(422, 'Campo respostas deve ser uma lista.');
        }
        if (count($respostas) > self::LOTE_MAXIMO) {
            throw new HttpError(422, 'Envie no máximo ' . self::LOTE_MAXIMO . ' respostas por lote.');
        }
        $entradas = array_map(static fn ($item) => self::entrada($item), $respostas);
        Db::transaction(static function () use ($entradas, $params, $aluno): void {
            foreach ($entradas as $entrada) {
                self::persistir((int) $params['id'], $aluno, $entrada);
            }
        });
        return Response::json(['status' => 'sincronizado', 'quantidade' => count($entradas)]);
    }

    /** @param array<string, string> $params */
    public static function finalizar(Request $request, array $params): Response
    {
        $aluno = Guard::aluno($request);
        $aplicacao = AplicacoesController::paraAluno($aluno, (int) $params['id']);
        $obrigatorias = array_map('intval', Db::column(
            'SELECT p.id FROM perguntas p
             JOIN categorias c ON c.id = p.categoria_id
             WHERE c.questionario_id = ? AND p.obrigatoria = 1
               AND p.deleted_at IS NULL AND c.deleted_at IS NULL',
            [$aplicacao['questionario_id']]
        ));
        $respondidas = $obrigatorias === [] ? [] : array_map('intval', Db::column(
            'SELECT pergunta_id FROM respostas
             WHERE aplicacao_id = ? AND aluno_id = ? AND pergunta_id IN (' . Db::in($obrigatorias) . ')',
            array_merge([$aplicacao['id'], $aluno['id']], $obrigatorias)
        ));
        if (array_diff($obrigatorias, $respondidas) !== []) {
            throw new HttpError(422, 'Ainda existem perguntas obrigatórias sem resposta.');
        }
        ResultadoService::calcular((int) $aplicacao['id'], (int) $aluno['id']);
        return Response::json(['status' => 'concluido']);
    }

    /** @return array<string, mixed> */
    private static function entrada(mixed $item): array
    {
        if (!is_array($item)) {
            throw new HttpError(422, 'Cada resposta deve ser um objeto.');
        }
        return [
            'pergunta_id' => Input::int($item, 'pergunta_id'),
            'opcao_id' => Input::int($item, 'opcao_id', false),
            'texto' => Input::str($item, 'texto', false, 0, 10000),
            'tempo_gasto_ms' => Input::int($item, 'tempo_gasto_ms', false, 0),
            'dispositivo' => Input::str($item, 'dispositivo', false, 0, 255),
            'responded_at' => Input::datetimeUtc($item, 'responded_at'),
            'client_uuid' => Input::uuid($item, 'client_uuid'),
        ];
    }

    /**
     * Grava a resposta. Se já existir uma mais recente para a mesma pergunta, mantém a atual
     * (sincronizações fora de ordem vindas do modo offline).
     *
     * @param array<string, mixed> $aluno
     * @param array<string, mixed> $entrada
     */
    private static function persistir(int $aplicacaoId, array $aluno, array $entrada): int
    {
        $aplicacao = AplicacoesController::paraAluno($aluno, $aplicacaoId);
        $perguntaId = Db::value(
            'SELECT p.id FROM perguntas p
             JOIN categorias c ON c.id = p.categoria_id
             WHERE p.id = ? AND c.questionario_id = ?',
            [$entrada['pergunta_id'], $aplicacao['questionario_id']]
        );
        if ($perguntaId === null) {
            throw new HttpError(404, 'Pergunta não encontrada.');
        }
        if ($entrada['opcao_id'] !== null) {
            $opcaoValida = Db::value(
                'SELECT id FROM opcoes_resposta WHERE id = ? AND pergunta_id = ?',
                [$entrada['opcao_id'], $perguntaId]
            );
            if ($opcaoValida === null) {
                throw new HttpError(422, 'Opção inválida para a pergunta.');
            }
        }

        $respondedAt = $entrada['responded_at'] ?? Db::now();
        $existente = Db::one(
            'SELECT id, responded_at FROM respostas WHERE aplicacao_id = ? AND aluno_id = ? AND pergunta_id = ?',
            [$aplicacao['id'], $aluno['id'], $perguntaId]
        );
        if ($existente !== null && (string) $existente['responded_at'] > $respondedAt) {
            return (int) $existente['id'];
        }
        $valores = [
            'opcao_id' => $entrada['opcao_id'],
            'texto' => $entrada['texto'],
            'tempo_gasto_ms' => $entrada['tempo_gasto_ms'],
            'dispositivo' => $entrada['dispositivo'],
            'responded_at' => $respondedAt,
            'client_uuid' => $entrada['client_uuid'],
        ];
        if ($existente === null) {
            $now = Db::now();
            return Db::insert('respostas', $valores + [
                'aplicacao_id' => $aplicacao['id'],
                'aluno_id' => $aluno['id'],
                'pergunta_id' => $perguntaId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        Db::update('respostas', $valores, (int) $existente['id']);
        return (int) $existente['id'];
    }
}
