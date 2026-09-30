<?php

declare(strict_types=1);

namespace Vise;

use Vise\Admin\AdminController;
use Vise\Admin\CrudController;
use Vise\Admin\PainelController;
use Vise\Api\AlunoExtrasController;
use Vise\Api\AplicacoesController;
use Vise\Api\AuthController;
use Vise\Api\CadastrosController;
use Vise\Api\PainelApiController;
use Vise\Api\RespostasController;
use Vise\Api\StaffController;
use Vise\Http\HttpError;
use Vise\Http\Request;
use Vise\Http\Response;
use Vise\Http\Router;
use Vise\Http\Url;
use Vise\Install\InstallController;

final class App
{
    public static function router(): Router
    {
        $r = new Router();

        $r->get('/', static fn () => Response::redirect(Url::to('/admin')));
        $r->get('/up', static fn () => Response::json(['status' => 'ok']));

        $api = '/api/v1';
        $id = '{id:\d+}';
        $r->post("$api/login", [AuthController::class, 'login']);
        $r->post("$api/logout", [AuthController::class, 'logout']);
        $r->get("$api/me", [AuthController::class, 'me']);

        $r->post("$api/staff/login", [StaffController::class, 'login']);
        $r->get("$api/staff/me", [StaffController::class, 'me']);
        $r->get("$api/staff/questionarios", [StaffController::class, 'questionarios']);
        $r->get("$api/staff/turmas", [StaffController::class, 'turmas']);
        $r->get("$api/staff/salas", [StaffController::class, 'salas']);
        $r->post("$api/staff/salas", [StaffController::class, 'criarSala']);
        $r->post("$api/staff/salas/$id/encerrar", [StaffController::class, 'encerrarSala']);

        $cad = "$api/staff/cadastros/{resource:[a-z-]+}";
        $r->get("$api/staff/cadastros", [CadastrosController::class, 'menu']);
        $r->get($cad, [CadastrosController::class, 'index']);
        $r->post($cad, [CadastrosController::class, 'create']);
        $r->get("$cad/formulario", [CadastrosController::class, 'form']);
        $r->post("$cad/acoes/{action:[a-z-]+}", [CadastrosController::class, 'action']);
        $r->get("$cad/$id", [CadastrosController::class, 'show']);
        $r->post("$cad/$id", [CadastrosController::class, 'update']);
        $r->post("$cad/$id/excluir", [CadastrosController::class, 'delete']);

        $r->get("$api/staff/painel", [PainelApiController::class, 'painel']);
        $r->get("$api/staff/relatorios/opcoes", [PainelApiController::class, 'opcoes']);
        $r->get("$api/staff/relatorios/exportar", [PainelApiController::class, 'exportar']);
        $r->get("$api/staff/importacao", [PainelApiController::class, 'tipos']);
        $r->get("$api/staff/importacao/{tipo:[a-z]+}/modelo", [PainelApiController::class, 'modelo']);
        $r->post("$api/staff/importacao/{tipo:[a-z]+}", [PainelApiController::class, 'importar']);

        $r->get("$api/aplicacoes", [AplicacoesController::class, 'listar']);
        $r->get("$api/salas/{codigo}", [AplicacoesController::class, 'salaPublica']);
        $r->post("$api/aplicacoes/entrar-com-codigo", [AplicacoesController::class, 'entrarComCodigo']);
        $r->get("$api/aplicacoes/$id/categorias", [AplicacoesController::class, 'categorias']);
        $r->get("$api/aplicacoes/$id/categorias/{categoria:\d+}/perguntas", [AplicacoesController::class, 'perguntas']);
        $r->post("$api/aplicacoes/$id/respostas", [RespostasController::class, 'salvar']);
        $r->post("$api/aplicacoes/$id/respostas/lote", [RespostasController::class, 'lote']);
        $r->post("$api/aplicacoes/$id/finalizar", [RespostasController::class, 'finalizar']);

        $r->get("$api/avatar", [AlunoExtrasController::class, 'avatar']);
        $r->post("$api/termos", [AlunoExtrasController::class, 'aceitarTermo']);

        $r->get('/install', [InstallController::class, 'form']);
        $r->post('/install', [InstallController::class, 'run']);

        $r->get('/admin', [PainelController::class, 'dashboard']);
        $r->get('/admin/login', [AdminController::class, 'loginForm']);
        $r->post('/admin/login', [AdminController::class, 'login']);
        $r->get('/admin/logout', [AdminController::class, 'logout']);
        $r->get('/admin/relatorios', [PainelController::class, 'relatorios']);
        $r->get('/admin/relatorios/exportar', [PainelController::class, 'exportar']);
        $r->get('/admin/importar', [PainelController::class, 'importarForm']);
        $r->post('/admin/importar', [PainelController::class, 'importar']);
        $r->get('/admin/importar/modelo/{tipo:[a-z]+}', [PainelController::class, 'modelo']);
        $r->get('/admin/importar-alunos', [PainelController::class, 'importarAlunosForm']);
        $r->post('/admin/importar-alunos', [PainelController::class, 'importarAlunos']);
        $res = '{resource:[a-z-]+}';
        $r->get("/admin/$res", [CrudController::class, 'index']);
        $r->get("/admin/$res/exportar", [CrudController::class, 'export']);
        $r->get("/admin/$res/create", [CrudController::class, 'createForm']);
        $r->post("/admin/$res/create", [CrudController::class, 'create']);
        $r->post("/admin/$res/action", [CrudController::class, 'action']);
        $r->get("/admin/$res/$id", [CrudController::class, 'show']);
        $r->get("/admin/$res/$id/edit", [CrudController::class, 'editForm']);
        $r->post("/admin/$res/$id/edit", [CrudController::class, 'edit']);
        $r->post("/admin/$res/$id/delete", [CrudController::class, 'delete']);

        return $r;
    }

