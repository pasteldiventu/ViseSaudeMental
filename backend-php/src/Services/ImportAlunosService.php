<?php

declare(strict_types=1);

namespace Vise\Services;

use Vise\Db;
use Vise\Support\Str;

/** Importação de alunos por CSV (coluna "escola" aceita nome ou INEP). */
final class ImportAlunosService
{
    public const COLUNAS = [
        'escola', 'nome', 'cpf', 'data_nascimento', 'sexo', 'matricula',
        'turma_nome', 'responsavel', 'contato_responsavel',
    ];

    /**
     * @param list<int>|null $escolasPermitidas null = sem restrição (admin geral)
     * @return array{success: int, errors: list<string>}
     */
    public static function importar(string $conteudo, ?array $escolasPermitidas = null): array
    {
        if (str_starts_with($conteudo, "\xEF\xBB\xBF")) {
            $conteudo = substr($conteudo, 3);
        }
        if (trim($conteudo) === '') {
            throw new \RuntimeException('O arquivo CSV está vazio.');
        }
        $amostra = substr($conteudo, 0, 4096);
        $delimitador = substr_count($amostra, ';') > substr_count($amostra, ',') ? ';' : ',';

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $conteudo);
        rewind($handle);

        $cabecalho = fgetcsv($handle, 0, $delimitador, '"', '');
        if ($cabecalho === false || $cabecalho === [null]) {
            throw new \RuntimeException('O arquivo CSV está vazio.');
        }
        $nomes = array_map(static fn ($nome) => Str::lower(trim((string) $nome)), $cabecalho);
        $faltantes = array_diff(self::COLUNAS, $nomes);
        if ($faltantes !== []) {
            sort($faltantes);
            throw new \RuntimeException('Colunas obrigatórias ausentes: ' . implode(', ', $faltantes));
        }

        $sucesso = 0;
        $erros = [];
        $linha = 1;
        while (($valores = fgetcsv($handle, 0, $delimitador, '"', '')) !== false) {
            $linha++;
            if ($valores === [null] || trim(implode('', array_map('strval', $valores))) === '') {
                continue;
            }
            $row = [];
            foreach ($nomes as $index => $nome) {
                $row[$nome] = isset($valores[$index]) ? (string) $valores[$index] : '';
            }
            try {
                Db::transaction(static fn () => self::criar($row, $escolasPermitidas));
                $sucesso++;
            } catch (\Throwable $error) {
                $erros[] = "Linha $linha: " . $error->getMessage();
            }
        }
        fclose($handle);
        return ['success' => $sucesso, 'errors' => $erros];
    }

    /** @return array<string, mixed> */
    private static function resolverEscola(string $valor): array
    {
        $valor = trim($valor);
        if ($valor === '') {
            throw new \InvalidArgumentException('escola não informada.');
        }
        $digits = Str::digits($valor);
        $escola = null;
        if ($digits !== '') {
            $escola = Db::one('SELECT * FROM escolas WHERE inep = ?', [$digits]);
            if ($escola === null && $digits !== $valor) {
                $escola = Db::one('SELECT * FROM escolas WHERE inep = ?', [$valor]);
            }
        }
        $escola ??= Db::one('SELECT * FROM escolas WHERE nome = ?', [$valor]);
        if ($escola === null) {
            throw new \InvalidArgumentException("escola '$valor' não encontrada (use nome ou INEP).");
        }
        return $escola;
    }

    /**
     * @param array<string, string> $row
     * @param list<int>|null $escolasPermitidas
     */
    private static function criar(array $row, ?array $escolasPermitidas): void
    {
        $escola = self::resolverEscola($row['escola'] ?? '');
        if ($escolasPermitidas !== null && !in_array((int) $escola['id'], $escolasPermitidas, true)) {
            throw new \InvalidArgumentException("sem permissão para importar na escola '{$escola['nome']}'.");
        }
        $nome = trim($row['nome'] ?? '');
        $cpf = Str::digits($row['cpf'] ?? '');
        $turmaNome = trim($row['turma_nome'] ?? '');
        if ($nome === '') {
            throw new \InvalidArgumentException('nome não informado.');
        }
        if (strlen($cpf) !== 11) {
            throw new \InvalidArgumentException('CPF deve conter 11 dígitos.');
        }
        if ($turmaNome === '') {
            throw new \InvalidArgumentException('turma_nome não informado.');
        }
        $turmaId = Db::value('SELECT id FROM turmas WHERE escola_id = ? AND nome = ?', [$escola['id'], $turmaNome]);
        if ($turmaId === null) {
            throw new \InvalidArgumentException("turma '$turmaNome' não encontrada nesta escola.");
        }
        $nascimento = self::data($row['data_nascimento'] ?? '');
        $opcional = static fn (string $key) => Str::orNull($row[$key] ?? null);

        $existente = Db::one('SELECT * FROM alunos WHERE escola_id = ? AND cpf = ?', [$escola['id'], $cpf]);
        if ($existente === null) {
            $now = Db::now();
            Db::insert('alunos', [
                'escola_id' => $escola['id'],
                'turma_id' => $turmaId,
                'nome' => $nome,
                'cpf' => $cpf,
                'data_nascimento' => $nascimento,
                'sexo' => $opcional('sexo'),
                'matricula' => $opcional('matricula'),
                'responsavel' => $opcional('responsavel'),
                'contato_responsavel' => $opcional('contato_responsavel'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            return;
        }
        Db::update('alunos', [
            'turma_id' => $turmaId,
            'nome' => $nome,
            'data_nascimento' => $nascimento,
            'sexo' => $opcional('sexo') ?? $existente['sexo'],
            'matricula' => $opcional('matricula') ?? $existente['matricula'],
            'responsavel' => $opcional('responsavel') ?? $existente['responsavel'],
            'contato_responsavel' => $opcional('contato_responsavel') ?? $existente['contato_responsavel'],
        ], (int) $existente['id']);
    }

    private static function data(string $valor): string
    {
        $valor = trim($valor);
        foreach (['Y-m-d', 'd/m/Y'] as $formato) {
            $data = \DateTimeImmutable::createFromFormat('!' . $formato, $valor);
            $avisos = \DateTimeImmutable::getLastErrors();
            $semAvisos = $avisos === false || ($avisos['warning_count'] === 0 && $avisos['error_count'] === 0);
            if ($data !== false && $semAvisos) {
                return $data->format('Y-m-d');
            }
        }
        throw new \InvalidArgumentException('data_nascimento inválida; use AAAA-MM-DD ou DD/MM/AAAA.');
    }
}
