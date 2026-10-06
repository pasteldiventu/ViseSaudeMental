<?php

declare(strict_types=1);

namespace Vise\Install;

use RuntimeException;
use Vise\Db;
use Vise\Services\ResultadoService;
use Vise\Support\Str;

/**
 * Importa o backup SQL do sistema anterior (tabelas aluno, escola, categoria_pergunta, pergunta…).
 *
 * No sistema anterior cada "categoria_pergunta" é um questionário inteiro e as regras de
 * classificação valem para a soma dele todo; aqui ele vira um questionário com uma única
 * categoria, e as subcategorias antigas viram subcategorias. Como questionários pertencem a
 * uma escola, cada um é copiado para as escolas que o usavam. As respostas antigas ficam numa
 * aplicação "encerrada" por questionário e escola.
 *
 * Pode ser executado de novo: o que já foi importado é reaproveitado pela tabela legado_map.
 */
final class ImportLegado
{
    private const NIVEL = ['verde' => 'baixo', 'amarelo' => 'moderado', 'laranja' => 'moderado', 'vermelho' => 'alto'];
    private const ROTULO = ['verde' => 'Adequado', 'amarelo' => 'Atenção', 'laranja' => 'Atenção elevada', 'vermelho' => 'Prioritário'];
    private const TURNOS = ['matutino', 'vespertino', 'noturno', 'integral'];
    private const ESCAPES = ['n' => "\n", 'r' => "\r", 't' => "\t", '0' => "\0", 'Z' => "\x1a"];

    /** @var array<string, list<array<string, ?string>>> */
    private array $t;
    /** @var array<string, int> */
    private array $mapa;
    /** @var array<string, int> */
    private array $ids = [];
    /** @var array<string, int> */
    private array $resumo = [];
    /** @var list<string> */
    private array $avisos = [];
    /** @var array<string, int> escola antiga -> escola nova */
    private array $escola = [];
    /** @var array<string, array<string, mixed>> aluno antigo -> dados */
    private array $aluno = [];
    /** @var array<string, string> pergunta antiga -> questionário antigo */
    private array $questionarioDaPergunta = [];
    private int $pesquisador = 0;

    /**
     * @return array{aplicado: bool, resumo: array<string, int>, avisos: list<string>}
     */
    public static function executar(string $sql, bool $aplicar, ?string $pesquisadorEmail = null): array
    {
        $tabelas = self::lerDump($sql);
        foreach (['aluno', 'escola', 'categoria_pergunta', 'pergunta', 'opcoes_resposta'] as $obrigatoria) {
            if (empty($tabelas[$obrigatoria])) {
                throw new RuntimeException("O arquivo não parece um backup do sistema anterior (tabela `$obrigatoria` ausente ou vazia).");
            }
        }
        Installer::migrate();

        $import = new self($tabelas);
        $import->pesquisador = self::pesquisador($pesquisadorEmail);
        $simulacao = new RuntimeException('simulação');
        try {
            Db::transaction(static function () use ($import, $aplicar, $simulacao): void {
                $import->importar();
                if (!$aplicar) {
                    throw $simulacao;
                }
            });
        } catch (RuntimeException $erro) {
            if ($erro !== $simulacao) {
                throw $erro;
            }
        }
        return ['aplicado' => $aplicar, 'resumo' => $import->resumo, 'avisos' => $import->avisos];
    }

