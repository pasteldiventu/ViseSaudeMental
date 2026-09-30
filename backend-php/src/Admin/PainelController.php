<?php

declare(strict_types=1);

namespace Vise\Admin;

use Vise\Db;
use Vise\Http\HttpError;
use Vise\Http\Request;
use Vise\Http\Response;
use Vise\Http\Url;
use Vise\Services\FiltrosRelatorio;
use Vise\Services\ImportacaoService;
use Vise\Services\Indicadores;
use Vise\Services\OpcoesFiltro;
use Vise\Services\RelatorioService;
use Vise\Support\Tempo;

/** Início (dashboard), relatórios em Excel e importação de planilhas do painel web. */
final class PainelController
{
    private const NIVEL_CLASSE = ['baixo' => 'n-baixo', 'moderado' => 'n-moderado', 'alto' => 'n-alto'];

    public static function dashboard(Request $request): Response
    {
        $ctx = Auth::context($request) ?? throw new LoginRequired();
        if (!$ctx->canAccess('resultado')) {
            return View::page($ctx, 'Painel', self::cadastros($ctx), 'dashboard');
        }
        $filtros = FiltrosRelatorio::deQuery($request->query, '30')->noEscopo($ctx);
        $ind = new Indicadores($ctx, $filtros);
        $r = $ind->resumo();

        $html = self::filtrosDashboard($ctx, $filtros);
        if ($r['alunos'] === 0) {
            $html .= '<div class="alert alert-warning">Ainda não há alunos neste recorte. '
                . ($ctx->canWrite('aluno') ? 'Comece pela <a href="' . View::e(Url::to('/admin/importar', ['tipo' => 'alunos'])) . '">importação da planilha de alunos</a>.' : '')
                . '</div>';
        }

        $html .= '<div class="kpis">'
            . self::kpi(number_format($r['alunos'], 0, ',', '.'), 'Alunos cadastrados', $r['turmas'] . ' turma(s) · ' . $r['escolas'] . ' escola(s)', 'kpi-hero', Url::to('/admin/alunos'))
            . self::kpi((string) $r['concluidos'], 'Questionários concluídos', $r['em_andamento'] . ' em andamento' . ($r['ultima_conclusao'] ? ' · último em ' . Tempo::local($r['ultima_conclusao'], 'd/m H:i') : ''), '')
            . self::kpi((string) $r['aplicacoes_ativas'], 'Aplicações ativas', 'Salas abertas agora', '', Url::to('/admin/aplicacoes'))
            . self::kpi((string) $r['prioritarios'], 'Nível prioritário', 'Alunos que pedem intervenção', $r['prioritarios'] > 0 ? 'kpi-alto' : '', $r['prioritarios'] > 0 ? '#alertas' : null)
            . self::kpi((string) $r['atencao'], 'Nível de atenção', 'Alunos para acompanhar de perto', $r['atencao'] > 0 ? 'kpi-moderado' : '')
            . '</div>';

        $evolucao = $ind->evolucao();
        $html .= '<div class="bento">'
            . '<section class="card span-8"><div class="card-head"><h3>Questionários concluídos</h3><span class="pill">por ' . ($evolucao['granularidade'] === 'dia' ? 'dia' : 'mês') . '</span></div>'
            . self::grafico($evolucao['pontos']) . '</section>'
            . '<section class="card span-4"><div class="card-head"><h3>Participação</h3><span class="pill">' . View::e(FiltrosRelatorio::PERIODOS[$filtros->periodo] ?? 'Período escolhido') . '</span></div>'
            . self::medidor($r) . '</section>'
            . '<section class="card span-7"><div class="card-head"><h3>Níveis de atenção por categoria</h3></div>' . self::classificacaoHtml($ind->classificacao()) . '</section>'
            . '<section class="card span-5"><div class="card-head"><h3>Aplicações ativas</h3><a class="btn btn-sm" href="' . View::e(Url::to('/admin/aplicacoes')) . '">Ver todas</a></div>'
            . self::aplicacoesHtml($ind->aplicacoesAtivas(6)) . '</section>'
            . '<section class="card span-7"><div class="card-head"><h3>Participação por turma</h3><span class="muted small">menor participação primeiro</span></div>'
            . self::turmasHtml($ind->porTurma(), $filtros) . '</section>'
            . '<section class="card span-5" id="alertas"><div class="card-head"><h3>Alertas recentes</h3><span class="badge badge-erro">nível prioritário</span></div>'
            . self::alertasHtml($ind->alertas(8), $ctx) . '</section>'
            . '</div>';
        $html .= '<details class="card"><summary><strong>Resumo dos cadastros</strong></summary>' . self::cadastros($ctx) . '</details>';

        $acoes = '';
        if (ImportacaoService::tiposPermitidos($ctx) !== []) {
            $acoes .= '<a class="btn btn-outline" href="' . View::e(Url::to('/admin/importar')) . '">' . View::icone('importar', 16) . 'Importar planilha</a>';
        }
        $acoes .= '<a class="btn btn-primary" href="' . View::e(Url::to('/admin/relatorios', $filtros->query())) . '">' . View::icone('relatorios', 16) . 'Gerar relatório</a>';
        return View::page($ctx, 'Painel', $html, 'dashboard', $acoes, 200, 'Visão geral da participação e dos níveis de atenção dos alunos.');
    }

