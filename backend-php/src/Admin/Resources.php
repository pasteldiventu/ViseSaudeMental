<?php

declare(strict_types=1);

namespace Vise\Admin;

use Vise\Db;
use Vise\Http\HttpError;
use Vise\Roles;
use Vise\Security\Password;
use Vise\Services\CodigoSala;
use Vise\Services\QuestionarioService;
use Vise\Support\Str;
use Vise\Support\Turmas;

/**
 * Definição declarativa dos cadastros do painel.
 *
 * Campo: label, type (text|email|textarea|int|decimal|bool|select|fk|date|datetime|password|json|color),
 * required, max, options (select), ref + strict (fk), form (false = só leitura), detail,
 * default, display (cpf|resumo), su_only, virtual (não é coluna), create_only, help, upper.
 */
final class Resources
{
    public const STATUS_VINCULO = ['ativo' => 'Ativo', 'inativo' => 'Inativo'];
    public const STATUS_QUESTIONARIO = ['rascunho' => 'Rascunho', 'publicado' => 'Publicado'];
    public const STATUS_APLICACAO = ['ativa' => 'Ativa', 'encerrada' => 'Encerrada'];
    public const ALVO_TIPO = ['escola' => 'Toda a escola', 'turma' => 'Turma', 'aluno' => 'Aluno'];
    public const TIPOS_PERGUNTA = ['multipla_escolha' => 'Múltipla escolha', 'texto' => 'Texto livre'];
    public const TURNOS = ['matutino' => 'Matutino', 'vespertino' => 'Vespertino', 'noturno' => 'Noturno', 'integral' => 'Integral'];
    public const NIVEIS = ['baixo' => 'Adequado (sem sinal de risco)', 'moderado' => 'Atenção (acompanhar)', 'alto' => 'Prioritário (intervir)'];

    public const MENU = [
        'Relatórios e dados' => ['@relatorios', '@importar'],
        'Escolas e equipe' => ['escolas', 'usuarios', 'vinculos', 'professores-turmas'],
        'Turmas e alunos' => ['series', 'turmas', 'alunos'],
        'Instrumentos' => ['questionarios', 'categorias', 'subcategorias', 'perguntas', 'opcoes', 'regras'],
        'Aplicação e dados' => ['aplicacoes', 'respostas', 'resultados', 'termos', 'avatares'],
    ];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $cache = null;

