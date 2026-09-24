<?php

declare(strict_types=1);

namespace Vise\Admin;

use Vise\Roles;

/** Usuário logado no painel com papéis, escolas e turmas (recalculados a cada requisição). */
final class Ctx
{
    /**
     * @param list<string> $roles
     * @param list<int> $escolaIds
     * @param list<int> $turmaIds
     */
    public function __construct(
        public int $uid,
        public string $name,
        public string $email,
        public bool $su,
        public array $roles,
        public array $escolaIds,
        public array $turmaIds,
    ) {
    }

    public function canAccess(string $perm): bool
    {
        if ($this->su) {
            return true;
        }
        foreach ($this->roles as $role) {
            if (isset(Roles::PERMISSIONS[$role][$perm])) {
                return true;
            }
        }
        return false;
    }

    public function canWrite(string $perm): bool
    {
        if ($this->su) {
            return true;
        }
        foreach ($this->roles as $role) {
            if ((Roles::PERMISSIONS[$role][$perm] ?? null) === 'write') {
                return true;
            }
        }
        return false;
    }

    /** Pesquisador sem admin da escola: enxerga só os próprios questionários (+ compartilhados). */
    public function pesquisadorOnly(): bool
    {
        return !$this->su
            && in_array(Roles::PESQUISADOR, $this->roles, true)
            && !in_array(Roles::ADMIN_ESCOLA, $this->roles, true);
    }

    /** Professor sem admin da escola: enxerga só as próprias turmas. */
    public function professorOnly(): bool
    {
        return !$this->su
            && in_array(Roles::PROFESSOR, $this->roles, true)
            && !in_array(Roles::ADMIN_ESCOLA, $this->roles, true);
    }
}