    public static function relatorios(Request $request): Response
    {
        $ctx = self::ctxRelatorio($request);
        $filtros = FiltrosRelatorio::deQuery($request->query)->noEscopo($ctx);
        $abas = RelatorioService::abasEscolhidas($request->query['abas'] ?? null);
        $anonimizar = ($request->query['anonimizar'] ?? '') === '1';

        $form = '<form class="card report-form" method="get" action="' . View::e(Url::to('/admin/relatorios')) . '">'
            . '<h3>1. Recorte dos dados</h3><div class="filter-grid">'
            . self::camposFiltro($ctx, $filtros, true)
            . '</div><h3>2. Conteúdo do arquivo</h3><div class="abas-grid">';
        foreach (RelatorioService::ABAS as $chave => [$rotulo, $descricao]) {
            $travada = $chave === 'resumo';
            $form .= '<label class="check-card"><input type="checkbox" name="abas[]" value="' . View::e($chave) . '"'
                . (in_array($chave, $abas, true) ? ' checked' : '') . ($travada ? ' checked disabled' : '') . '>'
                . '<span><strong>' . View::e($rotulo) . '</strong><small>' . View::e($descricao) . '</small></span></label>';
        }
        $form .= '</div><label class="check-line"><input type="checkbox" name="anonimizar" value="1"' . ($anonimizar ? ' checked' : '') . '> '
            . '<span><strong>Anonimizar alunos</strong> <small class="muted">troca nomes por códigos e remove CPF, matrícula e contatos — use para compartilhar com pesquisa ou secretaria.</small></span></label>'
            . '<div class="form-actions"><button class="btn btn-primary" type="submit" formaction="' . View::e(Url::to('/admin/relatorios/exportar')) . '">' . View::icone('baixar', 16) . 'Baixar Excel (.xlsx)</button>'
            . '<button class="btn btn-outline" type="submit">Atualizar prévia</button>'
            . '<button class="btn btn-outline" type="button" onclick="window.print()">Imprimir prévia</button></div></form>';

        $ind = new Indicadores($ctx, $filtros);
        $r = $ind->resumo();
        $chips = implode('', array_map(static fn ($f) => '<span class="chip">' . View::e($f[0]) . ': ' . View::e($f[1]) . '</span>', $filtros->descricao()));
        $previa = '<section class="card print-area"><h3>Prévia do relatório</h3><div class="filters">' . $chips . '</div>'
            . '<div class="kpis kpis-compact">'
            . self::kpi((string) $r['alunos'], 'Alunos', $r['turmas'] . ' turma(s)', 'kpi-hero')
            . self::kpi(self::pct($r['participacao'] ?? 0.0), 'Participação', $r['participantes'] . ' concluíram', '', null, $r['participacao'] ?? 0.0)
            . self::kpi((string) $r['concluidos'], 'Concluídos', $r['em_andamento'] . ' em andamento', '')
            . self::kpi((string) $r['prioritarios'], 'Prioritário', 'alunos', $r['prioritarios'] > 0 ? 'kpi-alto' : '')
            . self::kpi((string) $r['atencao'], 'Atenção', 'alunos', $r['atencao'] > 0 ? 'kpi-moderado' : '')
            . '</div>'
            . '<h4>Classificação por categoria</h4>' . self::classificacaoHtml($ind->classificacao())
            . '<h4>Por turma</h4>' . self::turmasHtml($ind->porTurma(), $filtros, 0)
            . '</section>';

        return View::page($ctx, 'Relatórios', $form . $previa, 'relatorios', '', 200, 'Escolha o recorte, confira a prévia e baixe a planilha em Excel.');
    }

    public static function exportar(Request $request): Response
    {
        $ctx = self::ctxRelatorio($request);
        $filtros = FiltrosRelatorio::deQuery($request->query)->noEscopo($ctx);
        $arquivo = RelatorioService::gerar(
            $ctx,
            $filtros,
            RelatorioService::abasEscolhidas($request->query['abas'] ?? null),
            ($request->query['anonimizar'] ?? '') === '1'
        );
        return Response::download($arquivo['conteudo'], $arquivo['arquivo']);
    }

