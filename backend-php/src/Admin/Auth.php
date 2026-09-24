<?php

declare(strict_types=1);

namespace Vise\Admin;

use Vise\Db;
use Vise\Http\HttpError;
use Vise\Http\Request;
use Vise\Http\Response;
use Vise\Http\Url;
use Vise\Roles;
use Vise\Security\Password;
use Vise\Support\Str;

/** Sessão PHP do painel: login, contexto do usuário, CSRF e mensagens flash. */
final class Auth
{
    public static function start(Request $request): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('vise_admin');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => (Url::base() ?: '') . '/admin',
            'httponly' => true,
            'secure' => $request->https,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function context(Request $request): ?Ctx
    {
        self::start($request);
        $uid = (int) ($_SESSION['admin_user_id'] ?? 0);
        if ($uid <= 0) {
            return null;
        }
        $ctx = self::buildContext($uid);
        if ($ctx === null) {
            unset($_SESSION['admin_user_id']);
        }
        return $ctx;
    }

    public static function redirectToLogin(): Response
    {
        return Response::redirect(Url::to('/admin/login'));
    }

    /** Retorna mensagem de erro ou null em caso de sucesso. */
    public static function attempt(Request $request, string $email, string $password): ?string
    {
        self::start($request);
        $user = Db::one(
            'SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1',
            [Str::lower(trim($email))]
        );
        if ($user === null || !Password::verify($password, (string) $user['password'])) {
            return 'E-mail ou senha inválidos.';
        }
        if (self::buildContext((int) $user['id']) === null) {
            return 'Usuário sem vínculo ativo com nenhuma escola.';
        }
        session_regenerate_id(true);
        $_SESSION['admin_user_id'] = (int) $user['id'];
        return null;
    }

    public static function logout(Request $request): void
    {
        self::start($request);
        $_SESSION = [];
        session_destroy();
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['csrf'];
    }

    public static function checkCsrf(Request $request): void
    {
        $sent = (string) ($request->post['_csrf'] ?? '');
        if ($sent === '' || !hash_equals(self::csrfToken(), $sent)) {
            throw new HttpError(419, 'Sessão expirada. Recarregue a página e tente novamente.');
        }
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return list<array{type: string, message: string}> */
    public static function takeFlash(): array
    {
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flash;
    }

    private static function buildContext(int $uid): ?Ctx
    {
        $user = Db::one('SELECT * FROM users WHERE id = ? AND deleted_at IS NULL', [$uid]);
        if ($user === null) {
            return null;
        }
        $vinculos = Db::all(
            "SELECT escola_id, role FROM escola_user WHERE user_id = ? AND status = 'ativo'",
            [$uid]
        );
        if (!$user['is_superuser'] && $vinculos === []) {
            return null;
        }
        $roles = array_values(array_unique(array_filter(array_map('strval', array_column($vinculos, 'role')))));
        sort($roles);
        $turmas = in_array(Roles::PROFESSOR, $roles, true)
            ? array_map('intval', Db::column('SELECT turma_id FROM professor_turma WHERE user_id = ?', [$uid]))
            : [];
        return new Ctx(
            $uid,
            (string) $user['name'],
            (string) $user['email'],
            (bool) $user['is_superuser'],
            $roles,
            array_values(array_unique(array_map('intval', array_column($vinculos, 'escola_id')))),
            $turmas,
        );
    }
}
