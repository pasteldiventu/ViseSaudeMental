<?php

declare(strict_types=1);

namespace Vise\Admin;

use Vise\Http\Request;
use Vise\Http\Response;
use Vise\Http\Url;

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

    /** Troca a escola em foco e volta para a lista/página de onde o usuário veio. */
    public static function foco(Request $request): Response
    {
        $ctx = Auth::context($request);
        if ($ctx === null) {
            return Auth::redirectToLogin();
        }
        $escola = (string) ($request->query['escola_id'] ?? '');
        Auth::focar($ctx, ctype_digit($escola) ? (int) $escola : null);
        $voltar = (string) ($request->query['voltar'] ?? '');
        $destino = match (true) {
            in_array($voltar, ['relatorios', 'importar'], true) => '/admin/' . $voltar,
            Resources::get($voltar) !== null => '/admin/' . $voltar,
            default => '/admin',
        };
        return Response::redirect(Url::to($destino));
    }

    public static function logout(Request $request): Response
    {
        Auth::logout($request);
        return Auth::redirectToLogin();
    }
}
