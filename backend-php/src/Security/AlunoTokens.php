<?php

declare(strict_types=1);

namespace Vise\Security;

use Vise\Db;

/** Token opaco do aluno: o app guarda o texto puro, o banco guarda só o SHA-256. */
final class AlunoTokens
{
    public static function create(int $alunoId, string $name = 'mobile'): string
    {
        $plain = rtrim(strtr(base64_encode(random_bytes(40)), '+/', '-_'), '=');
        Db::run(
            "DELETE FROM personal_access_tokens WHERE tokenable_type = 'aluno' AND tokenable_id = ?",
            [$alunoId]
        );
        $now = Db::now();
        Db::insert('personal_access_tokens', [
            'tokenable_type' => 'aluno',
            'tokenable_id' => $alunoId,
            'name' => $name,
            'token' => hash('sha256', $plain),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $plain;
    }

    /** @return array<string, mixed>|null aluno */
    public static function find(string $plain): ?array
    {
        $token = Db::one(
            "SELECT * FROM personal_access_tokens WHERE token = ? AND tokenable_type = 'aluno'",
            [hash('sha256', $plain)]
        );
        if ($token === null) {
            return null;
        }
        if ($token['expires_at'] !== null && $token['expires_at'] <= Db::now()) {
            Db::run('DELETE FROM personal_access_tokens WHERE id = ?', [$token['id']]);
            return null;
        }
        $aluno = Db::one(
            'SELECT * FROM alunos WHERE id = ? AND deleted_at IS NULL',
            [$token['tokenable_id']]
        );
        if ($aluno === null) {
            return null;
        }
        Db::run('UPDATE personal_access_tokens SET last_used_at = ? WHERE id = ?', [Db::now(), $token['id']]);
        return $aluno;
    }

    public static function revoke(string $plain): void
    {
        Db::run(
            "DELETE FROM personal_access_tokens WHERE token = ? AND tokenable_type = 'aluno'",
            [hash('sha256', $plain)]
        );
    }
}
