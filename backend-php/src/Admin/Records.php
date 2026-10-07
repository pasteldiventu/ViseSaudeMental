<?php

declare(strict_types=1);

namespace Vise\Admin;

use PDOException;
use Vise\Db;
use Vise\Http\HttpError;
use Vise\Support\Str;

/** Regras dos cadastros (permissão, escopo, validação, gravação) usadas pelo painel e pela API da equipe. */
final class Records
{
    public const PER_PAGE = 25;

    /** @return array<string, mixed> */
    public static function resource(string $key, Ctx $ctx, bool $write = false): array
    {
        $res = Resources::get($key) ?? throw new HttpError(404, 'Cadastro não encontrado.');
        if (!$ctx->canAccess($res['perm'])) {
            throw new HttpError(403, 'Seu perfil não tem acesso a este cadastro.');
        }
        if ($write && !self::writable($res, $ctx)) {
            throw new HttpError(403, 'Seu perfil não pode alterar este cadastro.');
        }
        return $res;
    }

    public static function writable(array $res, Ctx $ctx): bool
    {
        return empty($res['readonly']) && $ctx->canWrite($res['perm']);
    }

    /** @return array<string, array<string, mixed>> */
    public static function actions(array $res, Ctx $ctx): array
    {
        if (!self::writable($res, $ctx)) {
            return [];
        }
        $actions = [];
        foreach ($res['actions'] ?? [] as $name => $action) {
            if (empty($action['su_only']) || $ctx->su) {
                $actions[$name] = $action;
            }
        }
        $actions['excluir'] = ['label' => 'Excluir selecionados', 'confirm' => 'Excluir os registros selecionados?'];
        return $actions;
    }

    /**
     * Executa uma ação em lote.
     *
     * @param list<int> $ids
     * @return array{message: string, ok: bool}
     */
    public static function runAction(array $res, Ctx $ctx, string $name, array $ids): array
    {
        $actions = self::actions($res, $ctx);
        if (!isset($actions[$name])) {
            throw new HttpError(403, 'Ação não permitida para o seu perfil.');
        }
        $ids = array_values(array_filter(
            array_unique($ids),
            static fn (int $id) => Resources::inScope($res['key'], $id, $ctx, true)
        ));
        if ($ids === []) {
            throw new HttpError(422, 'Selecione ao menos um registro do seu escopo.');
        }
        if ($name !== 'excluir') {
            return ['message' => ($actions[$name]['handler'])($ids, $ctx), 'ok' => true];
        }
        $ok = 0;
        $erros = [];
        foreach ($ids as $id) {
            $row = Db::one("SELECT * FROM `{$res['table']}` WHERE id = ?", [$id]);
            $error = $row === null ? null : self::deleteRow($res, $row, $ctx);
            if ($error === null) {
                $ok++;
            } else {
                $erros[] = $error;
            }
        }
        return [
            'message' => "$ok registro(s) excluído(s)." . ($erros ? ' ' . implode(' ', array_unique($erros)) : ''),
            'ok' => $erros === [],
        ];
    }

    /** @return array<string, mixed> */
    public static function findRow(array $res, int $id, Ctx $ctx, bool $strict): array
    {
        [$where, $params] = Scope::where($res['key'], $ctx, $strict);
        $row = Db::one("SELECT t.* FROM `{$res['table']}` t WHERE t.id = ? AND $where", array_merge([$id], $params));
        if ($row === null) {
            throw new HttpError(404, 'Registro não encontrado ou fora do seu escopo.');
        }
        return $row;
    }

    /**
     * Filtros ?campo=id para colunas de chave estrangeira (ou campos virtuais com 'filter').
     *
     * @param array<string, mixed> $query
     * @return array<string, int>
     */
    public static function filters(array $res, array $query): array
    {
        $filters = [];
        foreach ($res['fields'] as $name => $field) {
            $value = $query[$name] ?? null;
            if (self::filterable($field) && is_string($value) && ctype_digit($value) && (int) $value > 0) {
                $filters[$name] = (int) $value;
            }
        }
        return $filters;
    }

    /** Campo que pode filtrar a lista por ID: chave estrangeira real ou virtual com SQL próprio ('filter'). */
    public static function filterable(array $field): bool
    {
        return $field['type'] === 'fk' && (empty($field['virtual']) || isset($field['filter']));
    }

    /**
     * Condição SQL (alias t) de um filtro.
     *
     * @return array{0: string, 1: list<int>}
     */
    public static function filterSql(array $res, string $name, int $value): array
    {
        $field = $res['fields'][$name];
        if (isset($field['filter'])) {
            return ['(' . $field['filter'] . ')', array_fill(0, substr_count($field['filter'], '?'), $value)];
        }
        return ["t.`$name` = ?", [$value]];
    }

