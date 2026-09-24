<?php

declare(strict_types=1);

namespace Vise\Install;

use Vise\Admin\View;
use Vise\Config;
use Vise\Http\HttpError;
use Vise\Http\Request;
use Vise\Http\Response;
use Vise\Http\Url;

/**
 * Instalação pelo navegador (hospedagens sem SSH). Só funciona com INSTALL_KEY definido no .env;
 * depois de instalar, apague o INSTALL_KEY para desativar esta página.
 */
final class InstallController
{
    public static function form(Request $request): Response
    {
        self::guard();
        return self::page();
    }

    public static function run(Request $request): Response
    {
        self::guard();
        $key = (string) ($request->post['key'] ?? '');
        if (!hash_equals(Config::get('INSTALL_KEY'), $key)) {
            return self::page('<div class="alert alert-error">Chave de instalação incorreta.</div>', 403);
        }
        try {
            $log = Installer::migrate();
            $email = trim((string) ($request->post['admin_email'] ?? ''));
            if ($email !== '') {
                $log[] = Installer::upsertSuperuser($email, (string) ($request->post['admin_password'] ?? ''), 'Administrador');
            }
            if (!empty($request->post['seed'])) {
                Seed::demo();
                $log[] = 'Dados de demonstração criados.';
            }
        } catch (\Throwable $error) {
            return self::page('<div class="alert alert-error">' . View::e($error->getMessage()) . '</div>', 500);
        }
        $html = '<div class="alert alert-success"><ul>' . implode('', array_map(
            static fn (string $line) => '<li>' . View::e($line) . '</li>',
            $log
        )) . '</ul></div><p>Pronto. <strong>Remova o INSTALL_KEY do .env</strong> e acesse o '
            . '<a href="' . View::e(Url::to('/admin')) . '">painel</a>.</p>';
        return self::page($html);
    }

    private static function guard(): void
    {
        if (Config::get('INSTALL_KEY') === '') {
            throw new HttpError(404, 'Not Found');
        }
    }

    private static function page(string $message = '', int $status = 200): Response
    {
        $body = '<div class="login-wrap"><form class="card login-card" method="post" action="' . View::e(Url::to('/install')) . '">'
            . '<h2>Instalação do Vise Saúde Mental</h2>' . $message
            . '<label>Chave de instalação (INSTALL_KEY)<input type="password" name="key" required></label>'
            . '<label>E-mail do administrador geral (opcional)<input type="email" name="admin_email"></label>'
            . '<label>Senha do administrador (mín. 8)<input type="password" name="admin_password" autocomplete="new-password"></label>'
            . '<label style="flex-direction:row;align-items:center;gap:8px"><input type="checkbox" name="seed" value="1"> Criar dados de demonstração</label>'
            . '<button class="btn btn-primary btn-block" type="submit">Instalar / atualizar banco</button>'
            . '</form></div>';
        $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Instalação · VISE-MT</title><link rel="stylesheet" href="' . View::e(Url::to('/static/admin.css')) . '"></head><body>'
            . $body . '</body></html>';
        return Response::html($html, $status);
    }
}
