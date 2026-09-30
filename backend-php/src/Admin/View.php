<?php

declare(strict_types=1);

namespace Vise\Admin;

use Vise\Http\Response;
use Vise\Http\Url;
use Vise\Roles;

final class View
{
    private const HEART = '<svg class="vise-heart" viewBox="0 0 32 32" width="%1$d" height="%1$d" aria-hidden="true"><defs><linearGradient id="viseHeartFill" x1="0" y1="0" x2="32" y2="0" gradientUnits="userSpaceOnUse"><stop offset="50%%" stop-color="#21a85a"/><stop offset="50%%" stop-color="#1e74c7"/></linearGradient></defs><path fill="url(#viseHeartFill)" d="M16 27.35c-.4 0-.78-.12-1.1-.36C9.7 23.2 5.2 19.4 3.85 16.05 2.7 13.2 3.15 9.7 5.7 8.05c1.55-1 3.55-1.05 5.2-.15 1.1.6 2 1.55 2.55 2.65.55-1.1 1.45-2.05 2.55-2.65 1.65-.9 3.65-.85 5.2.15 2.55 1.65 3 5.15 1.85 8-1.35 3.35-5.85 7.15-11.05 10.94-.32.24-.7.36-1.1.36z"/></svg>';

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::e(Auth::csrfToken()) . '">';
    }

    public static function page(Ctx $ctx, string $title, string $content, string $active = '', string $actions = '', int $status = 200): Response
    {
        $menu = '';
        foreach (Resources::MENU as $group => $keys) {
            $items = '';
            foreach ($keys as $key) {
                if ($key === '@relatorios') {
                    if ($ctx->canAccess('resultado')) {
                        $items .= self::menuItem(Url::to('/admin/relatorios'), 'Relatórios (Excel)', $active === 'relatorios');
                    }
                    continue;
                }
                if ($key === '@importar') {
                    if (\Vise\Services\ImportacaoService::tiposPermitidos($ctx) !== []) {
                        $items .= self::menuItem(Url::to('/admin/importar'), 'Importar planilha', $active === 'importar');
                    }
                    continue;
                }
                $res = Resources::get($key);
                if ($res !== null && $ctx->canAccess($res['perm'])) {
                    $items .= self::menuItem(Url::to('/admin/' . $key), $res['plural'], $active === $key);
                }
            }
            if ($items !== '') {
                $menu .= '<div class="menu-group"><div class="menu-title">' . self::e($group) . '</div>' . $items . '</div>';
            }
        }
        $papel = $ctx->su ? 'Administrador geral' : implode(', ', array_map([Roles::class, 'label'], $ctx->roles));

        $flash = '';
        foreach (Auth::takeFlash() as $item) {
            $flash .= '<div class="alert alert-' . self::e($item['type']) . '">' . self::e($item['message']) . '</div>';
        }

        $body = '<div class="layout">'
            . '<aside class="sidebar">'
            . '<a class="brand" href="' . self::e(Url::to('/admin')) . '">' . sprintf(self::HEART, 30) . '<span>VISE-MT</span></a>'
            . '<div class="user-box"><div class="user-name">' . self::e($ctx->name) . '</div>'
            . '<div class="user-email">' . self::e($ctx->email) . '</div>'
            . '<div class="user-role">' . self::e($papel) . '</div></div>'
            . '<nav>' . self::menuItem(Url::to('/admin'), 'Início', $active === 'dashboard') . $menu . '</nav>'
            . '<a class="btn btn-ghost logout" href="' . self::e(Url::to('/admin/logout')) . '">Sair</a>'
            . '</aside>'
            . '<main class="content">'
            . '<header class="page-header"><h1>' . self::e($title) . '</h1><div class="page-actions">' . $actions . '</div></header>'
            . $flash . $content
            . '</main></div>';

        return Response::html(self::document($title, $body), $status);
    }

    public static function loginPage(?string $error = null, string $email = ''): Response
    {
        $body = '<div class="login-wrap"><form class="card login-card" method="post" action="' . self::e(Url::to('/admin/login')) . '">'
            . self::csrfField()
            . '<div class="login-brand">' . sprintf(self::HEART, 34) . '<span>VISE-MT</span></div>'
            . '<h2>Entrar no painel</h2>'
            . ($error ? '<div class="alert alert-error">' . self::e($error) . '</div>' : '')
            . '<label>E-mail<input type="email" name="email" value="' . self::e($email) . '" required autofocus></label>'
            . '<label>Senha<input type="password" name="password" required></label>'
            . '<button class="btn btn-primary btn-block" type="submit">Entrar</button>'
            . '</form></div>';
        return Response::html(self::document('Entrar', $body), $error ? 422 : 200);
    }

    public static function errorPage(int $status, string $message): Response
    {
        $body = '<div class="login-wrap"><div class="card login-card">'
            . '<div class="login-brand">' . sprintf(self::HEART, 34) . '<span>VISE-MT</span></div>'
            . '<h2>Erro ' . $status . '</h2><p>' . self::e($message) . '</p>'
            . '<p><a class="btn btn-primary" href="' . self::e(Url::to('/admin')) . '">Voltar ao painel</a></p>'
            . '</div></div>';
        return Response::html(self::document('Erro ' . $status, $body), $status);
    }

    private static function menuItem(string $url, string $label, bool $active): string
    {
        return '<a class="menu-item' . ($active ? ' active' : '') . '" href="' . self::e($url) . '">' . self::e($label) . '</a>';
    }

    private static function document(string $title, string $body): string
    {
        return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . self::e($title) . ' · VISE-MT</title>'
            . '<link rel="stylesheet" href="' . self::e(Url::to('/static/admin.css')) . '?v=2">'
            . '</head><body>' . $body . '</body></html>';
    }
}