    /**
     * Lê os INSERTs de um dump do phpMyAdmin/mysqldump.
     *
     * @return array<string, list<array<string, ?string>>>
     */
    public static function lerDump(string $sql): array
    {
        $tabelas = [];
        preg_match_all('/INSERT INTO `(\w+)` \(([^)]*)\) VALUES\s*/', $sql, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        foreach ($matches as $match) {
            $colunas = array_map(static fn (string $c) => trim($c, " `\r\n\t"), explode(',', $match[2][0]));
            foreach (self::tuplas($sql, $match[0][1] + strlen($match[0][0])) as $valores) {
                if (count($valores) === count($colunas)) {
                    $tabelas[$match[1][0]][] = array_combine($colunas, $valores);
                }
            }
        }
        return $tabelas;
    }

    /** @return \Generator<int, list<?string>> */
    private static function tuplas(string $sql, int $i): \Generator
    {
        $n = strlen($sql);
        while ($i < $n) {
            $i += strspn($sql, " \r\n\t,", $i);
            if ($i >= $n || $sql[$i] !== '(') {
                return;
            }
            $i++;
            $linha = [];
            while (true) {
                $i += strspn($sql, " \r\n\t", $i);
                if ($i >= $n) {
                    throw new RuntimeException('Arquivo SQL incompleto.');
                }
                if ($sql[$i] === "'") {
                    $i++;
                    $texto = '';
                    while (true) {
                        $trecho = strcspn($sql, "'\\", $i);
                        $texto .= substr($sql, $i, $trecho);
                        $i += $trecho;
                        if ($i + 1 >= $n) {
                            throw new RuntimeException('Arquivo SQL incompleto.');
                        }
                        if ($sql[$i] === '\\') {
                            $texto .= self::ESCAPES[$sql[$i + 1]] ?? $sql[$i + 1];
                            $i += 2;
                        } elseif ($sql[$i + 1] === "'") {
                            $texto .= "'";
                            $i += 2;
                        } else {
                            $i++;
                            break;
                        }
                    }
                    $linha[] = $texto;
                } else {
                    $fim = strcspn($sql, ',)', $i);
                    $bruto = trim(substr($sql, $i, $fim));
                    $linha[] = strtoupper($bruto) === 'NULL' ? null : $bruto;
                    $i += $fim;
                }
                $i += strspn($sql, " \r\n\t", $i);
                if ($i >= $n) {
                    throw new RuntimeException('Arquivo SQL incompleto.');
                }
                if ($sql[$i++] === ')') {
                    break;
                }
            }
            yield $linha;
        }
    }

    /** @param array<string, list<array<string, ?string>>> $tabelas */
    private function __construct(array $tabelas)
    {
        $this->t = $tabelas;
        $this->mapa = [];
        foreach (Db::all('SELECT chave, novo_id FROM legado_map') as $row) {
            $this->mapa[(string) $row['chave']] = (int) $row['novo_id'];
        }
        foreach ($this->rows('pergunta') as $p) {
            $this->questionarioDaPergunta[(string) $p['pergunta_id']] = (string) $p['categoria_pergunta_id'];
        }
    }

    private static function pesquisador(?string $email): int
    {
        $email = trim((string) $email);
        $id = $email !== ''
            ? Db::value('SELECT id FROM users WHERE email = ? AND deleted_at IS NULL', [strtolower($email)])
            : Db::value('SELECT id FROM users WHERE is_superuser = 1 AND deleted_at IS NULL ORDER BY id LIMIT 1');
        if ($id === null) {
            throw new RuntimeException($email !== ''
                ? "Usuário $email não encontrado."
                : 'Crie um administrador geral antes de importar (ele será o dono dos questionários importados).');
        }
        return (int) $id;
    }

    private function importar(): void
    {
        $this->escolas();
        $this->alunos();
        $aplicacoes = $this->respostas($this->questionarios());
        $this->resultados($aplicacoes);
    }

    // ------------------------------------------------------------ escolas, turmas e alunos

    private function escolas(): void
    {
        $usadas = [];
        foreach ([...$this->rows('aluno'), ...$this->rows('escola_categoria_pergunta')] as $row) {
            $usadas[(string) $row['escola_id']] = true;
        }
        foreach ($this->rows('escola') as $e) {
            $antigo = (string) $e['escola_id'];
            if ($e['excluido_em'] !== null && !isset($usadas[$antigo])) {
                continue;
            }
            $this->escola[$antigo] = $this->obter("escola:$antigo", 'escolas', function () use ($e): int {
                $nome = self::texto($e['nome']);
                $existente = Db::value('SELECT id FROM escolas WHERE LOWER(nome) = LOWER(?) AND deleted_at IS NULL LIMIT 1', [$nome]);
                if ($existente !== null) {
                    $this->contar('Escolas já existentes reaproveitadas (mesmo nome)');
                    return (int) $existente;
                }
                [$municipio, $uf] = self::cidade($e['cidade']);
                $this->contar('Escolas criadas');
                return Db::insert('escolas', [
                    'nome' => $nome,
                    'municipio' => $municipio,
                    'uf' => $uf,
                    'telefone' => Str::orNull($e['telefone']),
                    'endereco' => implode(' - ', array_filter(array_map([Str::class, 'orNull'], [$e['endereco'], $e['bairro'], $e['cep']]))) ?: null,
                    'ativo' => $e['excluido_em'] === null ? 1 : 0,
                    'created_at' => self::data($e['criado_em']),
                    'updated_at' => self::data($e['alterado_em']),
                ]);
            });
        }
    }

    private function alunos(): void
    {
        $nomesTurma = [];
        foreach ([...$this->rows('backup_escola9_turma'), ...$this->rows('turma')] as $turma) {
            $nomesTurma[(string) $turma['turma_id']] = self::texto($turma['descricao'] ?? $turma['nome'] ?? '');
        }
        $series = [];
        foreach ($this->rows('serie') as $serie) {
            $series[(string) $serie['serie_id']] = self::texto($serie['descricao']);
        }

        $grupos = [];
        foreach ($this->rows('aluno') as $a) {
            $chave = $a['escola_id'] . ':' . $a['turma_id'];
            $grupos[$chave]['series'][] = (string) $a['serie_id'];
            $grupos[$chave]['turnos'][] = self::turno($a['periodo']);
        }

        // Cadastro mais recente primeiro: repetições do mesmo aluno (mesmo nome, CPF e nascimento)
        // são unificadas nele e ficam com a escola/turma atual.
        $alunos = $this->rows('aluno');
        usort($alunos, static fn (array $a, array $b) => [(int) $b['ano_letivo'], (int) $b['aluno_id']] <=> [(int) $a['ano_letivo'], (int) $a['aluno_id']]);
        $criados = [];
        $porCpf = [];
        foreach ($alunos as $a) {
            $antigo = (string) $a['aluno_id'];
            $nome = self::texto($a['nome']);
            $escola = $this->escola[(string) $a['escola_id']] ?? null;
            $nascimento = preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) $a['data_nascimento'])) ? trim((string) $a['data_nascimento']) : null;
            if ($escola === null || $nascimento === null) {
                $this->avisos[] = "Aluno #$antigo ($nome) não importado: " . ($escola === null ? 'escola inexistente.' : 'data de nascimento inválida.');
                continue;
            }
            $cpf = Str::digits($a['cpf']);
            if (strlen($cpf) >= 9 && strlen($cpf) < 11) {
                $cpf = str_pad($cpf, 11, '0', STR_PAD_LEFT);
            }
            if (strlen($cpf) !== 11) {
                $this->avisos[] = "Aluno #$antigo ($nome): CPF \"" . trim((string) $a['cpf']) . '" inválido — não conseguirá entrar no app até corrigir o cadastro.';
                $cpf = substr($cpf, 0, 11);
            } elseif (!self::cpfValido($cpf)) {
                $this->avisos[] = "Aluno #$antigo ($nome): CPF $cpf com dígito verificador inválido (provável erro de digitação).";
            }
            $porCpf[$cpf . '|' . $nascimento][Str::lower($nome)][] = "#$antigo $nome";

