<?php

declare(strict_types=1);

namespace Vise\Install;

use Vise\Db;
use Vise\Security\Password;
use Vise\Services\QuestionarioService;
use Vise\Support\Turmas;

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
        if (!self::columnExists('regras_classificacao', 'nivel')) {
            Db::pdo()->exec('ALTER TABLE regras_classificacao ADD COLUMN nivel VARCHAR(20) NULL AFTER descricao');
            $log[] = 'Coluna regras_classificacao.nivel criada.';
        }
        if (!self::indexExists('resultados', 'ix_resultados_created_at')) {
            Db::pdo()->exec('ALTER TABLE resultados ADD INDEX ix_resultados_created_at (created_at)');
            $log[] = 'Índice resultados.created_at criado.';
        }
        // O app envia a versão do termo como texto ("1.0"); o schema antigo usava INT.
        if (self::columnType('termos_aceite', 'versao') !== 'varchar') {
            Db::pdo()->exec('ALTER TABLE termos_aceite MODIFY versao VARCHAR(50) NOT NULL');
            $log[] = 'Coluna termos_aceite.versao convertida para VARCHAR(50).';
        }
        if (!self::indexExists('turmas', 'uq_turmas_escola_serie_nome')) {
            Db::pdo()->exec('ALTER TABLE turmas ADD UNIQUE KEY uq_turmas_escola_serie_nome (escola_id, serie_id, nome)');
            $log[] = 'Turmas agora são únicas por escola + série + turma.';
        }
        if (self::indexExists('turmas', 'uq_turmas_escola_nome')) {
            Db::pdo()->exec('ALTER TABLE turmas DROP INDEX uq_turmas_escola_nome');
        }
        $corrigidos = 0;
        foreach (Db::all('SELECT id FROM questionarios') as $q) {
            $corrigidos += QuestionarioService::propagarEscola((int) $q['id']);
        }
        if ($corrigidos > 0) {
            $log[] = "$corrigidos item(ns) de questionário (categorias, perguntas, opções, regras) passaram para a escola do seu questionário.";
        }
        $renomeadas = self::separarSerieDasTurmas();
        if ($renomeadas > 0) {
            $log[] = "$renomeadas turma(s) renomeada(s) para guardar só a identificação (ex.: \"9º Ano A\" → série 9º Ano, turma A).";
        }
        return $log;
    }

    /** Nomes antigos repetiam a série ("9º Ano A"); agora turmas.nome guarda só a identificação ("A"). */
    private static function separarSerieDasTurmas(): int
    {
        $total = 0;
        $turmas = Db::all('SELECT t.id, t.escola_id, t.serie_id, t.nome, s.descricao AS serie FROM turmas t JOIN series s ON s.id = t.serie_id');
        foreach ($turmas as $t) {
            $nome = Turmas::semSerie((string) $t['nome'], (string) $t['serie']);
            if ($nome === $t['nome']) {
                continue;
            }
            $ocupado = Db::value(
                'SELECT id FROM turmas WHERE escola_id = ? AND serie_id = ? AND nome = ? AND id <> ?',
                [$t['escola_id'], $t['serie_id'], $nome, $t['id']]
            );
            if ($ocupado === null) {
                Db::update('turmas', ['nome' => $nome], (int) $t['id']);
                $total++;
            }
        }
        return $total;
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

    private static function indexExists(string $table, string $index): bool
    {
        return Db::value(
            'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$table, $index]
        ) !== null;
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