    public static function importarForm(Request $request): Response
    {
        $ctx = Auth::context($request) ?? throw new LoginRequired();
        return self::paginaImportacao($ctx, self::tipoImportacao($ctx, $request->query['tipo'] ?? null));
    }

    public static function importarAlunosForm(Request $request): Response
    {
        $ctx = Auth::context($request) ?? throw new LoginRequired();
        return self::paginaImportacao($ctx, self::tipoImportacao($ctx, 'alunos'));
    }

    public static function importar(Request $request): Response
    {
        $ctx = Auth::context($request) ?? throw new LoginRequired();
        return self::processarImportacao($request, $ctx, self::tipoImportacao($ctx, $request->post['tipo'] ?? null));
    }

    public static function importarAlunos(Request $request): Response
    {
        $ctx = Auth::context($request) ?? throw new LoginRequired();
        return self::processarImportacao($request, $ctx, self::tipoImportacao($ctx, 'alunos'));
    }

    /** @param array<string, string> $p */
    public static function modelo(Request $request, array $p): Response
    {
        $ctx = Auth::context($request) ?? throw new LoginRequired();
        $tipo = self::tipoImportacao($ctx, $p['tipo'] ?? null);
        return Response::download(ImportacaoService::modelo($tipo, $ctx->su ? null : $ctx->escolaIds), "modelo-importacao-$tipo.xlsx");
    }

    private static function processarImportacao(Request $request, Ctx $ctx, string $tipo): Response
    {
        Auth::checkCsrf($request);
        $file = $request->files['arquivo'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            $erro = ($file['error'] ?? null) === UPLOAD_ERR_INI_SIZE ? 'Arquivo maior que o limite do servidor.' : 'Selecione uma planilha (.xlsx ou .csv).';
            return self::paginaImportacao($ctx, $tipo, '<div class="alert alert-error">' . $erro . '</div>', 422);
        }
        $simular = ($request->post['modo'] ?? '') === 'simular';
        try {
            $res = ImportacaoService::importar($tipo, (string) file_get_contents((string) $file['tmp_name']), $ctx->su ? null : $ctx->escolaIds, $simular);
        } catch (\RuntimeException $erro) {
            return self::paginaImportacao($ctx, $tipo, '<div class="alert alert-error">' . View::e($erro->getMessage()) . '</div>', 422);
        }
        return self::paginaImportacao($ctx, $tipo, self::resultadoImportacao($res, (string) ($file['name'] ?? '')));
    }

    /** @param array<string, mixed> $res */
    private static function resultadoImportacao(array $res, string $nomeArquivo): string
    {
        $ok = $res['criados'] + $res['atualizados'];
        $unidade = match ($res['tipo']) {
            'alunos' => 'aluno(s)', 'turmas' => 'turma(s)', 'escolas' => 'escola(s)', default => 'usuário(s)',
        };
        $classe = $res['erros'] === 0 ? 'alert-success' : ($ok > 0 ? 'alert-warning' : 'alert-error');
        $html = '<div class="alert ' . $classe . '">'
            . ($res['simulacao']
                ? '<strong>Simulação (nada foi gravado):</strong> ' . $ok . " $unidade seriam importado(s)"
                : '<strong>Importação concluída:</strong> ' . $ok . " $unidade importado(s)")
            . ' (' . $res['criados'] . ' novo(s), ' . $res['atualizados'] . ' atualizado(s)), ' . $res['erros'] . ' linha(s) com erro'
            . ' · ' . $res['total'] . ' linha(s) lidas de ' . View::e($nomeArquivo !== '' ? $nomeArquivo : 'arquivo') . ' (' . strtoupper((string) $res['formato']) . ').';
        if ($res['simulacao'] && $res['erros'] === 0 && $ok > 0) {
            $html .= ' Tudo certo: envie o mesmo arquivo com <strong>Importar</strong> para gravar.';
        }
        $html .= '</div>';
        if ($res['colunas_ignoradas'] !== []) {
            $html .= '<div class="alert alert-warning">Colunas não reconhecidas (ignoradas): ' . View::e(implode(', ', $res['colunas_ignoradas'])) . '</div>';
        }
        if ($res['linhas'] === []) {
            return $html;
        }
        $linhas = $res['linhas'];
        usort($linhas, static fn ($a, $b) => [$a['status'] !== 'erro', $a['linha']] <=> [$b['status'] !== 'erro', $b['linha']]);
        $html .= '<section class="card"><h3>Detalhe por linha</h3><div class="table-wrap"><table><thead><tr><th>Linha</th><th>Situação</th><th>Detalhe</th></tr></thead><tbody>';
        foreach (array_slice($linhas, 0, 500) as $l) {
            $badge = match ($l['status']) {
                'criado' => '<span class="badge badge-ok">' . ($res['simulacao'] ? 'Seria criado' : 'Criado') . '</span>',
                'atualizado' => '<span class="badge badge-info">' . ($res['simulacao'] ? 'Seria atualizado' : 'Atualizado') . '</span>',
                default => '<span class="badge badge-erro">Erro</span>',
            };
            $html .= '<tr><td>' . (int) $l['linha'] . '</td><td>' . $badge . '</td><td>' . View::e($l['mensagem']) . '</td></tr>';
        }
        $html .= '</tbody></table></div>';
        if (count($linhas) > 500) {
            $html .= '<p class="muted small">Exibindo 500 de ' . count($linhas) . ' linhas (erros primeiro).</p>';
        }
        return $html . '</section>';
    }

