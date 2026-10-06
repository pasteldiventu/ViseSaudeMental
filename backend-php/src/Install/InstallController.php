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

    public static function legado(Request $request): Response
    {
        self::guard();
        if (!hash_equals(Config::get('INSTALL_KEY'), (string) ($request->post['key'] ?? ''))) {
            return self::page('<div class="alert alert-error">Chave de instalação incorreta.</div>', 403);
        }
        $file = $request->files['backup'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            return self::page('<div class="alert alert-error">Envie o arquivo .sql do backup (verifique o limite de upload do servidor).</div>', 422);
        }
        $aplicar = !empty($request->post['aplicar']);
        try {
            $res = ImportLegado::executar((string) file_get_contents((string) $file['tmp_name']), $aplicar, (string) ($request->post['pesquisador'] ?? ''));
        } catch (\Throwable $error) {
            return self::page('<div class="alert alert-error">' . View::e($error->getMessage()) . '</div>', 422);
        }
        $itens = '';
        foreach ($res['resumo'] as $item => $quantidade) {
            $itens .= '<li><strong>' . $quantidade . '</strong> ' . View::e($item) . '</li>';
        }
        $avisos = implode('', array_map(static fn (string $a) => '<li>' . View::e($a) . '</li>', $res['avisos']));
        $html = '<div class="alert ' . ($aplicar ? 'alert-success' : 'alert-warning') . '"><p><strong>'
            . ($aplicar ? 'Importação concluída.' : 'Simulação: nada foi gravado. Confira e envie de novo marcando "Gravar no banco".')
            . '</strong></p><ul>' . $itens . '</ul></div>'
            . ($avisos !== '' ? '<details class="card" style="margin-top:12px"><summary>Avisos (' . count($res['avisos']) . ')</summary><ul style="max-height:320px;overflow:auto">' . $avisos . '</ul></details>' : '');
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
        $body = '<div class="login-wrap" style="flex-wrap:wrap;gap:16px;align-items:flex-start"><form class="card login-card" method="post" action="' . View::e(Url::to('/install')) . '">'
            . '<h2>Instalação do Vise Saúde Mental</h2>' . $message
            . '<label>Chave de instalação (INSTALL_KEY)<input type="password" name="key" required></label>'
            . '<label>E-mail do administrador geral (opcional)<input type="email" name="admin_email"></label>'
            . '<label>Senha do administrador (mín. 8)<input type="password" name="admin_password" autocomplete="new-password"></label>'
            . '<label style="flex-direction:row;align-items:center;gap:8px"><input type="checkbox" name="seed" value="1"> Criar dados de demonstração</label>'
            . '<button class="btn btn-primary btn-block" type="submit">Instalar / atualizar banco</button>'
            . '</form>'
            . '<form class="card login-card" method="post" enctype="multipart/form-data" action="' . View::e(Url::to('/install/legado')) . '">'
            . '<h2>Importar backup do sistema anterior</h2>'
            . '<p class="muted">Escolas, turmas, alunos, questionários e respostas do arquivo .sql antigo. Rode primeiro sem marcar "Gravar" para ver o relatório. Pode repetir sem duplicar.</p>'
            . '<label>Chave de instalação (INSTALL_KEY)<input type="password" name="key" required></label>'
            . '<label>Arquivo .sql<input type="file" name="backup" accept=".sql,text/plain" required></label>'
            . '<label>E-mail do dono dos questionários (opcional; padrão: primeiro administrador geral)<input type="email" name="pesquisador"></label>'
            . '<label style="flex-direction:row;align-items:center;gap:8px"><input type="checkbox" name="aplicar" value="1"> Gravar no banco</label>'
            . '<button class="btn btn-primary btn-block" type="submit">Importar</button>'
            . '</form></div>';
        $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Instalação · VISE-MT</title><link rel="stylesheet" href="' . View::e(Url::to('/static/admin.css')) . '"></head><body>'
            . $body . '</body></html>';
        return Response::html($html, $status);
    }
}
