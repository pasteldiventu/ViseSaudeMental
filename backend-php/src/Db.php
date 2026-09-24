<?php

declare(strict_types=1);

namespace Vise;

use PDO;
use PDOException;
use PDOStatement;

final class Db
{
    private static ?PDO $pdo = null;

    /** Tabelas com coluna updated_at (atualizada pela aplicação). */
    private const TIMESTAMPED = [
        'users', 'escolas', 'escola_user', 'professor_turma', 'series', 'turmas',
        'alunos', 'questionarios', 'categorias', 'subcategorias', 'perguntas',
        'opcoes_resposta', 'regras_classificacao', 'aplicacoes_questionario',
        'respostas', 'resultados', 'avatars', 'personal_access_tokens',
    ];

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                Config::get('DB_HOST', 'localhost'),
                Config::int('DB_PORT', 3306),
                Config::get('DB_NAME', 'vise')
            );
            self::$pdo = new PDO($dsn, Config::get('DB_USER', 'root'), Config::get('DB_PASSWORD'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
            self::$pdo->exec("SET time_zone = '+00:00'");
        }
        return self::$pdo;
    }

    public static function disconnect(): void
    {
        self::$pdo = null;
    }

    /** @param list<mixed> $params */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute(array_map(
            static fn ($value) => is_bool($value) ? (int) $value : $value,
            array_values($params)
        ));
        return $stmt;
    }

    /** @return list<array<string, mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $value = self::run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /** @return list<mixed> */
    public static function column(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @param array<string, mixed> $data */
    public static function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`, `', $columns),
            implode(', ', array_fill(0, count($columns), '?'))
        );
        self::run($sql, array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public static function update(string $table, array $data, int $id): int
    {
        if (in_array($table, self::TIMESTAMPED, true) && !array_key_exists('updated_at', $data)) {
            $data['updated_at'] = self::now();
        }
        $sets = implode(', ', array_map(static fn ($column) => "`$column` = ?", array_keys($data)));
        $params = array_values($data);
        $params[] = $id;
        return self::run("UPDATE `$table` SET $sets WHERE id = ?", $params)->rowCount();
    }

    /**
     * Placeholders para cláusula IN. Lista vazia vira "NULL" (não casa nada).
     *
     * @param list<mixed> $values
     */
    public static function in(array $values): string
    {
        return $values === [] ? 'NULL' : implode(', ', array_fill(0, count($values), '?'));
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $callback();
            $pdo->commit();
            return $result;
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    public static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    public static function isDuplicate(PDOException $error): bool
    {
        return (int) ($error->errorInfo[1] ?? 0) === 1062;
    }

    public static function isForeignKey(PDOException $error): bool
    {
        return in_array((int) ($error->errorInfo[1] ?? 0), [1451, 1452], true);
    }
}