    private static function paginaImportacao(Ctx $ctx, string $tipo, string $mensagem = '', int $status = 200): Response
    {
        $tipos = ImportacaoService::tiposPermitidos($ctx);
        $def = $tipos[$tipo];
        $abas = '<div class="tabs" role="tablist">';
        foreach ($tipos as $chave => $t) {
            $abas .= '<a class="tab' . ($chave === $tipo ? ' active' : '') . '" href="' . View::e(Url::to('/admin/importar', ['tipo' => $chave])) . '">' . View::e($t['label']) . '</a>';
        }
        $abas .= '</div>';

        $colunas = '';
        foreach ($def['colunas'] as $chave => $c) {
            $colunas .= '<tr><td><code>' . View::e($chave) . '</code></td><td>' . View::e($c['label'])
                . (!empty($c['obrigatoria']) ? ' <span class="req">*</span>' : '') . '</td><td class="small">' . View::e($c['ajuda'] ?? '')
                . '</td><td class="small muted">' . View::e($c['exemplo'] ?? '') . '</td></tr>';
        }

        $html = $mensagem . $abas
            . '<div class="grid-2 grid-import">'
            . '<form class="card" method="post" enctype="multipart/form-data" action="' . View::e(Url::to('/admin/importar')) . '">'
            . View::csrfField() . '<input type="hidden" name="tipo" value="' . View::e($tipo) . '">'
            . '<h3>Importar ' . View::e(mb_strtolower($def['label'])) . '</h3><p>' . View::e($def['descricao']) . '</p>'
            . '<ol class="steps"><li><a href="' . View::e(Url::to('/admin/importar/modelo/' . $tipo)) . '"><strong>Baixe a planilha modelo</strong></a> (já traz instruções e as escolas/turmas que você pode usar).</li>'
            . '<li>Preencha uma linha por registro. Também aceitamos planilhas próprias: os nomes das colunas são reconhecidos automaticamente.</li>'
            . '<li>Envie com <strong>Simular</strong> para conferir os erros sem gravar nada; depois clique em <strong>Importar</strong>.</li></ol>'
            . '<div class="field"><label for="arquivo">Planilha (.xlsx ou .csv)</label>'
            . '<input id="arquivo" type="file" name="arquivo" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required>'
            . '<small>Até ' . number_format(ImportacaoService::LIMITE_LINHAS, 0, ',', '.') . ' linhas por arquivo. Arquivos .xls antigos: salve como .xlsx antes.</small></div>'
            . '<div class="form-actions"><button class="btn btn-outline" type="submit" name="modo" value="simular">Simular</button>'
            . '<button class="btn btn-primary" type="submit" name="modo" value="importar">' . View::icone('importar', 16) . 'Importar</button></div></form>'
            . '<section class="card"><h3>Colunas aceitas</h3><div class="table-wrap"><table class="compact"><thead><tr><th>Coluna</th><th>Campo</th><th>Regras</th><th>Exemplo</th></tr></thead><tbody>'
            . $colunas . '</tbody></table></div><p class="small muted"><span class="req">*</span> obrigatória. A ordem das colunas não importa.</p></section>'
            . '</div>';
        return View::page($ctx, 'Importar planilha', $html, 'importar', '', $status, 'Cadastre em lote a partir de uma planilha .xlsx ou .csv.');
    }

    private static function tipoImportacao(Ctx $ctx, mixed $tipo): string
    {
        $tipos = ImportacaoService::tiposPermitidos($ctx);
        if ($tipos === []) {
            throw new HttpError(403, 'Seu perfil não pode importar planilhas.');
        }
        if (is_string($tipo) && $tipo !== '') {
            if (!isset(ImportacaoService::definicoes()[$tipo])) {
                throw new HttpError(404, 'Tipo de importação desconhecido.');
            }
            if (!isset($tipos[$tipo])) {
                throw new HttpError(403, 'Seu perfil não pode importar este tipo de planilha.');
            }
            return $tipo;
        }
        return isset($tipos['alunos']) ? 'alunos' : (string) array_key_first($tipos);
    }

