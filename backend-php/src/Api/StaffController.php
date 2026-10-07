<?php

declare(strict_types=1);

namespace Vise\Api;

use Vise\Config;
use Vise\Db;
use Vise\Http\HttpError;
use Vise\Http\Input;
use Vise\Http\Request;
use Vise\Http\Response;
use Vise\Roles;
use Vise\Security\Jwt;
use Vise\Security\Password;
use Vise\Services\CodigoSala;
use Vise\Services\QuestionarioService;
use Vise\Support\Str;
use Vise\Support\Turmas;

/** API do app de equipe (admin da escola, pesquisador, professor). */
final class StaffController
{
    public static function login(Request $request): Response
    {
        $data = $request->json();
        $email = Str::lower(trim((string) Input::str($data, 'email', true, 3, 255)));
        $password = (string) Input::str($data, 'password', true, 1, 255);

        $user = Db::one('SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1', [$email]);
        if ($user === null || !Password::verify($password, (string) $user['password'])) {
            throw new HttpError(422, 'E-mail ou senha inválidos.');
        }
        if (!$user['is_superuser'] && self::vinculosAtivos((int) $user['id']) === []) {
            throw new HttpError(403, 'Usuário sem perfil de equipe ativo.');
        }
        return Response::json([
            'token' => Jwt::forUser((int) $user['id']),
            'user' => self::staffMe($user),
        ]);
    }

    public static function me(Request $request): Response
    {
        $user = Guard::user($request);
        if (!$user['is_superuser'] && self::vinculosAtivos((int) $user['id']) === []) {
            throw new HttpError(403, 'Sem perfil de equipe.');
        }
        return Response::json(self::staffMe($user));
    }

    public static function questionarios(Request $request): Response
    {
        $user = Guard::user($request);
        $escolaId = $request->queryInt('escola_id');
        $where = ["q.deleted_at IS NULL", "q.status = 'publicado'"];
        $params = [];
        if ($user['is_superuser']) {
            if ($escolaId !== null) {
                $where[] = 'q.escola_id = ?';
                $params[] = $escolaId;
            }
        } else {
            $vinculos = self::vinculosAtivos((int) $user['id']);
            $escolas = self::escolasNoEscopo($vinculos, $escolaId);
            $where[] = 'q.escola_id IN (' . Db::in($escolas) . ')';
            array_push($params, ...$escolas);
            $roles = array_values(array_unique(array_column($vinculos, 'role')));
            if ($roles === [Roles::PESQUISADOR]) {
                $where[] = '(q.pesquisador_id = ? OR q.compartilhado_na_escola = 1)';
                $params[] = $user['id'];
            }
        }
        $rows = Db::all(
            'SELECT q.id, q.nome, q.versao, q.status, q.escola_id FROM questionarios q
             WHERE ' . implode(' AND ', $where) . ' ORDER BY q.nome, q.id',
            $params
        );
        return Response::json(array_map(static fn (array $q) => [
            'id' => (int) $q['id'],
            'nome' => (string) $q['nome'],
            'versao' => (int) $q['versao'],
            'status' => (string) $q['status'],
            'escola_id' => (int) $q['escola_id'],
        ], $rows));
    }

    public static function turmas(Request $request): Response
    {
        $user = Guard::user($request);
        $escolaId = $request->queryInt('escola_id');
        $where = ['t.deleted_at IS NULL'];
        $params = [];
        if ($user['is_superuser']) {
            if ($escolaId !== null) {
                $where[] = 't.escola_id = ?';
                $params[] = $escolaId;
            }
        } else {
            $vinculos = self::vinculosAtivos((int) $user['id']);
            $escolas = self::escolasNoEscopo($vinculos, $escolaId);
            $where[] = 't.escola_id IN (' . Db::in($escolas) . ')';
            array_push($params, ...$escolas);
            if (self::professorSemAdmin($vinculos, $escolas)) {
                $turmas = self::turmasDoProfessor((int) $user['id']) ?: [-1];
                $where[] = 't.id IN (' . Db::in($turmas) . ')';
                array_push($params, ...$turmas);
            }
        }
        $rows = Db::all(
            'SELECT t.id, t.nome, t.turno, t.escola_id, s.descricao AS serie FROM turmas t
             LEFT JOIN series s ON s.id = t.serie_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY s.descricao, t.nome, t.id',
            $params
        );
        return Response::json(array_map(static fn (array $t) => [
            'id' => (int) $t['id'],
            'nome' => Turmas::rotulo($t['serie'], $t['nome']),
            'turma' => (string) $t['nome'],
            'turno' => (string) $t['turno'],
            'escola_id' => (int) $t['escola_id'],
            'serie' => $t['serie'],
        ], $rows));
    }

