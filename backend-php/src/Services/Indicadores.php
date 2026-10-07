<?php

declare(strict_types=1);

namespace Vise\Services;

use Vise\Admin\Ctx;
use Vise\Admin\Resources;
use Vise\Db;
use Vise\Support\Tempo;
use Vise\Support\Turmas;

/**
 * Indicadores do painel e dos relatórios, sempre no escopo do usuário
 * (admin geral: tudo; demais: escolas vinculadas; professor: só as próprias turmas).
 */
final class Indicadores
{
    public const NIVEIS = ['baixo' => 'Adequado', 'moderado' => 'Atenção', 'alto' => 'Prioritário'];
    private const GRAVIDADE = ['baixo' => 1, 'moderado' => 2, 'alto' => 3];

    /** @var list<array<string, mixed>>|null */
    private ?array $resultados = null;
    /** @var array<int, array<string, mixed>> */
    private array $categorias = [];

    public function __construct(private Ctx $ctx, private FiltrosRelatorio $filtros)
    {
    }

    public function filtros(): FiltrosRelatorio
    {
        return $this->filtros;
    }

    /** @return array<string, mixed> */
    public function resumo(): array
    {
        [$al, $pa] = $this->condAlunos();
        $alunos = (int) Db::value("SELECT COUNT(*) FROM alunos al LEFT JOIN turmas tu ON tu.id = al.turma_id WHERE $al", $pa);
        [$tu, $pt] = $this->condTurmas();
        $turmas = (int) Db::value("SELECT COUNT(*) FROM turmas tu WHERE $tu", $pt);
        $escolas = $this->filtros->escolaId !== null ? 1 : ($this->ctx->su
            ? (int) Db::value('SELECT COUNT(*) FROM escolas WHERE deleted_at IS NULL AND ativo = 1')
            : count($this->ctx->escolaIds));

        $resultados = $this->resultados();
        $participantes = [];
        $prioritarios = [];
        $atencao = [];
        $ultima = null;
        foreach ($resultados as $r) {
            $participantes[$r['aluno_id']] = true;
            if ($r['nivel'] === 'alto') {
                $prioritarios[$r['aluno_id']] = true;
            } elseif ($r['nivel'] === 'moderado') {
                $atencao[$r['aluno_id']] = true;
            }
            $ultima = max($ultima ?? '', (string) $r['created_at']);
        }
        $atencao = array_diff_key($atencao, $prioritarios);

        [$ap, $pp] = $this->condAplicacoes();
        [$per, $pper] = $this->condPeriodo('rp.responded_at');
        $base = "FROM respostas rp
                 JOIN aplicacoes_questionario ap ON ap.id = rp.aplicacao_id
                 JOIN alunos al ON al.id = rp.aluno_id
                 LEFT JOIN turmas tu ON tu.id = al.turma_id
                 WHERE $al AND $ap AND $per";
        $params = array_merge($pa, $pp, $pper);
        $respostas = (int) Db::value("SELECT COUNT(*) $base", $params);
        $emAndamento = (int) Db::value(
            "SELECT COUNT(*) FROM (SELECT rp.aplicacao_id, rp.aluno_id $base
               AND NOT EXISTS (SELECT 1 FROM resultados rr WHERE rr.aplicacao_id = rp.aplicacao_id AND rr.aluno_id = rp.aluno_id)
             GROUP BY rp.aplicacao_id, rp.aluno_id) x",
            $params
        );
        [$apAlvo, $pAlvo] = $this->condAplicacoesAlvo();
        $ativas = (int) Db::value("SELECT COUNT(*) FROM aplicacoes_questionario ap WHERE ap.status = 'ativa' AND $apAlvo", $pAlvo);