    private static function ctxRelatorio(Request $request): Ctx
    {
        $ctx = Auth::context($request) ?? throw new LoginRequired();
        if (!$ctx->canAccess('resultado')) {
            throw new HttpError(403, 'Seu perfil não acessa relatórios.');
        }
        return $ctx;
    }

    private static function filtrosDashboard(Ctx $ctx, FiltrosRelatorio $f): string
    {
        return '<form class="card filter-bar" method="get" action="' . View::e(Url::to('/admin')) . '">'
            . self::camposFiltro($ctx, $f, false)
            . '<div class="filter-actions"><button class="btn" type="submit">Aplicar</button>'
            . ($f->query() !== ['periodo' => '30'] ? ' <a class="btn btn-link" href="' . View::e(Url::to('/admin')) . '">Limpar</a>' : '')
            . '</div></form>';
    }

    private static function camposFiltro(Ctx $ctx, FiltrosRelatorio $f, bool $completo): string
    {
        $auto = $completo ? '' : ' onchange="this.form.submit()"';
        $select = static function (string $nome, string $rotulo, array $opcoes, mixed $atual, ?string $vazio) use ($auto): string {
            $html = '<label class="filter"><span>' . View::e($rotulo) . '</span><select name="' . $nome . '"' . $auto . '>'
                . ($vazio === null ? '' : '<option value="">' . View::e($vazio) . '</option>');
            foreach ($opcoes as $valor => $texto) {
                $html .= '<option value="' . View::e($valor) . '"' . ((string) $valor === (string) $atual ? ' selected' : '') . '>' . View::e($texto) . '</option>';
            }
            return $html . '</select></label>';
        };

        $escolas = Resources::options('escolas', $ctx, false);
        $html = count($escolas) > 1 || $f->escolaId !== null ? $select('escola_id', 'Escola', $escolas, $f->escolaId, 'Todas as escolas') : '';
        $html .= $select('turma_id', 'Turma', self::turmas($ctx, $f->escolaId, count($escolas) > 1 && $f->escolaId === null), $f->turmaId, 'Todas as turmas');
        if ($completo) {
            $html .= $select('serie_id', 'Série', Resources::options('series', $ctx, false), $f->serieId, 'Todas as séries');
            $html .= $select('turno', 'Turno', Resources::TURNOS, $f->turno, 'Todos os turnos');
            $html .= $select('sexo', 'Sexo', OpcoesFiltro::sexos($ctx), $f->sexo, 'Todos');
        }
        $html .= $select('questionario_id', 'Questionário', Resources::options('questionarios', $ctx, false), $f->questionarioId, 'Todos os questionários');
        $periodos = FiltrosRelatorio::PERIODOS + ($completo ? ['personalizado' => 'Personalizado (datas abaixo)'] : []);
        $html .= $select('periodo', 'Período', $periodos, $f->periodo, null);
        if ($completo) {
            $html .= '<label class="filter"><span>De</span><input type="date" name="inicio" value="' . View::e($f->periodo === 'personalizado' ? $f->inicio : '') . '" onchange="this.form.periodo.value=\'personalizado\'"></label>'
                . '<label class="filter"><span>Até</span><input type="date" name="fim" value="' . View::e($f->periodo === 'personalizado' ? $f->fim : '') . '" onchange="this.form.periodo.value=\'personalizado\'"></label>';
        }
        if ($f->aplicacaoId !== null) {
            $html .= '<input type="hidden" name="aplicacao_id" value="' . $f->aplicacaoId . '">';
        }
        return $html;
    }

    /** @return array<int, string> */
    private static function turmas(Ctx $ctx, ?int $escolaId, bool $comEscola): array
    {
        $opcoes = [];
        foreach (OpcoesFiltro::turmas($ctx, $escolaId) as $t) {
            $opcoes[$t['id']] = $comEscola ? $t['nome'] . ' — ' . $t['escola'] : $t['nome'];
        }
        return $opcoes;
    }

    private static function kpi(string $valor, string $rotulo, string $sub, string $classe, ?string $href = null, ?float $progresso = null): string
    {
        $tag = $href === null ? 'div' : 'a';
        return '<' . $tag . ' class="kpi ' . $classe . '"' . ($href === null ? '' : ' href="' . View::e($href) . '"') . '>'
            . '<span class="kpi-top"><span class="kpi-label">' . View::e($rotulo) . '</span>'
            . ($href === null ? '' : '<span class="kpi-arrow">' . View::icone('seta', 16) . '</span>') . '</span>'
            . '<span class="kpi-value">' . View::e($valor) . '</span>'
            . ($progresso === null ? '' : self::barra($progresso))
            . '<span class="kpi-sub">' . View::e($sub) . '</span></' . $tag . '>';
    }