    public static function handle(Request $request): Response
    {
        $isApi = str_starts_with($request->path, '/api/') || $request->path === '/up';
        if ($isApi && $request->method === 'OPTIONS') {
            return self::withCors($request, new Response(204));
        }
        try {
            $response = self::router()->dispatch($request);
        } catch (Admin\LoginRequired) {
            $response = Admin\Auth::redirectToLogin();
        } catch (HttpError $error) {
            $response = str_starts_with($request->path, '/admin')
                ? Admin\View::errorPage($error->status, $error->getMessage())
                : Response::json(['detail' => $error->getMessage()], $error->status);
            foreach ($error->headers as $name => $value) {
                $response->withHeader($name, $value);
            }
        } catch (\Throwable $error) {
            error_log('[vise] ' . $error::class . ': ' . $error->getMessage() . "\n" . $error->getTraceAsString());
            $detail = Config::bool('APP_DEBUG') ? $error::class . ': ' . $error->getMessage() : 'Erro interno do servidor.';
            $response = str_starts_with($request->path, '/admin')
                ? Admin\View::errorPage(500, $detail)
                : Response::json(['detail' => $detail], 500);
        }
        return $isApi ? self::withCors($request, $response) : $response;
    }

    private static function withCors(Request $request, Response $response): Response
    {
        $origins = array_values(array_filter(array_map('trim', explode(',', Config::get('CORS_ORIGINS', '*')))));
        $origin = $request->header('origin');
        if ($origins === [] || in_array('*', $origins, true)) {
            $response->withHeader('Access-Control-Allow-Origin', '*');
        } elseif ($origin !== null && in_array($origin, $origins, true)) {
            $response->withHeader('Access-Control-Allow-Origin', $origin)
                ->withHeader('Access-Control-Allow-Credentials', 'true')
                ->withHeader('Vary', 'Origin');
        } else {
            return $response;
        }
        if ($request->method === 'OPTIONS') {
            $response->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                ->withHeader(
                    'Access-Control-Allow-Headers',
                    $request->header('access-control-request-headers') ?? 'Authorization, Content-Type, Accept, X-Auth-Token'
                )
                ->withHeader('Access-Control-Max-Age', '600');
        }
        return $response;
    }
}
