<?php

declare(strict_types=1);

namespace Vise\Services;

use Vise\Db;

/** Soma pontuação × peso por categoria e aplica as regras de classificação. */
final class ResultadoService
{
    public const GRAVIDADE = ['baixo' => 1, 'moderado' => 2, 'alto' => 3];
    public const OBSERVACAO = ['baixo' => 'Sem sinal de risco', 'moderado' => 'Acompanhar', 'alto' => 'Intervir'];

    /** Tom do retorno ao aluno: nunca expõe o nível nem os pontos. */
    private const RETORNO = [
        'baixo' => ['tom' => 'bem', 'emoji' => '🌱', 'mensagem' => 'Pelas suas respostas, esta parte parece estar indo bem. Continue cuidando de você!'],
        'moderado' => ['tom' => 'atencao', 'emoji' => '🌤️', 'mensagem' => 'Algumas respostas mostram que nem tudo tem sido fácil. Tudo bem sentir isso. Conversar com alguém de confiança pode ajudar.'],
        'alto' => ['tom' => 'cuidado', 'emoji' => '🤝', 'mensagem' => 'Parece que este assunto tem pesado para você. Você não está sozinho: procure um professor ou alguém da escola em quem confie para conversar.'],
        '' => ['tom' => 'neutro', 'emoji' => '💬', 'mensagem' => 'Obrigado por contar como você se sente sobre isso.'],
    ];

    private const RETORNO_GERAL = [
        'baixo' => 'Obrigado por responder! De modo geral, você parece estar bem. Que ótimo!',
        'moderado' => 'Obrigado por responder! Algumas coisas vão bem e outras merecem um pouco mais de cuidado.',
        'alto' => 'Obrigado pela coragem de responder com sinceridade. Algumas coisas parecem difíceis agora, e pedir ajuda é um passo importante.',
        '' => 'Obrigado por responder! Suas respostas ajudam a escola a cuidar melhor de todos.',
    ];

    public const AJUDA = 'Se precisar conversar com alguém agora, o CVV atende de graça, 24 horas, pelo telefone 188 ou em cvv.org.br.';

    /** @var array<string, string>|null nível pela regra ("categoria|rótulo"), para resultados gravados sem o nível */
    private static ?array $niveisRegra = null;