    private static function barra(?float $valor, string $classe = ''): string
    {
        $pct = max(0.0, min(100.0, ($valor ?? 0.0) * 100));
        return '<span class="progress ' . $classe . '"><span style="width:' . number_format($pct, 1, '.', '') . '%"></span></span>';
    }

    private static function pct(?float $valor): string
    {
        return $valor === null ? '—' : number_format($valor * 100, $valor > 0 && $valor < 0.1 ? 1 : 0, ',', '.') . '%';
    }

    /** @param list<array{chave: string, rotulo: string, valor: int}> $pontos */
    private static function grafico(array $pontos): string
    {
        $total = array_sum(array_column($pontos, 'valor'));
        if ($total === 0) {
            return '<p class="empty-state">Nenhum questionário concluído neste período.</p>';
        }
        $valores = array_column($pontos, 'valor');
        $max = max(1, ...$valores);
        $maiorIndice = (int) array_search($max, $valores, true);
        $n = count($pontos);
        $largura = 640;
        $altura = 230;
        $topo = 30;
        $base = 196;
        $passo = $largura / $n;
        $barra = min(40.0, max(4.0, $passo * 0.62));
        $raio = round($barra / 2, 1);
        $cadaRotulo = (int) max(1, ceil($n / 10));
        $svg = '<svg class="chart" viewBox="0 0 ' . $largura . ' ' . $altura . '" role="img" aria-label="Questionários concluídos ao longo do tempo">'
            . '<defs><pattern id="hachuraBarras" patternUnits="userSpaceOnUse" width="7" height="7" patternTransform="rotate(45)">'
            . '<rect width="7" height="7" fill="#f3f6f4"/><line x1="0" y1="0" x2="0" y2="7" stroke="#cdd8d1" stroke-width="2.4"/></pattern></defs>';
        foreach ($pontos as $i => $p) {
            $x = round($i * $passo + ($passo - $barra) / 2, 1);
            $svg .= '<rect class="track" x="' . $x . '" y="' . $topo . '" width="' . round($barra, 1) . '" height="' . ($base - $topo) . '" rx="' . $raio . '"'
                . ($p['valor'] === 0 ? ' fill="url(#hachuraBarras)"' : '') . '/>';
            if ($p['valor'] > 0) {
                $h = max($barra, ($base - $topo) * $p['valor'] / $max);
                $y = round($base - $h, 1);
                $svg .= '<rect class="bar' . ($i === $maiorIndice ? ' bar-max' : '') . '" x="' . $x . '" y="' . $y . '" width="' . round($barra, 1) . '" height="' . round($h, 1) . '" rx="' . $raio . '"><title>'
                    . View::e($p['rotulo'] . ': ' . $p['valor'] . ' concluído(s)') . '</title></rect>';
                if ($i === $maiorIndice) {
                    $cx = round($i * $passo + $passo / 2, 1);
                    $svg .= '<g class="bubble"><rect x="' . ($cx - 20) . '" y="' . ($y - 26) . '" width="40" height="20" rx="10"/>'
                        . '<text x="' . $cx . '" y="' . ($y - 12) . '" text-anchor="middle">' . $p['valor'] . '</text></g>';
                }
            }
            if ($i % $cadaRotulo === 0) {
                $cx = max(16.0, min($largura - 16.0, $i * $passo + $passo / 2));
                $svg .= '<text x="' . round($cx, 1) . '" y="220" class="axis" text-anchor="middle">' . View::e($p['rotulo']) . '</text>';
            }
        }
        return $svg . '</svg><div class="chart-foot"><span><strong>' . $total . '</strong> conclusão(ões) no período</span>'
            . '<span class="legend"><span><i class="lg-bar"></i>Concluídos</span><span><i class="lg-max"></i>Maior dia</span><span><i class="lg-vazio"></i>Sem conclusões</span></span></div>';
    }

