<?php

declare(strict_types=1);

namespace Vise\Api;

use Vise\Admin\Auth;
use Vise\Admin\Ctx;
use Vise\Http\HttpError;
use Vise\Http\Request;
use Vise\Http\Response;
use Vise\Services\FiltrosRelatorio;
use Vise\Services\ImportacaoService;
use Vise\Services\Indicadores;
use Vise\Services\OpcoesFiltro;
use Vise\Services\RelatorioService;

/** Painel, relatórios e importação para o app da equipe (mesmas regras e escopo do painel web). */
final class PainelApiController
{
    public static function painel(Request $request): Response
    {
        $ctx = self::ctxResultados($request);
        $filtros = FiltrosRelatorio::deQuery($request->query, '30')->noEscopo($ctx);
        $ind = new Indicadores($ctx, $filtros);
        $turmas = $ind->porTurma();
        usort($turmas, static fn ($a, $b) => [$a['participacao'] ?? 0, $a['escola'], $a['turma']] <=> [$b['participacao'] ?? 0, $b['escola'], $b['turma']]);
        return Response::json(['data' => [
            'filtros' => ['query' => $filtros->query(), 'descricao' => array_map(static fn ($f) => ['rotulo' => $f[0], 'valor' => $f[1]], $filtros->descricao())],
            'resumo' => $ind->resumo(),
            'evolucao' => $ind->evolucao(),
            'classificacao' => $ind->classificacao(),
            'turmas' => $turmas,
            'aplicacoes' => $ind->aplicacoesAtivas(10),
            'alertas' => $ind->alertas(10),
        ]]);
    }

    public static function opcoes(Request $request): Response
    {
        return Response::json(['data' => OpcoesFiltro::todas(self::ctxResultados($request))]);
    }

    public static function exportar(Request $request): Response
    {
        $ctx = self::ctxResultados($request);
        $arquivo = RelatorioService::gerar(
            $ctx,
            FiltrosRelatorio::deQuery($request->query)->noEscopo($ctx),
            RelatorioService::abasEscolhidas($request->query['abas'] ?? null),
            in_array($request->query['anonimizar'] ?? '', ['1', 'true'], true)
        );
        return Response::download($arquivo['conteudo'], $arquivo['arquivo']);
    }

    public static function tipos(Request $request): Response
    {
        $ctx = self::ctx($request);
        $tipos = [];
        foreach (ImportacaoService::tiposPermitidos($ctx) as $tipo => $def) {
            $colunas = [];
            foreach ($def['colunas'] as $chave => $c) {
                $colunas[] = ['chave' => $chave, 'label' => $c['label'], 'obrigatoria' => !empty($c['obrigatoria']), 'ajuda' => $c['ajuda'] ?? null, 'exemplo' => $c['exemplo'] ?? null];
            }
            $tipos[] = ['tipo' => $tipo, 'label' => $def['label'], 'descricao' => $def['descricao'], 'colunas' => $colunas];
        }
        return Response::json(['data' => $tipos, 'limite_linhas' => ImportacaoService::LIMITE_LINHAS]);
    }

    /** @param array<string, string> $p */
    public static function modelo(Request $request, array $p): Response
    {
        $ctx = self::ctx($request);
        $tipo = self::tipo($ctx, $p['tipo']);
        return Response::download(ImportacaoService::modelo($tipo, $ctx->su ? null : $ctx->escolaIds), "modelo-importacao-$tipo.xlsx");
    }

    /** @param array<string, string> $p */
    public static function importar(Request $request, array $p): Response
    {
        $ctx = self::ctx($request);
        $tipo = self::tipo($ctx, $p['tipo']);
        $file = $request->files['arquivo'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            throw new HttpError(422, ($file['error'] ?? null) === UPLOAD_ERR_INI_SIZE ? 'Arquivo maior que o limite do servidor.' : 'Envie a planilha no campo "arquivo" (.xlsx ou .csv).');
        }
        $simular = in_array((string) ($request->post['simular'] ?? ''), ['1', 'true'], true);
        try {
            $res = ImportacaoService::importar($tipo, (string) file_get_contents((string) $file['tmp_name']), $ctx->su ? null : $ctx->escolaIds, $simular);
        } catch (\RuntimeException $erro) {
            throw new HttpError(422, $erro->getMessage());
        }
        usort($res['linhas'], static fn ($a, $b) => [$a['status'] !== 'erro', $a['linha']] <=> [$b['status'] !== 'erro', $b['linha']]);
        $res['linhas_truncadas'] = count($res['linhas']) > 1000;
        $res['linhas'] = array_slice($res['linhas'], 0, 1000);
        return Response::json(['data' => $res]);
    }

    private static function tipo(Ctx $ctx, string $tipo): string
    {
        if (!isset(ImportacaoService::definicoes()[$tipo])) {
            throw new HttpError(404, 'Tipo de importação desconhecido.');
        }
        if (!ImportacaoService::permitido($tipo, $ctx)) {
            throw new HttpError(403, 'Seu perfil não pode importar este tipo de planilha.');
        }
        return $tipo;
    }

    private static function ctxResultados(Request $request): Ctx
    {
        $ctx = self::ctx($request);
        if (!$ctx->canAccess('resultado')) {
            throw new HttpError(403, 'Seu perfil não acessa relatórios.');
        }
        return $ctx;
    }

    private static function ctx(Request $request): Ctx
    {
        $user = Guard::user($request);
        return Auth::buildContext((int) $user['id'])
            ?? throw new HttpError(403, 'Usuário sem perfil de equipe ativo.');
    }
}
