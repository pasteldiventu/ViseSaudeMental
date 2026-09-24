<?php

declare(strict_types=1);

namespace Vise\Admin;

use PDOException;
use Vise\Db;
use Vise\Http\HttpError;
use Vise\Http\Request;
use Vise\Http\Response;
use Vise\Http\Url;
use Vise\Support\Str;

final class CrudController
{
    private const PER_PAGE = 25;

    /** @param array<string, string> $p */
    public static function index(Request $request, array $p): Response
    {
        $ctx = self::ctx($request);
        $res = self::resource($p, $ctx);
        $key = $res['key'];

        [$where, $params] = Scope::where($key, $ctx);
        $filters = self::filters($res, $request);
        foreach ($filters as $field => $value) {
            $where .= " AND t.`$field` = ?";
            $params[] = $value;
        }
        $q = trim((string) ($request->query['q'] ?? ''));
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

        $sort = (string) ($request->query['sort'] ?? '');
        $dir = ($request->query['dir'] ?? '') === 'asc' ? 'ASC' : 'DESC';
        $order = in_array($sort, $res['list'], true) ? "t.`$sort` $dir, t.id DESC" : ($res['order'] ?? 't.id DESC');

        $total = (int) Db::value("SELECT COUNT(*) FROM `{$res['table']}` t WHERE $where", $params);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($pages, max(1, (int) ($request->query['page'] ?? 1)));
        $rows = Db::all(
            "SELECT t.* FROM `{$res['table']}` t WHERE $where ORDER BY $order LIMIT " . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE),
            $params
        );
        $titles = self::fkTitles($res, $rows, $res['list']);
        $writable = self::writable($res, $ctx);
        $actions = self::bulkActions($res, $ctx);
        $baseQuery = array_merge($filters, ['q' => $q, 'sort' => $sort ?: null, 'dir' => $sort ? strtolower($dir) : null]);

        $html = '<div class="card">';
        $html .= '<form class="toolbar" method="get" action="' . View::e(Url::to("/admin/$key")) . '">';
        foreach ($filters as $field => $value) {
            $html .= '<input type="hidden" name="' . View::e($field) . '" value="' . View::e($value) . '">';
        }
        if ($res['search'] !== []) {
            $html .= '<input type="search" name="q" value="' . View::e($q) . '" placeholder="Buscar por ' . View::e(implode(', ', array_map(
                static fn ($c) => Str::lower($res['fields'][$c]['label']),
                $res['search']
            ))) . '">';
            $html .= '<button class="btn" type="submit">Buscar</button>';
        }
        $html .= '<span class="muted">' . $total . ' registro(s)</span></form>';

        if ($filters !== []) {
            $html .= '<div class="filters">';
            foreach ($filters as $field => $value) {
                $ref = $res['fields'][$field]['ref'];
                $html .= '<span class="chip">' . View::e($res['fields'][$field]['label']) . ': '
                    . View::e(Resources::title($ref, $value))
                    . ' <a href="' . View::e(Url::to("/admin/$key", array_diff_key($baseQuery, [$field => 1]))) . '" title="Remover filtro">×</a></span>';
            }
            $html .= '</div>';
        }