            $turma = $this->turma((string) $a['escola_id'], (string) $a['turma_id'], $grupos, $nomesTurma, $series);
            $id = $this->obter("aluno:$antigo", 'alunos', function () use ($a, $nome, $cpf, $nascimento, $escola, $turma, &$criados): int {
                $existente = Db::value(
                    'SELECT id FROM alunos WHERE cpf = ? AND data_nascimento = ? AND LOWER(nome) = LOWER(?) AND deleted_at IS NULL LIMIT 1',
                    [$cpf, $nascimento, $nome]
                );
                if ($existente !== null) {
                    $this->contar(isset($criados[(int) $existente])
                        ? 'Cadastros repetidos do mesmo aluno unificados (mesmo nome, CPF e nascimento)'
                        : 'Alunos que já estavam no sistema novo reaproveitados');
                    return (int) $existente;
                }
                $outro = Db::one('SELECT id, nome FROM alunos WHERE cpf = ? AND data_nascimento = ? AND deleted_at IS NULL LIMIT 1', [$cpf, $nascimento]);
                if ($outro !== null && !isset($criados[(int) $outro['id']])) {
                    $this->avisos[] = "Aluno $nome tem o mesmo CPF e nascimento de \"{$outro['nome']}\", que já estava no sistema novo (dados de demonstração?). No login entra só um deles.";
                }
                $this->contar('Alunos criados');
                $id = Db::insert('alunos', [
                    'nome' => $nome,
                    'sexo' => Str::orNull(Str::lower(self::texto($a['sexo']))),
                    'data_nascimento' => $nascimento,
                    'cpf' => $cpf,
                    'matricula' => Str::orNull($a['matricula']),
                    'turma_id' => $turma,
                    'escola_id' => $escola,
                    'telefone' => Str::orNull($a['telefone']),
                    'responsavel' => Str::orNull($a['responsavel']),
                    'contato_responsavel' => Str::orNull($a['contato_responsavel']),
                    'created_at' => self::data($a['criado_em']),
                    'updated_at' => self::data($a['alterado_em']),
                    'deleted_at' => self::dataOuNull($a['excluido_em']),
                ]);
                $criados[$id] = true;
                return $id;
            });
            $this->aluno[$antigo] = ['id' => $id, 'escola' => (string) $a['escola_id']];
        }
        foreach ($porCpf as $chave => $pessoas) {
            if (count($pessoas) > 1) {
                $nomes = array_map(static fn (array $cadastros) => $cadastros[0], $pessoas);
                $this->avisos[] = 'Mesmo CPF e nascimento (' . (explode('|', $chave)[0] ?: 'sem CPF') . ') em ' . count($nomes) . ' alunos diferentes: '
                    . implode(', ', $nomes) . '. No login entra só um deles; corrija o CPF dos demais.';
            }
        }
    }

    /**
     * @param array<string, array{series: list<string>, turnos: list<string>}> $grupos
     * @param array<string, string> $nomesTurma
     * @param array<string, string> $series
     */
    private function turma(string $escolaAntiga, string $turmaAntiga, array $grupos, array $nomesTurma, array $series): ?int
    {
        if ($turmaAntiga === '' || $turmaAntiga === '0') {
            return null;
        }
        $escola = $this->escola[$escolaAntiga];
        $grupo = $grupos["$escolaAntiga:$turmaAntiga"];
        $serie = $series[self::maisComum($grupo['series'])] ?? 'Série não informada';
        $nome = $nomesTurma[$turmaAntiga] ?? "$serie - turma $turmaAntiga";
        return $this->obter("turma:$escolaAntiga:$turmaAntiga", 'turmas', function () use ($escola, $nome, $serie, $grupo, $nomesTurma, $turmaAntiga): int {
            $existente = Db::value('SELECT id FROM turmas WHERE escola_id = ? AND nome = ?', [$escola, $nome]);
            if ($existente !== null) {
                return (int) $existente;
            }
            if (!isset($nomesTurma[$turmaAntiga])) {
                $this->contar('Turmas sem nome no backup (criadas como "série - turma N"; renomeie no painel)');
            }
            $this->contar('Turmas criadas');
            $now = Db::now();
            return Db::insert('turmas', [
                'nome' => $nome,
                'serie_id' => $this->serie($serie),
                'turno' => self::maisComum($grupo['turnos']),
                'escola_id' => $escola,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    private function serie(string $descricao): int
    {
        $id = Db::value('SELECT id FROM series WHERE descricao = ?', [$descricao]);
        if ($id !== null) {
            return (int) $id;
        }
        $now = Db::now();
        return Db::insert('series', ['descricao' => $descricao, 'created_at' => $now, 'updated_at' => $now]);
    }

    // ------------------------------------------------------------ questionários

    /** @return array<string, array<string, true>> questionário antigo -> escolas antigas onde foi copiado */
    private function questionarios(): array
    {
        $escolasPorQuestionario = [];
        foreach ($this->rows('escola_categoria_pergunta') as $link) {
            $escolasPorQuestionario[(string) $link['categoria_pergunta_id']][(string) $link['escola_id']] = true;
        }
        foreach ([...$this->rows('respostas'), ...$this->rows('respostas_abertas')] as $r) {
            $questionario = $this->questionarioDaPergunta[(string) $r['pergunta_id']] ?? null;
            $aluno = $this->aluno[(string) $r['aluno_id']] ?? null;
            if ($questionario !== null && $aluno !== null) {
                $escolasPorQuestionario[$questionario][$aluno['escola']] = true;
            }
        }

        $copiados = [];
        foreach ($this->rows('categoria_pergunta') as $q) {
            $antigo = (string) $q['categoria_pergunta_id'];
            $nome = self::texto($q['descricao_categoria']);
            if ($q['excluido_em'] !== null) {
                continue;
            }
            $escolas = array_filter(
                array_keys($escolasPorQuestionario[$antigo] ?? []),
                fn (string $e) => isset($this->escola[$e])
            );
            if ($escolas === []) {
                $this->avisos[] = "Questionário \"$nome\" não está ligado a nenhuma escola; não importado.";
                continue;
            }
            foreach ($escolas as $escola) {
                $this->copiarQuestionario($q, (string) $escola);
                $copiados[$antigo][(string) $escola] = true;
            }
        }
        return $copiados;
    }

    /** @param array<string, ?string> $q */
    private function copiarQuestionario(array $q, string $escolaAntiga): void
    {
        $antigo = (string) $q['categoria_pergunta_id'];
        $escola = $this->escola[$escolaAntiga];
        $nome = self::texto($q['descricao_categoria']);
        $criado = self::data($q['criado_em']);
        $alterado = self::data($q['alterado_em']);

        $questionario = $this->obter("questionario:$antigo:$escolaAntiga", 'questionarios', function () use ($escola, $nome, $criado, $alterado): int {
            $this->contar('Questionários criados (uma cópia por escola)');
            return Db::insert('questionarios', [
                'escola_id' => $escola,
                'pesquisador_id' => $this->pesquisador,
                'nome' => $nome,
                'descricao' => 'Importado do sistema anterior.',
                'status' => 'publicado',
                'versao' => 1,
                'compartilhado_na_escola' => 1,
                'created_at' => $criado,
                'updated_at' => $alterado,
            ]);
        });
        $this->ids["questionario-de:$antigo:$escolaAntiga"] = $questionario;

        $categoria = $this->obter("categoria:$antigo:$escolaAntiga", 'categorias', fn (): int => Db::insert('categorias', [
            'questionario_id' => $questionario,
            'escola_id' => $escola,
            'nome' => $nome,
            'ordem' => 1,
            'cor' => preg_match('/#[0-9a-f]{3,8}\b/i', (string) $q['cor'], $cor) ? $cor[0] : null,
            'created_at' => $criado,
            'updated_at' => $alterado,
        ]));

        $subcategorias = [];
        foreach ($this->ordenados($this->rows('subcategoria_pergunta'), 'subcategoria_pergunta_id') as $s) {
            if ((string) $s['categoria_pergunta_id'] !== $antigo || $s['excluido_em'] !== null) {
                continue;
            }
            $sub = (string) $s['subcategoria_pergunta_id'];
            $subcategorias[$sub] = $this->obter("subcategoria:$sub:$escolaAntiga", 'subcategorias', fn (): int => Db::insert('subcategorias', [
                'categoria_id' => $categoria,
                'escola_id' => $escola,
                'nome' => self::texto($s['descricao_subcategoria']),
                'ordem' => (int) $s['ordem'],
                'created_at' => self::data($s['criado_em']),
                'updated_at' => self::data($s['alterado_em']),
            ]));
        }

        $opcoesPorPergunta = [];
        foreach ($this->ordenados($this->rows('opcoes_resposta'), 'opcoes_resposta_id') as $o) {
            $opcoesPorPergunta[(string) $o['pergunta_id']][] = $o;
        }
        $ordem = 0;
        foreach ($this->ordenados($this->rows('pergunta'), 'pergunta_id') as $p) {
            if ((string) $p['categoria_pergunta_id'] !== $antigo || $p['excluido_em'] !== null) {
                continue;
            }
            $perguntaAntiga = (string) $p['pergunta_id'];
            $opcoes = $opcoesPorPergunta[$perguntaAntiga] ?? [];
            $objetiva = array_filter($opcoes, static fn (array $o) => $o['excluido_em'] === null) !== [];
            $imagem = Str::orNull($p['imagem']);
            $ordem++;
            $pergunta = $this->obter("pergunta:$perguntaAntiga:$escolaAntiga", 'perguntas', function () use ($p, $categoria, $subcategorias, $escola, $objetiva, $imagem, $ordem): int {
                if ($imagem !== null && !str_starts_with($imagem, 'http')) {
                    $this->contar('Imagens de perguntas não migradas (arquivo ficou no servidor antigo)');
                }
                return Db::insert('perguntas', [
                    'categoria_id' => $categoria,
                    'subcategoria_id' => $subcategorias[(string) $p['subcategoria_pergunta_id']] ?? null,
                    'escola_id' => $escola,
                    'tipo' => $objetiva ? 'multipla_escolha' : 'texto',
                    'texto' => self::texto($p['descricao_pergunta']),
                    'ordem' => $ordem,
                    'obrigatoria' => $objetiva ? 1 : 0,
                    'peso' => '1.00',
                    'imagem' => $imagem !== null && str_starts_with($imagem, 'http') ? $imagem : null,
                    'created_at' => self::data($p['criado_em']),
                    'updated_at' => self::data($p['alterado_em']),
                ]);
            });
            foreach ($opcoes as $i => $o) {
                $this->obter("opcao:{$o['opcoes_resposta_id']}:$escolaAntiga", 'opcoes_resposta', fn (): int => Db::insert('opcoes_resposta', [
                    'pergunta_id' => $pergunta,
                    'escola_id' => $escola,
                    'descricao' => self::texto($o['descricao_opcoes_resposta']),
                    'pontuacao' => (int) $o['pontuacao'],
                    'ordem' => $i + 1,
                    'created_at' => self::data($o['criado_em']),
                    'updated_at' => self::data($o['alterado_em']),
                    'deleted_at' => self::dataOuNull($o['excluido_em']),
                ]));
            }
        }

        foreach ($this->rows('regras_classificacao') as $r) {
            if ((string) $r['categoria_pergunta_id'] !== $antigo || $r['excluido_em'] !== null) {
                continue;
            }
            $status = Str::lower(self::texto($r['status']));
            $this->obter("regra:{$r['regra_classificacao_id']}:$escolaAntiga", 'regras_classificacao', fn (): int => Db::insert('regras_classificacao', [
                'escola_id' => $escola,
                'categoria_id' => $categoria,
                'min_score' => (string) (int) $r['min_score'],
                'max_score' => (string) (int) $r['max_score'],
                'rotulo' => Str::orNull($r['titulo']) ?? self::ROTULO[$status] ?? ($status ?: 'Sem rótulo'),
                'descricao' => Str::orNull($r['interpretacao']),
                'nivel' => self::NIVEL[$status] ?? null,
                'created_at' => self::data($r['criado_em']),
                'updated_at' => self::data($r['alterado_em']),
            ]));
        }
    }

    // ------------------------------------------------------------ respostas e resultados

    /**
     * @param array<string, array<string, true>> $copiados
     * @return array<string, int> "questionário:escola" antigos -> aplicação nova
     */
    private function respostas(array $copiados): array
    {
        $linhas = [];
        $ignoradas = 0;
        $fontes = [['respostas', 'resposta_id', 'opcoes_resposta_id'], ['respostas_abertas', 'resposta_aberta_id', null]];
        foreach ($fontes as [$tabela, $pk, $colunaOpcao]) {
            // Mais recentes primeiro: se a mesma pergunta foi respondida duas vezes, fica a última.
            $rows = $this->rows($tabela);
            usort($rows, static fn (array $a, array $b) => (int) $b[$pk] <=> (int) $a[$pk]);
            foreach ($rows as $r) {
                $aluno = $this->aluno[(string) $r['aluno_id']] ?? null;
                $questionario = $this->questionarioDaPergunta[(string) $r['pergunta_id']] ?? null;
                $pergunta = $aluno === null ? null : ($this->ids["pergunta:{$r['pergunta_id']}:{$aluno['escola']}"] ?? null);
                if ($r['excluido_em'] !== null || $pergunta === null || !isset($copiados[$questionario][$aluno['escola']])) {
                    $ignoradas++;
                    continue;
                }
                $opcao = $colunaOpcao === null ? null : ($this->ids["opcao:{$r[$colunaOpcao]}:{$aluno['escola']}"] ?? null);
                $texto = $colunaOpcao === null ? Str::orNull($r['resposta_aberta']) : null;
                if ($opcao === null && $texto === null) {
                    $ignoradas++;
                    continue;
                }
                $linhas["$questionario:{$aluno['escola']}"][] = [
                    'aluno' => $aluno['id'], 'pergunta' => $pergunta, 'opcao' => $opcao, 'texto' => $texto,
                    'quando' => self::data($r['criado_em']),
                ];
            }
        }
        if ($ignoradas > 0) {
            $this->contar('Respostas ignoradas (questionário/pergunta excluídos no sistema anterior)', $ignoradas);
        }

        $aplicacoes = [];
        foreach ($linhas as $chave => $respostas) {
            [$questionario, $escolaAntiga] = explode(':', $chave);
            $datas = array_column($respostas, 'quando');
            $aplicacoes[$chave] = $this->obter("aplicacao:$chave", 'aplicacoes_questionario', function () use ($questionario, $escolaAntiga, $datas): int {
                $this->contar('Aplicações históricas criadas (encerradas)');
                return Db::insert('aplicacoes_questionario', [
                    'questionario_id' => $this->ids["questionario-de:$questionario:$escolaAntiga"],
                    'escola_id' => $this->escola[$escolaAntiga],
                    'alvo_tipo' => 'escola',
                    'status' => 'encerrada',
                    'inicia_em' => min($datas),
                    'termina_em' => max($datas),
                    'created_at' => min($datas),
                    'updated_at' => max($datas),
                ]);
            });
            foreach (array_chunk($respostas, 200) as $lote) {
                $valores = [];
                foreach ($lote as $r) {
                    array_push($valores, $aplicacoes[$chave], $r['aluno'], $r['pergunta'], $r['opcao'], $r['texto'], $r['quando'], $r['quando'], $r['quando']);
                }
                $inseridas = Db::run(
                    'INSERT INTO respostas (aplicacao_id, aluno_id, pergunta_id, opcao_id, texto, responded_at, created_at, updated_at) VALUES '
                    . implode(', ', array_fill(0, count($lote), '(?, ?, ?, ?, ?, ?, ?, ?)'))
                    . ' ON DUPLICATE KEY UPDATE aplicacao_id = aplicacao_id',
                    $valores
                )->rowCount();
                $this->contar('Respostas importadas', $inseridas);
            }
        }
        return $aplicacoes;
    }

    /** Calcula o resultado de quem respondeu todas as perguntas de múltipla escolha. */
    private function resultados(array $aplicacoes): void
    {
        foreach ($aplicacoes as $aplicacao) {
            $total = (int) Db::value(
                "SELECT COUNT(p.id) FROM aplicacoes_questionario a
                 JOIN categorias c ON c.questionario_id = a.questionario_id AND c.deleted_at IS NULL
                 JOIN perguntas p ON p.categoria_id = c.id AND p.deleted_at IS NULL AND p.tipo = 'multipla_escolha'
                 WHERE a.id = ?",
                [$aplicacao]
            );
            $alunos = Db::all(
                "SELECT r.aluno_id, MAX(r.responded_at) AS fim,
                        COUNT(DISTINCT CASE WHEN p.tipo = 'multipla_escolha' THEN r.pergunta_id END) AS objetivas
                 FROM respostas r JOIN perguntas p ON p.id = r.pergunta_id
                 WHERE r.aplicacao_id = ? GROUP BY r.aluno_id",
                [$aplicacao]
            );
            foreach ($alunos as $a) {
                if ($total === 0 || (int) $a['objetivas'] < $total) {
                    $this->contar('Alunos com questionário incompleto (respostas mantidas, sem resultado)');
                    continue;
                }
                ResultadoService::calcular($aplicacao, (int) $a['aluno_id']);
                Db::run(
                    'UPDATE resultados SET created_at = ?, updated_at = ? WHERE aplicacao_id = ? AND aluno_id = ?',
                    [$a['fim'], $a['fim'], $aplicacao, (int) $a['aluno_id']]
                );
                $this->contar('Resultados calculados (questionários completos)');
            }
        }
    }

    // ------------------------------------------------------------ apoio

    /** Reaproveita o registro já importado (se ainda existir) ou cria um novo. */
    private function obter(string $chave, string $tabela, callable $criar): int
    {
        if (isset($this->ids[$chave])) {
            return $this->ids[$chave];
        }
        $id = $this->mapa[$chave] ?? null;
        if ($id !== null && Db::value("SELECT id FROM `$tabela` WHERE id = ?", [$id]) === null) {
            $id = null;
        }
        if ($id === null) {
            $id = $criar();
            Db::run('INSERT INTO legado_map (chave, novo_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE novo_id = VALUES(novo_id)', [$chave, $id]);
        } else {
            $this->contar('Registros já importados antes (reaproveitados)');
        }
        return $this->ids[$chave] = $id;
    }

    private function contar(string $item, int $quantidade = 1): void
    {
        $this->resumo[$item] = ($this->resumo[$item] ?? 0) + $quantidade;
    }

    /** @return list<array<string, ?string>> */
    private function rows(string $tabela): array
    {
        return $this->t[$tabela] ?? [];
    }

    /**
     * @param list<array<string, ?string>> $rows
     * @return list<array<string, ?string>>
     */
    private function ordenados(array $rows, string $pk): array
    {
        usort($rows, static fn (array $a, array $b) => [(int) ($a['ordem'] ?? 0), (int) $a[$pk]] <=> [(int) ($b['ordem'] ?? 0), (int) $b[$pk]]);
        return $rows;
    }

    private static function texto(?string $valor): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $valor));
    }

    private static function data(?string $valor): string
    {
        return self::dataOuNull($valor) ?? Db::now();
    }

    private static function dataOuNull(?string $valor): ?string
    {
        $valor = trim((string) $valor);
        return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $valor) && !str_starts_with($valor, '0000') ? $valor : null;
    }

    private static function turno(?string $periodo): string
    {
        $turno = Str::lower(self::texto($periodo));
        return in_array($turno, self::TURNOS, true) ? $turno : 'matutino';
    }

    /** @param list<string> $valores */
    private static function maisComum(array $valores): string
    {
        $contagem = array_count_values($valores);
        arsort($contagem);
        return (string) array_key_first($contagem);
    }

    /** "Sinop - MT" -> ["Sinop", "MT"]. Sem UF informada, assume MT. */
    private static function cidade(?string $cidade): array
    {
        $nome = self::texto($cidade);
        $uf = 'MT';
        if (preg_match('/^(.+?)\s*[-\/]\s*([A-Za-z]{2})$/u', $nome, $m)) {
            [$nome, $uf] = [trim($m[1]), strtoupper($m[2])];
        } elseif (preg_match('/^[A-Za-z]{2}$/', $nome)) {
            [$nome, $uf] = ['', strtoupper($nome)];
        }
        if ($nome !== '' && ($nome === Str::lower($nome) || $nome === Str::upper($nome))) {
            $nome = mb_convert_case(Str::lower($nome), MB_CASE_TITLE, 'UTF-8');
        }
        return [$nome !== '' ? $nome : 'Não informado', $uf];
    }

    private static function cpfValido(string $cpf): bool
    {
        if (!preg_match('/^\d{11}$/', $cpf) || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }
        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $cpf[$i] * ($t + 1 - $i);
            }
            if ((int) $cpf[$t] !== ($soma * 10) % 11 % 10) {
                return false;
            }
        }
        return true;
    }
}
