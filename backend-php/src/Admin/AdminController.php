<?php

declare(strict_types=1);

namespace Vise\Admin;

use Vise\Db;
use Vise\Http\HttpError;
use Vise\Http\Request;
use Vise\Http\Response;
use Vise\Http\Url;
use Vise\Services\ImportAlunosService;

final class AdminController
{
    public static function loginForm(Request $request): Response
    {
        if (Auth::context($request) !== null) {
            return Response::redirect(Url::to('/admin'));
        }
        return View::loginPage();
    }

    public static function login(Request $request): Response
    {
        Auth::start($request);
        Auth::checkCsrf($request);
        $email = trim((string) ($request->post['email'] ?? ''));
        $error = Auth::attempt($request, $email, (string) ($request->post['password'] ?? ''));
        if ($error !== null) {
            return View::loginPage($error, $email);
        }
        return Response::redirect(Url::to('/admin'));
    }

    public static function logout(Request $request): Response
    {
        Auth::logout($request);
        return Auth::redirectToLogin();
    }

    public static function dashboard(Request $request): Response
    {
        $ctx = Auth::context($request) ?? throw new LoginRequired();
        $cards = '';
        foreach (Resources::MENU as $group => $keys) {
            foreach ($keys as $key) {
                $res = Resources::get($key);
                if ($res === null || !$ctx->canAccess($res['perm'])) {
                    continue;
                }
                [$where, $params] = Scope::where($key, $ctx);
                $count = (int) Db::value("SELECT COUNT(*) FROM `{$res['table']}` t WHERE $where", $params);
                $cards .= '<a class="stat" href="' . View::e(Url::to("/admin/$key")) . '">'
                    . '<span class="stat-value">' . $count . '</span>'
                    . '<span class="stat-label">' . View::e($res['plural']) . '</span>'
                    . '<span class="stat-group">' . View::e($group) . '</span></a>';
            }
        }
        $html = '<div class="stats">' . $cards . '</div>';
        return View::page($ctx, 'Painel', $html, 'dashboard');
    }

    public static function importForm(Request $request): Response
    {
        return self::importPage(self::importCtx($request));
    }

    public static function import(Request $request): Response
    {
        $ctx = self::importCtx($request);
        Auth::checkCsrf($request);
        $file = $request->files['arquivo'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            return self::importPage($ctx, '<div class="alert alert-error">Selecione um arquivo CSV.</div>');
        }
        try {
            $resultado = ImportAlunosService::importar(
                (string) file_get_contents((string) $file['tmp_name']),
                $ctx->su ? null : $ctx->escolaIds
            );
        } catch (\RuntimeException $error) {
            return self::importPage($ctx, '<div class="alert alert-error">' . View::e($error->getMessage()) . '</div>');
        }
        $html = '<div class="alert alert-success">' . $resultado['success'] . ' aluno(s) importado(s).</div>';
        if ($resultado['errors'] !== []) {
            $html .= '<div class="alert alert-error"><ul>' . implode('', array_map(
                static fn (string $erro) => '<li>' . View::e($erro) . '</li>',
                $resultado['errors']
            )) . '</ul></div>';
        }
        return self::importPage($ctx, $html);
    }

    private static function importCtx(Request $request): Ctx
    {
        $ctx = Auth::context($request) ?? throw new LoginRequired();
        if (!$ctx->canWrite('aluno')) {
            throw new HttpError(403, 'Seu perfil não pode importar alunos.');
        }
        return $ctx;
    }

    private static function importPage(Ctx $ctx, string $mensagem = ''): Response
    {
        $html = $mensagem . '<form class="card form" method="post" enctype="multipart/form-data" action="' . View::e(Url::to('/admin/importar-alunos')) . '">'
            . View::csrfField()
            . '<p>Colunas obrigatórias:</p><p><code>' . View::e(implode(',', ImportAlunosService::COLUNAS)) . '</code></p>'
            . '<p><code>escola</code> aceita o <strong>nome</strong> ou o <strong>INEP</strong>. '
            . 'Delimitador <code>,</code> ou <code>;</code>. Datas em <code>AAAA-MM-DD</code> ou <code>DD/MM/AAAA</code>. '
            . 'A turma (<code>turma_nome</code>) precisa estar cadastrada na escola. Alunos já existentes (mesmo CPF na escola) são atualizados.</p>'
            . '<div class="field"><label for="arquivo">Arquivo CSV</label><input id="arquivo" type="file" name="arquivo" accept=".csv,text/csv" required></div>'
            . '<div class="form-actions"><button class="btn btn-primary" type="submit">Enviar CSV</button> '
            . '<a class="btn" href="' . View::e(Url::to('/admin/alunos')) . '">Voltar aos alunos</a></div></form>';
        return View::page($ctx, 'Importar alunos (CSV)', $html, 'importar-alunos');
    }
}