    public static function salas(Request $request): Response
    {
        $user = Guard::user($request);
        $escolaId = $request->queryInt('escola_id');
        $where = ['a.deleted_at IS NULL'];
        $params = [];
        if ($user['is_superuser']) {
            if ($escolaId !== null) {
                $where[] = 'a.escola_id = ?';
                $params[] = $escolaId;
            }
        } else {
            $vinculos = self::vinculosAtivos((int) $user['id']);
            $escolas = self::escolasNoEscopo($vinculos, $escolaId);
            $where[] = 'a.escola_id IN (' . Db::in($escolas) . ')';
            array_push($params, ...$escolas);
            if (self::professorSemAdmin($vinculos, $escolas)) {
                $turmas = self::turmasDoProfessor((int) $user['id']) ?: [-1];
                $where[] = "(a.alvo_tipo = 'escola' OR a.turma_id IN (" . Db::in($turmas) . '))';
                array_push($params, ...$turmas);
            }
        }
        $ids = array_map('intval', Db::column(
            'SELECT a.id FROM aplicacoes_questionario a WHERE ' . implode(' AND ', $where) . ' ORDER BY a.id DESC',
            $params
        ));
        $data = [];
        foreach ($ids as $id) {
            $data[] = self::salaItem($id);
        }
        return Response::json(['data' => $data]);
    }