    /** Medidor semicircular: alunos que concluíram × pendentes. */
    private static function medidor(array $r): string
    {
        $p = max(0.0, min(1.0, (float) ($r['participacao'] ?? 0.0)));
        $arco = 'M 24 112 A 86 86 0 0 1 196 112';
        $svg = '<svg class="gauge" viewBox="0 0 220 128" role="img" aria-label="Participação ' . self::pct($p) . '">'
            . '<defs><pattern id="hachuraMedidor" patternUnits="userSpaceOnUse" width="7" height="7" patternTransform="rotate(45)">'
            . '<rect width="7" height="7" fill="#f3f6f4"/><line x1="0" y1="0" x2="0" y2="7" stroke="#cdd8d1" stroke-width="2.4"/></pattern></defs>'
            . '<path d="' . $arco . '" class="gauge-track" stroke="url(#hachuraMedidor)"/>';
        if ($p > 0) {
            $svg .= '<path d="' . $arco . '" class="gauge-fill" pathLength="100" stroke-dasharray="' . number_format($p * 100, 2, '.', '') . ' 100"/>';
        }
        $svg .= '</svg>';
        $pendentes = max(0, (int) $r['alunos'] - (int) $r['participantes']);
        return '<div class="gauge-wrap">' . $svg . '<div class="gauge-center"><strong>' . self::pct($r['participacao'] ?? null) . '</strong><span>dos alunos concluíram</span></div></div>'
            . '<div class="gauge-legend">'
            . '<span><i class="lg-max"></i>Concluíram <strong>' . (int) $r['participantes'] . '</strong></span>'
            . '<span><i class="lg-vazio"></i>Pendentes <strong>' . $pendentes . '</strong></span>'
            . '<span><i class="lg-bar"></i>Respondendo <strong>' . (int) $r['em_andamento'] . '</strong></span>'
            . '</div>';
    }

    /** @param list<array<string, mixed>> $categorias */
    private static function classificacaoHtml(array $categorias): string
    {
        if ($categorias === []) {
            return '<p class="empty-state">Sem resultados para classificar neste recorte.</p>';
        }
        $html = '<div class="legend"><span><i class="n-baixo"></i>Adequado</span><span><i class="n-moderado"></i>Atenção</span><span><i class="n-alto"></i>Prioritário</span><span><i class="n-sem"></i>Sem nível definido</span></div>';
        $questionario = null;
        foreach ($categorias as $c) {
            if ($c['questionario'] !== $questionario) {
                $questionario = $c['questionario'];
                $html .= '<div class="stack-group">' . View::e($questionario) . '</div>';
            }
            $segmentos = '';
            foreach ($c['faixas'] as $f) {
                $classe = self::NIVEL_CLASSE[$f['nivel'] ?? ''] ?? 'n-sem';
                $segmentos .= '<span class="' . $classe . '" style="width:' . number_format($f['pct'] * 100, 2, '.', '') . '%" title="'
                    . View::e($f['rotulo'] . ': ' . $f['qtd'] . ' (' . self::pct($f['pct']) . ')') . '">' . ($f['pct'] >= 0.12 ? self::pct($f['pct']) : '') . '</span>';
            }
            $html .= '<div class="stack-row"><div class="stack-label"><strong>' . View::e($c['categoria']) . '</strong><span class="muted small">'
                . $c['avaliacoes'] . ' avaliação(ões)' . ($c['media'] !== null ? ' · média ' . number_format((float) $c['media'], 1, ',', '') : '') . '</span></div>'
                . '<div class="stack">' . $segmentos . '</div></div>';
        }
        return $html;
    }

    /** @param list<array<string, mixed>> $turmas */
    private static function turmasHtml(array $turmas, FiltrosRelatorio $f, int $limite = 8): string
    {
        if ($turmas === []) {
            return '<p class="empty-state">Nenhuma turma com alunos neste recorte.</p>';
        }
        usort($turmas, static fn ($a, $b) => [$a['participacao'] ?? 0, $a['escola'], $a['turma']] <=> [$b['participacao'] ?? 0, $b['escola'], $b['turma']]);
        $mostrar = $limite > 0 ? array_slice($turmas, 0, $limite) : $turmas;
        $html = '<div class="table-wrap"><table class="compact"><thead><tr><th>Turma</th><th>Participação</th><th>Pendentes</th><th>Prioritário</th><th>Atenção</th></tr></thead><tbody>';
        foreach ($mostrar as $t) {
            $link = $t['turma_id'] !== null ? Url::to('/admin/relatorios', array_merge($f->query(), ['escola_id' => $t['escola_id'], 'turma_id' => $t['turma_id']])) : null;
            $nome = View::e($t['turma']) . '<div class="muted small">' . View::e($t['escola'] . ($t['turno'] ? ' · ' . $t['turno'] : '')) . '</div>';
            $html .= '<tr><td>' . ($link !== null ? '<a href="' . View::e($link) . '" title="Relatório desta turma">' . $nome . '</a>' : $nome) . '</td>'
                . '<td class="cell-progress">' . self::barra($t['participacao'], ($t['participacao'] ?? 0) < 0.5 ? 'progress-low' : '') . '<span class="small">'
                . self::pct($t['participacao']) . ' · ' . $t['participantes'] . '/' . $t['alunos'] . '</span></td>'
                . '<td>' . $t['pendentes'] . '</td>'
                . '<td>' . ($t['prioritarios'] > 0 ? '<span class="badge badge-erro">' . $t['prioritarios'] . '</span>' : '0') . '</td>'
                . '<td>' . ($t['atencao'] > 0 ? '<span class="badge badge-warn">' . $t['atencao'] . '</span>' : '0') . '</td></tr>';
        }
        $html .= '</tbody></table></div>';
        if ($limite > 0 && count($turmas) > $limite) {
            $html .= '<p class="small"><a href="' . View::e(Url::to('/admin/relatorios', $f->query())) . '">Ver todas as ' . count($turmas) . ' turmas no relatório →</a></p>';
        }
        return $html;
    }

