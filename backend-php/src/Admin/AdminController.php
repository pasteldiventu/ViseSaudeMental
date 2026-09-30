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

    public static function logout(Request $request): Response
    {
        Auth::logout($request);
        return Auth::redirectToLogin();
    }
}
