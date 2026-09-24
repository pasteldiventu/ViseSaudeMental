<?php

declare(strict_types=1);

namespace Vise;

/** Papéis da equipe (staff). Aluno não usa este módulo. */
final class Roles
{
    public const ADMIN_ESCOLA = 'admin_escola';
    public const PESQUISADOR = 'pesquisador';
    public const PROFESSOR = 'professor';

    public const STAFF = [self::ADMIN_ESCOLA, self::PESQUISADOR, self::PROFESSOR];

    public const LABELS = [
        self::ADMIN_ESCOLA => 'Administrador da Escola',
        self::PESQUISADOR => 'Pesquisador',
        self::PROFESSOR => 'Professor',
    ];

    /** "write" inclui criar/editar/excluir; "read" só listagem/detalhe. */
    public const PERMISSIONS = [
        self::ADMIN_ESCOLA => [
            'escola' => 'read',
            'user' => 'write',
            'escola_user' => 'write',
            'professor_turma' => 'write',
            'serie' => 'write',
            'turma' => 'write',
            'aluno' => 'write',
            'questionario' => 'read',
            'aplicacao' => 'write',
            'resposta' => 'read',
            'resultado' => 'read',
            'avatar' => 'write',
            'termo' => 'read',
        ],
        self::PESQUISADOR => [
            'questionario' => 'write',
            'categoria' => 'write',
            'subcategoria' => 'write',
            'pergunta' => 'write',
            'opcao' => 'write',
            'regra' => 'write',
            'aplicacao' => 'write',
            'resposta' => 'read',
            'resultado' => 'read',
            'avatar' => 'read',
        ],
        self::PROFESSOR => [
            'turma' => 'read',
            'aluno' => 'read',
            'aplicacao' => 'read',
            'resposta' => 'read',
            'resultado' => 'read',
        ],
    ];

    public static function label(string $role): string
    {
        return self::LABELS[$role] ?? $role;
    }
}