    /** @param list<array<string, mixed>> $aplicacoes */
    private static function aplicacoesHtml(array $aplicacoes): string
    {
        if ($aplicacoes === []) {
            return '<p class="empty-state">Nenhuma aplicação ativa no momento.</p>';
        }
        $html = '<ul class="app-list">';
        foreach ($aplicacoes as $a) {
            $html .= '<li><span class="app-icon">' . View::icone('aplicacoes', 18) . '</span><div class="app-main">'
                . '<div class="app-head"><a href="' . View::e(Url::to('/admin/aplicacoes/' . $a['id'])) . '"><strong>' . View::e($a['questionario']) . '</strong></a>'
                . '<span class="app-pct">' . self::pct($a['progresso']) . '</span></div>'
                . '<div class="muted small">' . View::e($a['alvo'] . ' · ' . $a['escola']) . ($a['codigo'] ? ' · sala <code>' . View::e($a['codigo']) . '</code>' : '')
                . ($a['termina_em'] ? ' · até ' . View::e(Tempo::local($a['termina_em'], 'd/m H:i')) : '') . '</div>'
                . self::barra($a['progresso']) . '<div class="small muted">' . $a['concluidos'] . ' de ' . $a['alvo_total'] . ' concluíram'
                . ($a['em_andamento'] > 0 ? ' · ' . $a['em_andamento'] . ' respondendo' : '') . '</div></div></li>';
        }
        return $html . '</ul>';
    }

    /** @param list<array<string, mixed>> $alertas */
    private static function alertasHtml(array $alertas, Ctx $ctx): string
    {
        if ($alertas === []) {
            return '<p class="empty-state">Nenhum aluno em nível prioritário neste recorte. Configure o "Nível de atenção" nas <a href="'
                . View::e(Url::to('/admin/regras')) . '">regras de classificação</a> para que os alertas apareçam.</p>';
        }
        $html = '<ul class="people">';
        foreach ($alertas as $a) {
            $html .= '<li><span class="avatar avatar-alto">' . View::e(View::iniciais((string) $a['aluno'])) . '</span>'
                . '<div class="people-main"><strong>' . View::e($a['aluno']) . '</strong>'
                . '<span class="muted small">' . View::e(($a['turma'] ?? '—') . ' · ' . $a['escola'] . ' · ' . Tempo::local($a['data'], 'd/m H:i')) . '</span>'
                . '<span class="tags">' . implode('', array_map(static fn ($c) => '<span class="badge badge-erro">' . View::e($c) . '</span>', $a['categorias'])) . '</span></div>'
                . ($ctx->canAccess('resultado') ? '<a class="btn btn-sm" href="' . View::e(Url::to('/admin/resultados/' . $a['resultado_id'])) . '" title="' . View::e($a['questionario']) . '">Ver</a>' : '')
                . '</li>';
        }
        return $html . '</ul><p class="muted small">Oriente o acolhimento conforme o protocolo da escola. Estes dados são sensíveis (LGPD).</p>';
    }

    private static function cadastros(Ctx $ctx): string
    {
        $cards = '';
        foreach (Resources::MENU as $group => $keys) {
            foreach ($keys as $key) {
                $res = Resources::get($key);
                if ($res === null || !$ctx->canAccess($res['perm'])) {
                    continue;
                }
                [$where, $params] = Scope::where($key, $ctx);
                $count = (int) Db::value("SELECT COUNT(*) FROM `{$res['table']}` t WHERE $where", $params);
                $cards .= '<a class="stat" href="' . View::e(Url::to("/admin/$key")) . '">'
                    . '<span class="stat-value">' . $count . '</span>'
                    . '<span class="stat-label">' . View::e($res['plural']) . '</span>'
                    . '<span class="stat-group">' . View::e($group) . '</span></a>';
            }
        }
        return '<div class="stats">' . $cards . '</div>';
    }
}
