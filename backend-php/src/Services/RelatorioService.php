<?php

declare(strict_types=1);

namespace Vise\Services;

use Vise\Admin\Ctx;
use Vise\Config;
use Vise\Support\Str;
use Vise\Support\Tempo;
use Vise\Support\XlsxWriter as X;

/** Relatório de resultados em Excel: resumo + abas escolhidas pelo usuário, no recorte dos filtros. */
final class RelatorioService
{
    public const ABAS = [
        'resumo' => ['Resumo', 'Filtros aplicados, indicadores gerais e classificação por categoria.', true],
        'escolas' => ['Por escola', 'Participação e níveis de atenção por escola.', true],
        'turmas' => ['Por turma', 'Participação, pendências e níveis de atenção por turma.', true],
        'classificacao' => ['Classificação', 'Quantos alunos em cada faixa de cada categoria.', true],
        'alunos' => ['Resultados por aluno', 'Pontuação e classificação de cada aluno em cada categoria.', true],
        'perguntas' => ['Respostas por pergunta', 'Distribuição das respostas de cada pergunta.', true],
        'pendentes' => ['Pendentes', 'Alunos das aplicações ativas que ainda não concluíram (para busca ativa).', false],
        'respostas' => ['Respostas detalhadas', 'Todas as respostas, uma por linha (arquivo maior).', false],
    ];

    /** @return list<string> */
    public static function abasPadrao(): array
    {
        return array_keys(array_filter(self::ABAS, static fn ($aba) => $aba[2]));
    }

    /** @param mixed $valor lista (ou CSV) de abas vinda do formulário/URL */
    public static function abasEscolhidas(mixed $valor): array
    {
        if (is_string($valor)) {
            $valor = explode(',', $valor);
        }
        if (!is_array($valor) || $valor === []) {
            return self::abasPadrao();
        }
        $abas = array_values(array_intersect(array_keys(self::ABAS), array_map('strval', $valor)));
        return $abas === [] ? self::abasPadrao() : array_values(array_unique(array_merge(['resumo'], $abas)));
    }