    /** @return array<string, mixed>|null */
    public static function get(string $key): ?array
    {
        $all = self::all();
        return isset($all[$key]) ? $all[$key] + ['key' => $key] : null;
    }

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return self::$cache ??= self::build();
    }

    /**
     * Rótulos de registros relacionados (para colunas de chave estrangeira).
     *
     * @param list<int> $ids
     * @return array<int, string>
     */
    public static function titles(string $key, array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn ($id) => $id !== null)));
        $res = self::get($key);
        if ($res === null || $ids === []) {
            return [];
        }
        $rows = Db::all(
            "SELECT t.id, {$res['title']} AS titulo FROM `{$res['table']}` t {$res['join']} WHERE t.id IN (" . Db::in($ids) . ')',
            $ids
        );
        $titles = [];
        foreach ($rows as $row) {
            $titles[(int) $row['id']] = (string) $row['titulo'];
        }
        return $titles;
    }

    public static function title(string $key, int $id): string
    {
        return self::titles($key, [$id])[$id] ?? '#' . $id;
    }

    /**
     * Opções de um select de chave estrangeira, já filtradas pelo escopo do usuário.
     *
     * @return array<int, string>
     */
    public static function options(string $key, Ctx $ctx, bool $strict = true): array
    {
        $res = self::get($key);
        if ($res === null) {
            return [];
        }
        [$where, $params] = Scope::where($key, $ctx, $strict);
        $rows = Db::all(
            "SELECT t.id, {$res['title']} AS titulo FROM `{$res['table']}` t {$res['join']} WHERE $where ORDER BY titulo LIMIT 2000",
            $params
        );
        $options = [];
        foreach ($rows as $row) {
            $options[(int) $row['id']] = (string) $row['titulo'];
        }
        return $options;
    }

    public static function inScope(string $key, int $id, Ctx $ctx, bool $strict = true): bool
    {
        $res = self::get($key);
        if ($res === null) {
            return false;
        }
        [$where, $params] = Scope::where($key, $ctx, $strict);
        return Db::value("SELECT t.id FROM `{$res['table']}` t WHERE t.id = ? AND $where", array_merge([$id], $params)) !== null;
    }

    /** @return array<string, array<string, mixed>> */
    private static function build(): array
    {
        $ordem = ['label' => 'Ordem', 'type' => 'int', 'required' => true, 'default' => 1];

        return [
            'escolas' => [
                'table' => 'escolas',
                'perm' => 'escola',
                'singular' => 'Escola',
                'plural' => 'Escolas',
                'title' => "CONCAT(t.nome, IF(t.inep IS NULL OR t.inep = '', '', CONCAT(' · INEP ', t.inep)))",
                'join' => '',
                'list' => ['nome', 'municipio', 'uf', 'inep', 'responsavel', 'ativo'],
                'search' => ['nome', 'inep', 'municipio'],
                'order' => 't.nome ASC',
                'cascade' => [['escola_user', 'escola_id']],
                'fields' => [
                    'nome' => ['label' => 'Nome', 'type' => 'text', 'required' => true, 'max' => 255],
                    'municipio' => ['label' => 'Município', 'type' => 'text', 'required' => true, 'max' => 255],
                    'uf' => ['label' => 'UF', 'type' => 'text', 'required' => true, 'max' => 2, 'upper' => true],
                    'inep' => ['label' => 'INEP', 'type' => 'text', 'max' => 20],
                    'responsavel' => ['label' => 'Responsável', 'type' => 'text', 'max' => 255],
                    'telefone' => ['label' => 'Telefone', 'type' => 'text', 'max' => 30],
                    'endereco' => ['label' => 'Endereço', 'type' => 'text', 'max' => 500],
                    'ativo' => ['label' => 'Ativa', 'type' => 'bool', 'default' => 1],
                ],
            ],

            'usuarios' => [
                'table' => 'users',
                'perm' => 'user',
                'singular' => 'Usuário',
                'plural' => 'Usuários',
                'title' => "CONCAT(t.name, ' (', t.email, ')')",
                'join' => '',
                'list' => ['name', 'email', 'is_superuser'],
                'search' => ['name', 'email'],
                'order' => 't.name ASC',
                'cascade' => [['escola_user', 'user_id'], ['professor_turma', 'user_id']],
                'fields' => [
                    'name' => ['label' => 'Nome', 'type' => 'text', 'required' => true, 'max' => 255],
                    'email' => ['label' => 'E-mail', 'type' => 'email', 'required' => true, 'max' => 255],
                    'password' => ['label' => 'Senha', 'type' => 'password', 'help' => 'Na edição, deixe em branco para manter a senha atual.'],
                    'is_superuser' => ['label' => 'Admin geral', 'type' => 'bool', 'su_only' => true, 'default' => 0],
                    'vinculo_escola_id' => [
                        'label' => 'Vincular à escola', 'type' => 'fk', 'ref' => 'escolas',
                        'virtual' => true, 'create_only' => true,
                        'help' => 'Opcional para o admin geral. Cria o vínculo junto com o usuário.',
                    ],
                    'vinculo_role' => [
                        'label' => 'Perfil na escola', 'type' => 'select', 'options' => Roles::LABELS,
                        'virtual' => true, 'create_only' => true,
                    ],
                    'escola_id' => [
                        'label' => 'Escola', 'type' => 'fk', 'ref' => 'escolas', 'virtual' => true, 'form' => false,
                        'filter' => "EXISTS (SELECT 1 FROM escola_user f_eu WHERE f_eu.user_id = t.id AND f_eu.escola_id = ? AND f_eu.status = 'ativo')",
                    ],
                ],
                'validate' => static function (array &$data, ?array $row, Ctx $ctx, array $input): void {
                    $data['email'] = Str::lower((string) $data['email']);
                    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                        throw new FormError('E-mail inválido.');
                    }
                    if (!$ctx->su && $row !== null && $row['is_superuser']) {
                        throw new FormError('Não é possível editar o admin geral.');
                    }
                    if ($row === null && empty($data['password'])) {
                        throw new FormError('Informe a senha do novo usuário.');
                    }
                    if (!empty($data['password']) && !Password::isHash((string) $data['password'])) {
                        $data['password'] = Password::hash((string) $data['password']);
                    }
                    if ($row === null) {
                        $escola = $data['vinculo_escola_id'] ?? null;
                        $role = $data['vinculo_role'] ?? null;
                        if (!$ctx->su && ($escola === null || $role === null)) {
                            throw new FormError('Informe a escola e o perfil do novo usuário.');
                        }
                        if (($escola === null) !== ($role === null)) {
                            throw new FormError('Para criar o vínculo, informe a escola e o perfil.');
                        }
                    }
                },
                'after_save' => static function (int $id, array $data, bool $created): void {
                    if ($created && !empty($data['vinculo_escola_id']) && !empty($data['vinculo_role'])) {
                        $now = Db::now();
                        Db::insert('escola_user', [
                            'user_id' => $id,
                            'escola_id' => $data['vinculo_escola_id'],
                            'role' => $data['vinculo_role'],
                            'status' => 'ativo',
                            'vinculado_em' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                },
                'before_delete' => static function (array $row, Ctx $ctx): void {
                    if ((int) $row['id'] === $ctx->uid) {
                        throw new FormError('Você não pode excluir o próprio usuário.');
                    }
                    if (!$ctx->su && $row['is_superuser']) {
                        throw new FormError('Não é possível excluir o admin geral.');
                    }
                },
            ],

            'vinculos' => [
                'table' => 'escola_user',
                'perm' => 'escola_user',
                'singular' => 'Vínculo',
                'plural' => 'Vínculos de escola',
                'title' => "CONCAT(COALESCE(u.name, CONCAT('user #', t.user_id)), ' — ', COALESCE(e.nome, CONCAT('escola #', t.escola_id)), ' (', t.role, ')')",
                'join' => 'LEFT JOIN users u ON u.id = t.user_id LEFT JOIN escolas e ON e.id = t.escola_id',
                'list' => ['user_id', 'escola_id', 'role', 'status'],
                'search' => [],
                'fields' => [
                    'user_id' => ['label' => 'Usuário', 'type' => 'fk', 'ref' => 'usuarios', 'required' => true],
                    'escola_id' => ['label' => 'Escola', 'type' => 'fk', 'ref' => 'escolas', 'required' => true],
                    'role' => ['label' => 'Perfil', 'type' => 'select', 'options' => Roles::LABELS, 'required' => true],
                    'status' => ['label' => 'Status', 'type' => 'select', 'options' => self::STATUS_VINCULO, 'required' => true, 'default' => 'ativo'],
                    'vinculado_em' => ['label' => 'Vinculado em', 'type' => 'datetime', 'form' => false],
                ],
                'defaults' => static fn () => ['vinculado_em' => Db::now()],
            ],

            'professores-turmas' => [
                'table' => 'professor_turma',
                'perm' => 'professor_turma',
                'singular' => 'Professor × turma',
                'plural' => 'Professores e turmas',
                'title' => "CONCAT(COALESCE(u.name, '?'), ' → ', COALESCE(CONCAT_WS(' ', s.descricao, tu.nome), '?'))",
                'join' => 'LEFT JOIN users u ON u.id = t.user_id LEFT JOIN turmas tu ON tu.id = t.turma_id LEFT JOIN series s ON s.id = tu.serie_id',
                'list' => ['user_id', 'turma_id'],
                'search' => [],
                'fields' => [
                    'user_id' => ['label' => 'Professor', 'type' => 'fk', 'ref' => 'usuarios', 'required' => true],
                    'turma_id' => ['label' => 'Turma', 'type' => 'fk', 'ref' => 'turmas', 'required' => true],
                    'escola_id' => [
                        'label' => 'Escola', 'type' => 'fk', 'ref' => 'escolas', 'virtual' => true, 'form' => false,
                        'filter' => 'EXISTS (SELECT 1 FROM turmas f_t WHERE f_t.id = t.turma_id AND f_t.escola_id = ?)',
                    ],
                ],
            ],

            'series' => [
                'table' => 'series',
                'perm' => 'serie',
                'singular' => 'Série',
                'plural' => 'Séries',
                'title' => 't.descricao',
                'join' => '',
                'list' => ['descricao'],
                'search' => ['descricao'],
                'order' => 't.descricao ASC',
                'fields' => [
                    'descricao' => ['label' => 'Descrição', 'type' => 'text', 'required' => true, 'max' => 255],
                ],
            ],

            'turmas' => [
                'table' => 'turmas',
                'perm' => 'turma',
                'singular' => 'Turma',
                'plural' => 'Turmas',
                'title' => "CONCAT(COALESCE(e.nome, ''), ' · ', CONCAT_WS(' ', s.descricao, t.nome), ' (', t.turno, ')')",
                'join' => 'LEFT JOIN escolas e ON e.id = t.escola_id LEFT JOIN series s ON s.id = t.serie_id',
                'list' => ['serie_id', 'nome', 'turno', 'escola_id'],
                'search' => ['nome'],
                'order' => 't.serie_id ASC, t.nome ASC',
                'cascade' => [['professor_turma', 'turma_id']],
                'fields' => [
                    'escola_id' => ['label' => 'Escola', 'type' => 'fk', 'ref' => 'escolas', 'required' => true],
                    'serie_id' => ['label' => 'Série', 'type' => 'fk', 'ref' => 'series', 'required' => true, 'help' => 'Ex.: 6º Ano. Cadastre novas séries em Turmas e alunos › Séries.'],
                    'nome' => ['label' => 'Turma', 'type' => 'text', 'required' => true, 'max' => 255, 'help' => 'Só a identificação da turma, ex.: A, B ou 601. A série já vem do campo acima.'],
                    'turno' => ['label' => 'Turno', 'type' => 'select', 'options' => self::TURNOS, 'required' => true],
                ],
                'validate' => static function (array &$data, ?array $row): void {
                    $serie = Db::value('SELECT descricao FROM series WHERE id = ?', [$data['serie_id']]);
                    $data['nome'] = Turmas::semSerie((string) $data['nome'], $serie === null ? null : (string) $serie);
                    $repetida = Db::value(
                        'SELECT id FROM turmas WHERE escola_id = ? AND serie_id = ? AND nome = ? AND id <> ?',
                        [$data['escola_id'], $data['serie_id'], $data['nome'], $row['id'] ?? 0]
                    );
                    if ($repetida !== null) {
                        throw new FormError('Já existe a turma ' . Turmas::rotulo((string) $serie, (string) $data['nome']) . ' nesta escola.');
                    }
                },
            ],

            'alunos' => [
                'table' => 'alunos',
                'perm' => 'aluno',
                'singular' => 'Aluno',
                'plural' => 'Alunos',
                'title' => "CONCAT(t.nome, ' (CPF ', CONCAT_WS('.', SUBSTRING(t.cpf, 1, 3), SUBSTRING(t.cpf, 4, 3), SUBSTRING(t.cpf, 7, 3)), '-', SUBSTRING(t.cpf, 10, 2), ')')",
                'join' => '',
                'list' => ['nome', 'cpf', 'data_nascimento', 'turma_id', 'escola_id', 'matricula'],
                'search' => ['nome', 'cpf', 'matricula'],
                'order' => 't.nome ASC',
                'cascade' => [['termos_aceite', 'aluno_id'], ['personal_access_tokens', 'tokenable_id', "tokenable_type = 'aluno'"]],
                'fields' => [
                    'nome' => ['label' => 'Nome', 'type' => 'text', 'required' => true, 'max' => 255],
                    'cpf' => ['label' => 'CPF', 'type' => 'text', 'required' => true, 'max' => 14, 'display' => 'cpf'],
                    'data_nascimento' => ['label' => 'Nascimento', 'type' => 'date', 'required' => true],
                    'sexo' => ['label' => 'Sexo', 'type' => 'text', 'max' => 30],
                    'matricula' => ['label' => 'Matrícula', 'type' => 'text', 'max' => 100],
                    'escola_id' => ['label' => 'Escola', 'type' => 'fk', 'ref' => 'escolas', 'required' => true],
                    'turma_id' => ['label' => 'Turma', 'type' => 'fk', 'ref' => 'turmas'],
                    'telefone' => ['label' => 'Telefone', 'type' => 'text', 'max' => 30],
                    'responsavel' => ['label' => 'Responsável', 'type' => 'text', 'max' => 255],
                    'contato_responsavel' => ['label' => 'Contato do responsável', 'type' => 'text', 'max' => 255],
                ],
                'validate' => static function (array &$data): void {
                    $data['cpf'] = Str::digits($data['cpf']);
                    if (strlen($data['cpf']) !== 11) {
                        throw new FormError('CPF deve conter 11 dígitos.');
                    }
                    self::exigirMesmaEscola('turmas', $data['turma_id'] ?? null, (int) $data['escola_id'], 'A turma não pertence à escola escolhida.');
                },
            ],

            'questionarios' => [
                'table' => 'questionarios',
                'perm' => 'questionario',
                'singular' => 'Questionário',
                'plural' => 'Questionários',
                'title' => "CONCAT(t.nome, ' (v', t.versao, ' · ', t.status, ')')",
                'join' => '',
                'list' => ['nome', 'status', 'versao', 'pesquisador_id', 'escola_id', 'compartilhado_na_escola'],
                'search' => ['nome'],
                'fields' => [
                    'nome' => ['label' => 'Nome', 'type' => 'text', 'required' => true, 'max' => 255],
                    'descricao' => ['label' => 'Descrição', 'type' => 'textarea'],
                    'publico_alvo' => ['label' => 'Público-alvo', 'type' => 'text', 'max' => 255],
                    'escola_id' => ['label' => 'Escola', 'type' => 'fk', 'ref' => 'escolas', 'required' => true],
                    'pesquisador_id' => ['label' => 'Pesquisador', 'type' => 'fk', 'ref' => 'usuarios', 'help' => 'Em branco = você.'],
                    'status' => ['label' => 'Status', 'type' => 'select', 'options' => self::STATUS_QUESTIONARIO, 'required' => true, 'default' => 'rascunho'],
                    'versao' => ['label' => 'Versão', 'type' => 'int', 'required' => true, 'default' => 1],
                    'compartilhado_na_escola' => ['label' => 'Compartilhado na escola', 'type' => 'bool', 'default' => 0],
                    'parent_id' => ['label' => 'Versão anterior', 'type' => 'fk', 'ref' => 'questionarios', 'form' => false],
                ],
                'validate' => static function (array &$data, ?array $row, Ctx $ctx): void {
                    if ($ctx->pesquisadorOnly()) {
                        $data['pesquisador_id'] = $row === null ? $ctx->uid : (int) $row['pesquisador_id'];
                    } elseif (empty($data['pesquisador_id'])) {
                        $data['pesquisador_id'] = $row['pesquisador_id'] ?? $ctx->uid;
                    }
                },
                'after_save' => static function (int $id, array $data, bool $created): void {
                    if (!$created) {
                        QuestionarioService::propagarEscola($id);
                    }
                },
                'actions' => [
                    'publicar' => [
                        'label' => 'Publicar',
                        'confirm' => 'Publicar os questionários em rascunho selecionados?',
                        'handler' => static function (array $ids): string {
                            $ok = 0;
                            foreach ($ids as $id) {
                                try {
                                    QuestionarioService::publicar($id);
                                    $ok++;
                                } catch (HttpError) {
                                }
                            }
                            return "$ok questionário(s) publicado(s). Apenas rascunhos podem ser publicados.";
                        },
                    ],
                    'nova-versao' => [
                        'label' => 'Nova versão',
                        'confirm' => 'Criar rascunho de nova versão a partir dos publicados?',
                        'handler' => static function (array $ids): string {
                            $ok = 0;
                            foreach ($ids as $id) {
                                try {
                                    QuestionarioService::criarNovaVersao($id);
                                    $ok++;
                                } catch (HttpError) {
                                }
                            }
                            return "$ok nova(s) versão(ões) criada(s) como rascunho. Apenas publicados podem gerar nova versão.";
                        },
                    ],
                ],
            ],

            'categorias' => [
                'table' => 'categorias',
                'perm' => 'categoria',
                'singular' => 'Categoria',
                'plural' => 'Categorias',
                'title' => "CONCAT(COALESCE(q.nome, ''), ' · ', t.nome)",
                'join' => 'LEFT JOIN questionarios q ON q.id = t.questionario_id',
                'list' => ['nome', 'questionario_id', 'ordem', 'cor'],
                'search' => ['nome'],
                'derive_escola' => [['questionario_id', 'questionarios']],
                'fields' => [
                    'questionario_id' => ['label' => 'Questionário', 'type' => 'fk', 'ref' => 'questionarios', 'required' => true],
                    'nome' => ['label' => 'Nome', 'type' => 'text', 'required' => true, 'max' => 255],
                    'ordem' => $ordem,
                    'cor' => ['label' => 'Cor', 'type' => 'color', 'max' => 30],
                    'mensagem_avatar' => ['label' => 'Mensagem do avatar', 'type' => 'textarea'],
                    'imagem_apoio' => ['label' => 'Imagem de apoio (URL)', 'type' => 'text', 'max' => 500],
                    'escola_id' => ['label' => 'Escola', 'type' => 'fk', 'ref' => 'escolas', 'form' => false],
                ],
            ],

            'subcategorias' => [
                'table' => 'subcategorias',
                'perm' => 'subcategoria',
                'singular' => 'Subcategoria',
                'plural' => 'Subcategorias',
                'title' => "CONCAT(COALESCE(c.nome, ''), ' › ', t.nome)",
                'join' => 'LEFT JOIN categorias c ON c.id = t.categoria_id',
                'list' => ['nome', 'categoria_id', 'ordem'],
                'search' => ['nome'],
                'derive_escola' => [['categoria_id', 'categorias']],
                'fields' => [
                    'categoria_id' => ['label' => 'Categoria', 'type' => 'fk', 'ref' => 'categorias', 'required' => true],
                    'nome' => ['label' => 'Nome', 'type' => 'text', 'required' => true, 'max' => 255],
                    'ordem' => $ordem,
                    'escola_id' => ['label' => 'Escola', 'type' => 'fk', 'ref' => 'escolas', 'form' => false],
                ],
            ],

            'perguntas' => [
                'table' => 'perguntas',
                'perm' => 'pergunta',
                'singular' => 'Pergunta',
                'plural' => 'Perguntas',
                'title' => "CONCAT(COALESCE(c.nome, ''), ' · ', LEFT(t.texto, 70))",
                'join' => 'LEFT JOIN categorias c ON c.id = t.categoria_id',
                'list' => ['texto', 'tipo', 'categoria_id', 'ordem', 'obrigatoria'],
                'search' => ['texto'],
                'order' => 't.categoria_id ASC, t.ordem ASC, t.id ASC',
                'related_order' => 10,
                'derive_escola' => [['questionario_id', 'questionarios'], ['categoria_id', 'categorias']],
                'fields' => [
                    'questionario_id' => [
                        'label' => 'Questionário', 'type' => 'fk', 'ref' => 'questionarios', 'virtual' => true, 'create_only' => true,
                        'filter' => 'EXISTS (SELECT 1 FROM categorias f_c WHERE f_c.id = t.categoria_id AND f_c.questionario_id = ?)',
                        'help' => 'A pergunta entra neste questionário.',
                    ],
                    'categoria_id' => [
                        'label' => 'Categoria', 'type' => 'fk', 'ref' => 'categorias',
                        'help' => 'Opcional: em branco, entra na primeira categoria do questionário (ou numa categoria "Geral", criada automaticamente).',
                    ],
                    'subcategoria_id' => ['label' => 'Subcategoria', 'type' => 'fk', 'ref' => 'subcategorias'],
                    'tipo' => ['label' => 'Tipo', 'type' => 'select', 'options' => self::TIPOS_PERGUNTA, 'required' => true, 'default' => 'multipla_escolha'],
                    'texto' => ['label' => 'Pergunta', 'type' => 'textarea', 'required' => true, 'display' => 'resumo'],
                    'opcoes_texto' => [
                        'label' => 'Opções de resposta', 'type' => 'textarea', 'virtual' => true, 'create_only' => true,
                        'default' => "Nunca = 0\nÀs vezes = 1\nFrequentemente = 2\nSempre = 3",
                        'help' => 'Para múltipla escolha: uma opção por linha, no formato "Texto = pontos". Depois dá para editar em Opções de resposta.',
                    ],
                    'ordem' => ['label' => 'Ordem', 'type' => 'int', 'help' => 'Em branco = no fim da categoria.'],
                    'obrigatoria' => ['label' => 'Obrigatória', 'type' => 'bool', 'default' => 1],
                    'peso' => ['label' => 'Peso', 'type' => 'decimal', 'required' => true, 'default' => '1.00'],
                    'imagem' => ['label' => 'Imagem (URL)', 'type' => 'text', 'max' => 500],
                    'escola_id' => ['label' => 'Escola', 'type' => 'fk', 'ref' => 'escolas', 'form' => false],
                ],
                'validate' => static function (array &$data, ?array $row, Ctx $ctx, array $input): void {
                    $questionario = !empty($data['questionario_id']) ? (int) $data['questionario_id'] : null;
                    if (empty($data['categoria_id'])) {
                        if ($questionario === null) {
                            if ($row !== null) {
                                throw new FormError('Informe a categoria.');
                            }
                            throw new FormError('Escolha o questionário (ou a categoria) da pergunta.');
                        }
                        $data['categoria_id'] = QuestionarioService::categoriaPadrao($questionario);
                    } elseif ($questionario !== null && (int) Db::value('SELECT questionario_id FROM categorias WHERE id = ?', [$data['categoria_id']]) !== $questionario) {
                        throw new FormError('A categoria escolhida não pertence a este questionário.');
                    }
                    $data['escola_id'] = (int) Db::value('SELECT escola_id FROM categorias WHERE id = ?', [$data['categoria_id']]);
                    if (!empty($data['subcategoria_id'])) {
                        $categoria = Db::value('SELECT categoria_id FROM subcategorias WHERE id = ?', [$data['subcategoria_id']]);
                        if ((int) $categoria !== (int) $data['categoria_id']) {
                            throw new FormError('A subcategoria não pertence à categoria escolhida.');
                        }
                    }
                    if ($data['ordem'] === null) {
                        $data['ordem'] = $row !== null && (int) $row['categoria_id'] === (int) $data['categoria_id']
                            ? (int) $row['ordem']
                            : (int) Db::value('SELECT COALESCE(MAX(ordem), 0) + 1 FROM perguntas WHERE categoria_id = ?', [$data['categoria_id']]);
                    }
                    $comOpcoes = $row === null && $data['tipo'] === 'multipla_escolha' && array_key_exists('opcoes_texto', $input);
                    if ($comOpcoes && count(self::opcoesDoTexto((string) ($data['opcoes_texto'] ?? ''))) < 2) {
                        throw new FormError('Informe ao menos duas opções de resposta, uma por linha (ex.: "Nunca = 0").');
                    }
                },
                'after_save' => static function (int $id, array $data, bool $created): void {
                    if (!$created || $data['tipo'] !== 'multipla_escolha') {
                        return;
                    }
                    $now = Db::now();
                    foreach (self::opcoesDoTexto((string) ($data['opcoes_texto'] ?? '')) as $i => [$descricao, $pontos]) {
                        Db::insert('opcoes_resposta', [
                            'pergunta_id' => $id, 'escola_id' => $data['escola_id'], 'descricao' => $descricao,
                            'pontuacao' => $pontos, 'ordem' => $i + 1, 'created_at' => $now, 'updated_at' => $now,
                        ]);
                    }
                },
            ],

            'opcoes' => [
                'table' => 'opcoes_resposta',
                'perm' => 'opcao',
                'singular' => 'Opção de resposta',
                'plural' => 'Opções de resposta',
                'title' => "CONCAT(COALESCE(CONCAT(t.emoji, ' '), ''), t.descricao)",
                'join' => '',
                'list' => ['descricao', 'pergunta_id', 'pontuacao', 'ordem', 'emoji'],
                'search' => ['descricao'],
                'derive_escola' => [['pergunta_id', 'perguntas']],
                'fields' => [
                    'pergunta_id' => ['label' => 'Pergunta', 'type' => 'fk', 'ref' => 'perguntas', 'required' => true],
                    'descricao' => ['label' => 'Descrição', 'type' => 'text', 'required' => true, 'max' => 500],
                    'pontuacao' => ['label' => 'Pontuação', 'type' => 'int', 'required' => true, 'default' => 0],
                    'ordem' => $ordem,
                    'emoji' => ['label' => 'Emoji', 'type' => 'text', 'max' => 50],
                    'cor' => ['label' => 'Cor', 'type' => 'color', 'max' => 30],
                    'escola_id' => ['label' => 'Escola', 'type' => 'fk', 'ref' => 'escolas', 'form' => false],
                ],
            ],

            'regras' => [
                'table' => 'regras_classificacao',
                'perm' => 'regra',
                'singular' => 'Regra de classificação',
                'plural' => 'Regras de classificação',
                'title' => "CONCAT(t.rotulo, ' (', t.min_score, '–', t.max_score, ')')",
                'join' => '',
                'list' => ['rotulo', 'nivel', 'categoria_id', 'questionario_id', 'min_score', 'max_score'],
                'search' => ['rotulo'],
                'derive_escola' => [['categoria_id', 'categorias'], ['questionario_id', 'questionarios']],
                'fields' => [
                    'categoria_id' => ['label' => 'Categoria', 'type' => 'fk', 'ref' => 'categorias', 'help' => 'O resultado do aluno é classificado por categoria.'],
                    'questionario_id' => ['label' => 'Questionário', 'type' => 'fk', 'ref' => 'questionarios'],
                    'min_score' => ['label' => 'Pontuação mín.', 'type' => 'decimal', 'required' => true],
                    'max_score' => ['label' => 'Pontuação máx.', 'type' => 'decimal', 'required' => true],
                    'rotulo' => ['label' => 'Rótulo', 'type' => 'text', 'required' => true, 'max' => 255],
                    'nivel' => [
                        'label' => 'Nível de atenção', 'type' => 'select', 'options' => self::NIVEIS,
                        'help' => 'Usado no painel e nos relatórios para destacar quem precisa de acompanhamento.',
                    ],
                    'descricao' => ['label' => 'Descrição', 'type' => 'textarea'],
                    'escola_id' => ['label' => 'Escola', 'type' => 'fk', 'ref' => 'escolas', 'form' => false],
                ],
                'validate' => static function (array &$data): void {
                    if ((float) $data['min_score'] > (float) $data['max_score']) {
                        throw new FormError('A pontuação mínima não pode ser maior que a máxima.');
                    }
                },
            ],

            'aplicacoes' => [
                'table' => 'aplicacoes_questionario',
                'perm' => 'aplicacao',
                'singular' => 'Aplicação',
                'plural' => 'Aplicações',
                'title' => "CONCAT(COALESCE(q.nome, CONCAT('Aplicação #', t.id)), ' · ', t.alvo_tipo, ' · ', t.status, IF(t.codigo_sala IS NULL, '', CONCAT(' · ', t.codigo_sala)))",
                'join' => 'LEFT JOIN questionarios q ON q.id = t.questionario_id',
                'list' => ['questionario_id', 'escola_id', 'alvo_tipo', 'turma_id', 'aluno_id', 'status', 'codigo_sala', 'inicia_em'],
                'search' => ['codigo_sala'],
                'fields' => [
                    'questionario_id' => ['label' => 'Questionário', 'type' => 'fk', 'ref' => 'questionarios', 'required' => true, 'strict' => false],
                    'escola_id' => ['label' => 'Escola', 'type' => 'fk', 'ref' => 'escolas', 'required' => true],
                    'alvo_tipo' => ['label' => 'Alvo', 'type' => 'select', 'options' => self::ALVO_TIPO, 'required' => true, 'default' => 'escola'],
                    'turma_id' => ['label' => 'Turma', 'type' => 'fk', 'ref' => 'turmas', 'help' => 'Obrigatória quando o alvo é Turma.'],
                    'aluno_id' => ['label' => 'Aluno', 'type' => 'fk', 'ref' => 'alunos', 'help' => 'Obrigatório quando o alvo é Aluno.'],
                    'inicia_em' => ['label' => 'Início', 'type' => 'datetime'],
                    'termina_em' => ['label' => 'Término', 'type' => 'datetime'],
                    'status' => ['label' => 'Status', 'type' => 'select', 'options' => self::STATUS_APLICACAO, 'required' => true, 'default' => 'ativa'],
                    'codigo_sala' => ['label' => 'Código da sala', 'type' => 'text', 'max' => 12, 'help' => 'Em branco = gerado automaticamente.'],
                ],
                'validate' => static function (array &$data): void {
                    $escola = (int) $data['escola_id'];
                    self::exigirMesmaEscola('questionarios', $data['questionario_id'], $escola, 'O questionário não pertence à escola escolhida.');
                    if ($data['alvo_tipo'] === 'turma' && empty($data['turma_id'])) {
                        throw new FormError('Informe a turma para o alvo Turma.');
                    }
                    if ($data['alvo_tipo'] === 'aluno' && empty($data['aluno_id'])) {
                        throw new FormError('Informe o aluno para o alvo Aluno.');
                    }
                    self::exigirMesmaEscola('turmas', $data['turma_id'] ?? null, $escola, 'A turma não pertence à escola escolhida.');
                    self::exigirMesmaEscola('alunos', $data['aluno_id'] ?? null, $escola, 'O aluno não pertence à escola escolhida.');
                    if ($data['status'] === 'ativa' && QuestionarioService::totalPerguntas((int) $data['questionario_id']) === 0) {
                        throw new FormError(QuestionarioService::mensagemSemPerguntas((int) $data['questionario_id']));
                    }
                    $codigo = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) ($data['codigo_sala'] ?? '')));
                    $data['codigo_sala'] = $codigo === '' ? CodigoSala::gerar() : $codigo;
                },
                'actions' => [
                    'encerrar' => [
                        'label' => 'Encerrar',
                        'confirm' => 'Encerrar as aplicações selecionadas? Os alunos deixam de vê-las.',
                        'handler' => static function (array $ids): string {
                            foreach ($ids as $id) {
                                Db::update('aplicacoes_questionario', ['status' => 'encerrada'], $id);
                            }
                            return count($ids) . ' aplicação(ões) encerrada(s).';
                        },
                    ],
                    'limpar-respostas' => [
                        'label' => 'Limpar respostas (demo)',
                        'confirm' => 'Apaga respostas e resultados no servidor. Só o admin geral pode fazer isso.',
                        'su_only' => true,
                        'handler' => static function (array $ids): string {
                            $respostas = 0;
                            $resultados = 0;
                            foreach ($ids as $id) {
                                $removidos = QuestionarioService::limparAplicacao($id);
                                $respostas += $removidos['respostas'];
                                $resultados += $removidos['resultados'];
                            }
                            return "$respostas resposta(s) e $resultados resultado(s) removidos.";
                        },
                    ],
                ],
            ],

            'respostas' => [
                'table' => 'respostas',
                'perm' => 'resposta',
                'singular' => 'Resposta',
                'plural' => 'Respostas',
                'readonly' => true,
                'title' => "CONCAT(COALESCE(al.nome, ''), ' → pergunta #', t.pergunta_id)",
                'join' => 'LEFT JOIN alunos al ON al.id = t.aluno_id',
                'list' => ['aluno_id', 'pergunta_id', 'opcao_id', 'texto', 'responded_at', 'aplicacao_id'],
                'search' => ['texto'],
                'fields' => [
                    'aplicacao_id' => ['label' => 'Aplicação', 'type' => 'fk', 'ref' => 'aplicacoes'],
                    'aluno_id' => ['label' => 'Aluno', 'type' => 'fk', 'ref' => 'alunos'],
                    'pergunta_id' => ['label' => 'Pergunta', 'type' => 'fk', 'ref' => 'perguntas'],
                    'opcao_id' => ['label' => 'Opção', 'type' => 'fk', 'ref' => 'opcoes'],
                    'texto' => ['label' => 'Texto', 'type' => 'textarea', 'display' => 'resumo'],
                    'responded_at' => ['label' => 'Respondido em', 'type' => 'datetime'],
                    'tempo_gasto_ms' => ['label' => 'Tempo (ms)', 'type' => 'int'],
                    'dispositivo' => ['label' => 'Dispositivo', 'type' => 'text'],
                    'client_uuid' => ['label' => 'UUID do app', 'type' => 'text'],
                ],
            ],

            'resultados' => [
                'table' => 'resultados',
                'perm' => 'resultado',
                'singular' => 'Resultado',
                'plural' => 'Resultados',
                'readonly' => true,
                'title' => "CONCAT('Resultado de ', COALESCE(al.nome, CONCAT('aluno #', t.aluno_id)))",
                'join' => 'LEFT JOIN alunos al ON al.id = t.aluno_id',
                'list' => ['aluno_id', 'aplicacao_id', 'created_at'],
                'search' => [],
                'fields' => [
                    'aluno_id' => ['label' => 'Aluno', 'type' => 'fk', 'ref' => 'alunos'],
                    'aplicacao_id' => ['label' => 'Aplicação', 'type' => 'fk', 'ref' => 'aplicacoes'],
                    'totais_json' => ['label' => 'Totais por categoria', 'type' => 'json'],
                    'classificacao_json' => ['label' => 'Classificação', 'type' => 'json'],
                    'created_at' => ['label' => 'Calculado em', 'type' => 'datetime'],
                ],
            ],

            'termos' => [
                'table' => 'termos_aceite',
                'perm' => 'termo',
                'singular' => 'Termo aceito',
                'plural' => 'Termos aceitos',
                'readonly' => true,
                'title' => "CONCAT(COALESCE(al.nome, ''), ' · termo v', t.versao)",
                'join' => 'LEFT JOIN alunos al ON al.id = t.aluno_id',
                'list' => ['aluno_id', 'versao', 'accepted_at'],
                'search' => [],
                'fields' => [
                    'aluno_id' => ['label' => 'Aluno', 'type' => 'fk', 'ref' => 'alunos'],
                    'versao' => ['label' => 'Versão', 'type' => 'text'],
                    'accepted_at' => ['label' => 'Aceito em', 'type' => 'datetime'],
                    'ip' => ['label' => 'IP', 'type' => 'text'],
                    'user_agent' => ['label' => 'Navegador', 'type' => 'textarea'],
                    'texto_hash' => ['label' => 'Hash do texto', 'type' => 'text'],
                ],
            ],

            'avatares' => [
                'table' => 'avatars',
                'perm' => 'avatar',
                'singular' => 'Avatar',
                'plural' => 'Avatares',
                'title' => 't.nome',
                'join' => '',
                'list' => ['nome', 'escola_id', 'mensagem_padrao'],
                'search' => ['nome'],
                'fields' => [
                    'nome' => ['label' => 'Nome', 'type' => 'text', 'required' => true, 'max' => 255],
                    'escola_id' => ['label' => 'Escola', 'type' => 'fk', 'ref' => 'escolas', 'help' => 'Em branco = avatar padrão de todas as escolas (só admin geral).'],
                    'imagem_path' => ['label' => 'Imagem (URL ou caminho)', 'type' => 'text', 'required' => true, 'max' => 500],
                    'mensagem_padrao' => ['label' => 'Mensagem padrão', 'type' => 'textarea', 'display' => 'resumo'],
                ],
                'validate' => static function (array &$data, ?array $row, Ctx $ctx): void {
                    if (!$ctx->su && empty($data['escola_id'])) {
                        throw new FormError('Escolha a escola do avatar.');
                    }
                },
            ],
        ];
    }

    private static function exigirMesmaEscola(string $table, mixed $id, int $escolaId, string $mensagem): void
    {
        if ($id === null || $id === '') {
            return;
        }
        $escola = Db::value("SELECT escola_id FROM `$table` WHERE id = ?", [(int) $id]);
        if ((int) $escola !== $escolaId) {
            throw new FormError($mensagem);
        }
    }

    /**
     * Opções digitadas uma por linha: "Nunca = 0", "Às vezes; 1" ou só "Sempre" (pontos = posição, a partir de 0).
     *
     * @return list<array{0: string, 1: int}>
     */
    public static function opcoesDoTexto(string $texto): array
    {
        $opcoes = [];
        foreach (preg_split('/\R/u', $texto) ?: [] as $linha) {
            $linha = trim($linha);
            if ($linha === '') {
                continue;
            }
            $pontos = count($opcoes);
            if (preg_match('/^(.*?)\s*[=;|:]\s*(-?\d+)\s*(pts?|pontos?)?$/iu', $linha, $m) && trim($m[1]) !== '') {
                $linha = trim($m[1]);
                $pontos = (int) $m[2];
            }
            if (Str::len($linha) > 500) {
                throw new FormError('Cada opção de resposta pode ter no máximo 500 caracteres.');
            }
            $opcoes[] = [$linha, $pontos];
        }
        return $opcoes;
    }
}