    public static function criarSala(Request $request): Response
    {
        $user = Guard::user($request);
        $data = $request->json();
        $questionarioId = (int) Input::int($data, 'questionario_id');
        $escolaId = (int) Input::int($data, 'escola_id');
        $alvoTipo = $data['alvo_tipo'] ?? 'turma';
        if (!in_array($alvoTipo, ['escola', 'turma'], true)) {
            throw new HttpError(422, "Campo alvo_tipo deve ser 'escola' ou 'turma'.");
        }
        $turmaId = Input::int($data, 'turma_id', false);

        $roles = self::rolesNaEscola($user, $escolaId);
        if ($roles === []) {
            throw new HttpError(403, 'Escola fora do seu escopo.');
        }
        $adminEscola = in_array(Roles::ADMIN_ESCOLA, $roles, true);

        $questionario = Db::one('SELECT * FROM questionarios WHERE id = ?', [$questionarioId]);
        if (
            $questionario === null
            || $questionario['deleted_at'] !== null
            || $questionario['status'] !== 'publicado'
            || (int) $questionario['escola_id'] !== $escolaId
        ) {
            throw new HttpError(422, 'Questionário inválido.');
        }
        if (
            in_array(Roles::PESQUISADOR, $roles, true) && !$adminEscola
            && (int) $questionario['pesquisador_id'] !== (int) $user['id']
            && !$questionario['compartilhado_na_escola']
        ) {
            throw new HttpError(403, 'Você só pode liberar seus questionários ou os compartilhados.');
        }
        QuestionarioService::exigirPerguntas($questionarioId);

        $professorSemAdmin = in_array(Roles::PROFESSOR, $roles, true) && !$adminEscola;
        $turmaAlvo = null;
        if ($alvoTipo === 'turma') {
            if ($turmaId === null) {
                throw new HttpError(422, 'Informe a turma.');
            }
            $turma = Db::one('SELECT id, escola_id FROM turmas WHERE id = ?', [$turmaId]);
            if ($turma === null || (int) $turma['escola_id'] !== $escolaId) {
                throw new HttpError(422, 'Turma inválida.');
            }
            if ($professorSemAdmin && !in_array((int) $turma['id'], self::turmasDoProfessor((int) $user['id']), true)) {
                throw new HttpError(403, 'Turma fora do seu vínculo.');
            }
            $turmaAlvo = (int) $turma['id'];
        } elseif ($professorSemAdmin) {
            throw new HttpError(403, 'Professor só pode criar sala para as suas turmas.');
        }

        $now = Db::now();
        $id = Db::insert('aplicacoes_questionario', [
            'questionario_id' => $questionarioId,
            'escola_id' => $escolaId,
            'alvo_tipo' => $alvoTipo,
            'turma_id' => $turmaAlvo,
            'status' => 'ativa',
            'codigo_sala' => CodigoSala::gerar(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return Response::json(self::salaItem($id), 201);
    }

    /** @param array<string, string> $params */
    public static function encerrarSala(Request $request, array $params): Response
    {
        $user = Guard::user($request);
        $aplicacao = Db::one(
            'SELECT id, escola_id FROM aplicacoes_questionario WHERE id = ? AND deleted_at IS NULL',
            [(int) $params['id']]
        );
        if ($aplicacao === null) {
            throw new HttpError(404, 'Sala não encontrada.');
        }
        if (self::rolesNaEscola($user, (int) $aplicacao['escola_id']) === []) {
            throw new HttpError(403, 'Sem permissão.');
        }
        Db::update('aplicacoes_questionario', ['status' => 'encerrada'], (int) $aplicacao['id']);
        return Response::json(self::salaItem((int) $aplicacao['id']));
    }

    /** @return list<array<string, mixed>> vínculos ativos com papel de equipe (+ nome da escola) */
    private static function vinculosAtivos(int $userId): array
    {
        return Db::all(
            "SELECT eu.escola_id, eu.role, e.nome AS escola_nome FROM escola_user eu
             LEFT JOIN escolas e ON e.id = eu.escola_id
             WHERE eu.user_id = ? AND eu.status = 'ativo' AND eu.role IN (" . Db::in(Roles::STAFF) . ')
             ORDER BY eu.id',
            array_merge([$userId], Roles::STAFF)
        );
    }

    /** @param array<string, mixed> $user */
    private static function staffMe(array $user): array
    {
        $escolas = array_map(static fn (array $v) => [
            'id' => (int) $v['escola_id'],
            'nome' => (string) ($v['escola_nome'] ?? 'Escola #' . $v['escola_id']),
            'role' => (string) $v['role'],
            'role_label' => Roles::label((string) $v['role']),
        ], self::vinculosAtivos((int) $user['id']));
        if ($user['is_superuser'] && $escolas === []) {
            $escolas = array_map(static fn (array $e) => [
                'id' => (int) $e['id'],
                'nome' => (string) $e['nome'],
                'role' => 'superuser',
                'role_label' => 'Administrador geral',
            ], Db::all('SELECT id, nome FROM escolas WHERE deleted_at IS NULL ORDER BY id'));
        }
        return [
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'is_superuser' => (bool) $user['is_superuser'],
            'escolas' => $escolas,
        ];
    }

    /**
     * @param list<array<string, mixed>> $vinculos
     * @return list<int>
     */
    private static function escolasNoEscopo(array $vinculos, ?int $escolaId): array
    {
        $escolas = array_values(array_unique(array_map(static fn (array $v) => (int) $v['escola_id'], $vinculos)));
        if ($escolaId === null) {
            return $escolas;
        }
        if (!in_array($escolaId, $escolas, true)) {
            throw new HttpError(403, 'Escola fora do seu escopo.');
        }
        return [$escolaId];
    }

    /**
     * @param list<array<string, mixed>> $vinculos
     * @param list<int> $escolas
     */
    private static function professorSemAdmin(array $vinculos, array $escolas): bool
    {
        $roles = [];
        foreach ($vinculos as $vinculo) {
            if (in_array((int) $vinculo['escola_id'], $escolas, true)) {
                $roles[] = $vinculo['role'];
            }
        }
        return in_array(Roles::PROFESSOR, $roles, true) && !in_array(Roles::ADMIN_ESCOLA, $roles, true);
    }

    /**
     * @param array<string, mixed> $user
     * @return list<string>
     */
    private static function rolesNaEscola(array $user, int $escolaId): array
    {
        if ($user['is_superuser']) {
            return Roles::STAFF;
        }
        $roles = [];
        foreach (self::vinculosAtivos((int) $user['id']) as $vinculo) {
            if ((int) $vinculo['escola_id'] === $escolaId) {
                $roles[] = (string) $vinculo['role'];
            }
        }
        return array_values(array_unique($roles));
    }

    /** @return list<int> */
    private static function turmasDoProfessor(int $userId): array
    {
        return array_map('intval', Db::column('SELECT turma_id FROM professor_turma WHERE user_id = ?', [$userId]));
    }

    private static function salaItem(int $id): array
    {
        $a = Db::one(
            'SELECT a.*, q.nome AS questionario_nome, e.nome AS escola_nome, ' . Turmas::sql('t') . ' AS turma_nome
             FROM aplicacoes_questionario a
             LEFT JOIN questionarios q ON q.id = a.questionario_id
             LEFT JOIN escolas e ON e.id = a.escola_id
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE a.id = ?',
            [$id]
        );
        if ($a === null) {
            throw new HttpError(404, 'Sala não encontrada.');
        }
        $codigo = (string) ($a['codigo_sala'] ?? '');
        if ($codigo === '') {
            $codigo = CodigoSala::gerar();
            Db::update('aplicacoes_questionario', ['codigo_sala' => $codigo], $id);
        }
        return [
            'id' => (int) $a['id'],
            'codigo' => $codigo,
            'link' => rtrim(Config::get('PUBLIC_APP_URL', 'http://localhost:8081'), '/') . '/?sala=' . $codigo,
            'status' => (string) $a['status'],
            'alvo_tipo' => (string) $a['alvo_tipo'],
            'questionario_id' => (int) $a['questionario_id'],
            'questionario_nome' => (string) ($a['questionario_nome'] ?? '#' . $a['questionario_id']),
            'escola_id' => (int) $a['escola_id'],
            'escola_nome' => (string) ($a['escola_nome'] ?? '#' . $a['escola_id']),
            'turma_id' => $a['turma_id'] === null ? null : (int) $a['turma_id'],
            'turma_nome' => $a['turma_id'] === null ? null : $a['turma_nome'],
            'inicia_em' => Str::iso($a['inicia_em']),
            'termina_em' => Str::iso($a['termina_em']),
            'respondentes' => (int) Db::value(
                'SELECT COUNT(DISTINCT aluno_id) FROM respostas WHERE aplicacao_id = ?',
                [$id]
            ),
            'concluidos' => (int) Db::value('SELECT COUNT(id) FROM resultados WHERE aplicacao_id = ?', [$id]),
        ];
    }
}
