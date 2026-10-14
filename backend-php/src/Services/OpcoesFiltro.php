<?php

declare(strict_types=1);

namespace Vise\Services;

use Vise\Admin\Ctx;
use Vise\Admin\Resources;
use Vise\Admin\Scope;
use Vise\Db;
use Vise\Support\Turmas;

/** Valores disponíveis nos filtros do painel e dos relatórios, no escopo do usuário. */
final class OpcoesFiltro
{
    /** @return list<array{id: int, nome: string, escola_id: int, escola: string, turno: ?string}> */
    public static function turmas(Ctx $ctx, ?int $escolaId = null): array
    {
        [$where, $params] = Scope::where('turmas', $ctx);
        if ($escolaId !== null) {
            $where .= ' AND t.escola_id = ?';
            $params[] = $escolaId;
        }
        return array_map(static fn (array $t) => [
            'id' => (int) $t['id'], 'nome' => Turmas::rotulo($t['serie'], $t['nome']), 'escola_id' => (int) $t['escola_id'], 'escola' => (string) $t['escola'], 'turno' => $t['turno'],
        ], Db::all(
            "SELECT t.id, t.nome, s.descricao AS serie, t.escola_id, t.turno, e.nome AS escola FROM turmas t JOIN escolas e ON e.id = t.escola_id
             LEFT JOIN series s ON s.id = t.serie_id
             WHERE $where AND t.deleted_at IS NULL ORDER BY e.nome, s.descricao, t.nome LIMIT 2000",
            $params
        ));
    }

    /** @return list<array{id: int, nome: string, descricao: ?string, emoji: ?string, cor: ?string, questionarios: int}> temas ativos (contagem no escopo) */
    public static function temas(Ctx $ctx): array
    {
        [$where, $params] = Scope::where('questionarios', $ctx);
        return array_map(static fn (array $t) => [
            'id' => (int) $t['id'], 'nome' => (string) $t['nome'], 'descricao' => $t['descricao'], 'emoji' => $t['emoji'], 'cor' => $t['cor'],
            'questionarios' => (int) $t['questionarios'],
        ], Db::all(
            "SELECT te.id, te.nome, te.descricao, te.emoji, te.cor,
                    (SELECT COUNT(*) FROM questionarios t WHERE t.tema_id = te.id AND t.deleted_at IS NULL AND $where) AS questionarios
             FROM temas te WHERE te.ativo = 1 AND te.deleted_at IS NULL ORDER BY te.nome",
            $params
        ));
    }

    /** @return list<array{id: int, nome: string, tema_id: ?int}> */
    public static function questionarios(Ctx $ctx, ?int $temaId = null): array
    {
        [$where, $params] = Scope::where('questionarios', $ctx);
        if ($temaId !== null) {
            $where .= ' AND t.tema_id = ?';
            $params[] = $temaId;
        }
        $titulo = Resources::get('questionarios')['title'];
        return array_map(static fn (array $q) => [
            'id' => (int) $q['id'], 'nome' => (string) $q['nome'], 'tema_id' => $q['tema_id'] === null ? null : (int) $q['tema_id'],
        ], Db::all("SELECT t.id, $titulo AS nome, t.tema_id FROM questionarios t WHERE $where ORDER BY nome LIMIT 2000", $params));
    }

    /** @return array<string, string> */
    public static function sexos(Ctx $ctx): array
    {
        [$where, $params] = Scope::where('alunos', $ctx);
        $opcoes = [];
        foreach (Db::all("SELECT DISTINCT t.sexo FROM alunos t WHERE $where AND t.sexo IS NOT NULL AND t.sexo <> '' ORDER BY t.sexo LIMIT 20", $params) as $s) {
            $opcoes[(string) $s['sexo']] = ucfirst((string) $s['sexo']);
        }
        return $opcoes;
    }

    /** @return array<string, mixed> tudo que um formulário de relatório precisa */
    public static function todas(Ctx $ctx): array
    {
        $lista = static fn (array $mapa): array => array_map(
            static fn ($id, $nome) => ['id' => $id, 'nome' => $nome],
            array_keys($mapa),
            array_values($mapa)
        );
        $pares = static fn (array $mapa): array => array_map(
            static fn ($valor, $rotulo) => ['valor' => (string) $valor, 'rotulo' => $rotulo],
            array_keys($mapa),
            array_values($mapa)
        );
        $abas = [];
        foreach (RelatorioService::ABAS as $chave => [$rotulo, $descricao, $padrao]) {
            $abas[] = ['chave' => $chave, 'rotulo' => $rotulo, 'descricao' => $descricao, 'padrao' => $padrao, 'obrigatoria' => $chave === 'resumo'];
        }
        return [
            'temas' => self::temas($ctx),
            'escolas' => $lista(Resources::options('escolas', $ctx, false)),
            'turmas' => self::turmas($ctx),
            'series' => $lista(Resources::options('series', $ctx, false)),
            'turnos' => $pares(Resources::TURNOS),
            'sexos' => $pares(self::sexos($ctx)),
            'questionarios' => self::questionarios($ctx),
            'periodos' => $pares(FiltrosRelatorio::PERIODOS + ['personalizado' => 'Personalizado']),
            'abas' => $abas,
        ];
    }
}
