<?php

declare(strict_types=1);

namespace Vise\Services;

use Vise\Db;

/** Soma pontuação × peso por categoria e aplica as regras de classificação. */
final class ResultadoService
{
    /** @return array{totais: array<string, float>, classificacao: array<string, mixed>} */
    public static function calcular(int $aplicacaoId, int $alunoId): array
    {
        $respostas = Db::all(
            'SELECT p.categoria_id, p.peso, o.pontuacao FROM respostas r
             JOIN perguntas p ON p.id = r.pergunta_id
             LEFT JOIN opcoes_resposta o ON o.id = r.opcao_id
             WHERE r.aplicacao_id = ? AND r.aluno_id = ?',
            [$aplicacaoId, $alunoId]
        );
        $totais = [];
        foreach ($respostas as $resposta) {
            $categoria = (string) $resposta['categoria_id'];
            $peso = $resposta['peso'] === null ? 1.0 : (float) $resposta['peso'];
            $pontos = $resposta['pontuacao'] === null ? 0 : (int) $resposta['pontuacao'];
            $totais[$categoria] = ($totais[$categoria] ?? 0.0) + $pontos * $peso;
        }

        $classificacao = [];
        foreach ($totais as $categoria => $total) {
            $regra = Db::one(
                'SELECT rotulo, descricao, nivel FROM regras_classificacao
                 WHERE categoria_id = ? AND min_score <= ? AND max_score >= ? AND deleted_at IS NULL
                 ORDER BY id LIMIT 1',
                [(int) $categoria, $total, $total]
            );
            $classificacao[(string) $categoria] = $regra === null
                ? null
                : ['rotulo' => $regra['rotulo'], 'descricao' => $regra['descricao'], 'nivel' => $regra['nivel']];
        }

        $totaisJson = (string) json_encode((object) $totais, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        $classificacaoJson = (string) json_encode((object) $classificacao, JSON_UNESCAPED_UNICODE);
        $existente = Db::value(
            'SELECT id FROM resultados WHERE aplicacao_id = ? AND aluno_id = ?',
            [$aplicacaoId, $alunoId]
        );
        if ($existente === null) {
            $now = Db::now();
            Db::insert('resultados', [
                'aplicacao_id' => $aplicacaoId,
                'aluno_id' => $alunoId,
                'totais_json' => $totaisJson,
                'classificacao_json' => $classificacaoJson,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            Db::update('resultados', [
                'totais_json' => $totaisJson,
                'classificacao_json' => $classificacaoJson,
            ], (int) $existente);
        }
        return ['totais' => $totais, 'classificacao' => $classificacao];
    }
}
