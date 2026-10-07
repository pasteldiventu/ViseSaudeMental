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

    /** Escola em foco na sessão (null = todas). Só existe para quem enxerga mais de uma escola. */
    public static function escolaFoco(Ctx $ctx): ?int
    {
        $id = (int) ($_SESSION['escola_foco'] ?? 0);
        if ($id <= 0 || !self::podeFocar($ctx, $id)) {
            unset($_SESSION['escola_foco']);
            return null;
        }
        return $id;
    }

    public static function focar(Ctx $ctx, ?int $escolaId): void
    {
        if ($escolaId !== null && $escolaId > 0 && self::podeFocar($ctx, $escolaId)) {
            $_SESSION['escola_foco'] = $escolaId;
        } else {
            unset($_SESSION['escola_foco']);
        }
    }

    /**
     * Aplica a escola em foco a uma query de filtros: ?escola_id=X passa a ser o foco, ?escola_id=0 (ou vazio)
     * limpa o foco e, sem o parâmetro, o foco vira o filtro padrão.
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public static function comFoco(Ctx $ctx, array $query): array
    {
        if (array_key_exists('escola_id', $query)) {
            $valor = $query['escola_id'];
            $id = is_string($valor) && ctype_digit($valor) ? (int) $valor : 0;
            self::focar($ctx, $id > 0 ? $id : null);
            if ($id <= 0) {
                unset($query['escola_id']);
            }
            return $query;
        }
        $foco = self::escolaFoco($ctx);
        if ($foco !== null) {
            $query['escola_id'] = (string) $foco;
        }
        return $query;
    }

    /** @return array<int, string> escolas que o usuário pode pôr em foco (vazio se só tiver uma) */
    public static function escolasParaFoco(Ctx $ctx): array
    {
        $escolas = Resources::options('escolas', $ctx);
        return count($escolas) > 1 ? $escolas : [];
    }

    private static function podeFocar(Ctx $ctx, int $escolaId): bool
    {
        if ($ctx->su) {
            return Db::value('SELECT COUNT(*) FROM escolas WHERE deleted_at IS NULL') > 1
                && Db::value('SELECT id FROM escolas WHERE id = ? AND deleted_at IS NULL', [$escolaId]) !== null;
        }
        return count($ctx->escolaIds) > 1 && in_array($escolaId, $ctx->escolaIds, true);
    }

    /** Contexto (papéis, escolas, turmas) de um usuário; null se não tiver acesso à gestão. */
    public static function buildContext(int $uid): ?Ctx
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
