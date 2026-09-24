<?php

declare(strict_types=1);

namespace Vise\Install;

use Vise\Db;
use Vise\Security\Password;

final class Installer
{
    /**
     * Cria as tabelas que faltam e aplica ajustes em bancos criados pelo backend Python.
     *
     * @return list<string> log
     */
    public static function migrate(): array
    {
        $log = [];
        $sql = (string) file_get_contents(VISE_ROOT . '/database/schema.sql');
        $sql = (string) preg_replace('/^\s*--.*$/m', '', $sql);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            Db::pdo()->exec($statement);
        }
        $log[] = 'Tabelas verificadas/criadas.';

        if (!self::columnExists('aplicacoes_questionario', 'codigo_sala')) {
            Db::pdo()->exec('ALTER TABLE aplicacoes_questionario ADD COLUMN codigo_sala VARCHAR(12) NULL UNIQUE');
            $log[] = 'Coluna aplicacoes_questionario.codigo_sala criada.';
        }
        // O app envia a versão do termo como texto ("1.0"); o schema antigo usava INT.
        if (self::columnType('termos_aceite', 'versao') !== 'varchar') {
            Db::pdo()->exec('ALTER TABLE termos_aceite MODIFY versao VARCHAR(50) NOT NULL');
            $log[] = 'Coluna termos_aceite.versao convertida para VARCHAR(50).';
        }
        return $log;
    }

    /** Cria ou atualiza um administrador geral. */
    public static function upsertSuperuser(string $email, string $password, string $name = 'Administrador'): string
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('E-mail do administrador inválido.');
        }
        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('A senha do administrador deve ter pelo menos 8 caracteres.');
        }
        $id = Db::value('SELECT id FROM users WHERE email = ?', [$email]);
        if ($id === null) {
            $now = Db::now();
            Db::insert('users', [
                'name' => $name,
                'email' => $email,
                'password' => Password::hash($password),
                'is_superuser' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            return "Administrador geral $email criado.";
        }
        Db::update('users', ['password' => Password::hash($password), 'is_superuser' => 1, 'deleted_at' => null], (int) $id);
        return "Administrador geral $email atualizado.";
    }

    private static function columnExists(string $table, string $column): bool
    {
        return self::columnType($table, $column) !== null;
    }

    private static function columnType(string $table, string $column): ?string
    {
        $type = Db::value(
            'SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
        return $type === null ? null : strtolower((string) $type);
    }
}