    /**
     * Listagem paginada com busca, filtros e ordenação, já no escopo do usuário.
     *
     * @param array<string, mixed> $query
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, pages: int, filters: array<string, int>, q: string, sort: string, dir: string}
     */
    public static function search(array $res, Ctx $ctx, array $query, int $perPage = self::PER_PAGE): array
    {
        [$where, $params] = Scope::where($res['key'], $ctx);
        $filters = self::filters($res, $query);
        foreach ($filters as $field => $value) {
            [$sql, $values] = self::filterSql($res, $field, $value);
            $where .= " AND $sql";
            array_push($params, ...$values);
        }
        $q = trim((string) (is_string($query['q'] ?? null) ? $query['q'] : ''));
        if ($q !== '' && $res['search'] !== []) {
            $likes = [];
            foreach ($res['search'] as $column) {
                $likes[] = "t.`$column` LIKE ?";
                $params[] = '%' . $q . '%';
                if (($res['fields'][$column]['display'] ?? '') === 'cpf' && Str::digits($q) !== '') {
                    $likes[] = "t.`$column` LIKE ?";
                    $params[] = '%' . Str::digits($q) . '%';
                }
            }
            $where .= ' AND (' . implode(' OR ', $likes) . ')';
        }

        $sort = is_string($query['sort'] ?? null) ? $query['sort'] : '';
        $dir = ($query['dir'] ?? '') === 'asc' ? 'ASC' : 'DESC';
        $order = in_array($sort, $res['list'], true) ? "t.`$sort` $dir, t.id DESC" : ($res['order'] ?? 't.id DESC');

        $total = (int) Db::value("SELECT COUNT(*) FROM `{$res['table']}` t WHERE $where", $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) ($query['page'] ?? 1)));
        $rows = Db::all(
            "SELECT t.* FROM `{$res['table']}` t WHERE $where ORDER BY $order LIMIT $perPage OFFSET " . (($page - 1) * $perPage),
            $params
        );
        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'filters' => $filters,
            'q' => $q,
            'sort' => in_array($sort, $res['list'], true) ? $sort : '',
            'dir' => $dir,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<string> $columns
     * @return array<string, array<int, string>>
     */
    public static function fkTitles(array $res, array $rows, array $columns): array
    {
        $titles = [];
        foreach ($columns as $column) {
            $field = $res['fields'][$column] ?? null;
            if ($field === null || $field['type'] !== 'fk' || !empty($field['virtual'])) {
                continue;
            }
            $ids = array_map('intval', array_filter(array_column($rows, $column), static fn ($v) => $v !== null));
            $titles[$column] = Resources::titles($field['ref'], $ids);
        }
        return $titles;
    }

    /**
     * Cadastros que apontam para este registro (ex.: categorias de um questionário).
     *
     * @return list<array{key: string, field: string, label: string, count: int, can_add: bool}>
     */
    public static function related(array $res, array $row, Ctx $ctx): array
    {
        $related = [];
        foreach (Resources::all() as $childKey => $child) {
            if (!$ctx->canAccess($child['perm'])) {
                continue;
            }
            foreach ($child['fields'] as $name => $field) {
                if (($field['ref'] ?? '') !== $res['key'] || !self::filterable($field)) {
                    continue;
                }
                [$where, $params] = Scope::where($childKey, $ctx);
                [$filter, $filterParams] = self::filterSql($child + ['key' => $childKey], $name, (int) $row['id']);
                $related[] = [
                    'key' => $childKey,
                    'field' => $name,
                    'label' => $child['plural'] . ($name === 'parent_id' ? ' (versões seguintes)' : ''),
                    'count' => (int) Db::value(
                        "SELECT COUNT(*) FROM `{$child['table']}` t WHERE $filter AND $where",
                        array_merge($filterParams, $params)
                    ),
                    'can_add' => self::writable($child, $ctx) && ($field['form'] ?? true) !== false,
                ];
            }
        }
        usort($related, static fn (array $a, array $b) => (Resources::get($b['key'])['related_order'] ?? 0) <=> (Resources::get($a['key'])['related_order'] ?? 0));
        return $related;
    }

    /** @return array<string, array<string, mixed>> */
    public static function formFields(array $res, Ctx $ctx, ?array $row): array
    {
        $fields = [];
        foreach ($res['fields'] as $name => $field) {
            if (($field['form'] ?? true) === false || $field['type'] === 'json') {
                continue;
            }
            if (!empty($field['su_only']) && !$ctx->su) {
                continue;
            }
            if (!empty($field['create_only']) && $row !== null) {
                continue;
            }
            if ($name === 'pesquisador_id' && $ctx->pesquisadorOnly()) {
                continue;
            }
            $fields[$name] = $field;
        }
        return $fields;
    }

    /** @return array<int|string, string> */
    public static function fkOptions(array $field, Ctx $ctx, ?array $row, string $name): array
    {
        $options = Resources::options($field['ref'], $ctx, $field['strict'] ?? true);
        $current = $row[$name] ?? null;
        if ($current !== null && !isset($options[(int) $current])) {
            $options[(int) $current] = Resources::title($field['ref'], (int) $current);
        }
        return $options;
    }

    /**
     * Valida e grava (insert/update). Erros de validação e de integridade viram FormError.
     *
     * @param array<string, mixed> $input valores do formulário (strings) ou JSON
     */
    public static function persist(array $res, Ctx $ctx, array $input, ?array $row): int
    {
        try {
            $data = self::parse($res, $ctx, $input, $row);
            if (!empty($res['derive_escola'])) {
                $escolaId = null;
                foreach ($res['derive_escola'] as [$field, $table]) {
                    $parent = $data[$field] ?? $row[$field] ?? null;
                    if (!empty($parent)) {
                        $escolaId = Db::value("SELECT escola_id FROM `$table` WHERE id = ?", [(int) $parent]);
                        break;
                    }
                }
                if ($escolaId === null) {
                    $first = $res['derive_escola'][0][0];
                    throw new FormError('Informe o campo ' . $res['fields'][$first]['label'] . '.');
                }
                $data['escola_id'] = (int) $escolaId;
            }
            if (isset($res['validate'])) {
                ($res['validate'])($data, $row, $ctx, $input);
            }

            $columns = array_filter(
                $data,
                static fn ($name) => empty($res['fields'][$name]['virtual']),
                ARRAY_FILTER_USE_KEY
            );
            return Db::transaction(static function () use ($res, $row, $columns, $data, $ctx): int {
                if ($row === null) {
                    foreach ($res['fields'] as $name => $field) {
                        if (empty($field['virtual']) && !array_key_exists($name, $columns) && array_key_exists('default', $field)) {
                            $columns[$name] = $field['default'];
                        }
                    }
                    if (isset($res['defaults'])) {
                        $columns += ($res['defaults'])();
                    }
                    $now = Db::now();
                    $columns += ['created_at' => $now, 'updated_at' => $now];
                    $newId = Db::insert($res['table'], $columns);
                } else {
                    $newId = (int) $row['id'];
                    if ($columns !== []) {
                        Db::update($res['table'], $columns, $newId);
                    }
                }
                if (isset($res['after_save'])) {
                    ($res['after_save'])($newId, $data, $row === null, $ctx);
                }
                return $newId;
            });
        } catch (PDOException $error) {
            if (Db::isDuplicate($error)) {
                throw new FormError('Já existe um registro com esses dados (valor que deve ser único está repetido).');
            }
            if (Db::isForeignKey($error)) {
                throw new FormError('Algum dos registros escolhidos não existe mais.');
            }
            throw $error;
        }
    }

    /**
     * Converte e valida a entrada conforme os tipos dos campos.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function parse(array $res, Ctx $ctx, array $input, ?array $row): array
    {
        $data = [];
        foreach (self::formFields($res, $ctx, $row) as $name => $field) {
            $label = $field['label'];
            $raw = $input[$name] ?? null;
            if (is_bool($raw)) {
                $raw = $raw ? '1' : '';
            } elseif (is_int($raw) || is_float($raw)) {
                $raw = (string) $raw;
            }
            if ($field['type'] === 'bool') {
                $data[$name] = ($raw !== null && $raw !== '0' && $raw !== '') ? 1 : 0;
                continue;
            }
            $value = is_string($raw) ? trim($raw) : '';
            if ($field['type'] === 'password') {
                if ($value !== '') {
                    $data[$name] = $value;
                }
                continue;
            }
            if (!empty($field['upper'])) {
                $value = Str::upper($value);
            }
            if ($value === '') {
                if (!empty($field['required'])) {
                    throw new FormError("Preencha o campo $label.");
                }
                $data[$name] = null;
                continue;
            }
            switch ($field['type']) {
                case 'int':
                    if (!preg_match('/^-?\d+$/', $value)) {
                        throw new FormError("$label deve ser um número inteiro.");
                    }
                    $data[$name] = (int) $value;
                    break;
                case 'decimal':
                    $value = str_replace(',', '.', $value);
                    if (!is_numeric($value)) {
                        throw new FormError("$label deve ser um número.");
                    }
                    $data[$name] = $value;
                    break;
                case 'select':
                    if (!array_key_exists($value, $field['options'])) {
                        throw new FormError("Valor inválido para $label.");
                    }
                    $data[$name] = $value;
                    break;
                case 'fk':
                    $unchanged = $row !== null && (string) ($row[$name] ?? '') === $value;
                    if (!ctype_digit($value) || (!$unchanged && !Resources::inScope($field['ref'], (int) $value, $ctx, $field['strict'] ?? true))) {
                        throw new FormError("Valor inválido para $label.");
                    }
                    $data[$name] = (int) $value;
                    break;
                case 'date':
                    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                    if ($date === false || $date->format('Y-m-d') !== $value) {
                        throw new FormError("$label deve ser uma data válida.");
                    }
                    $data[$name] = $value;
                    break;
                case 'datetime':
                    $value = str_replace('T', ' ', $value);
                    $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', substr($value, 0, 16));
                    if ($date === false) {
                        throw new FormError("$label deve ser uma data/hora válida.");
                    }
                    $data[$name] = $date->format('Y-m-d H:i:00');
                    break;
                default:
                    if (isset($field['max']) && Str::len($value) > $field['max']) {
                        throw new FormError("$label deve ter no máximo {$field['max']} caracteres.");
                    }
                    $data[$name] = $value;
            }
        }
        return $data;
    }

    /** @return string|null mensagem de erro */
    public static function deleteRow(array $res, array $row, Ctx $ctx): ?string
    {
        try {
            if (isset($res['before_delete'])) {
                ($res['before_delete'])($row, $ctx);
            }
            Db::transaction(static function () use ($res, $row): void {
                foreach ($res['cascade'] ?? [] as $cascade) {
                    [$table, $column] = $cascade;
                    $extra = isset($cascade[2]) ? ' AND ' . $cascade[2] : '';
                    Db::run("DELETE FROM `$table` WHERE `$column` = ?$extra", [$row['id']]);
                }
                Db::run("DELETE FROM `{$res['table']}` WHERE id = ?", [$row['id']]);
            });
            return null;
        } catch (FormError $error) {
            return $error->getMessage();
        } catch (PDOException $error) {
            if (Db::isForeignKey($error)) {
                return 'Não é possível excluir "' . Resources::title($res['key'], (int) $row['id']) . '": existem registros vinculados a ele.';
            }
            throw $error;
        }
    }

    /**
     * Valor em texto simples para listas e detalhes.
     *
     * @param array<int, string> $titles títulos da chave estrangeira
     */
    public static function text(array $field, mixed $value, array $titles = [], bool $list = false): string
    {
        $type = $field['type'];
        if ($type === 'bool') {
            return $value ? 'Sim' : 'Não';
        }
        if ($value === null || $value === '') {
            return '—';
        }
        switch ($type) {
            case 'select':
                return (string) ($field['options'][$value] ?? $value);
            case 'fk':
                return $titles[(int) $value] ?? '#' . (int) $value;
            case 'date':
                return date('d/m/Y', (int) strtotime((string) $value));
            case 'datetime':
                return date('d/m/Y H:i', (int) strtotime((string) $value));
            case 'password':
                return '••••••';
            case 'json':
                return self::jsonText((string) $value, $list);
        }
        if (($field['display'] ?? '') === 'cpf') {
            return Str::cpf($value);
        }
        if ($list && ($type === 'textarea' || ($field['display'] ?? '') === 'resumo')) {
            return Str::limit($value, 80);
        }
        return (string) $value;
    }

    /**
     * JSON de resultado (chaves = IDs de categoria) como pares categoria → valor.
     *
     * @return array<string, string>|null
     */
    public static function jsonPairs(string $json): ?array
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }
        $titles = Resources::titles('categorias', array_map('intval', array_filter(array_keys($data), 'is_numeric')));
        $pairs = [];
        foreach ($data as $categoria => $valor) {
            if (is_array($valor)) {
                $valor = trim(($valor['rotulo'] ?? '') . (empty($valor['descricao']) ? '' : ' — ' . $valor['descricao']));
            }
            $pairs[$titles[(int) $categoria] ?? 'Categoria #' . $categoria] = $valor === null ? 'sem regra' : (string) $valor;
        }
        return $pairs;
    }

    private static function jsonText(string $json, bool $list): string
    {
        if ($list) {
            return Str::limit($json, 60);
        }
        $pairs = self::jsonPairs($json);
        if ($pairs === null) {
            return $json;
        }
        $lines = [];
        foreach ($pairs as $categoria => $valor) {
            $lines[] = "$categoria: $valor";
        }
        return implode("\n", $lines);
    }
}