    /** @var array<int, array{nome: string, cor: ?string}> */
    private static array $categorias = [];

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
        $nivel = self::nivelGeral(array_filter($classificacao, static fn ($c) => is_array($c) && $c['nivel'] !== null));
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
                'nivel' => $nivel,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            Db::update('resultados', [
                'totais_json' => $totaisJson,
                'classificacao_json' => $classificacaoJson,
                'nivel' => $nivel,
            ], (int) $existente);
        }
        return ['totais' => $totais, 'classificacao' => $classificacao];
    }

    /** Preenche resultados.nivel a partir da classificação gravada (bancos anteriores à coluna). */
    public static function recalcularNiveis(): int
    {
        $total = 0;
        foreach (Db::all('SELECT id, classificacao_json FROM resultados') as $row) {
            $nivel = self::nivelGeral(self::decode($row['classificacao_json']));
            if ($nivel !== null) {
                Db::run('UPDATE resultados SET nivel = ? WHERE id = ?', [$nivel, $row['id']]);
                $total++;
            }
        }
        return $total;
    }

    /**
     * Situação geral do aluno: o nível mais grave entre as categorias.
     *
     * @param array<string, mixed> $classificacao
     */
    public static function nivelGeral(array $classificacao): ?string
    {
        $pior = null;
        foreach ($classificacao as $categoria => $classe) {
            $nivel = self::nivelDaClasse((string) $categoria, $classe);
            if ($nivel !== null && self::GRAVIDADE[$nivel] > (self::GRAVIDADE[$pior] ?? 0)) {
                $pior = $nivel;
            }
        }
        return $pior;
    }

    public static function nivelRotulo(?string $nivel): string
    {
        return Indicadores::NIVEIS[$nivel ?? ''] ?? 'Sem classificação';
    }

    /**
     * Classificação por categoria para listas: [{categoria, rotulo, nivel}].
     *
     * @return list<array{categoria: string, rotulo: string, nivel: ?string}>
     */
    public static function classes(string $classificacaoJson): array
    {
        $classes = self::decode($classificacaoJson);
        $info = self::categorias(array_map('intval', array_keys($classes)));
        $itens = [];
        foreach ($classes as $categoria => $classe) {
            $itens[] = [
                'categoria' => $info[(int) $categoria]['nome'] ?? 'Categoria #' . $categoria,
                'rotulo' => is_array($classe) ? (string) ($classe['rotulo'] ?? '') : 'sem regra',
                'nivel' => self::nivelDaClasse((string) $categoria, $classe),
            ];
        }
        return $itens;
    }

    /** "Ansiedade: Moderada · Humor: Adequado". */
    public static function resumoTexto(string $classificacaoJson): string
    {
        $partes = array_map(static fn (array $c) => $c['categoria'] . ': ' . $c['rotulo'], self::classes($classificacaoJson));
        return $partes === [] ? '—' : implode(' · ', $partes);
    }

    /**
     * Resultado completo de um aluno: dados, situação, classificação por categoria e as respostas dadas.
     *
     * @return array<string, mixed>|null
     */
    public static function detalhe(int $resultadoId): ?array
    {
        $r = Db::one(
            "SELECT r.id, r.aplicacao_id, r.aluno_id, r.totais_json, r.classificacao_json, r.nivel, r.created_at,
                    ap.questionario_id, ap.codigo_sala, q.nome AS questionario, q.versao,
                    al.nome AS aluno, al.matricula, e.nome AS escola,
                    IF(tu.id IS NULL, NULL, CONCAT_WS(' ', s.descricao, tu.nome)) AS turma, tu.turno
             FROM resultados r
             JOIN aplicacoes_questionario ap ON ap.id = r.aplicacao_id
             JOIN alunos al ON al.id = r.aluno_id
             LEFT JOIN questionarios q ON q.id = ap.questionario_id
             LEFT JOIN turmas tu ON tu.id = al.turma_id
             LEFT JOIN series s ON s.id = tu.serie_id
             LEFT JOIN escolas e ON e.id = al.escola_id
             WHERE r.id = ?",
            [$resultadoId]
        );
        if ($r === null) {
            return null;
        }
        $totais = self::decode($r['totais_json']);
        $classes = self::decode($r['classificacao_json']);
        $perguntas = Db::all(
            'SELECT p.id, p.categoria_id, p.texto, p.tipo, c.nome AS categoria, c.cor,
                    rs.id AS resposta_id, rs.texto AS resposta_texto, o.descricao AS opcao, o.pontuacao, o.emoji
             FROM perguntas p
             JOIN categorias c ON c.id = p.categoria_id
             LEFT JOIN respostas rs ON rs.pergunta_id = p.id AND rs.aplicacao_id = ? AND rs.aluno_id = ?
             LEFT JOIN opcoes_resposta o ON o.id = rs.opcao_id
             WHERE c.questionario_id = ? AND ((p.deleted_at IS NULL AND c.deleted_at IS NULL) OR rs.id IS NOT NULL)
             ORDER BY c.ordem, c.id, p.ordem, p.id',
            [$r['aplicacao_id'], $r['aluno_id'], (int) $r['questionario_id']]
        );

        $categorias = [];
        $respondidas = 0;
        foreach ($perguntas as $p) {
            $catId = (int) $p['categoria_id'];
            $categorias[$catId] ??= self::categoriaDetalhe($catId, $p['categoria'], $p['cor'], $totais, $classes);
            $respondida = $p['resposta_id'] !== null;
            $respondidas += $respondida ? 1 : 0;
            $texto = $p['opcao'] ?? $p['resposta_texto'];
            $categorias[$catId]['perguntas'][] = [
                'numero' => count($categorias[$catId]['perguntas']) + 1,
                'texto' => (string) $p['texto'],
                'respondida' => $respondida,
                'resposta' => $respondida ? (trim((string) $texto) === '' ? '(em branco)' : (string) $texto) : null,
                'emoji' => $p['emoji'],
                'pontos' => $p['pontuacao'] === null ? null : (int) $p['pontuacao'],
            ];
        }
        $faltando = array_diff(array_map('intval', array_keys($totais + $classes)), array_keys($categorias));
        $info = self::categorias($faltando);
        foreach ($faltando as $catId) {
            $categorias[$catId] = self::categoriaDetalhe($catId, $info[$catId]['nome'] ?? 'Categoria #' . $catId, $info[$catId]['cor'] ?? null, $totais, $classes);
        }

        $nivel = $r['nivel'] ?? self::nivelGeral($classes);
        return [
            'nivel' => $nivel,
            'nivel_rotulo' => self::nivelRotulo($nivel),
            'observacao' => self::OBSERVACAO[$nivel ?? ''] ?? 'Nenhuma regra de classificação com nível para este questionário.',
            'aluno' => ['id' => (int) $r['aluno_id'], 'nome' => $r['aluno'], 'matricula' => $r['matricula']],
            'turma' => $r['turma'],
            'turno' => $r['turno'],
            'escola' => $r['escola'],
            'questionario' => $r['questionario'] === null ? null : $r['questionario'] . ' (v' . $r['versao'] . ')',
            'aplicacao' => ['id' => (int) $r['aplicacao_id'], 'codigo_sala' => $r['codigo_sala']],
            'concluido_em' => $r['created_at'],
            'respondidas' => $respondidas,
            'total_perguntas' => count($perguntas),
            'categorias' => array_values($categorias),
        ];
    }

    /**
     * Retorno simplificado que o próprio aluno vê ao concluir: mensagem geral e uma por categoria.
     * Null se ainda não concluiu ou se o questionário não mostra retorno.
     *
     * @return array{mensagem: string, tom: string, categorias: list<array{nome: string, cor: ?string, tom: string, emoji: string, mensagem: string}>, ajuda: ?string}|null
     */
    public static function retornoAluno(int $aplicacaoId, int $alunoId): ?array
    {
        $r = Db::one(
            'SELECT r.classificacao_json, r.nivel, q.mostrar_retorno
             FROM resultados r
             JOIN aplicacoes_questionario ap ON ap.id = r.aplicacao_id
             LEFT JOIN questionarios q ON q.id = ap.questionario_id
             WHERE r.aplicacao_id = ? AND r.aluno_id = ?',
            [$aplicacaoId, $alunoId]
        );
        if ($r === null || (int) ($r['mostrar_retorno'] ?? 1) !== 1) {
            return null;
        }
        $classes = self::decode($r['classificacao_json']);
        $ids = array_map('intval', array_keys($classes));
        $categorias = $ids === [] ? [] : Db::all(
            'SELECT id, nome, cor FROM categorias WHERE id IN (' . Db::in($ids) . ') ORDER BY ordem, id',
            $ids
        );
        $mensagens = [];
        if ($ids !== []) {
            foreach (Db::all(
                "SELECT categoria_id, rotulo, mensagem_aluno FROM regras_classificacao
                 WHERE categoria_id IN (" . Db::in($ids) . ") AND deleted_at IS NULL AND TRIM(COALESCE(mensagem_aluno, '')) <> ''",
                $ids
            ) as $regra) {
                $mensagens[$regra['categoria_id'] . '|' . $regra['rotulo']] = trim((string) $regra['mensagem_aluno']);
            }
        }

        $itens = [];
        foreach ($categorias as $c) {
            $classe = $classes[(string) $c['id']] ?? null;
            $nivel = self::nivelDaClasse((string) $c['id'], $classe);
            $padrao = self::RETORNO[$nivel ?? ''];
            $propria = is_array($classe) ? ($mensagens[$c['id'] . '|' . ($classe['rotulo'] ?? '')] ?? null) : null;
            $itens[] = [
                'nome' => (string) $c['nome'],
                'cor' => $c['cor'],
                'tom' => $padrao['tom'],
                'emoji' => $padrao['emoji'],
                'mensagem' => $propria ?? $padrao['mensagem'],
            ];
        }
        $geral = $r['nivel'] ?? self::nivelGeral($classes);
        return [
            'mensagem' => self::RETORNO_GERAL[$geral ?? ''],
            'tom' => self::RETORNO[$geral ?? '']['tom'],
            'categorias' => $itens,
            'ajuda' => $geral === 'alto' ? self::AJUDA : null,
        ];
    }

    /**
     * @param array<string, mixed> $totais
     * @param array<string, mixed> $classes
     * @return array<string, mixed>
     */
    private static function categoriaDetalhe(int $id, string $nome, ?string $cor, array $totais, array $classes): array
    {
        $classe = $classes[(string) $id] ?? null;
        $nivel = self::nivelDaClasse((string) $id, $classe);
        $total = $totais[(string) $id] ?? null;
        return [
            'id' => $id,
            'nome' => $nome,
            'cor' => $cor,
            'total' => is_numeric($total) ? (float) $total : null,
            'rotulo' => is_array($classe) ? ($classe['rotulo'] ?? null) : null,
            'descricao' => is_array($classe) ? ($classe['descricao'] ?? null) : null,
            'nivel' => $nivel,
            'nivel_rotulo' => self::nivelRotulo($nivel),
            'perguntas' => [],
        ];
    }

    private static function nivelDaClasse(string $categoria, mixed $classe): ?string
    {
        if (!is_array($classe)) {
            return null;
        }
        $nivel = $classe['nivel'] ?? null;
        if ($nivel === null && isset($classe['rotulo'])) {
            if (self::$niveisRegra === null) {
                self::$niveisRegra = [];
                foreach (Db::all('SELECT categoria_id, rotulo, nivel FROM regras_classificacao WHERE nivel IS NOT NULL AND categoria_id IS NOT NULL') as $regra) {
                    self::$niveisRegra[$regra['categoria_id'] . '|' . $regra['rotulo']] = $regra['nivel'];
                }
            }
            $nivel = self::$niveisRegra[$categoria . '|' . $classe['rotulo']] ?? null;
        }
        return isset(self::GRAVIDADE[$nivel ?? '']) ? $nivel : null;
    }

    /**
     * @param list<int> $ids
     * @return array<int, array{nome: string, cor: ?string}>
     */
    private static function categorias(array $ids): array
    {
        $faltando = array_values(array_diff(array_unique(array_filter($ids)), array_keys(self::$categorias)));
        if ($faltando !== []) {
            foreach (Db::all('SELECT id, nome, cor FROM categorias WHERE id IN (' . Db::in($faltando) . ')', $faltando) as $c) {
                self::$categorias[(int) $c['id']] = ['nome' => (string) $c['nome'], 'cor' => $c['cor']];
            }
        }
        return array_intersect_key(self::$categorias, array_flip($ids));
    }

    /** @return array<string, mixed> */
    private static function decode(mixed $json): array
    {
        $data = json_decode((string) $json, true);
        return is_array($data) ? $data : [];
    }
}