        return [
            'escolas' => $escolas,
            'turmas' => $turmas,
            'alunos' => $alunos,
            'participantes' => count($participantes),
            'participacao' => $alunos > 0 ? round(count($participantes) / $alunos, 4) : null,
            'concluidos' => count($resultados),
            'em_andamento' => $emAndamento,
            'respostas' => $respostas,
            'aplicacoes_ativas' => $ativas,
            'prioritarios' => count($prioritarios),
            'atencao' => count($atencao),
            'ultima_conclusao' => $ultima,
        ];
    }

    /** @return array{granularidade: string, pontos: list<array{chave: string, rotulo: string, valor: int}>} */
    public function evolucao(): array
    {
        $contagem = [];
        $primeira = null;
        foreach ($this->resultados() as $r) {
            $dia = Tempo::local($r['created_at'], 'Y-m-d');
            $contagem[$dia] = ($contagem[$dia] ?? 0) + 1;
            $primeira = $primeira === null ? $dia : min($primeira, $dia);
        }
        $hoje = Tempo::hoje();
        $fim = $this->filtros->fim ?? $hoje;
        $inicio = $this->filtros->inicio ?? $primeira ?? (new \DateTimeImmutable($fim))->modify('-29 days')->format('Y-m-d');
        if ($this->filtros->inicio === null && (strtotime($fim) - strtotime($inicio)) / 86400 < 13) {
            $inicio = (new \DateTimeImmutable($fim))->modify('-13 days')->format('Y-m-d');
        }
        $dias = (int) round((strtotime($fim) - strtotime($inicio)) / 86400) + 1;

        $pontos = [];
        if ($dias <= 62) {
            for ($d = new \DateTimeImmutable($inicio); $d->format('Y-m-d') <= $fim; $d = $d->modify('+1 day')) {
                $chave = $d->format('Y-m-d');
                $pontos[] = ['chave' => $chave, 'rotulo' => $d->format('d/m'), 'valor' => $contagem[$chave] ?? 0];
            }
            return ['granularidade' => 'dia', 'pontos' => $pontos];
        }
        $porMes = [];
        foreach ($contagem as $dia => $valor) {
            $porMes[substr($dia, 0, 7)] = ($porMes[substr($dia, 0, 7)] ?? 0) + $valor;
        }
        $meses = ['01' => 'jan', '02' => 'fev', '03' => 'mar', '04' => 'abr', '05' => 'mai', '06' => 'jun', '07' => 'jul', '08' => 'ago', '09' => 'set', '10' => 'out', '11' => 'nov', '12' => 'dez'];
        for ($d = new \DateTimeImmutable(substr($inicio, 0, 7) . '-01'); $d->format('Y-m') <= substr($fim, 0, 7); $d = $d->modify('+1 month')) {
            $chave = $d->format('Y-m');
            $pontos[] = ['chave' => $chave, 'rotulo' => $meses[$d->format('m')] . '/' . $d->format('y'), 'valor' => $porMes[$chave] ?? 0];
        }
        return ['granularidade' => 'mes', 'pontos' => $pontos];
    }

    /**
     * Distribuição das classificações por categoria (faixas ordenadas: adequado → atenção → prioritário → sem nível).
     *
     * @return list<array<string, mixed>>
     */
    public function classificacao(): array
    {
        $porCategoria = [];
        foreach ($this->resultados() as $r) {
            foreach ($r['categorias'] as $catId => $cat) {
                $item = &$porCategoria[$catId];
                $item ??= ['avaliacoes' => 0, 'soma' => 0.0, 'min' => null, 'max' => null, 'faixas' => [], 'niveis' => ['baixo' => 0, 'moderado' => 0, 'alto' => 0, 'sem' => 0]];
                $item['avaliacoes']++;
                if ($cat['total'] !== null) {
                    $item['soma'] += $cat['total'];
                    $item['min'] = $item['min'] === null ? $cat['total'] : min($item['min'], $cat['total']);
                    $item['max'] = $item['max'] === null ? $cat['total'] : max($item['max'], $cat['total']);
                }
                $rotulo = $cat['rotulo'] ?? 'Sem classificação';
                $item['faixas'][$rotulo] ??= ['rotulo' => $rotulo, 'nivel' => $cat['nivel'], 'qtd' => 0];
                $item['faixas'][$rotulo]['qtd']++;
                $item['niveis'][$cat['nivel'] ?? 'sem']++;
                unset($item);
            }
        }
        $info = $this->categoriasInfo(array_keys($porCategoria));
        $lista = [];
        foreach ($porCategoria as $catId => $item) {
            $faixas = array_values($item['faixas']);
            usort($faixas, static fn ($a, $b) => [self::GRAVIDADE[$a['nivel'] ?? ''] ?? 9, $a['rotulo']] <=> [self::GRAVIDADE[$b['nivel'] ?? ''] ?? 9, $b['rotulo']]);
            foreach ($faixas as &$faixa) {
                $faixa['pct'] = round($faixa['qtd'] / $item['avaliacoes'], 4);
            }
            unset($faixa);
            $lista[] = [
                'categoria_id' => $catId,
                'categoria' => $info[$catId]['nome'] ?? 'Categoria #' . $catId,
                'questionario' => $info[$catId]['questionario'] ?? '',
                'ordem' => $info[$catId]['ordem'] ?? 0,
                'cor' => $info[$catId]['cor'] ?? null,
                'avaliacoes' => $item['avaliacoes'],
                'media' => $item['avaliacoes'] > 0 ? round($item['soma'] / $item['avaliacoes'], 2) : null,
                'min' => $item['min'],
                'max' => $item['max'],
                'faixas' => $faixas,
                'niveis' => $item['niveis'],
            ];
        }
        usort($lista, static fn ($a, $b) => [$a['questionario'], $a['ordem'], $a['categoria']] <=> [$b['questionario'], $b['ordem'], $b['categoria']]);
        return $lista;
    }

    /** @return list<array<string, mixed>> uma linha por turma do recorte (e "Sem turma", se houver) */
    public function porTurma(): array
    {
        [$al, $pa] = $this->condAlunos();
        $alunos = [];
        foreach (Db::all("SELECT al.turma_id, COUNT(*) AS n FROM alunos al LEFT JOIN turmas tu ON tu.id = al.turma_id WHERE $al GROUP BY al.turma_id", $pa) as $row) {
            $alunos[(int) ($row['turma_id'] ?? 0)] = (int) $row['n'];
        }
        [$tu, $pt] = $this->condTurmas();
        $turmas = Db::all(
            "SELECT tu.id, tu.nome, tu.turno, s.descricao AS serie, e.id AS escola_id, e.nome AS escola
             FROM turmas tu LEFT JOIN series s ON s.id = tu.serie_id LEFT JOIN escolas e ON e.id = tu.escola_id
             WHERE $tu ORDER BY e.nome, s.descricao, tu.nome",
            $pt
        );
        $agregado = $this->agregar('turma_id');
        $linhas = [];
        foreach ($turmas as $t) {
            $id = (int) $t['id'];
            if (($alunos[$id] ?? 0) === 0 && !isset($agregado[$id])) {
                continue;
            }
            $linhas[] = $this->linhaAgregada($agregado[$id] ?? null, $alunos[$id] ?? 0) + [
                'turma_id' => $id, 'turma' => Turmas::rotulo($t['serie'], $t['nome']), 'serie' => $t['serie'], 'turno' => Resources::TURNOS[$t['turno']] ?? $t['turno'],
                'escola_id' => (int) $t['escola_id'], 'escola' => $t['escola'],
            ];
        }
        if (($alunos[0] ?? 0) > 0 || isset($agregado[0])) {
            $linhas[] = $this->linhaAgregada($agregado[0] ?? null, $alunos[0] ?? 0) + [
                'turma_id' => null, 'turma' => 'Sem turma', 'serie' => null, 'turno' => null, 'escola_id' => null, 'escola' => '—',
            ];
        }
        return $linhas;
    }

    /** @return list<array<string, mixed>> */
    public function porEscola(): array
    {
        [$al, $pa] = $this->condAlunos();
        $alunos = [];
        foreach (Db::all("SELECT al.escola_id, COUNT(*) AS n, COUNT(DISTINCT al.turma_id) AS turmas FROM alunos al LEFT JOIN turmas tu ON tu.id = al.turma_id WHERE $al GROUP BY al.escola_id", $pa) as $row) {
            $alunos[(int) $row['escola_id']] = ['n' => (int) $row['n'], 'turmas' => (int) $row['turmas']];
        }
        $agregado = $this->agregar('escola_id');
        $ids = array_values(array_unique(array_merge(array_keys($alunos), array_keys($agregado))));
        if ($ids === []) {
            return [];
        }
        $linhas = [];
        foreach (Db::all('SELECT id, nome, municipio, uf, inep FROM escolas WHERE id IN (' . Db::in($ids) . ') ORDER BY nome', $ids) as $e) {
            $id = (int) $e['id'];
            $linhas[] = $this->linhaAgregada($agregado[$id] ?? null, $alunos[$id]['n'] ?? 0) + [
                'escola_id' => $id, 'escola' => $e['nome'], 'municipio' => $e['municipio'] . '/' . $e['uf'], 'inep' => $e['inep'],
                'turmas' => $alunos[$id]['turmas'] ?? 0,
            ];
        }
        return $linhas;
    }

    /** @return list<array<string, mixed>> aplicações ativas com o progresso de cada uma */
    public function aplicacoesAtivas(int $limite = 0): array
    {
        [$apAlvo, $pAlvo] = $this->condAplicacoesAlvo();
        $turma = Turmas::sql('tu');
        $rows = Db::all(
            "SELECT ap.id, ap.alvo_tipo, ap.turma_id, ap.aluno_id, ap.escola_id, ap.codigo_sala, ap.created_at, ap.inicia_em, ap.termina_em,
                    q.nome AS questionario, q.versao, e.nome AS escola, $turma AS turma
             FROM aplicacoes_questionario ap
             LEFT JOIN questionarios q ON q.id = ap.questionario_id
             LEFT JOIN escolas e ON e.id = ap.escola_id
             LEFT JOIN turmas tu ON tu.id = ap.turma_id
             WHERE ap.status = 'ativa' AND $apAlvo
             ORDER BY ap.id DESC" . ($limite > 0 ? ' LIMIT ' . $limite : ''),
            $pAlvo
        );
        [$al, $pa] = $this->condAlunos();
        $lista = [];
        foreach ($rows as $row) {
            [$alvoSql, $alvoParams] = match ($row['alvo_tipo']) {
                'turma' => ['al.turma_id = ?', [(int) $row['turma_id']]],
                'aluno' => ['al.id = ?', [(int) $row['aluno_id']]],
                default => ['al.escola_id = ?', [(int) $row['escola_id']]],
            };
            $params = array_merge($pa, $alvoParams);
            $alvo = (int) Db::value("SELECT COUNT(*) FROM alunos al LEFT JOIN turmas tu ON tu.id = al.turma_id WHERE $al AND $alvoSql", $params);
            $concluidos = (int) Db::value(
                "SELECT COUNT(DISTINCT r.aluno_id) FROM resultados r JOIN alunos al ON al.id = r.aluno_id LEFT JOIN turmas tu ON tu.id = al.turma_id
                 WHERE r.aplicacao_id = ? AND $al AND $alvoSql",
                array_merge([(int) $row['id']], $params)
            );
            $iniciados = (int) Db::value(
                "SELECT COUNT(DISTINCT rp.aluno_id) FROM respostas rp JOIN alunos al ON al.id = rp.aluno_id LEFT JOIN turmas tu ON tu.id = al.turma_id
                 WHERE rp.aplicacao_id = ? AND $al AND $alvoSql",
                array_merge([(int) $row['id']], $params)
            );
            $lista[] = [
                'id' => (int) $row['id'],
                'questionario' => $row['questionario'] . ' (v' . $row['versao'] . ')',
                'escola' => $row['escola'],
                'alvo' => match ($row['alvo_tipo']) {
                    'turma' => 'Turma ' . ($row['turma'] ?? '#' . $row['turma_id']),
                    'aluno' => 'Aluno específico',
                    default => 'Toda a escola',
                },
                'codigo' => $row['codigo_sala'],
                'criada_em' => $row['created_at'],
                'termina_em' => $row['termina_em'],
                'alvo_total' => $alvo,
                'concluidos' => $concluidos,
                'em_andamento' => max(0, $iniciados - $concluidos),
                'progresso' => $alvo > 0 ? round(min(1, $concluidos / $alvo), 4) : null,
            ];
        }
        return $lista;
    }

    /** @return list<array<string, mixed>> resultados mais recentes com alguma categoria em nível prioritário */
    public function alertas(int $limite = 10): array
    {
        $alertas = array_values(array_filter($this->resultados(), static fn ($r) => $r['nivel'] === 'alto'));
        usort($alertas, static fn ($a, $b) => strcmp((string) $b['created_at'], (string) $a['created_at']));
        $info = $this->categoriasInfo(array_merge([], ...array_map(static fn ($r) => array_keys($r['categorias']), $alertas)));
        $lista = [];
        foreach (array_slice($alertas, 0, $limite > 0 ? $limite : null) as $r) {
            $categorias = [];
            foreach ($r['categorias'] as $catId => $cat) {
                if ($cat['nivel'] === 'alto') {
                    $categorias[] = ($info[$catId]['nome'] ?? 'Categoria #' . $catId) . ': ' . ($cat['rotulo'] ?? '—');
                }
            }
            $lista[] = [
                'resultado_id' => $r['id'], 'aluno_id' => $r['aluno_id'], 'aluno' => $r['aluno_nome'], 'turma' => $r['turma_nome'],
                'escola' => $r['escola_nome'], 'questionario' => $r['questionario_nome'], 'categorias' => $categorias, 'data' => $r['created_at'],
            ];
        }
        return $lista;
    }

    /** @return list<array<string, mixed>> por pergunta: opções com quantidade e percentual */
    public function distribuicaoRespostas(): array
    {
        [$al, $pa] = $this->condAlunos();
        [$ap, $pp] = $this->condAplicacoes();
        [$per, $pper] = $this->condPeriodo('rp.responded_at');
        $contagens = Db::all(
            "SELECT rp.pergunta_id, rp.opcao_id, COUNT(*) AS qtd, SUM(CASE WHEN rp.texto IS NOT NULL AND rp.texto <> '' THEN 1 ELSE 0 END) AS textos
             FROM respostas rp
             JOIN aplicacoes_questionario ap ON ap.id = rp.aplicacao_id
             JOIN alunos al ON al.id = rp.aluno_id
             LEFT JOIN turmas tu ON tu.id = al.turma_id
             WHERE $al AND $ap AND $per
             GROUP BY rp.pergunta_id, rp.opcao_id",
            array_merge($pa, $pp, $pper)
        );
        if ($contagens === []) {
            return [];
        }
        $porPergunta = [];
        foreach ($contagens as $c) {
            $pid = (int) $c['pergunta_id'];
            $porPergunta[$pid] ??= ['total' => 0, 'textos' => 0, 'opcoes' => []];
            $porPergunta[$pid]['total'] += (int) $c['qtd'];
            $porPergunta[$pid]['textos'] += (int) $c['textos'];
            if ($c['opcao_id'] !== null) {
                $porPergunta[$pid]['opcoes'][(int) $c['opcao_id']] = (int) $c['qtd'];
            }
        }
        $ids = array_keys($porPergunta);
        $perguntas = Db::all(
            'SELECT p.id, p.texto, p.tipo, p.ordem, c.nome AS categoria, c.ordem AS categoria_ordem, q.nome AS questionario, q.versao
             FROM perguntas p JOIN categorias c ON c.id = p.categoria_id JOIN questionarios q ON q.id = c.questionario_id
             WHERE p.id IN (' . Db::in($ids) . ') ORDER BY q.nome, q.versao, c.ordem, p.ordem, p.id',
            $ids
        );
        $opcoes = [];
        foreach (Db::all('SELECT id, pergunta_id, descricao, pontuacao, emoji FROM opcoes_resposta WHERE pergunta_id IN (' . Db::in($ids) . ') ORDER BY ordem, id', $ids) as $o) {
            $opcoes[(int) $o['pergunta_id']][] = $o;
        }
        $lista = [];
        foreach ($perguntas as $p) {
            $pid = (int) $p['id'];
            $dados = $porPergunta[$pid];
            $itens = [];
            foreach ($opcoes[$pid] ?? [] as $o) {
                $qtd = $dados['opcoes'][(int) $o['id']] ?? 0;
                $itens[] = [
                    'opcao' => trim(($o['emoji'] ? $o['emoji'] . ' ' : '') . $o['descricao']),
                    'pontuacao' => (int) $o['pontuacao'],
                    'qtd' => $qtd,
                    'pct' => $dados['total'] > 0 ? round($qtd / $dados['total'], 4) : 0.0,
                ];
            }
            $lista[] = [
                'pergunta_id' => $pid, 'questionario' => $p['questionario'] . ' (v' . $p['versao'] . ')', 'categoria' => $p['categoria'],
                'pergunta' => $p['texto'], 'tipo' => $p['tipo'], 'total' => $dados['total'], 'textos' => $dados['textos'], 'opcoes' => $itens,
            ];
        }
        return $lista;
    }

    /** @return array{linhas: list<array<string, mixed>>, truncado: bool} */
    public function respostasDetalhadas(int $limite = 50000): array
    {
        [$al, $pa] = $this->condAlunos();
        [$ap, $pp] = $this->condAplicacoes();
        [$per, $pper] = $this->condPeriodo('rp.responded_at');
        $turma = Turmas::sql('tu');
        $linhas = Db::all(
            "SELECT rp.responded_at, rp.tempo_gasto_ms, al.id AS aluno_id, al.nome AS aluno, al.cpf, al.matricula,
                    e.nome AS escola, $turma AS turma, q.nome AS questionario, q.versao, ap.codigo_sala,
                    c.nome AS categoria, p.texto AS pergunta, o.descricao AS opcao, o.pontuacao, rp.texto
             FROM respostas rp
             JOIN aplicacoes_questionario ap ON ap.id = rp.aplicacao_id
             JOIN alunos al ON al.id = rp.aluno_id
             LEFT JOIN turmas tu ON tu.id = al.turma_id
             LEFT JOIN escolas e ON e.id = al.escola_id
             JOIN perguntas p ON p.id = rp.pergunta_id
             JOIN categorias c ON c.id = p.categoria_id
             LEFT JOIN questionarios q ON q.id = ap.questionario_id
             LEFT JOIN opcoes_resposta o ON o.id = rp.opcao_id
             WHERE $al AND $ap AND $per
             ORDER BY e.nome, tu.nome, al.nome, q.nome, c.ordem, p.ordem
             LIMIT " . ($limite + 1),
            array_merge($pa, $pp, $pper)
        );
        $truncado = count($linhas) > $limite;
        return ['linhas' => array_slice($linhas, 0, $limite), 'truncado' => $truncado];
    }

    /** @return array{linhas: list<array<string, mixed>>, truncado: bool} alunos-alvo de aplicações ativas que ainda não concluíram */
    public function pendentes(int $limite = 20000): array
    {
        [$al, $pa] = $this->condAlunos();
        [$apAlvo, $pAlvo] = $this->condAplicacoesAlvo();
        $turma = Turmas::sql('tu');
        $linhas = Db::all(
            "SELECT ap.id AS aplicacao_id, ap.codigo_sala, q.nome AS questionario, q.versao, e.nome AS escola, $turma AS turma,
                    al.id AS aluno_id, al.nome AS aluno, al.cpf, al.matricula, al.telefone, al.responsavel, al.contato_responsavel,
                    (SELECT COUNT(*) FROM respostas rp WHERE rp.aplicacao_id = ap.id AND rp.aluno_id = al.id) AS respondidas
             FROM aplicacoes_questionario ap
             JOIN alunos al ON al.escola_id = ap.escola_id AND (
                    ap.alvo_tipo = 'escola'
                 OR (ap.alvo_tipo = 'turma' AND al.turma_id = ap.turma_id)
                 OR (ap.alvo_tipo = 'aluno' AND al.id = ap.aluno_id))
             LEFT JOIN turmas tu ON tu.id = al.turma_id
             LEFT JOIN escolas e ON e.id = al.escola_id
             LEFT JOIN questionarios q ON q.id = ap.questionario_id
             WHERE ap.status = 'ativa' AND $apAlvo AND $al
               AND NOT EXISTS (SELECT 1 FROM resultados r WHERE r.aplicacao_id = ap.id AND r.aluno_id = al.id)
             ORDER BY e.nome, tu.nome, al.nome, q.nome
             LIMIT " . ($limite + 1),
            array_merge($pAlvo, $pa)
        );
        $truncado = count($linhas) > $limite;
        return ['linhas' => array_slice($linhas, 0, $limite), 'truncado' => $truncado];
    }

    /**
     * Resultados (questionários concluídos) do recorte, com a classificação por categoria já decodificada.
     *
     * @return list<array<string, mixed>>
     */
    public function resultados(): array
    {
        if ($this->resultados !== null) {
            return $this->resultados;
        }
        [$al, $pa] = $this->condAlunos();
        [$ap, $pp] = $this->condAplicacoes();
        [$per, $pper] = $this->condPeriodo('r.created_at');
        $rows = Db::all(
            "SELECT r.id, r.aluno_id, r.aplicacao_id, r.totais_json, r.classificacao_json, r.created_at,
                    ap.questionario_id, ap.codigo_sala, q.nome AS questionario_nome, q.versao,
                    al.nome AS aluno_nome, al.cpf, al.matricula, al.sexo, al.data_nascimento, al.turma_id, al.escola_id,
                    IF(tu.id IS NULL, NULL, CONCAT_WS(' ', s.descricao, tu.nome)) AS turma_nome, tu.turno, s.descricao AS serie, e.nome AS escola_nome
             FROM resultados r
             JOIN aplicacoes_questionario ap ON ap.id = r.aplicacao_id
             JOIN alunos al ON al.id = r.aluno_id
             LEFT JOIN turmas tu ON tu.id = al.turma_id
             LEFT JOIN series s ON s.id = tu.serie_id
             LEFT JOIN escolas e ON e.id = al.escola_id
             LEFT JOIN questionarios q ON q.id = ap.questionario_id
             WHERE $al AND $ap AND $per
             ORDER BY e.nome, tu.nome, al.nome, r.created_at",
            array_merge($pa, $pp, $pper)
        );
        $niveisRegra = [];
        foreach (Db::all('SELECT categoria_id, rotulo, nivel FROM regras_classificacao WHERE nivel IS NOT NULL AND categoria_id IS NOT NULL') as $regra) {
            $niveisRegra[$regra['categoria_id'] . '|' . $regra['rotulo']] = $regra['nivel'];
        }
        foreach ($rows as &$row) {
            $totais = json_decode((string) $row['totais_json'], true);
            $classes = json_decode((string) $row['classificacao_json'], true);
            $totais = is_array($totais) ? $totais : [];
            $classes = is_array($classes) ? $classes : [];
            $categorias = [];
            $pior = null;
            foreach (array_unique(array_merge(array_keys($totais), array_keys($classes))) as $catId) {
                $classe = is_array($classes[$catId] ?? null) ? $classes[$catId] : null;
                $rotulo = $classe['rotulo'] ?? null;
                $nivel = $classe['nivel'] ?? ($rotulo !== null ? ($niveisRegra[$catId . '|' . $rotulo] ?? null) : null);
                $nivel = isset(self::GRAVIDADE[$nivel ?? '']) ? $nivel : null;
                $categorias[(int) $catId] = [
                    'total' => isset($totais[$catId]) && is_numeric($totais[$catId]) ? (float) $totais[$catId] : null,
                    'rotulo' => $rotulo,
                    'nivel' => $nivel,
                ];
                if ($nivel !== null && (self::GRAVIDADE[$nivel] > (self::GRAVIDADE[$pior] ?? 0))) {
                    $pior = $nivel;
                }
            }
            unset($row['totais_json'], $row['classificacao_json']);
            $row['id'] = (int) $row['id'];
            $row['aluno_id'] = (int) $row['aluno_id'];
            $row['turma_id'] = $row['turma_id'] === null ? null : (int) $row['turma_id'];
            $row['escola_id'] = (int) $row['escola_id'];
            $row['categorias'] = $categorias;
            $row['nivel'] = $pior;
        }
        unset($row);
        return $this->resultados = $rows;
    }

    /**
     * @param list<int> $ids
     * @return array<int, array{nome: string, ordem: int, cor: ?string, questionario: string}>
     */
    public function categoriasInfo(array $ids): array
    {
        $faltando = array_values(array_diff(array_unique(array_map('intval', $ids)), array_keys($this->categorias)));
        if ($faltando !== []) {
            foreach (Db::all(
                "SELECT c.id, c.nome, c.ordem, c.cor, CONCAT(q.nome, ' (v', q.versao, ')') AS questionario
                 FROM categorias c LEFT JOIN questionarios q ON q.id = c.questionario_id WHERE c.id IN (" . Db::in($faltando) . ')',
                $faltando
            ) as $c) {
                $this->categorias[(int) $c['id']] = ['nome' => $c['nome'], 'ordem' => (int) $c['ordem'], 'cor' => $c['cor'], 'questionario' => (string) $c['questionario']];
            }
        }
        return array_intersect_key($this->categorias, array_flip(array_map('intval', $ids)));
    }

    // ------------------------------------------------------------------ agregações

    /** @return array<int, array{participantes: array<int, true>, concluidos: int, prioritarios: array<int, true>, atencao: array<int, true>}> */
    private function agregar(string $campo): array
    {
        $grupos = [];
        foreach ($this->resultados() as $r) {
            $chave = (int) ($r[$campo] ?? 0);
            $grupos[$chave] ??= ['participantes' => [], 'concluidos' => 0, 'prioritarios' => [], 'atencao' => []];
            $grupos[$chave]['participantes'][$r['aluno_id']] = true;
            $grupos[$chave]['concluidos']++;
            if ($r['nivel'] === 'alto') {
                $grupos[$chave]['prioritarios'][$r['aluno_id']] = true;
            } elseif ($r['nivel'] === 'moderado') {
                $grupos[$chave]['atencao'][$r['aluno_id']] = true;
            }
        }
        return $grupos;
    }

    /** @param array<string, mixed>|null $grupo */
    private function linhaAgregada(?array $grupo, int $alunos): array
    {
        $participantes = $grupo === null ? 0 : count($grupo['participantes']);
        $prioritarios = $grupo === null ? 0 : count($grupo['prioritarios']);
        $atencao = $grupo === null ? 0 : count(array_diff_key($grupo['atencao'], $grupo['prioritarios']));
        return [
            'alunos' => $alunos,
            'participantes' => $participantes,
            'pendentes' => max(0, $alunos - $participantes),
            'participacao' => $alunos > 0 ? round(min(1, $participantes / $alunos), 4) : null,
            'concluidos' => $grupo['concluidos'] ?? 0,
            'prioritarios' => $prioritarios,
            'atencao' => $atencao,
        ];
    }

    // ------------------------------------------------------------------ condições SQL

    /** @return array{0: string, 1: list<mixed>} alunos (alias al; turmas LEFT JOIN como tu) */
    private function condAlunos(): array
    {
        $c = ['al.deleted_at IS NULL'];
        $p = [];
        if (!$this->ctx->su) {
            $c[] = 'al.escola_id IN (' . Db::in($this->ctx->escolaIds) . ')';
            array_push($p, ...$this->ctx->escolaIds);
        }
        if ($this->ctx->professorOnly()) {
            $c[] = 'al.turma_id IN (' . Db::in($this->ctx->turmaIds) . ')';
            array_push($p, ...$this->ctx->turmaIds);
        }
        $f = $this->filtros;
        foreach ([['al.escola_id', $f->escolaId], ['al.turma_id', $f->turmaId], ['tu.serie_id', $f->serieId], ['tu.turno', $f->turno], ['al.sexo', $f->sexo]] as [$coluna, $valor]) {
            if ($valor !== null) {
                $c[] = "$coluna = ?";
                $p[] = $valor;
            }
        }
        return [implode(' AND ', $c), $p];
    }

    /** @return array{0: string, 1: list<mixed>} turmas (alias tu) */
    private function condTurmas(): array
    {
        $c = ['tu.deleted_at IS NULL'];
        $p = [];
        if (!$this->ctx->su) {
            $c[] = 'tu.escola_id IN (' . Db::in($this->ctx->escolaIds) . ')';
            array_push($p, ...$this->ctx->escolaIds);
        }
        if ($this->ctx->professorOnly()) {
            $c[] = 'tu.id IN (' . Db::in($this->ctx->turmaIds) . ')';
            array_push($p, ...$this->ctx->turmaIds);
        }
        $f = $this->filtros;
        foreach ([['tu.escola_id', $f->escolaId], ['tu.id', $f->turmaId], ['tu.serie_id', $f->serieId], ['tu.turno', $f->turno]] as [$coluna, $valor]) {
            if ($valor !== null) {
                $c[] = "$coluna = ?";
                $p[] = $valor;
            }
        }
        return [implode(' AND ', $c), $p];
    }

    /** @return array{0: string, 1: list<mixed>} aplicações (alias ap) por escola, questionário e aplicação */
    private function condAplicacoes(): array
    {
        $c = ['ap.deleted_at IS NULL'];
        $p = [];
        if (!$this->ctx->su) {
            $c[] = 'ap.escola_id IN (' . Db::in($this->ctx->escolaIds) . ')';
            array_push($p, ...$this->ctx->escolaIds);
        }
        foreach ([['ap.escola_id', $this->filtros->escolaId], ['ap.questionario_id', $this->filtros->questionarioId], ['ap.id', $this->filtros->aplicacaoId]] as [$coluna, $valor]) {
            if ($valor !== null) {
                $c[] = "$coluna = ?";
                $p[] = $valor;
            }
        }
        return [implode(' AND ', $c), $p];
    }

    /** @return array{0: string, 1: list<mixed>} aplicações visíveis (professor: escola toda ou suas turmas) e compatíveis com o filtro de turma */
    private function condAplicacoesAlvo(): array
    {
        [$c, $p] = $this->condAplicacoes();
        if ($this->ctx->professorOnly()) {
            $in = Db::in($this->ctx->turmaIds);
            $c .= " AND (ap.alvo_tipo = 'escola' OR (ap.alvo_tipo = 'turma' AND ap.turma_id IN ($in))"
                . " OR (ap.alvo_tipo = 'aluno' AND ap.aluno_id IN (SELECT s_al.id FROM alunos s_al WHERE s_al.turma_id IN ($in))))";
            array_push($p, ...$this->ctx->turmaIds, ...$this->ctx->turmaIds);
        }
        if ($this->filtros->turmaId !== null) {
            $c .= " AND (ap.alvo_tipo = 'escola' OR (ap.alvo_tipo = 'turma' AND ap.turma_id = ?)"
                . " OR (ap.alvo_tipo = 'aluno' AND ap.aluno_id IN (SELECT s_al.id FROM alunos s_al WHERE s_al.turma_id = ?)))";
            array_push($p, $this->filtros->turmaId, $this->filtros->turmaId);
        }
        return [$c, $p];
    }

    /** @return array{0: string, 1: list<mixed>} */
    private function condPeriodo(string $coluna): array
    {
        $c = ['1=1'];
        $p = [];
        if (($inicio = $this->filtros->inicioUtc()) !== null) {
            $c[] = "$coluna >= ?";
            $p[] = $inicio;
        }
        if (($fim = $this->filtros->fimUtc()) !== null) {
            $c[] = "$coluna <= ?";
            $p[] = $fim;
        }
        return [implode(' AND ', $c), $p];
    }
}
