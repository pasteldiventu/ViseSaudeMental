<?php

declare(strict_types=1);

namespace Vise\Admin;

use Vise\Db;

/**
 * Filtro de visibilidade por papel, aplicado à tabela principal com alias "t".
 * $strict = true restringe o pesquisador aos próprios questionários (edição/exclusão/ações).
 */
final class Scope
{
    /** @return array{0: string, 1: list<mixed>} */
    public static function where(string $resource, Ctx $ctx, bool $strict = false): array
    {
        if ($ctx->su) {
            return ['1=1', []];
        }
        $conds = [];
        $params = [];
        $add = static function (string $sql, array $values = []) use (&$conds, &$params): void {
            $conds[] = $sql;
            array_push($params, ...$values);
        };

        $escolas = $ctx->escolaIds;
        $inEscolas = Db::in($escolas);
        $turmas = $ctx->turmaIds;
        $inTurmas = Db::in($turmas);
        $professor = $ctx->professorOnly();
        [$appProfessor, $appProfessorParams] = self::aplicacaoDoProfessor('s_a', $turmas);

        switch ($resource) {
            case 'series':
                break;
            case 'escolas':
                $add("t.id IN ($inEscolas)", $escolas);
                break;
            case 'usuarios':
                $add(
                    "EXISTS (SELECT 1 FROM escola_user s_eu WHERE s_eu.user_id = t.id AND s_eu.status = 'ativo' AND s_eu.escola_id IN ($inEscolas))",
                    $escolas
                );
                break;
            case 'professores-turmas':
                $add("EXISTS (SELECT 1 FROM turmas s_t WHERE s_t.id = t.turma_id AND s_t.escola_id IN ($inEscolas))", $escolas);
                break;
            case 'respostas':
            case 'resultados':
                $extra = $professor ? " AND $appProfessor" : '';
                $add(
                    "EXISTS (SELECT 1 FROM aplicacoes_questionario s_a WHERE s_a.id = t.aplicacao_id AND s_a.escola_id IN ($inEscolas)$extra)",
                    array_merge($escolas, $professor ? $appProfessorParams : [])
                );
                break;
            case 'termos':
                $add("EXISTS (SELECT 1 FROM alunos s_al WHERE s_al.id = t.aluno_id AND s_al.escola_id IN ($inEscolas))", $escolas);
                break;
            default:
                $add("t.escola_id IN ($inEscolas)", $escolas);
        }

        if ($ctx->pesquisadorOnly()) {
            $vis = 'SELECT s_q.id FROM questionarios s_q WHERE s_q.escola_id IN (' . $inEscolas . ') AND (s_q.pesquisador_id = ?'
                . ($strict ? '' : ' OR s_q.compartilhado_na_escola = 1') . ')';
            $visParams = array_merge($escolas, [$ctx->uid]);
            $categorias = "SELECT s_c.id FROM categorias s_c WHERE s_c.questionario_id IN ($vis)";
            switch ($resource) {
                case 'questionarios':
                    $add("t.id IN ($vis)", $visParams);
                    break;
                case 'categorias':
                    $add("t.questionario_id IN ($vis)", $visParams);
                    break;
                case 'subcategorias':
                case 'perguntas':
                    $add("t.categoria_id IN ($categorias)", $visParams);
                    break;
                case 'opcoes':
                    $add("t.pergunta_id IN (SELECT s_p.id FROM perguntas s_p WHERE s_p.categoria_id IN ($categorias))", $visParams);
                    break;
                case 'regras':
                    $add("(t.questionario_id IN ($vis) OR t.categoria_id IN ($categorias))", array_merge($visParams, $visParams));
                    break;
            }
        }

        if ($professor) {
            switch ($resource) {
                case 'turmas':
                    $add("t.id IN ($inTurmas)", $turmas);
                    break;
                case 'alunos':
                    $add("t.turma_id IN ($inTurmas)", $turmas);
                    break;
                case 'aplicacoes':
                    [$sql, $values] = self::aplicacaoDoProfessor('t', $turmas);
                    $add($sql, $values);
                    break;
            }
        }

        return [$conds === [] ? '1=1' : implode(' AND ', $conds), $params];
    }

    /**
     * Aplicações visíveis ao professor: escola toda, suas turmas ou alunos das suas turmas.
     *
     * @param list<int> $turmas
     * @return array{0: string, 1: list<mixed>}
     */
    private static function aplicacaoDoProfessor(string $alias, array $turmas): array
    {
        if ($turmas === []) {
            return ['1=0', []];
        }
        $in = Db::in($turmas);
        return [
            "($alias.alvo_tipo = 'escola'"
            . " OR ($alias.alvo_tipo = 'turma' AND $alias.turma_id IN ($in))"
            . " OR ($alias.alvo_tipo = 'aluno' AND $alias.aluno_id IN (SELECT s_al2.id FROM alunos s_al2 WHERE s_al2.turma_id IN ($in))))",
            array_merge($turmas, $turmas),
        ];
    }
}
