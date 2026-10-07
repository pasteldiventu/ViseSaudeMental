<?php

declare(strict_types=1);

namespace Vise\Admin;

use Vise\Http\Response;
use Vise\Http\Url;
use Vise\Roles;

final class View
{
    private const HEART = '<svg class="vise-heart" viewBox="0 0 32 32" width="%1$d" height="%1$d" aria-hidden="true"><defs><linearGradient id="viseHeartFill" x1="0" y1="0" x2="32" y2="0" gradientUnits="userSpaceOnUse"><stop offset="50%%" stop-color="#21a85a"/><stop offset="50%%" stop-color="#1e74c7"/></linearGradient></defs><path fill="url(#viseHeartFill)" d="M16 27.35c-.4 0-.78-.12-1.1-.36C9.7 23.2 5.2 19.4 3.85 16.05 2.7 13.2 3.15 9.7 5.7 8.05c1.55-1 3.55-1.05 5.2-.15 1.1.6 2 1.55 2.55 2.65.55-1.1 1.45-2.05 2.55-2.65 1.65-.9 3.65-.85 5.2.15 2.55 1.65 3 5.15 1.85 8-1.35 3.35-5.85 7.15-11.05 10.94-.32.24-.7.36-1.1.36z"/></svg>';

    /** Traços dos ícones (SVG 24×24, sem preenchimento). */
    private const ICONES = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
        'relatorios' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 17v-3M12 17v-6M15 17v-2"/>',
        'importar' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/>',
        'escolas' => '<path d="M3 21h18"/><path d="M5 21V9l7-5 7 5v12"/><path d="M10 21v-5h4v5"/>',
        'usuarios' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8"/><path d="M21.5 20a6.5 6.5 0 0 0-3.8-5.9"/>',
        'vinculos' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
        'professores-turmas' => '<path d="M3 7l9-4 9 4-9 4z"/><path d="M7 9v5c0 1.7 2.2 3 5 3s5-1.3 5-3V9"/>',
        'series' => '<path d="M12 3l9 5-9 5-9-5z"/><path d="M3 13l9 5 9-5"/>',
        'turmas' => '<rect x="3" y="4" width="18" height="14" rx="2"/><path d="M8 21h8M12 18v3"/>',
        'alunos' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'questionarios' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V3h6v1"/><path d="M9 11h6M9 15h4"/>',
        'categorias' => '<path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4z"/><circle cx="16.5" cy="16.5" r="3.5"/>',
        'subcategorias' => '<path d="M6 4v12a2 2 0 0 0 2 2h10"/><path d="M14 14l4 4-4 4"/>',
        'perguntas' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6v.6"/><path d="M12 17.5h.01"/>',
        'opcoes' => '<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1.2"/><circle cx="4.5" cy="12" r="1.2"/><circle cx="4.5" cy="18" r="1.2"/>',
        'regras' => '<path d="M4 6h9M17 6h3M4 12h3M11 12h9M4 18h11M19 18h1"/><circle cx="15" cy="6" r="2"/><circle cx="9" cy="12" r="2"/><circle cx="17" cy="18" r="2"/>',
        'aplicacoes' => '<circle cx="12" cy="12" r="9"/><path d="M10 8.5l5 3.5-5 3.5z"/>',
        'respostas' => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/>',
        'resultados' => '<path d="M3 12h4l3-8 4 16 3-8h4"/>',
        'termos' => '<path d="M12 3l7 3v6c0 4.4-3 7.8-7 9-4-1.2-7-4.6-7-9V6z"/><path d="M9 12l2 2 4-4"/>',
        'avatares' => '<circle cx="12" cy="12" r="9"/><path d="M8.5 14.5a4.5 4.5 0 0 0 7 0"/><path d="M9 9.5h.01M15 9.5h.01"/>',
        'sair' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'seta' => '<path d="M7 17L17 7"/><path d="M8 7h9v9"/>',
        'baixar' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/>',
        'mais' => '<path d="M12 5v14M5 12h14"/>',
        'filtro' => '<path d="M3 5h18l-7 8v6l-4 2v-8z"/>',
        'calendario' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'ponto' => '<circle cx="12" cy="12" r="3"/>',
    ];

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function icone(string $nome, int $tamanho = 18): string
    {
        return '<svg class="ico" viewBox="0 0 24 24" width="' . $tamanho . '" height="' . $tamanho . '" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . (self::ICONES[$nome] ?? self::ICONES['ponto']) . '</svg>';
    }

    /** Iniciais para o avatar (até duas letras). */
    public static function iniciais(string $nome): string
    {
        $partes = preg_split('/\s+/u', trim($nome)) ?: [];
        $letras = '';
        foreach ([reset($partes), count($partes) > 1 ? end($partes) : ''] as $parte) {
            if (is_string($parte) && $parte !== '') {
                $letras .= mb_strtoupper(mb_substr($parte, 0, 1));
            }
        }
        return $letras !== '' ? $letras : '?';
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::e(Auth::csrfToken()) . '">';
    }

    public static function page(Ctx $ctx, string $title, string $content, string $active = '', string $actions = '', int $status = 200, string $subtitle = ''): Response
    {
        $menu = '';
        foreach (Resources::MENU as $group => $keys) {
            $items = '';
            foreach ($keys as $key) {
                if ($key === '@relatorios') {
                    if ($ctx->canAccess('resultado')) {
                        $items .= self::menuItem(Url::to('/admin/relatorios'), 'Relatórios (Excel)', 'relatorios', $active === 'relatorios');
                    }
                    continue;
                }
                if ($key === '@importar') {
                    if (\Vise\Services\ImportacaoService::tiposPermitidos($ctx) !== []) {
                        $items .= self::menuItem(Url::to('/admin/importar'), 'Importar planilha', 'importar', $active === 'importar');
                    }
                    continue;
                }
                $res = Resources::get($key);
                if ($res !== null && $ctx->canAccess($res['perm'])) {
                    $items .= self::menuItem(Url::to('/admin/' . $key), $res['plural'], $key, $active === $key);
                }
            }
            if ($items !== '') {
                $menu .= '<div class="menu-group"><div class="menu-title">' . self::e($group) . '</div>' . $items . '</div>';
            }
        }
        $papel = $ctx->su ? 'Administrador geral' : implode(', ', array_map([Roles::class, 'label'], $ctx->roles));
        $primeiroNome = explode(' ', trim($ctx->name))[0] ?? '';

        $flash = '';
        foreach (Auth::takeFlash() as $item) {
            $flash .= '<div class="alert alert-' . self::e($item['type']) . '">' . self::e($item['message']) . '</div>';
        }

        $body = '<div class="layout">'
            . '<aside class="sidebar">'
            . '<a class="brand" href="' . self::e(Url::to('/admin')) . '">' . sprintf(self::HEART, 30) . '<span>VISE-MT</span></a>'
            . '<nav><div class="menu-title">Menu</div>' . self::menuItem(Url::to('/admin'), 'Início', 'dashboard', $active === 'dashboard') . $menu . '</nav>'
            . '<div class="sidebar-card"><strong>Dados sensíveis</strong><span>Use as informações só para o acolhimento dos alunos (LGPD).</span></div>'
            . '</aside>'
            . '<main class="content">'
            . '<div class="topbar"><div class="greeting"><strong>Olá, ' . self::e($primeiroNome) . '</strong><span>' . self::e(self::hoje()) . '</span></div>'
            . self::seletorFoco($ctx, $active)
            . '<div class="user-chip"><span class="avatar">' . self::e(self::iniciais($ctx->name)) . '</span>'
            . '<span class="user-info"><span class="user-name">' . self::e($ctx->name) . '</span><span class="user-role">' . self::e($papel) . '</span></span>'
            . '<a class="icon-btn" href="' . self::e(Url::to('/admin/logout')) . '" title="Sair" aria-label="Sair">' . self::icone('sair') . '</a></div></div>'
            . '<header class="page-header"><div><h1>' . self::e($title) . '</h1>'
            . ($subtitle !== '' ? '<p class="page-sub">' . self::e($subtitle) . '</p>' : '') . '</div>'
            . '<div class="page-actions">' . $actions . '</div></header>'
            . $flash . $content
            . '</main></div>';

        return Response::html(self::document($title, $body), $status);
    }

    /** Escola em foco: listas, formulários novos, painel e relatórios passam a mostrar só ela. */
    private static function seletorFoco(Ctx $ctx, string $active): string
    {
        $escolas = Auth::escolasParaFoco($ctx);
        if ($escolas === []) {
            return '';
        }
        $foco = Auth::escolaFoco($ctx);
        $html = '<form class="foco' . ($foco !== null ? ' ativo' : '') . '" method="get" action="' . self::e(Url::to('/admin/foco')) . '">'
            . '<input type="hidden" name="voltar" value="' . self::e($active) . '">'
            . '<label for="foco-escola">Escola em foco</label>'
            . '<select id="foco-escola" name="escola_id" onchange="this.form.submit()"><option value="0">Todas as escolas</option>';
        foreach ($escolas as $id => $nome) {
            $html .= '<option value="' . $id . '"' . ($id === $foco ? ' selected' : '') . '>' . self::e($nome) . '</option>';
        }
        return $html . '</select><noscript><button class="btn" type="submit">Ok</button></noscript></form>';
    }

    public static function loginPage(?string $error = null, string $email = ''): Response
    {
        $body = '<div class="login-wrap">'
            . '<section class="login-hero"><div class="login-brand">' . sprintf(self::HEART, 40) . '<span>VISE-MT</span></div>'
            . '<h1>Vigilância e monitoramento em saúde do escolar</h1>'
            . '<p>Acompanhe a participação, os níveis de atenção e gere relatórios das suas escolas e turmas.</p>'
            . '<ul><li>Painel com indicadores gerais</li><li>Relatórios em Excel com filtros</li><li>Importação de planilhas</li></ul></section>'
            . '<form class="card login-card" method="post" action="' . self::e(Url::to('/admin/login')) . '">'
            . self::csrfField()
            . '<h2>Entrar no painel</h2><p class="muted">Use o e-mail e a senha da equipe.</p>'
            . ($error ? '<div class="alert alert-error">' . self::e($error) . '</div>' : '')
            . '<label>E-mail<input type="email" name="email" value="' . self::e($email) . '" required autofocus></label>'
            . '<label>Senha<input type="password" name="password" required></label>'
            . '<button class="btn btn-primary btn-block" type="submit">Entrar</button>'
            . '</form></div>';
        return Response::html(self::document('Entrar', $body, 'login'), $error ? 422 : 200);
    }

    public static function errorPage(int $status, string $message): Response
    {
        $body = '<div class="error-wrap"><div class="card error-card">'
            . '<div class="login-brand dark">' . sprintf(self::HEART, 34) . '<span>VISE-MT</span></div>'
            . '<h2>Erro ' . $status . '</h2><p>' . self::e($message) . '</p>'
            . '<p><a class="btn btn-primary" href="' . self::e(Url::to('/admin')) . '">Voltar ao painel</a></p>'
            . '</div></div>';
        return Response::html(self::document('Erro ' . $status, $body), $status);
    }

    private static function menuItem(string $url, string $label, string $icone, bool $active): string
    {
        return '<a class="menu-item' . ($active ? ' active' : '') . '" href="' . self::e($url) . '">'
            . self::icone($icone) . '<span>' . self::e($label) . '</span></a>';
    }

    private static function hoje(): string
    {
        $dias = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
        $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        $d = new \DateTimeImmutable('now', \Vise\Support\Tempo::tz());
        return ucfirst($dias[(int) $d->format('w')]) . ', ' . $d->format('j') . ' de ' . $meses[(int) $d->format('n') - 1];
    }

    private static function document(string $title, string $body, string $classe = ''): string
    {
        return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . self::e($title) . ' · VISE-MT</title>'
            . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&display=swap">'
            . '<link rel="stylesheet" href="' . self::e(Url::to('/static/admin.css')) . '?v=3">'
            . '</head><body' . ($classe !== '' ? ' class="' . $classe . '"' : '') . '>' . $body . '</body></html>';
    }
}
