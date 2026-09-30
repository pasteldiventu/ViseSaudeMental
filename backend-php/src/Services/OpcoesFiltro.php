<?php

declare(strict_types=1);

namespace Vise\Services;

use Vise\Admin\Ctx;
use Vise\Admin\Resources;
use Vise\Admin\Scope;
use Vise\Db;

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
            'id' => (int) $t['id'], 'nome' => (string) $t['nome'], 'escola_id' => (int) $t['escola_id'], 'escola' => (string) $t['escola'], 'turno' => $t['turno'],
        ], Db::all(
            "SELECT t.id, t.nome, t.escola_id, t.turno, e.nome AS escola FROM turmas t JOIN escolas e ON e.id = t.escola_id
             WHERE $where AND t.deleted_at IS NULL ORDER BY e.nome, t.nome LIMIT 2000",
            $params
        ));
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
            'escolas' => $lista(Resources::options('escolas', $ctx, false)),
            'turmas' => self::turmas($ctx),
            'series' => $lista(Resources::options('series', $ctx, false)),
            'turnos' => $pares(Resources::TURNOS),
            'sexos' => $pares(self::sexos($ctx)),
            'questionarios' => $lista(Resources::options('questionarios', $ctx, false)),
            'periodos' => $pares(FiltrosRelatorio::PERIODOS + ['personalizado' => 'Personalizado']),
            'abas' => $abas,
        ];
    }
}
