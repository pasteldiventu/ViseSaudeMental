<?php

declare(strict_types=1);

namespace Vise\Admin;

use Vise\Http\HttpError;
use Vise\Http\Request;
use Vise\Http\Response;
use Vise\Http\Url;
use Vise\Support\Str;

final class CrudController
{
    /** @param array<string, string> $p */
    public static function index(Request $request, array $p): Response
    {
        $ctx = self::ctx($request);
        $res = self::resource($p, $ctx);
        $key = $res['key'];

        $found = Records::search($res, $ctx, $request->query);
        ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'filters' => $filters, 'q' => $q, 'sort' => $sort, 'dir' => $dir] = $found;
        $titles = Records::fkTitles($res, $rows, $res['list']);
        $writable = Records::writable($res, $ctx);
        $actions = Records::actions($res, $ctx);
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
        $row = Records::findRow($res, (int) $p['id'], $ctx, false);
        $titles = Records::fkTitles($res, [$row], array_keys($res['fields']));

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
        if (Records::writable($res, $ctx) && Resources::inScope($key, (int) $row['id'], $ctx, true)) {
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
        $row = Records::findRow($res, (int) $p['id'], $ctx, true);
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
        $row = Records::findRow($res, (int) $p['id'], $ctx, true);
        $error = Records::deleteRow($res, $row, $ctx);
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
        $back = Response::redirect(Url::to("/admin/{$res['key']}"));
        $name = (string) ($request->post['action'] ?? '');
        $ids = array_values(array_map('intval', array_filter(
            (array) ($request->post['ids'] ?? []),
            static fn ($id) => is_string($id) && ctype_digit($id)
        )));
        if (!isset(Records::actions($res, $ctx)[$name])) {
            throw new HttpError(403, 'Ação não permitida para o seu perfil.');
        }
        if ($ids === []) {
            Auth::flash('warning', 'Selecione ao menos um registro.');
            return $back;
        }
        try {
            ['message' => $message, 'ok' => $ok] = Records::runAction($res, $ctx, $name, $ids);
        } catch (HttpError $error) {
            if ($error->status !== 422) {
                throw $error;
            }
            Auth::flash('warning', $error->getMessage());
            return $back;
        }
        Auth::flash($ok ? 'success' : 'warning', $message);
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
        return Records::resource($p['resource'], $ctx, $write);
    }

    /** Links para cadastros que apontam para este registro (ex.: categorias de um questionário). */
    private static function related(array $res, array $row, Ctx $ctx): string
    {
        $links = '';
        foreach (Records::related($res, $row, $ctx) as $item) {
            $links .= '<li><a href="' . View::e(Url::to("/admin/{$item['key']}", [$item['field'] => $row['id']])) . '">' . View::e($item['label']) . '</a> <span class="badge">' . $item['count'] . '</span>';
            if ($item['can_add']) {
                $links .= ' <a class="small" href="' . View::e(Url::to("/admin/{$item['key']}/create", [$item['field'] => $row['id']])) . '">+ adicionar</a>';
            }
            $links .= '</li>';
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
        $pairs = $list ? null : Records::jsonPairs($json);
        if ($pairs === null) {
            return View::e($list ? Str::limit($json, 60) : $json);
        }
        $html = '<table class="mini"><tbody>';
        foreach ($pairs as $categoria => $valor) {
            $html .= '<tr><th>' . View::e($categoria) . '</th><td>'
                . ($valor === 'sem regra' ? '<span class="muted">sem regra</span>' : View::e($valor)) . '</td></tr>';
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
        foreach (Records::formFields($res, $ctx, $row) as $name => $field) {
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
                'fk' => self::select($attrs, Records::fkOptions($field, $ctx, $row, $name), $value, $required),
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
        $row = $id === null ? null : Records::findRow($res, $id, $ctx, true);
        $post = $request->post;

        try {
            $savedId = Records::persist($res, $ctx, $post, $row);
        } catch (FormError $error) {
            return self::form($ctx, $res, $row, $post + ($row ?? []), $error->getMessage());
        }

        Auth::flash('success', $res['singular'] . ($row === null ? ' criado(a).' : ' atualizado(a).'));
        return Response::redirect(Url::to("/admin/{$res['key']}/$savedId"));
    }
}
