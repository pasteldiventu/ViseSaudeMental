<?php

declare(strict_types=1);

namespace Vise\Api;

use Vise\Admin\Auth;
use Vise\Admin\Ctx;
use Vise\Admin\FormError;
use Vise\Admin\Records;
use Vise\Admin\Resources;
use Vise\Http\HttpError;
use Vise\Http\Request;
use Vise\Http\Response;

/**
 * Gestão pelo app (equipe): os mesmos cadastros, regras e escopo do painel web, em JSON.
 * Só usa GET/POST para funcionar em hospedagens que bloqueiam PUT/DELETE.
 */
final class CadastrosController
{
    public static function menu(Request $request): Response
    {
        $ctx = self::ctx($request);
        $grupos = [];
        $cadastros = [];
        foreach (Resources::MENU as $grupo => $keys) {
            $itens = [];
            foreach ($keys as $key) {
                if ($key === '@importar-alunos') {
                    continue;
                }
                $res = Resources::get($key);
                if ($res === null || !$ctx->canAccess($res['perm'])) {
                    continue;
                }
                $itens[] = $key;
                $cadastros[] = self::meta($res, $ctx) + ['grupo' => $grupo];
            }
            if ($itens !== []) {
                $grupos[] = ['grupo' => $grupo, 'itens' => $itens];
            }
        }
        return Response::json(['menu' => $grupos, 'data' => $cadastros]);
    }

    /** @param array<string, string> $p */
    public static function index(Request $request, array $p): Response
    {
        $ctx = self::ctx($request);
        $res = Records::resource($p['resource'], $ctx);
        $found = Records::search($res, $ctx, $request->query, 30);
        $rows = $found['rows'];
        $titles = Records::fkTitles($res, $rows, $res['list']);
        $rowTitles = Resources::titles($res['key'], array_map(static fn ($row) => (int) $row['id'], $rows));

        $data = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $colunas = [];
            foreach ($res['list'] as $column) {
                $colunas[] = Records::text($res['fields'][$column] ?? ['type' => 'text'], $row[$column] ?? null, $titles[$column] ?? [], true);
            }
            $data[] = ['id' => $id, 'titulo' => $rowTitles[$id] ?? '#' . $id, 'colunas' => $colunas];
        }