        $html .= '<form method="post" action="' . View::e(Url::to("/admin/$key/action")) . '" data-bulk>';
        $html .= View::csrfField();
        $html .= '<div class="table-wrap"><table><thead><tr>';
        if ($actions !== []) {
            $html .= '<th class="check"><input type="checkbox" onclick="document.querySelectorAll(\'input[name=&quot;ids[]&quot;]\').forEach(c => c.checked = this.checked)"></th>';
        }
        foreach ($res['list'] as $column) {
            $label = $res['fields'][$column]['label'] ?? $column;
            $nextDir = ($sort === $column && $dir === 'ASC') ? 'desc' : 'asc';
            $arrow = $sort === $column ? ($dir === 'ASC' ? ' ▲' : ' ▼') : '';
            $html .= '<th><a href="' . View::e(Url::to("/admin/$key", array_merge($baseQuery, ['sort' => $column, 'dir' => $nextDir]))) . '">'
                . View::e($label) . $arrow . '</a></th>';
        }
        $html .= '<th></th></tr></thead><tbody>';
        if ($rows === []) {
            $html .= '<tr><td class="empty" colspan="' . (count($res['list']) + 2) . '">Nenhum registro encontrado.</td></tr>';
        }
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $html .= '<tr>';
            if ($actions !== []) {
                $html .= '<td class="check"><input type="checkbox" name="ids[]" value="' . $id . '"></td>';
            }
            foreach ($res['list'] as $index => $column) {
                $cell = self::display($res['fields'][$column] ?? ['type' => 'text'], $row[$column] ?? null, $titles[$column] ?? [], $ctx, true);
                if ($index === 0) {
                    $cell = '<a href="' . View::e(Url::to("/admin/$key/$id")) . '">' . $cell . '</a>';
                }
                $html .= '<td>' . $cell . '</td>';
            }
            $html .= '<td class="row-actions"><a href="' . View::e(Url::to("/admin/$key/$id")) . '">Ver</a>';
            if ($writable) {
                $html .= ' <a href="' . View::e(Url::to("/admin/$key/$id/edit")) . '">Editar</a>';
            }
            $html .= '</td></tr>';
        }
        $html .= '</tbody></table></div>';

        if ($actions !== [] && $rows !== []) {
            $html .= '<div class="bulk"><select name="action" required><option value="">Ação com selecionados…</option>';
            foreach ($actions as $name => $action) {
                $html .= '<option value="' . View::e($name) . '" data-confirm="' . View::e($action['confirm'] ?? '') . '">' . View::e($action['label']) . '</option>';
            }
            $html .= '</select> <button class="btn" type="submit" onclick="var o=this.form.action.selectedOptions[0];return !o||!o.dataset.confirm||confirm(o.dataset.confirm)">Executar</button></div>';
        }
        $html .= '</form>';

        if ($pages > 1) {
            $html .= '<div class="pagination">';
            if ($page > 1) {
                $html .= '<a class="btn" href="' . View::e(Url::to("/admin/$key", array_merge($baseQuery, ['page' => $page - 1]))) . '">‹ Anterior</a>';
            }
            $html .= '<span>Página ' . $page . ' de ' . $pages . '</span>';
            if ($page < $pages) {
                $html .= '<a class="btn" href="' . View::e(Url::to("/admin/$key", array_merge($baseQuery, ['page' => $page + 1]))) . '">Próxima ›</a>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';

        $top = $writable
            ? '<a class="btn btn-primary" href="' . View::e(Url::to("/admin/$key/create", $filters)) . '">+ Novo(a) ' . View::e(Str::lower($res['singular'])) . '</a>'
            : '';
        return View::page($ctx, $res['plural'], $html, $key, $top);
    }

    /** @param array<string, string> $p */
    public static function show(Request $request, array $p): Response
    {
        $ctx = self::ctx($request);
        $res = self::resource($p, $ctx);
        $key = $res['key'];
        $row = self::findRow($res, (int) $p['id'], $ctx, false);
        $titles = self::fkTitles($res, [$row], array_keys($res['fields']));

        $html = '<div class="card"><dl class="details">';
        foreach ($res['fields'] as $name => $field) {
            if (!empty($field['virtual']) || ($field['detail'] ?? true) === false || $field['type'] === 'password') {
                continue;
            }
            if (!empty($field['su_only']) && !$ctx->su) {
                continue;
            }
            $html .= '<dt>' . View::e($field['label']) . '</dt><dd>' . self::display($field, $row[$name] ?? null, $titles[$name] ?? [], $ctx, false) . '</dd>';
        }
        foreach (['created_at' => 'Criado em', 'updated_at' => 'Atualizado em'] as $column => $label) {
            if (array_key_exists($column, $row) && !isset($res['fields'][$column])) {
                $html .= '<dt>' . $label . '</dt><dd>' . self::display(['type' => 'datetime'], $row[$column], [], $ctx, false) . '</dd>';
            }
        }
        $html .= '</dl></div>';
        $html .= self::related($res, $row, $ctx);

        $top = '<a class="btn" href="' . View::e(Url::to("/admin/$key")) . '">‹ Voltar</a>';
        if (self::writable($res, $ctx) && Resources::inScope($key, (int) $row['id'], $ctx, true)) {
            $top .= ' <a class="btn btn-primary" href="' . View::e(Url::to("/admin/$key/{$row['id']}/edit")) . '">Editar</a>'
                . ' <form class="inline" method="post" action="' . View::e(Url::to("/admin/$key/{$row['id']}/delete")) . '" onsubmit="return confirm(\'Excluir este registro?\')">'
                . View::csrfField() . '<button class="btn btn-danger" type="submit">Excluir</button></form>';
        }
        $title = $res['singular'] . ': ' . Resources::title($key, (int) $row['id']);
        return View::page($ctx, $title, $html, $key, $top);
    }

    /** @param array<string, string> $p */
    public static function createForm(Request $request, array $p): Response
    {
        $ctx = self::ctx($request);
        $res = self::resource($p, $ctx, true);
        $values = [];
        foreach ($res['fields'] as $name => $field) {
            if (isset($request->query[$name]) && is_string($request->query[$name])) {
                $values[$name] = $request->query[$name];
            } elseif (array_key_exists('default', $field)) {
                $values[$name] = $field['default'];
            }
        }
        return self::form($ctx, $res, null, $values);
    }

    /** @param array<string, string> $p */
    public static function create(Request $request, array $p): Response
    {
        return self::save($request, $p, null);
    }

    /** @param array<string, string> $p */
    public static function editForm(Request $request, array $p): Response
    {
        $ctx = self::ctx($request);
        $res = self::resource($p, $ctx, true);
        $row = self::findRow($res, (int) $p['id'], $ctx, true);
        return self::form($ctx, $res, $row, $row);
    }

    /** @param array<string, string> $p */
    public static function edit(Request $request, array $p): Response
    {
        return self::save($request, $p, (int) $p['id']);
    }

    /** @param array<string, string> $p */
    public static function delete(Request $request, array $p): Response
    {
        $ctx = self::ctx($request);
        $res = self::resource($p, $ctx, true);
        Auth::checkCsrf($request);
        $row = self::findRow($res, (int) $p['id'], $ctx, true);
        $error = self::deleteRow($res, $row, $ctx);
        if ($error !== null) {
            Auth::flash('error', $error);
            return Response::redirect(Url::to("/admin/{$res['key']}/{$row['id']}"));
        }
        Auth::flash('success', $res['singular'] . ' excluído(a).');
        return Response::redirect(Url::to("/admin/{$res['key']}"));
    }

    /** @param array<string, string> $p */
    public static function action(Request $request, array $p): Response
    {
        $ctx = self::ctx($request);
        $res = self::resource($p, $ctx);
        Auth::checkCsrf($request);
        $key = $res['key'];
        $back = Response::redirect(Url::to("/admin/$key"));
        $name = (string) ($request->post['action'] ?? '');
        $ids = array_values(array_unique(array_map('intval', array_filter(
            (array) ($request->post['ids'] ?? []),
            static fn ($id) => is_string($id) && ctype_digit($id)
        ))));
        $actions = self::bulkActions($res, $ctx);
        if (!isset($actions[$name])) {
            throw new HttpError(403, 'Ação não permitida para o seu perfil.');
        }
        if ($ids === []) {
            Auth::flash('warning', 'Selecione ao menos um registro.');
            return $back;
        }
        $ids = array_values(array_filter($ids, static fn (int $id) => Resources::inScope($key, $id, $ctx, true)));

        if ($name === 'excluir') {
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
            Auth::flash($erros === [] ? 'success' : 'warning', "$ok registro(s) excluído(s)." . ($erros ? ' ' . implode(' ', array_unique($erros)) : ''));
            return $back;
        }

        Auth::flash('success', ($actions[$name]['handler'])($ids, $ctx));
        return $back;
    }

    // ------------------------------------------------------------------

    private static function ctx(Request $request): Ctx
    {
        $ctx = Auth::context($request);
        if ($ctx === null) {
            throw new LoginRequired();
        }
        return $ctx;
    }

    /**
     * @param array<string, string> $p
     * @return array<string, mixed>
     */
    private static function resource(array $p, Ctx $ctx, bool $write = false): array
    {
        $res = Resources::get($p['resource']) ?? throw new HttpError(404, 'Cadastro não encontrado.');
        if (!$ctx->canAccess($res['perm'])) {
            throw new HttpError(403, 'Seu perfil não tem acesso a este cadastro.');
        }
        if ($write && !self::writable($res, $ctx)) {
            throw new HttpError(403, 'Seu perfil não pode alterar este cadastro.');
        }
        return $res;
    }

    private static function writable(array $res, Ctx $ctx): bool
    {
        return empty($res['readonly']) && $ctx->canWrite($res['perm']);
    }

    /** @return array<string, array<string, mixed>> */
    private static function bulkActions(array $res, Ctx $ctx): array
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

    /** @return array<string, mixed> */
    private static function findRow(array $res, int $id, Ctx $ctx, bool $strict): array
    {
        [$where, $params] = Scope::where($res['key'], $ctx, $strict);
        $row = Db::one("SELECT t.* FROM `{$res['table']}` t WHERE t.id = ? AND $where", array_merge([$id], $params));
        if ($row === null) {
            throw new HttpError(404, 'Registro não encontrado ou fora do seu escopo.');
        }
        return $row;
    }

    /** @return array<string, int> filtros ?campo=id para colunas de chave estrangeira */
    private static function filters(array $res, Request $request): array
    {
        $filters = [];
        foreach ($res['fields'] as $name => $field) {
            $value = $request->query[$name] ?? null;
            if ($field['type'] === 'fk' && empty($field['virtual']) && is_string($value) && ctype_digit($value)) {
                $filters[$name] = (int) $value;
            }
        }
        return $filters;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<string> $columns
     * @return array<string, array<int, string>>
     */
    private static function fkTitles(array $res, array $rows, array $columns): array
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

    /** Links para cadastros que apontam para este registro (ex.: categorias de um questionário). */
    private static function related(array $res, array $row, Ctx $ctx): string
    {
        $links = '';
        foreach (Resources::all() as $childKey => $child) {
            if (!$ctx->canAccess($child['perm'])) {
                continue;
            }
            foreach ($child['fields'] as $name => $field) {
                if ($field['type'] !== 'fk' || ($field['ref'] ?? '') !== $res['key'] || !empty($field['virtual'])) {
                    continue;
                }
                [$where, $params] = Scope::where($childKey, $ctx);
                $count = (int) Db::value(
                    "SELECT COUNT(*) FROM `{$child['table']}` t WHERE t.`$name` = ? AND $where",
                    array_merge([$row['id']], $params)
                );
                $label = $child['plural'] . ($name === 'parent_id' ? ' (versões seguintes)' : '');
                $links .= '<li><a href="' . View::e(Url::to("/admin/$childKey", [$name => $row['id']])) . '">' . View::e($label) . '</a> <span class="badge">' . $count . '</span>';
                if (empty($child['readonly']) && $ctx->canWrite($child['perm']) && ($field['form'] ?? true) !== false) {
                    $links .= ' <a class="small" href="' . View::e(Url::to("/admin/$childKey/create", [$name => $row['id']])) . '">+ adicionar</a>';
                }
                $links .= '</li>';
            }
        }
        return $links === '' ? '' : '<div class="card"><h3>Relacionados</h3><ul class="related">' . $links . '</ul></div>';
    }

    /** @param array<string, string> $titles */
    private static function display(array $field, mixed $value, array $titles, Ctx $ctx, bool $list): string
    {
        $type = $field['type'];
        if ($type === 'bool') {
            return $value ? '<span class="badge badge-ok">Sim</span>' : '<span class="badge">Não</span>';
        }
        if ($value === null || $value === '') {
            return '<span class="muted">—</span>';
        }
        switch ($type) {
            case 'select':
                return View::e($field['options'][$value] ?? $value);
            case 'fk':
                $id = (int) $value;
                $label = View::e($titles[$id] ?? '#' . $id);
                $ref = Resources::get($field['ref']);
                return ($ref !== null && $ctx->canAccess($ref['perm']) && !$list)
                    ? '<a href="' . View::e(Url::to("/admin/{$field['ref']}/$id")) . '">' . $label . '</a>'
                    : $label;
            case 'date':
                return View::e(date('d/m/Y', (int) strtotime((string) $value)));
            case 'datetime':
                return View::e(date('d/m/Y H:i', (int) strtotime((string) $value)));
            case 'password':
                return '••••••';
            case 'color':
                return '<span class="swatch" style="background:' . View::e($value) . '"></span> ' . View::e($value);
            case 'json':
                return self::displayJson((string) $value, $list);
            case 'textarea':
                return $list ? View::e(Str::limit($value, 80)) : nl2br(View::e($value));
        }
        if (($field['display'] ?? '') === 'cpf') {
            return View::e(Str::cpf($value));
        }
        if ($list && ($field['display'] ?? '') === 'resumo') {
            return View::e(Str::limit($value, 80));
        }
        return View::e($value);
    }

    /** JSON de resultado: chaves são IDs de categoria. */
    private static function displayJson(string $json, bool $list): string
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return View::e($json);
        }
        if ($list) {
            return View::e(Str::limit($json, 60));
        }
        $titles = Resources::titles('categorias', array_map('intval', array_filter(array_keys($data), 'is_numeric')));
        $html = '<table class="mini"><tbody>';
        foreach ($data as $categoria => $valor) {
            if (is_array($valor)) {
                $valor = trim(($valor['rotulo'] ?? '') . (empty($valor['descricao']) ? '' : ' — ' . $valor['descricao']));
            }
            $html .= '<tr><th>' . View::e($titles[(int) $categoria] ?? 'Categoria #' . $categoria) . '</th><td>'
                . ($valor === null ? '<span class="muted">sem regra</span>' : View::e($valor)) . '</td></tr>';
        }
        return $html . '</tbody></table>';
    }

    /**
     * @param array<string, mixed>|null $row
     * @param array<string, mixed> $values
     */
    private static function form(Ctx $ctx, array $res, ?array $row, array $values, ?string $error = null): Response
    {
        $key = $res['key'];
        $action = $row === null ? Url::to("/admin/$key/create") : Url::to("/admin/$key/{$row['id']}/edit");
        $html = '<form class="card form" method="post" action="' . View::e($action) . '">' . View::csrfField();
        if ($error !== null) {
            $html .= '<div class="alert alert-error">' . View::e($error) . '</div>';
        }
        foreach (self::formFields($res, $ctx, $row) as $name => $field) {
            $value = $values[$name] ?? null;
            $required = !empty($field['required']);
            $label = View::e($field['label']) . ($required ? ' <span class="req">*</span>' : '');
            $attrs = ' name="' . View::e($name) . '" id="f_' . View::e($name) . '"' . ($required ? ' required' : '');
            if (isset($field['max'])) {
                $attrs .= ' maxlength="' . (int) $field['max'] . '"';
            }
            $input = match ($field['type']) {
                'textarea' => '<textarea rows="4"' . $attrs . '>' . View::e($value) . '</textarea>',
                'int' => '<input type="number" step="1"' . $attrs . ' value="' . View::e($value) . '">',
                'decimal' => '<input type="number" step="0.01"' . $attrs . ' value="' . View::e($value) . '">',
                'email' => '<input type="email"' . $attrs . ' value="' . View::e($value) . '">',
                'password' => '<input type="password" autocomplete="new-password"' . str_replace(' required', '', $attrs) . '>',
                'date' => '<input type="date"' . $attrs . ' value="' . View::e($value ? substr((string) $value, 0, 10) : '') . '">',
                'datetime' => '<input type="datetime-local"' . $attrs . ' value="' . View::e($value ? str_replace(' ', 'T', substr((string) $value, 0, 16)) : '') . '">',
                'bool' => '<input type="checkbox" value="1" name="' . View::e($name) . '" id="f_' . View::e($name) . '"' . ($value ? ' checked' : '') . '>',
                'select' => self::select($attrs, $field['options'], $value, $required),
                'fk' => self::select($attrs, self::fkOptions($field, $ctx, $row, $name), $value, $required),
                'color' => '<input type="text" placeholder="#14B8A6"' . $attrs . ' value="' . View::e($value) . '">',
                default => '<input type="text"' . $attrs . ' value="' . View::e($value) . '">',
            };
            $help = isset($field['help']) ? '<small>' . View::e($field['help']) . '</small>' : '';
            $html .= $field['type'] === 'bool'
                ? '<div class="field field-check">' . $input . '<label for="f_' . View::e($name) . '">' . $label . '</label>' . $help . '</div>'
                : '<div class="field"><label for="f_' . View::e($name) . '">' . $label . '</label>' . $input . $help . '</div>';
        }
        $cancel = $row === null ? Url::to("/admin/$key") : Url::to("/admin/$key/{$row['id']}");
        $html .= '<div class="form-actions"><button class="btn btn-primary" type="submit">Salvar</button> '
            . '<a class="btn" href="' . View::e($cancel) . '">Cancelar</a></div></form>';

        $title = $row === null ? 'Novo(a) ' . Str::lower($res['singular']) : 'Editar ' . Str::lower($res['singular']);
        return View::page($ctx, $title, $html, $key, '', $error === null ? 200 : 422);
    }

    /** @return array<string, array<string, mixed>> */
    private static function formFields(array $res, Ctx $ctx, ?array $row): array
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
    private static function fkOptions(array $field, Ctx $ctx, ?array $row, string $name): array
    {
        $options = Resources::options($field['ref'], $ctx, $field['strict'] ?? true);
        $current = $row[$name] ?? null;
        if ($current !== null && !isset($options[(int) $current])) {
            $options[(int) $current] = Resources::title($field['ref'], (int) $current);
        }
        return $options;
    }

    /** @param array<int|string, string> $options */
    private static function select(string $attrs, array $options, mixed $value, bool $required): string
    {
        $html = '<select' . $attrs . '><option value="">' . ($required ? 'Selecione…' : '—') . '</option>';
        foreach ($options as $optionValue => $label) {
            $selected = $value !== null && (string) $value === (string) $optionValue ? ' selected' : '';
            $html .= '<option value="' . View::e($optionValue) . '"' . $selected . '>' . View::e($label) . '</option>';
        }
        return $html . '</select>';
    }

    /** @param array<string, string> $p */
    private static function save(Request $request, array $p, ?int $id): Response
    {
        $ctx = self::ctx($request);
        $res = self::resource($p, $ctx, true);
        Auth::checkCsrf($request);
        $row = $id === null ? null : self::findRow($res, $id, $ctx, true);
        $post = $request->post;

        try {
            $data = self::parse($res, $ctx, $post, $row);
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
                ($res['validate'])($data, $row, $ctx, $post);
            }

            $columns = array_filter(
                $data,
                static fn ($name) => empty($res['fields'][$name]['virtual']),
                ARRAY_FILTER_USE_KEY
            );
            $savedId = Db::transaction(static function () use ($res, $row, $columns, $data, $ctx): int {
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
        } catch (FormError $error) {
            return self::form($ctx, $res, $row, $post + ($row ?? []), $error->getMessage());
        } catch (PDOException $error) {
            if (Db::isDuplicate($error)) {
                return self::form($ctx, $res, $row, $post + ($row ?? []), 'Já existe um registro com esses dados (valor que deve ser único está repetido).');
            }
            if (Db::isForeignKey($error)) {
                return self::form($ctx, $res, $row, $post + ($row ?? []), 'Algum dos registros escolhidos não existe mais.');
            }
            throw $error;
        }

        Auth::flash('success', $res['singular'] . ($row === null ? ' criado(a).' : ' atualizado(a).'));
        return Response::redirect(Url::to("/admin/{$res['key']}/$savedId"));
    }

    /**
     * Converte e valida o POST conforme os tipos dos campos.
     *
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private static function parse(array $res, Ctx $ctx, array $post, ?array $row): array
    {
        $data = [];
        foreach (self::formFields($res, $ctx, $row) as $name => $field) {
            $label = $field['label'];
            $raw = $post[$name] ?? null;
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
    private static function deleteRow(array $res, array $row, Ctx $ctx): ?string
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
}
