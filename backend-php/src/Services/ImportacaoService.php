<?php

declare(strict_types=1);

namespace Vise\Services;

use Vise\Admin\Ctx;
use Vise\Admin\Resources;
use Vise\Db;
use Vise\Roles;
use Vise\Security\Password;
use Vise\Support\Planilha;
use Vise\Support\Str;
use Vise\Support\XlsxWriter;

/**
 * Importação de cadastros por planilha (.xlsx ou .csv): escolas, turmas, alunos e equipe.
 * Cada linha é gravada isoladamente; com $simular = true tudo é validado e desfeito no final.
 */
final class ImportacaoService
{
    public const LIMITE_LINHAS = 10000;

    /** @return array<string, array{label: string, descricao: string, colunas: array<string, array<string, mixed>>}> */
    public static function definicoes(): array
    {
        $escola = ['label' => 'Escola', 'obrigatoria' => true, 'ajuda' => 'Nome exato da escola ou código INEP.', 'exemplo' => '00000001', 'aliases' => ['escola_inep', 'inep', 'nome_escola', 'escola_nome', 'unidade', 'unidade_escolar'], 'texto' => true];
        $turno = ['label' => 'Turno', 'ajuda' => 'Matutino, Vespertino, Noturno ou Integral (aceita manhã/tarde/noite).', 'exemplo' => 'Matutino', 'aliases' => ['periodo']];
        return [
            'alunos' => [
                'label' => 'Alunos',
                'descricao' => 'Cadastra ou atualiza alunos. Mesmo CPF na mesma escola = atualização dos dados.',
                'colunas' => [
                    'escola' => $escola,
                    'nome' => ['label' => 'Nome', 'obrigatoria' => true, 'ajuda' => 'Nome completo do aluno.', 'exemplo' => 'Maria da Silva', 'aliases' => ['nome_aluno', 'aluno', 'nome_completo', 'estudante', 'nome_do_aluno']],
                    'cpf' => ['label' => 'CPF', 'obrigatoria' => true, 'ajuda' => 'Com ou sem pontuação. Zeros à esquerda perdidos pelo Excel são corrigidos.', 'exemplo' => '529.982.247-25', 'aliases' => ['cpf_aluno', 'cpf_do_aluno'], 'texto' => true],
                    'data_nascimento' => ['label' => 'Data de nascimento', 'obrigatoria' => true, 'ajuda' => 'DD/MM/AAAA ou AAAA-MM-DD (usada no login do aluno).', 'exemplo' => '21/10/2010', 'aliases' => ['nascimento', 'dt_nascimento', 'data_nasc', 'data_de_nasc']],
                    'turma_nome' => ['label' => 'Turma', 'obrigatoria' => true, 'ajuda' => 'Nome da turma na escola (ex.: 9º Ano A).', 'exemplo' => '9º Ano A', 'aliases' => ['turma', 'nome_turma', 'classe']],
                    'serie' => ['label' => 'Série', 'ajuda' => 'Opcional. Com Série e Turno preenchidos, a turma é criada se ainda não existir.', 'exemplo' => '9º Ano', 'aliases' => ['ano', 'serie_ano', 'ano_serie']],
                    'turno' => $turno + ['ajuda' => 'Opcional (usado junto com Série para criar a turma).'],
                    'sexo' => ['label' => 'Sexo', 'ajuda' => 'Feminino, Masculino ou outro (aceita F/M).', 'exemplo' => 'Feminino', 'aliases' => ['genero']],
                    'matricula' => ['label' => 'Matrícula', 'ajuda' => 'Código do aluno na escola.', 'exemplo' => '2026001', 'aliases' => ['ra', 'codigo', 'codigo_aluno', 'numero_matricula'], 'texto' => true],
                    'telefone' => ['label' => 'Telefone', 'exemplo' => '(65) 99999-0000', 'aliases' => ['celular', 'telefone_aluno'], 'texto' => true],
                    'responsavel' => ['label' => 'Responsável', 'exemplo' => 'Joana da Silva', 'aliases' => ['nome_responsavel', 'mae', 'nome_da_mae']],
                    'contato_responsavel' => ['label' => 'Contato do responsável', 'exemplo' => '(65) 98888-0000', 'aliases' => ['telefone_responsavel', 'celular_responsavel'], 'texto' => true],
                ],
            ],
            'turmas' => [
                'label' => 'Turmas',
                'descricao' => 'Cadastra turmas. Mesmo nome na mesma escola = atualização da série e do turno. Séries novas são criadas automaticamente.',
                'colunas' => [
                    'escola' => $escola,
                    'nome' => ['label' => 'Turma', 'obrigatoria' => true, 'ajuda' => 'Nome da turma (único na escola).', 'exemplo' => '9º Ano A', 'aliases' => ['turma', 'turma_nome', 'nome_turma']],
                    'serie' => ['label' => 'Série', 'obrigatoria' => true, 'exemplo' => '9º Ano', 'aliases' => ['ano', 'serie_ano']],
                    'turno' => $turno + ['obrigatoria' => true],
                ],
            ],
            'escolas' => [
                'label' => 'Escolas',
                'descricao' => 'Cadastra escolas (só administrador geral). Mesmo INEP (ou mesmo nome no mesmo município) = atualização.',
                'colunas' => [
                    'nome' => ['label' => 'Nome', 'obrigatoria' => true, 'exemplo' => 'EE Professor Exemplo', 'aliases' => ['escola', 'nome_escola']],
                    'municipio' => ['label' => 'Município', 'obrigatoria' => true, 'exemplo' => 'Cuiabá', 'aliases' => ['cidade']],
                    'uf' => ['label' => 'UF', 'obrigatoria' => true, 'exemplo' => 'MT', 'aliases' => ['estado']],
                    'inep' => ['label' => 'INEP', 'exemplo' => '51000001', 'aliases' => ['codigo_inep', 'cod_inep'], 'texto' => true],
                    'responsavel' => ['label' => 'Responsável', 'exemplo' => 'Diretora Ana', 'aliases' => ['diretor', 'diretora', 'gestor']],
                    'telefone' => ['label' => 'Telefone', 'exemplo' => '(65) 3333-0000', 'texto' => true],
                    'endereco' => ['label' => 'Endereço', 'exemplo' => 'Rua A, 100 - Centro'],
                ],
            ],
            'equipe' => [
                'label' => 'Equipe (usuários)',
                'descricao' => 'Cria usuários da equipe e o vínculo com a escola. Usuário já existente (mesmo e-mail) ganha o vínculo; a senha dele não muda.',
                'colunas' => [
                    'nome' => ['label' => 'Nome', 'obrigatoria' => true, 'exemplo' => 'Carlos Pereira', 'aliases' => ['name', 'nome_completo']],
                    'email' => ['label' => 'E-mail', 'obrigatoria' => true, 'exemplo' => 'carlos@escola.br', 'aliases' => ['e_mail', 'email_institucional']],
                    'perfil' => ['label' => 'Perfil', 'obrigatoria' => true, 'ajuda' => 'Administrador da escola, Pesquisador ou Professor.', 'exemplo' => 'Professor', 'aliases' => ['papel', 'funcao', 'cargo', 'role']],
                    'escola' => $escola,
                    'senha' => ['label' => 'Senha inicial', 'ajuda' => 'Obrigatória para usuários novos (mínimo 8 caracteres). Oriente a troca no primeiro acesso.', 'exemplo' => 'Troque@2026', 'aliases' => ['senha', 'password'], 'texto' => true],
                    'turmas' => ['label' => 'Turmas do professor', 'ajuda' => 'Opcional. Nomes das turmas separados por ";" (só para Professor).', 'exemplo' => '9º Ano A; 9º Ano B', 'aliases' => ['turma', 'turmas_professor']],
                ],
            ],
        ];
    }