        $filtros = [];
        foreach ($found['filters'] as $field => $value) {
            $filtros[] = [
                'campo' => $field,
                'label' => $res['fields'][$field]['label'],
                'valor' => $value,
                'titulo' => Resources::title($res['fields'][$field]['ref'], $value),
            ];
        }
        return Response::json([
            'data' => $data,
            'total' => $found['total'],
            'page' => $found['page'],
            'pages' => $found['pages'],
            'filtros' => $filtros,
        ]);
    }

    /** @param array<string, string> $p */
    public static function show(Request $request, array $p): Response
    {
        $ctx = self::ctx($request);
        $res = Records::resource($p['resource'], $ctx);
        $row = Records::findRow($res, (int) $p['id'], $ctx, false);
        $titles = Records::fkTitles($res, [$row], array_keys($res['fields']));

        $campos = [];
        foreach ($res['fields'] as $name => $field) {
            if (!empty($field['virtual']) || ($field['detail'] ?? true) === false || $field['type'] === 'password') {
                continue;
            }
            if (!empty($field['su_only']) && !$ctx->su) {
                continue;
            }
            $item = [
                'name' => $name,
                'label' => $field['label'],
                'tipo' => $field['type'],
                'valor' => Records::text($field, $row[$name] ?? null, $titles[$name] ?? []),
            ];
            if ($field['type'] === 'fk' && $row[$name] !== null) {
                $ref = Resources::get($field['ref']);
                if ($ref !== null && $ctx->canAccess($ref['perm'])) {
                    $item['link'] = ['key' => $field['ref'], 'id' => (int) $row[$name]];
                }
            }
            if ($field['type'] === 'json' && $row[$name] !== null) {
                $item['pares'] = Records::jsonPairs((string) $row[$name]);
            }
            $campos[] = $item;
        }
        foreach (['created_at' => 'Criado em', 'updated_at' => 'Atualizado em'] as $column => $label) {
            if (array_key_exists($column, $row) && !isset($res['fields'][$column])) {
                $campos[] = ['name' => $column, 'label' => $label, 'tipo' => 'datetime', 'valor' => Records::text(['type' => 'datetime'], $row[$column])];
            }
        }

        $editavel = Records::writable($res, $ctx) && Resources::inScope($res['key'], (int) $row['id'], $ctx, true);
        return Response::json([
            'id' => (int) $row['id'],
            'key' => $res['key'],
            'singular' => $res['singular'],
            'titulo' => Resources::title($res['key'], (int) $row['id']),
            'pode_editar' => $editavel,
            'campos' => $campos,
            'relacionados' => Records::related($res, $row, $ctx),
            'acoes' => $editavel ? self::acoes($res, $ctx) : [],
        ]);
    }

    /** @param array<string, string> $p */
    public static function form(Request $request, array $p): Response
    {
        $ctx = self::ctx($request);
        $res = Records::resource($p['resource'], $ctx, true);
        $id = $request->queryInt('id');
        $row = $id === null ? null : Records::findRow($res, $id, $ctx, true);

        $campos = [];
        foreach (Records::formFields($res, $ctx, $row) as $name => $field) {
            if ($row !== null) {
                $valor = $row[$name] ?? null;
            } elseif (isset($request->query[$name]) && is_string($request->query[$name])) {
                $valor = $request->query[$name];
            } else {
                $valor = $field['default'] ?? null;
            }
            $campos[] = [
                'name' => $name,
                'label' => $field['label'],
                'tipo' => $field['type'],
                'obrigatorio' => !empty($field['required']),
                'max' => $field['max'] ?? null,
                'ajuda' => $field['help'] ?? null,
                'opcoes' => self::opcoes($field, $ctx, $row, $name),
                'valor' => self::valorFormulario($field, $valor),
            ];
        }
        return Response::json([
            'key' => $res['key'],
            'titulo' => $row === null ? 'Novo(a) ' . mb_strtolower($res['singular']) : 'Editar ' . mb_strtolower($res['singular']),
            'campos' => $campos,
        ]);
    }

    /** @param array<string, string> $p */
    public static function create(Request $request, array $p): Response
    {
        return self::save($request, $p, null);
    }

    /** @param array<string, string> $p */
    public static function update(Request $request, array $p): Response
    {
        return self::save($request, $p, (int) $p['id']);
    }

    /** @param array<string, string> $p */
    public static function delete(Request $request, array $p): Response
    {
        $ctx = self::ctx($request);
        $res = Records::resource($p['resource'], $ctx, true);
        $row = Records::findRow($res, (int) $p['id'], $ctx, true);
        $error = Records::deleteRow($res, $row, $ctx);
        if ($error !== null) {
            throw new HttpError(422, $error);
        }
        return Response::json(['status' => 'ok', 'mensagem' => $res['singular'] . ' excluído(a).']);
    }

    /** @param array<string, string> $p */
    public static function action(Request $request, array $p): Response
    {
        $ctx = self::ctx($request);
        $res = Records::resource($p['resource'], $ctx);
        $ids = $request->json()['ids'] ?? null;
        if (!is_array($ids) || $ids === []) {
            throw new HttpError(422, 'Informe os registros (ids).');
        }
        $ids = array_values(array_map('intval', array_filter($ids, static fn ($id) => is_int($id) || (is_string($id) && ctype_digit($id)))));
        ['message' => $message, 'ok' => $ok] = Records::runAction($res, $ctx, $p['action'], $ids);
        return Response::json(['mensagem' => $message, 'ok' => $ok]);
    }

    // ------------------------------------------------------------------

    private static function ctx(Request $request): Ctx
    {
        $user = Guard::user($request);
        return Auth::buildContext((int) $user['id'])
            ?? throw new HttpError(403, 'Usuário sem perfil de equipe ativo.');
    }

    /** @param array<string, string> $p */
    private static function save(Request $request, array $p, ?int $id): Response
    {
        $ctx = self::ctx($request);
        $res = Records::resource($p['resource'], $ctx, true);
        $row = $id === null ? null : Records::findRow($res, $id, $ctx, true);
        try {
            $savedId = Records::persist($res, $ctx, $request->json(), $row);
        } catch (FormError $error) {
            throw new HttpError(422, $error->getMessage());
        }
        return Response::json([
            'id' => $savedId,
            'titulo' => Resources::title($res['key'], $savedId),
            'mensagem' => $res['singular'] . ($row === null ? ' criado(a).' : ' atualizado(a).'),
        ], $row === null ? 201 : 200);
    }

    /** @return array<string, mixed> */
    private static function meta(array $res, Ctx $ctx): array
    {
        return [
            'key' => $res['key'],
            'singular' => $res['singular'],
            'plural' => $res['plural'],
            'pode_criar' => Records::writable($res, $ctx),
            'busca' => $res['search'] !== [],
            'busca_label' => implode(', ', array_map(static fn ($c) => mb_strtolower($res['fields'][$c]['label']), $res['search'])),
            'colunas' => array_map(static fn ($c) => $res['fields'][$c]['label'] ?? $c, $res['list']),
            'acoes' => self::acoes($res, $ctx),
        ];
    }

    /** @return list<array{nome: string, label: string, confirmar: string|null}> */
    private static function acoes(array $res, Ctx $ctx): array
    {
        $acoes = [];
        foreach (Records::actions($res, $ctx) as $name => $action) {
            if ($name === 'excluir') {
                continue;
            }
            $acoes[] = ['nome' => $name, 'label' => $action['label'], 'confirmar' => $action['confirm'] ?? null];
        }
        return $acoes;
    }

    /** @return list<array{valor: string, label: string}>|null */
    private static function opcoes(array $field, Ctx $ctx, ?array $row, string $name): ?array
    {
        $options = match ($field['type']) {
            'select' => $field['options'],
            'fk' => Records::fkOptions($field, $ctx, $row, $name),
            default => null,
        };
        if ($options === null) {
            return null;
        }
        $list = [];
        foreach ($options as $value => $label) {
            $list[] = ['valor' => (string) $value, 'label' => (string) $label];
        }
        return $list;
    }

    private static function valorFormulario(array $field, mixed $valor): mixed
    {
        if ($field['type'] === 'password') {
            return null;
        }
        if ($field['type'] === 'bool') {
            return (bool) $valor;
        }
        if ($valor === null || $valor === '') {
            return null;
        }
        return match ($field['type']) {
            'date' => substr((string) $valor, 0, 10),
            'datetime' => substr(str_replace('T', ' ', (string) $valor), 0, 16),
            default => (string) $valor,
        };
    }
}
