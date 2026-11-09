<?php

declare(strict_types=1);

namespace Vise\Admin;

use Vise\Http\Url;
use Vise\Services\ResultadoService;

/** HTML do resultado de um aluno: situação colorida, classificação por categoria e respostas. */
final class ResultadoView
{
    /** Selo da situação geral (Adequado, Atenção, Prioritário ou Sem classificação). */
    public static function selo(?string $nivel, bool $grande = false): string
    {
        $observacao = ResultadoService::OBSERVACAO[$nivel ?? ''] ?? '';
        return '<span class="nivel nivel-' . View::e($nivel ?? 'sem') . ($grande ? ' nivel-lg' : '') . '"'
            . ($observacao === '' ? '' : ' title="' . View::e($observacao) . '"') . '><i></i>'
            . View::e(ResultadoService::nivelRotulo($nivel)) . '</span>';
    }

    /** Classificação de cada categoria, com a cor do nível (para a lista). */
    public static function classes(string $classificacaoJson): string
    {
        $html = '';
        foreach (ResultadoService::classes($classificacaoJson) as $c) {
            $html .= '<span class="res-cat"><i class="dot n-' . View::e($c['nivel'] ?? 'sem') . '"></i>'
                . View::e($c['categoria']) . ': <strong>' . View::e($c['rotulo']) . '</strong></span>';
        }
        return $html === '' ? '<span class="muted">—</span>' : '<div class="res-cats">' . $html . '</div>';
    }

    /** @param array<string, mixed> $d dados de ResultadoService::detalhe() */
    public static function detalhe(array $d, Ctx $ctx): string
    {
        $local = array_filter([$d['turma'], $d['turno'] === null ? null : ucfirst((string) $d['turno']), $d['escola']]);
        $aluno = View::e($d['aluno']['nome']);
        if ($ctx->canAccess('aluno')) {
            $aluno = '<a href="' . View::e(Url::to('/admin/alunos/' . $d['aluno']['id'])) . '">' . $aluno . '</a>';
        }
        $aplicacao = $d['aplicacao']['codigo_sala'] === null ? '#' . $d['aplicacao']['id'] : 'Sala ' . $d['aplicacao']['codigo_sala'];

        $html = '<div class="card res-topo res-' . View::e($d['nivel'] ?? 'sem') . '">'
            . '<div class="res-aluno"><div><h2>' . $aluno . '</h2>'
            . '<p class="muted">' . View::e($local === [] ? 'Sem turma' : implode(' · ', $local)) . '</p></div>'
            . self::selo($d['nivel'], true) . '</div>'
            . '<p class="res-obs"><strong>Observação:</strong> ' . View::e($d['observacao']) . '</p>'
            . '<dl class="details">'
            . '<dt>Questionário</dt><dd>' . View::e($d['questionario'] ?? '—') . '</dd>'
            . '<dt>Aplicação</dt><dd><a href="' . View::e(Url::to('/admin/aplicacoes/' . $d['aplicacao']['id'])) . '">' . View::e($aplicacao) . '</a></dd>'
            . '<dt>Concluído em</dt><dd>' . View::e(date('d/m/Y H:i', (int) strtotime((string) $d['concluido_em']))) . '</dd>'
            . '<dt>Perguntas respondidas</dt><dd>' . $d['respondidas'] . ' de ' . $d['total_perguntas'] . '</dd>'
            . '</dl></div>';

        foreach ($d['categorias'] as $c) {
            $cor = is_string($c['cor']) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $c['cor']) ? $c['cor'] : '#c5cbd3';
            $html .= '<div class="card res-categoria" style="--cat:' . View::e($cor) . '">'
                . '<div class="res-cat-topo"><h3>' . View::e($c['nome']) . '</h3><div class="res-cat-nota">'
                . ($c['total'] === null ? '' : '<span class="pill">' . self::pontos($c['total']) . '</span> ')
                . ($c['rotulo'] === null
                    ? '<span class="muted">Sem regra de classificação</span>'
                    : '<span class="res-cat"><i class="dot n-' . View::e($c['nivel'] ?? 'sem') . '"></i><strong>' . View::e($c['rotulo']) . '</strong></span>')
                . '</div></div>';
            if (!empty($c['descricao'])) {
                $html .= '<p class="res-desc">' . nl2br(View::e($c['descricao'])) . '</p>';
            }
            if ($c['perguntas'] === []) {
                $html .= '<p class="muted">Nenhuma pergunta desta categoria.</p></div>';
                continue;
            }
            $html .= '<ol class="res-perguntas">';
            foreach ($c['perguntas'] as $p) {
                $html .= '<li><p class="res-pergunta">' . nl2br(View::e($p['texto'])) . '</p>'
                    . ($p['respondida']
                        ? '<p class="res-resposta">' . ($p['emoji'] ? View::e($p['emoji']) . ' ' : '') . View::e($p['resposta'])
                            . ($p['pontos'] === null ? '' : ' <span class="pill">' . self::pontos((float) $p['pontos']) . '</span>') . '</p>'
                        : '<p class="res-resposta res-vazia">Não respondida</p>')
                    . '</li>';
            }
            $html .= '</ol></div>';
        }
        return $html;
    }

    private static function pontos(float $valor): string
    {
        $texto = rtrim(rtrim(number_format($valor, 2, ',', '.'), '0'), ',');
        return $texto . ($texto === '1' ? ' ponto' : ' pontos');
    }
}