    /**
     * @param list<string> $abas
     * @return array{conteudo: string, arquivo: string}
     */
    public static function gerar(Ctx $ctx, FiltrosRelatorio $filtros, array $abas, bool $anonimizar): array
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }
        $ind = new Indicadores($ctx, $filtros);
        $x = new X($ctx->name);
        $anon = $anonimizar ? static fn (int $id): string => 'Aluno ' . strtoupper(substr(hash_hmac('sha256', 'aluno:' . $id, Config::secretKey()), 0, 8)) : null;

        foreach ($abas as $aba) {
            match ($aba) {
                'resumo' => self::resumo($x, $ctx, $ind, $anonimizar),
                'escolas' => self::escolas($x, $ind),
                'turmas' => self::turmas($x, $ind),
                'classificacao' => self::classificacao($x, $ind),
                'alunos' => self::alunos($x, $ind, $anon),
                'perguntas' => self::perguntas($x, $ind),
                'pendentes' => self::pendentes($x, $ind, $anon),
                'respostas' => self::respostas($x, $ind, $anon),
                default => null,
            };
        }
        $sufixo = $filtros->escolaId !== null ? '-' . self::slug((string) ($filtros->descricao()[1][1] ?? '')) : '';
        return [
            'conteudo' => $x->gerar(),
            'arquivo' => 'relatorio-vise' . $sufixo . '-' . Tempo::local(gmdate('Y-m-d H:i:s'), 'Ymd-Hi') . '.xlsx',
        ];
    }

    private static function resumo(X $x, Ctx $ctx, Indicadores $ind, bool $anonimizar): void
    {
        $r = $ind->resumo();
        $linhas = [
            [X::c('Relatório de resultados — VISE-MT', 'title')],
            [X::c('Gerado em ' . Tempo::local(gmdate('Y-m-d H:i:s')) . ' por ' . $ctx->name . ' (' . $ctx->email . ')' . ($anonimizar ? ' · alunos anonimizados' : ''), 'muted')],
            [],
            [X::c('Filtros aplicados', 'subtitle')],
        ];
        foreach ($ind->filtros()->descricao() as [$rotulo, $valor]) {
            $linhas[] = [X::c($rotulo, 'bold'), $valor];
        }
        $linhas[] = [];
        $linhas[] = [X::c('Indicadores gerais', 'subtitle')];
        $linhas[] = [X::c('Indicador', 'header'), X::c('Valor', 'header'), X::c('Como ler', 'header')];
        $mesclar = ['C' . count($linhas) . ':I' . count($linhas)];
        $kpis = [
            ['Escolas', $r['escolas'], '', ''],
            ['Turmas', $r['turmas'], '', ''],
            ['Alunos cadastrados', $r['alunos'], 'Alunos no recorte dos filtros.', ''],
            ['Alunos que concluíram ao menos 1 questionário', $r['participantes'], 'No período selecionado.', ''],
            ['Taxa de participação', $r['participacao'] ?? 0.0, 'Alunos que concluíram ÷ alunos cadastrados.', 'percent'],
            ['Questionários concluídos', $r['concluidos'], 'Um aluno pode concluir mais de um questionário.', ''],
            ['Questionários em andamento', $r['em_andamento'], 'Iniciados e ainda não finalizados.', ''],
            ['Respostas registradas', $r['respostas'], '', ''],
            ['Aplicações ativas', $r['aplicacoes_ativas'], 'Salas abertas agora.', ''],
            ['Alunos em nível Prioritário', $r['prioritarios'], 'Ao menos uma categoria classificada como prioritária: sugerimos intervenção.', 'alert'],
            ['Alunos em nível de Atenção', $r['atencao'], 'Sem categoria prioritária, mas com alguma em atenção: acompanhar.', 'warn'],
        ];
        foreach ($kpis as [$rotulo, $valor, $ajuda, $estilo]) {
            $realce = in_array($estilo, ['alert', 'warn'], true) && $valor > 0 ? " $estilo" : '';
            $linhas[] = [
                X::c($rotulo, 'border' . $realce),
                X::c($valor, 'kpi border' . ($estilo === 'percent' ? ' percent' : '') . $realce),
                X::c($ajuda, 'muted border' . $realce),
            ];
            $mesclar[] = 'C' . count($linhas) . ':I' . count($linhas);
        }

        $linhas[] = [];
        $linhas[] = [X::c('Classificação por categoria', 'subtitle')];
        $cabecalho = ['Questionário', 'Categoria', 'Avaliações', 'Média de pontos', 'Adequado', 'Atenção', 'Prioritário', 'Sem nível', '% Prioritário'];
        $linhas[] = array_map(static fn ($c) => X::c($c, 'header'), $cabecalho);
        $classificacao = $ind->classificacao();
        foreach ($classificacao as $c) {
            $pct = $c['avaliacoes'] > 0 ? $c['niveis']['alto'] / $c['avaliacoes'] : 0.0;
            $linhas[] = [
                X::c($c['questionario'], 'border'), X::c($c['categoria'], 'border bold'), X::c($c['avaliacoes'], 'border'),
                X::c($c['media'], 'border decimal'), X::c($c['niveis']['baixo'], 'border ok'), X::c($c['niveis']['moderado'], 'border warn'),
                X::c($c['niveis']['alto'], 'border alert'), X::c($c['niveis']['sem'], 'border'), X::c($pct, 'border percent' . ($pct >= 0.2 ? ' alert' : '')),
            ];
        }
        if ($classificacao === []) {
            $linhas[] = [X::c('Nenhum questionário concluído no recorte.', 'muted')];
        }
        $linhas[] = [];
        $linhas[] = [X::c('Níveis: Adequado, Atenção e Prioritário vêm do campo "Nível de atenção" das regras de classificação. Categorias sem regra aparecem como "Sem nível".', 'muted')];
        $linhas[] = [X::c('Dados sensíveis de saúde mental (LGPD): compartilhe apenas com profissionais que precisam deles e não envie por canais públicos.', 'muted')];

        $x->aba('Resumo', $linhas, ['larguras' => [44, 30, 13, 15, 12, 12, 12, 12, 14], 'mesclar' => $mesclar]);
    }

    private static function escolas(X $x, Indicadores $ind): void
    {
        $cab = ['Escola', 'INEP', 'Município', 'Turmas', 'Alunos', 'Concluíram', 'Participação', 'Pendentes', 'Questionários concluídos', 'Prioritário', 'Atenção'];
        $linhas = [array_map(static fn ($c) => X::c($c, 'header'), $cab)];
        foreach ($ind->porEscola() as $e) {
            $linhas[] = [
                $e['escola'], X::c((string) $e['inep'], 'text'), $e['municipio'], $e['turmas'], $e['alunos'], $e['participantes'],
                self::participacao($e['participacao']), $e['pendentes'], $e['concluidos'],
                X::c($e['prioritarios'], $e['prioritarios'] > 0 ? 'alert' : ''), X::c($e['atencao'], $e['atencao'] > 0 ? 'warn' : ''),
            ];
        }
        $x->aba('Por escola', $linhas, ['congelar' => 1, 'filtro' => 1]);
    }

    private static function turmas(X $x, Indicadores $ind): void
    {
        $cab = ['Escola', 'Turma', 'Série', 'Turno', 'Alunos', 'Concluíram', 'Participação', 'Pendentes', 'Questionários concluídos', 'Prioritário', 'Atenção'];
        $linhas = [array_map(static fn ($c) => X::c($c, 'header'), $cab)];
        foreach ($ind->porTurma() as $t) {
            $linhas[] = [
                $t['escola'], X::c($t['turma'], 'bold'), $t['serie'], $t['turno'], $t['alunos'], $t['participantes'],
                self::participacao($t['participacao']), $t['pendentes'], $t['concluidos'],
                X::c($t['prioritarios'], $t['prioritarios'] > 0 ? 'alert' : ''), X::c($t['atencao'], $t['atencao'] > 0 ? 'warn' : ''),
            ];
        }
        $x->aba('Por turma', $linhas, ['congelar' => 1, 'filtro' => 1]);
    }

    private static function classificacao(X $x, Indicadores $ind): void
    {
        $cab = ['Questionário', 'Categoria', 'Classificação', 'Nível de atenção', 'Avaliações', '% da categoria'];
        $linhas = [array_map(static fn ($c) => X::c($c, 'header'), $cab)];
        foreach ($ind->classificacao() as $c) {
            foreach ($c['faixas'] as $f) {
                $estilo = self::estiloNivel($f['nivel']);
                $linhas[] = [$c['questionario'], $c['categoria'], X::c($f['rotulo'], $estilo), X::c(Indicadores::NIVEIS[$f['nivel']] ?? 'Sem nível', $estilo), $f['qtd'], X::c($f['pct'], 'percent')];
            }
        }
        $x->aba('Classificação', $linhas, ['congelar' => 1, 'filtro' => 1]);
    }

    /** @param (callable(int): string)|null $anon */
    private static function alunos(X $x, Indicadores $ind, ?callable $anon): void
    {
        $cab = ['Escola', 'Turma', 'Série', 'Turno', 'Aluno'];
        if ($anon === null) {
            array_push($cab, 'CPF', 'Matrícula');
        }
        array_push($cab, 'Sexo', 'Idade', 'Questionário', 'Sala', 'Concluído em', 'Categoria', 'Pontuação', 'Classificação', 'Nível de atenção');
        $linhas = [array_map(static fn ($c) => X::c($c, 'header'), $cab)];
        $resultados = $ind->resultados();
        $info = $ind->categoriasInfo(array_merge([], ...array_map(static fn ($r) => array_keys($r['categorias']), $resultados)));
        foreach ($resultados as $r) {
            $categorias = $r['categorias'];
            uksort($categorias, static fn ($a, $b) => ($info[$a]['ordem'] ?? 0) <=> ($info[$b]['ordem'] ?? 0));
            foreach ($categorias as $catId => $cat) {
                $estilo = self::estiloNivel($cat['nivel']);
                $linha = [$r['escola_nome'], $r['turma_nome'], $r['serie'], self::turno($r['turno']), $anon === null ? $r['aluno_nome'] : $anon($r['aluno_id'])];
                if ($anon === null) {
                    array_push($linha, X::c(Str::cpf($r['cpf']), 'text'), X::c((string) $r['matricula'], 'text'));
                }
                array_push(
                    $linha,
                    $r['sexo'] === null ? null : ucfirst((string) $r['sexo']),
                    self::idade($r['data_nascimento'], $r['created_at']),
                    $r['questionario_nome'] . ' (v' . $r['versao'] . ')',
                    $r['codigo_sala'],
                    X::c(Tempo::local($r['created_at'], 'Y-m-d H:i'), 'datetime'),
                    $info[$catId]['nome'] ?? 'Categoria #' . $catId,
                    X::c($cat['total'], 'decimal'),
                    X::c($cat['rotulo'] ?? 'Sem regra', $estilo),
                    X::c(Indicadores::NIVEIS[$cat['nivel']] ?? 'Sem nível', $estilo)
                );
                $linhas[] = $linha;
            }
        }
        $x->aba('Resultados por aluno', $linhas, ['congelar' => 1, 'filtro' => 1]);
    }

    private static function perguntas(X $x, Indicadores $ind): void
    {
        $cab = ['Questionário', 'Categoria', 'Pergunta', 'Opção de resposta', 'Pontuação', 'Respostas', '% da pergunta'];
        $linhas = [array_map(static fn ($c) => X::c($c, 'header'), $cab)];
        foreach ($ind->distribuicaoRespostas() as $p) {
            if ($p['tipo'] === 'texto') {
                $linhas[] = [$p['questionario'], $p['categoria'], X::c($p['pergunta'], 'wrap'), X::c('(texto livre — ver aba Respostas detalhadas)', 'muted'), null, $p['textos'], null];
                continue;
            }
            foreach ($p['opcoes'] as $o) {
                $linhas[] = [$p['questionario'], $p['categoria'], X::c($p['pergunta'], 'wrap'), $o['opcao'], $o['pontuacao'], $o['qtd'], X::c($o['pct'], 'percent')];
            }
        }
        $x->aba('Respostas por pergunta', $linhas, ['congelar' => 1, 'filtro' => 1, 'larguras' => [30, 26, 60, 26, 11, 11, 13]]);
    }

    /** @param (callable(int): string)|null $anon */
    private static function pendentes(X $x, Indicadores $ind, ?callable $anon): void
    {
        $dados = $ind->pendentes();
        $cab = ['Escola', 'Turma', 'Aluno'];
        if ($anon === null) {
            array_push($cab, 'Matrícula', 'Telefone', 'Responsável', 'Contato do responsável');
        }
        array_push($cab, 'Questionário', 'Sala', 'Situação');
        $linhas = [array_map(static fn ($c) => X::c($c, 'header'), $cab)];
        foreach ($dados['linhas'] as $p) {
            $linha = [$p['escola'], $p['turma'], $anon === null ? $p['aluno'] : $anon((int) $p['aluno_id'])];
            if ($anon === null) {
                array_push($linha, X::c((string) $p['matricula'], 'text'), X::c((string) $p['telefone'], 'text'), $p['responsavel'], X::c((string) $p['contato_responsavel'], 'text'));
            }
            $respondidas = (int) $p['respondidas'];
            array_push(
                $linha,
                $p['questionario'] . ' (v' . $p['versao'] . ')',
                $p['codigo_sala'],
                X::c($respondidas > 0 ? "Em andamento ($respondidas resposta(s))" : 'Não iniciou', $respondidas > 0 ? 'warn' : '')
            );
            $linhas[] = $linha;
        }
        if ($dados['truncado']) {
            $linhas[] = [X::c('Lista limitada: refine os filtros para ver todos os pendentes.', 'muted')];
        }
        $x->aba('Pendentes', $linhas, ['congelar' => 1, 'filtro' => 1]);
    }

    /** @param (callable(int): string)|null $anon */
    private static function respostas(X $x, Indicadores $ind, ?callable $anon): void
    {
        $dados = $ind->respostasDetalhadas();
        $cab = ['Escola', 'Turma', 'Aluno'];
        if ($anon === null) {
            $cab[] = 'CPF';
        }
        array_push($cab, 'Questionário', 'Sala', 'Categoria', 'Pergunta', 'Resposta', 'Pontuação', 'Respondido em', 'Tempo (s)');
        $linhas = [array_map(static fn ($c) => X::c($c, 'header'), $cab)];
        foreach ($dados['linhas'] as $r) {
            $linha = [$r['escola'], $r['turma'], $anon === null ? $r['aluno'] : $anon((int) $r['aluno_id'])];
            if ($anon === null) {
                $linha[] = X::c(Str::cpf($r['cpf']), 'text');
            }
            array_push(
                $linha,
                $r['questionario'] . ' (v' . $r['versao'] . ')',
                $r['codigo_sala'],
                $r['categoria'],
                $r['pergunta'],
                $r['opcao'] ?? $r['texto'],
                $r['pontuacao'] === null ? null : (int) $r['pontuacao'],
                X::c(Tempo::local($r['responded_at'], 'Y-m-d H:i'), 'datetime'),
                $r['tempo_gasto_ms'] === null ? null : round((int) $r['tempo_gasto_ms'] / 1000, 1)
            );
            $linhas[] = $linha;
        }
        if ($dados['truncado']) {
            $linhas[] = [X::c('Lista limitada a 50.000 respostas: refine os filtros (turma, questionário ou período).', 'muted')];
        }
        $x->aba('Respostas detalhadas', $linhas, ['congelar' => 1, 'filtro' => 1, 'larguras' => $anon === null
            ? [26, 14, 30, 15, 26, 10, 24, 50, 30, 11, 16, 10]
            : [26, 14, 16, 26, 10, 24, 50, 30, 11, 16, 10]]);
    }

    /** @return array{v: mixed, s: string} */
    private static function participacao(?float $valor): array
    {
        return X::c($valor ?? 0.0, 'percent' . ($valor !== null && $valor < 0.5 ? ' warn' : ''));
    }

    private static function estiloNivel(?string $nivel): string
    {
        return match ($nivel) {
            'alto' => 'alert',
            'moderado' => 'warn',
            'baixo' => 'ok',
            default => '',
        };
    }

    private static function turno(?string $turno): ?string
    {
        return $turno === null ? null : (\Vise\Admin\Resources::TURNOS[$turno] ?? $turno);
    }

    private static function idade(?string $nascimento, ?string $referencia): ?int
    {
        if ($nascimento === null || $nascimento === '') {
            return null;
        }
        $ref = new \DateTimeImmutable($referencia ?? 'now');
        return (new \DateTimeImmutable($nascimento))->diff($ref)->y;
    }

    private static function slug(string $texto): string
    {
        $texto = \Vise\Support\Planilha::chave($texto);
        return Str::sub(str_replace('_', '-', $texto), 0, 40);
    }
}