    public static function permitido(string $tipo, Ctx $ctx): bool
    {
        return match ($tipo) {
            'alunos' => $ctx->canWrite('aluno'),
            'turmas' => $ctx->canWrite('turma'),
            'escolas' => $ctx->su,
            'equipe' => $ctx->canWrite('user') && $ctx->canWrite('escola_user'),
            default => false,
        };
    }

    /** @return array<string, array{label: string, descricao: string, colunas: array<string, array<string, mixed>>}> */
    public static function tiposPermitidos(Ctx $ctx): array
    {
        return array_filter(self::definicoes(), static fn (string $tipo) => self::permitido($tipo, $ctx), ARRAY_FILTER_USE_KEY);
    }

    /**
     * @param list<int>|null $escolasPermitidas null = sem restrição (admin geral)
     * @return array{tipo: string, simulacao: bool, formato: string, total: int, criados: int, atualizados: int, erros: int,
     *               colunas_ignoradas: list<string>, linhas: list<array{linha: int, status: string, mensagem: string}>}
     */
    public static function importar(string $tipo, string $conteudo, ?array $escolasPermitidas = null, bool $simular = false): array
    {
        $definicao = self::definicoes()[$tipo] ?? throw new \RuntimeException('Tipo de importação desconhecido.');
        $planilha = Planilha::ler($conteudo);
        [$mapa, $ignoradas] = self::mapearColunas($planilha['cabecalho'], $definicao['colunas']);
        if (count($planilha['linhas']) > self::LIMITE_LINHAS) {
            throw new \RuntimeException('A planilha tem mais de ' . self::LIMITE_LINHAS . ' linhas. Divida o arquivo em partes menores.');
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $cache = [];
        $resultado = [
            'tipo' => $tipo, 'simulacao' => $simular, 'formato' => $planilha['formato'], 'total' => 0,
            'criados' => 0, 'atualizados' => 0, 'erros' => 0, 'colunas_ignoradas' => $ignoradas, 'linhas' => [],
        ];
        $processar = static function () use ($tipo, $planilha, $mapa, $escolasPermitidas, &$cache, &$resultado): void {
            foreach ($planilha['linhas'] as $numero => $valores) {
                $row = [];
                foreach ($mapa as $indice => $chave) {
                    $row[$chave] = trim((string) ($valores[$indice] ?? ''));
                }
                $resultado['total']++;
                try {
                    [$status, $mensagem] = Db::transaction(static fn () => match ($tipo) {
                        'alunos' => self::aluno($row, $escolasPermitidas, $cache),
                        'turmas' => self::turma($row, $escolasPermitidas, $cache),
                        'escolas' => self::escola($row),
                        'equipe' => self::membro($row, $escolasPermitidas, $cache),
                    });
                    $resultado[$status === 'criado' ? 'criados' : 'atualizados']++;
                } catch (\Throwable $erro) {
                    $status = 'erro';
                    $mensagem = self::mensagemErro($erro);
                    $resultado['erros']++;
                }
                $resultado['linhas'][] = ['linha' => (int) $numero, 'status' => $status, 'mensagem' => $mensagem];
            }
        };

        if ($simular) {
            $pdo = Db::pdo();
            $pdo->beginTransaction();
            try {
                $processar();
            } finally {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            }
        } else {
            $processar();
        }
        return $resultado;
    }

    /**
     * Planilha modelo: aba "Preencher" (só o cabeçalho), instruções e, quando houver, escolas/turmas do usuário.
     *
     * @param list<int>|null $escolasPermitidas
     */
    public static function modelo(string $tipo, ?array $escolasPermitidas = null): string
    {
        $definicao = self::definicoes()[$tipo] ?? throw new \RuntimeException('Tipo de importação desconhecido.');
        $cabecalho = [];
        $larguras = [];
        foreach (array_values($definicao['colunas']) as $i => $coluna) {
            $cabecalho[] = XlsxWriter::c($coluna['label'] . (!empty($coluna['obrigatoria']) ? ' *' : ''), 'header');
            $larguras[$i] = max(14, Str::len($coluna['label']) + 6, Str::len((string) ($coluna['exemplo'] ?? '')) + 4);
        }
        $linhas = [$cabecalho];
        for ($r = 0; $r < 300; $r++) {
            $linha = [];
            foreach (array_values($definicao['colunas']) as $coluna) {
                $linha[] = !empty($coluna['texto']) ? XlsxWriter::c(null, 'text') : null;
            }
            $linhas[] = $linha;
        }

        $instrucoes = [
            [XlsxWriter::c('Importação de ' . Str::lower($definicao['label']) . ' — VISE-MT', 'title')],
            [XlsxWriter::c($definicao['descricao'], 'muted')],
            [],
            [XlsxWriter::c('Como usar', 'subtitle')],
            ['1. Preencha a aba "Preencher", uma linha por registro, sem mudar os nomes das colunas.'],
            ['2. Colunas com * são obrigatórias. Colunas extras são ignoradas.'],
            ['3. No painel, use "Simular" para conferir os erros antes de gravar. Depois clique em "Importar".'],
            ['4. Também é aceito CSV (separado por vírgula ou ponto e vírgula).'],
            [],
            [XlsxWriter::c('Coluna', 'header'), XlsxWriter::c('Obrigatória', 'header'), XlsxWriter::c('O que preencher', 'header'), XlsxWriter::c('Exemplo', 'header')],
        ];
        foreach ($definicao['colunas'] as $coluna) {
            $instrucoes[] = [
                XlsxWriter::c($coluna['label'], 'bold border'),
                XlsxWriter::c(!empty($coluna['obrigatoria']) ? 'Sim' : 'Não', 'border center'),
                XlsxWriter::c($coluna['ajuda'] ?? '', 'border wrap'),
                XlsxWriter::c((string) ($coluna['exemplo'] ?? ''), 'border'),
            ];
        }

        $xlsx = (new XlsxWriter())
            ->aba('Preencher', $linhas, ['congelar' => 1, 'larguras' => $larguras])
            ->aba('Instruções', $instrucoes, ['larguras' => [26, 12, 70, 24]]);

        if ($tipo !== 'escolas') {
            $referencia = [[XlsxWriter::c('Escolas e turmas disponíveis para você', 'title')], [], [
                XlsxWriter::c('Escola', 'header'), XlsxWriter::c('INEP', 'header'), XlsxWriter::c('Turma', 'header'),
                XlsxWriter::c('Série', 'header'), XlsxWriter::c('Turno', 'header'),
            ]];
            $where = $escolasPermitidas === null ? '1=1' : 'e.id IN (' . Db::in($escolasPermitidas) . ')';
            $rows = Db::all(
                "SELECT e.nome AS escola, e.inep, t.nome AS turma, s.descricao AS serie, t.turno
                 FROM escolas e
                 LEFT JOIN turmas t ON t.escola_id = e.id AND t.deleted_at IS NULL
                 LEFT JOIN series s ON s.id = t.serie_id
                 WHERE e.deleted_at IS NULL AND $where ORDER BY e.nome, t.nome LIMIT 5000",
                $escolasPermitidas ?? []
            );
            foreach ($rows as $row) {
                $referencia[] = [$row['escola'], XlsxWriter::c((string) ($row['inep'] ?? ''), 'text'), $row['turma'], $row['serie'], Resources::TURNOS[$row['turno'] ?? ''] ?? $row['turno']];
            }
            $xlsx->aba('Escolas e turmas', $referencia, ['congelar' => 3, 'filtro' => 3]);
        }
        return $xlsx->gerar();
    }

    // ------------------------------------------------------------------ mapeamento

    /**
     * @param list<string> $cabecalho
     * @param array<string, array<string, mixed>> $colunas
     * @return array{0: array<int, string>, 1: list<string>} índice da coluna => chave; colunas ignoradas
     */
    private static function mapearColunas(array $cabecalho, array $colunas): array
    {
        $apelidos = [];
        foreach ($colunas as $chave => $coluna) {
            foreach (array_merge([$chave, Planilha::chave($coluna['label'])], $coluna['aliases'] ?? []) as $apelido) {
                $apelidos[Planilha::chave($apelido)] ??= $chave;
            }
        }
        $mapa = [];
        $ignoradas = [];
        foreach ($cabecalho as $indice => $titulo) {
            $normalizado = Planilha::chave($titulo);
            if ($normalizado === '') {
                continue;
            }
            $chave = $apelidos[$normalizado] ?? null;
            if ($chave === null || in_array($chave, $mapa, true)) {
                $ignoradas[] = $titulo;
                continue;
            }
            $mapa[$indice] = $chave;
        }
        $faltando = [];
        foreach ($colunas as $chave => $coluna) {
            if (!empty($coluna['obrigatoria']) && !in_array($chave, $mapa, true)) {
                $faltando[] = $coluna['label'];
            }
        }
        if ($faltando !== []) {
            throw new \RuntimeException(
                'Colunas obrigatórias ausentes: ' . implode(', ', $faltando)
                . '. Confira a primeira linha da planilha (cabeçalho) ou baixe o modelo.'
            );
        }
        return [$mapa, $ignoradas];
    }

    // ------------------------------------------------------------------ linhas

    /**
     * @param array<string, string> $row
     * @param list<int>|null $escolas
     * @param array<string, mixed> $cache
     * @return array{0: string, 1: string}
     */
    private static function aluno(array $row, ?array $escolas, array &$cache): array
    {
        $escola = self::escolaDaLinha($row['escola'] ?? '', $escolas, $cache);
        $nome = self::obrigatorio($row, 'nome', 'Nome');
        $cpf = self::cpf($row['cpf'] ?? '');
        $nascimento = self::data($row['data_nascimento'] ?? '', 'Data de nascimento');
        $turmaNome = self::obrigatorio($row, 'turma_nome', 'Turma');
        $turmaId = self::turmaId((int) $escola['id'], $turmaNome, $row['serie'] ?? '', $row['turno'] ?? '', $cache);
        if (Str::len($nome) > 255) {
            throw new \InvalidArgumentException('Nome muito longo (máximo 255 caracteres).');
        }

        $dados = [
            'turma_id' => $turmaId,
            'nome' => $nome,
            'data_nascimento' => $nascimento,
            'sexo' => self::sexo($row['sexo'] ?? ''),
            'matricula' => Str::orNull($row['matricula'] ?? null),
            'telefone' => Str::orNull($row['telefone'] ?? null),
            'responsavel' => Str::orNull($row['responsavel'] ?? null),
            'contato_responsavel' => Str::orNull($row['contato_responsavel'] ?? null),
        ];
        $existente = Db::one('SELECT * FROM alunos WHERE escola_id = ? AND cpf = ? AND deleted_at IS NULL', [$escola['id'], $cpf]);
        $descricao = $nome . ' (CPF ' . Str::cpf($cpf) . ')';
        if ($existente === null) {
            $now = Db::now();
            Db::insert('alunos', $dados + ['escola_id' => $escola['id'], 'cpf' => $cpf, 'created_at' => $now, 'updated_at' => $now]);
            return ['criado', $descricao];
        }
        foreach (['sexo', 'matricula', 'telefone', 'responsavel', 'contato_responsavel'] as $campo) {
            $dados[$campo] ??= $existente[$campo];
        }
        Db::update('alunos', $dados, (int) $existente['id']);
        return ['atualizado', $descricao];
    }

    /**
     * @param array<string, string> $row
     * @param list<int>|null $escolas
     * @param array<string, mixed> $cache
     * @return array{0: string, 1: string}
     */
    private static function turma(array $row, ?array $escolas, array &$cache): array
    {
        $escola = self::escolaDaLinha($row['escola'] ?? '', $escolas, $cache);
        $nome = self::obrigatorio($row, 'nome', 'Turma');
        $serieId = self::serieId(self::obrigatorio($row, 'serie', 'Série'));
        $turno = self::turno(self::obrigatorio($row, 'turno', 'Turno'));
        $existente = Db::value('SELECT id FROM turmas WHERE escola_id = ? AND nome = ?', [$escola['id'], $nome]);
        $descricao = $nome . ' — ' . $escola['nome'];
        if ($existente === null) {
            $now = Db::now();
            Db::insert('turmas', ['escola_id' => $escola['id'], 'nome' => $nome, 'serie_id' => $serieId, 'turno' => $turno, 'created_at' => $now, 'updated_at' => $now]);
            return ['criado', $descricao];
        }
        Db::update('turmas', ['serie_id' => $serieId, 'turno' => $turno, 'deleted_at' => null], (int) $existente);
        return ['atualizado', $descricao];
    }

    /**
     * @param array<string, string> $row
     * @return array{0: string, 1: string}
     */
    private static function escola(array $row): array
    {
        $nome = self::obrigatorio($row, 'nome', 'Nome');
        $municipio = self::obrigatorio($row, 'municipio', 'Município');
        $uf = Str::upper(self::obrigatorio($row, 'uf', 'UF'));
        if (!preg_match('/^[A-Z]{2}$/', $uf)) {
            throw new \InvalidArgumentException('UF deve ter 2 letras (ex.: MT).');
        }
        $inep = Str::digits($row['inep'] ?? '');
        if ($inep !== '' && strlen($inep) < 8) {
            $inep = str_pad($inep, 8, '0', STR_PAD_LEFT);
        }
        $dados = [
            'nome' => $nome, 'municipio' => $municipio, 'uf' => $uf, 'inep' => $inep === '' ? null : $inep,
            'responsavel' => Str::orNull($row['responsavel'] ?? null), 'telefone' => Str::orNull($row['telefone'] ?? null),
            'endereco' => Str::orNull($row['endereco'] ?? null),
        ];
        $existente = $inep !== '' ? Db::value('SELECT id FROM escolas WHERE inep = ?', [$inep]) : null;
        $existente ??= Db::value('SELECT id FROM escolas WHERE nome = ? AND municipio = ? AND uf = ?', [$nome, $municipio, $uf]);
        if ($existente === null) {
            $now = Db::now();
            Db::insert('escolas', $dados + ['ativo' => 1, 'created_at' => $now, 'updated_at' => $now]);
            return ['criado', $nome];
        }
        foreach (['inep', 'responsavel', 'telefone', 'endereco'] as $campo) {
            if ($dados[$campo] === null) {
                unset($dados[$campo]);
            }
        }
        Db::update('escolas', $dados, (int) $existente);
        return ['atualizado', $nome];
    }

    /**
     * @param array<string, string> $row
     * @param list<int>|null $escolas
     * @param array<string, mixed> $cache
     * @return array{0: string, 1: string}
     */
    private static function membro(array $row, ?array $escolas, array &$cache): array
    {
        $nome = self::obrigatorio($row, 'nome', 'Nome');
        $email = Str::lower(self::obrigatorio($row, 'email', 'E-mail'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("E-mail '$email' inválido.");
        }
        $perfil = self::perfil(self::obrigatorio($row, 'perfil', 'Perfil'));
        $escola = self::escolaDaLinha($row['escola'] ?? '', $escolas, $cache);
        $now = Db::now();

        $usuario = Db::one('SELECT id, deleted_at FROM users WHERE email = ?', [$email]);
        $criado = false;
        if ($usuario === null) {
            $senha = (string) ($row['senha'] ?? '');
            if (Str::len($senha) < 8) {
                throw new \InvalidArgumentException('Informe a senha inicial (mínimo 8 caracteres) para o usuário novo.');
            }
            $userId = Db::insert('users', ['name' => $nome, 'email' => $email, 'password' => Password::hash($senha), 'is_superuser' => 0, 'created_at' => $now, 'updated_at' => $now]);
            $criado = true;
        } else {
            $userId = (int) $usuario['id'];
            if ($usuario['deleted_at'] !== null) {
                throw new \InvalidArgumentException("O usuário $email foi excluído; reative-o pelo cadastro de usuários.");
            }
        }

        $vinculo = Db::one('SELECT id, status FROM escola_user WHERE user_id = ? AND escola_id = ? AND role = ?', [$userId, $escola['id'], $perfil]);
        if ($vinculo === null) {
            Db::insert('escola_user', ['user_id' => $userId, 'escola_id' => $escola['id'], 'role' => $perfil, 'status' => 'ativo', 'vinculado_em' => $now, 'created_at' => $now, 'updated_at' => $now]);
        } elseif ($vinculo['status'] !== 'ativo') {
            Db::update('escola_user', ['status' => 'ativo'], (int) $vinculo['id']);
        }

        $turmas = array_filter(array_map('trim', preg_split('/[;|\n]/', (string) ($row['turmas'] ?? '')) ?: []));
        if ($turmas !== [] && $perfil !== Roles::PROFESSOR) {
            throw new \InvalidArgumentException('Turmas só podem ser informadas para o perfil Professor.');
        }
        foreach ($turmas as $turmaNome) {
            $turmaId = Db::value('SELECT id FROM turmas WHERE escola_id = ? AND nome = ?', [$escola['id'], $turmaNome]);
            if ($turmaId === null) {
                throw new \InvalidArgumentException("Turma '$turmaNome' não encontrada na escola {$escola['nome']}.");
            }
            if (Db::value('SELECT id FROM professor_turma WHERE user_id = ? AND turma_id = ?', [$userId, $turmaId]) === null) {
                Db::insert('professor_turma', ['user_id' => $userId, 'turma_id' => $turmaId, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        $descricao = "$nome <$email> — " . Roles::label($perfil) . ' em ' . $escola['nome'];
        return [$criado ? 'criado' : 'atualizado', $criado ? $descricao : $descricao . ' (usuário já existia; senha mantida)'];
    }

    // ------------------------------------------------------------------ conversões

    /**
     * @param list<int>|null $escolas
     * @param array<string, mixed> $cache
     * @return array<string, mixed>
     */
    private static function escolaDaLinha(string $valor, ?array $escolas, array &$cache): array
    {
        $valor = trim($valor);
        if ($valor === '') {
            throw new \InvalidArgumentException('Escola não informada.');
        }
        $chave = 'escola:' . Str::lower($valor);
        if (!array_key_exists($chave, $cache)) {
            $escola = null;
            $digits = Str::digits($valor);
            if ($digits !== '' && $digits === preg_replace('/\s+/', '', $valor)) {
                foreach (array_unique([$digits, str_pad($digits, 8, '0', STR_PAD_LEFT), ltrim($digits, '0')]) as $inep) {
                    $escola ??= Db::one('SELECT id, nome FROM escolas WHERE inep = ? AND deleted_at IS NULL', [$inep]);
                }
            }
            $escola ??= Db::one('SELECT id, nome FROM escolas WHERE inep = ? AND deleted_at IS NULL', [$valor]);
            $escola ??= Db::one('SELECT id, nome FROM escolas WHERE nome = ? AND deleted_at IS NULL', [$valor]);
            $cache[$chave] = $escola;
        }
        $escola = $cache[$chave];
        if ($escola === null) {
            throw new \InvalidArgumentException("Escola '$valor' não encontrada (use o nome exato ou o INEP).");
        }
        if ($escolas !== null && !in_array((int) $escola['id'], $escolas, true)) {
            throw new \InvalidArgumentException("Sem permissão para importar na escola '{$escola['nome']}'.");
        }
        return $escola;
    }

    /** @param array<string, mixed> $cache */
    private static function turmaId(int $escolaId, string $nome, string $serie, string $turno, array &$cache): int
    {
        $id = Db::value('SELECT id FROM turmas WHERE escola_id = ? AND nome = ?', [$escolaId, $nome]);
        if ($id !== null) {
            return (int) $id;
        }
        if (trim($serie) === '' || trim($turno) === '') {
            throw new \InvalidArgumentException("Turma '$nome' não encontrada nesta escola. Cadastre a turma ou preencha as colunas Série e Turno para criá-la.");
        }
        $now = Db::now();
        return Db::insert('turmas', ['escola_id' => $escolaId, 'nome' => $nome, 'serie_id' => self::serieId($serie), 'turno' => self::turno($turno), 'created_at' => $now, 'updated_at' => $now]);
    }

    private static function serieId(string $descricao): int
    {
        $descricao = trim($descricao);
        $id = Db::value('SELECT id FROM series WHERE descricao = ?', [$descricao]);
        if ($id !== null) {
            return (int) $id;
        }
        $now = Db::now();
        return Db::insert('series', ['descricao' => $descricao, 'created_at' => $now, 'updated_at' => $now]);
    }

    private static function turno(string $valor): string
    {
        $chave = Planilha::chave($valor);
        $mapa = [
            'matutino' => 'matutino', 'manha' => 'matutino', 'm' => 'matutino',
            'vespertino' => 'vespertino', 'tarde' => 'vespertino', 'v' => 'vespertino', 't' => 'vespertino',
            'noturno' => 'noturno', 'noite' => 'noturno', 'n' => 'noturno',
            'integral' => 'integral', 'i' => 'integral', 'tempo_integral' => 'integral',
        ];
        return $mapa[$chave] ?? throw new \InvalidArgumentException("Turno '$valor' inválido. Use Matutino, Vespertino, Noturno ou Integral.");
    }

    private static function perfil(string $valor): string
    {
        $chave = Planilha::chave($valor);
        if (str_contains($chave, 'admin') || str_contains($chave, 'diret') || str_contains($chave, 'coorden') || str_contains($chave, 'gestor')) {
            return Roles::ADMIN_ESCOLA;
        }
        if (str_starts_with($chave, 'pesquisador')) {
            return Roles::PESQUISADOR;
        }
        if (str_starts_with($chave, 'professor')) {
            return Roles::PROFESSOR;
        }
        throw new \InvalidArgumentException("Perfil '$valor' inválido. Use Administrador da escola, Pesquisador ou Professor.");
    }

    private static function cpf(string $valor): string
    {
        $cpf = Str::digits($valor);
        if ($cpf !== '' && strlen($cpf) < 11 && strlen($cpf) >= 8 && Str::digits($valor) === preg_replace('/\s+/', '', $valor)) {
            $cpf = str_pad($cpf, 11, '0', STR_PAD_LEFT);
        }
        if (strlen($cpf) !== 11) {
            throw new \InvalidArgumentException('CPF deve conter 11 dígitos' . ($valor === '' ? ' (não informado).' : " (recebido: $valor)."));
        }
        return $cpf;
    }

    private static function data(string $valor, string $rotulo): string
    {
        $valor = trim($valor);
        if ($valor === '') {
            throw new \InvalidArgumentException("$rotulo não informada.");
        }
        $valor = (string) preg_replace('/\s+\d{1,2}:\d{2}(:\d{2})?$/', '', $valor);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'd/m/y'] as $formato) {
            $data = \DateTimeImmutable::createFromFormat('!' . $formato, $valor);
            $avisos = \DateTimeImmutable::getLastErrors();
            $semAvisos = $avisos === false || ($avisos['warning_count'] === 0 && $avisos['error_count'] === 0);
            if ($data !== false && $semAvisos) {
                if ((int) $data->format('Y') < 1900 || $data > new \DateTimeImmutable('today')) {
                    break;
                }
                return $data->format('Y-m-d');
            }
        }
        throw new \InvalidArgumentException("$rotulo inválida ('$valor'); use DD/MM/AAAA.");
    }

    private static function sexo(string $valor): ?string
    {
        $chave = Planilha::chave($valor);
        if ($chave === '') {
            return null;
        }
        return match (true) {
            in_array($chave, ['f', 'fem', 'feminino', 'mulher', 'menina'], true) => 'feminino',
            in_array($chave, ['m', 'masc', 'masculino', 'homem', 'menino'], true) => 'masculino',
            default => Str::sub(Str::lower(trim($valor)), 0, 30),
        };
    }

    /** @param array<string, string> $row */
    private static function obrigatorio(array $row, string $chave, string $rotulo): string
    {
        $valor = trim((string) ($row[$chave] ?? ''));
        if ($valor === '') {
            throw new \InvalidArgumentException("$rotulo não informado(a).");
        }
        return $valor;
    }

    private static function mensagemErro(\Throwable $erro): string
    {
        if ($erro instanceof \PDOException) {
            if (Db::isDuplicate($erro)) {
                return 'Registro duplicado (algum valor único já está cadastrado).';
            }
            error_log('[vise] importação: ' . $erro->getMessage());
            return 'Erro ao gravar no banco de dados.';
        }
        return $erro->getMessage();
    }
}
