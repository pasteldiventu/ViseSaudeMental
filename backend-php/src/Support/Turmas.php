<?php

declare(strict_types=1);

namespace Vise\Support;

use Vise\Db;

/**
 * Turma = série + identificação ("6º Ano" + "A"). O banco guarda só a identificação em turmas.nome;
 * para exibir, use rotulo()/sql().
 */
final class Turmas
{
    private const SERIE = '/^\s*(\d{1,2})\s*[º°ªo]?\s*(ano|s[ée]rie|per[ií]odo|etapa|termo)\b[\s\-–·:]*(.*)$/iu';

    public static function rotulo(?string $serie, ?string $nome): string
    {
        return trim(trim((string) $serie) . ' ' . trim((string) $nome));
    }

    /** Expressão SQL do rótulo completo ("6º Ano A") para a tabela turmas com o alias informado; NULL sem turma. */
    public static function sql(string $alias = 'tu'): string
    {
        return "IF($alias.id IS NULL, NULL, CONCAT_WS(' ', (SELECT s_rot.descricao FROM series s_rot WHERE s_rot.id = $alias.serie_id), $alias.nome))";
    }

    /**
     * Separa um nome completo em série e identificação: "6º ano B" => ["6º Ano", "B"].
     *
     * @return array{0: ?string, 1: string}
     */
    public static function separar(string $texto): array
    {
        $texto = trim($texto);
        if (preg_match(self::SERIE, $texto, $m)) {
            return [self::serie($m[1], $m[2]), trim($m[3])];
        }
        return [null, $texto];
    }

    /** Remove a série do começo do nome: ("6º Ano B", "6º Ano") => "B". */
    public static function semSerie(string $nome, ?string $serie): string
    {
        $nome = trim($nome);
        $serie = trim((string) $serie);
        if ($serie === '') {
            return $nome;
        }
        $resto = null;
        if (Str::lower(substr($nome, 0, strlen($serie))) === Str::lower($serie)) {
            $resto = substr($nome, strlen($serie));
        } else {
            [$serieDoNome, $restoDoNome] = self::separar($nome);
            [$serieNormal] = self::separar($serie);
            if ($serieDoNome !== null && $serieNormal !== null && Str::lower($serieDoNome) === Str::lower($serieNormal)) {
                $resto = $restoDoNome;
            }
        }
        if ($resto === null) {
            return $nome;
        }
        $resto = trim((string) preg_replace('/^[\s\-–·:]+/u', '', $resto));
        return $resto === '' ? $nome : $resto;
    }

    /**
     * Turma da escola a partir do texto digitado: "6º Ano A", ou "A" com a série informada à parte.
     * Só a identificação ("A") também serve, desde que não exista em mais de uma série.
     */
    public static function encontrar(int $escolaId, string $turma, string $serie = ''): ?int
    {
        $turma = trim($turma);
        $serie = trim($serie);
        if ($turma === '') {
            return null;
        }
        $procurado = self::chave($serie === '' ? $turma : self::rotulo($serie, self::semSerie($turma, $serie)));
        $candidatos = Db::all(
            'SELECT t.id, t.nome, s.descricao AS serie FROM turmas t LEFT JOIN series s ON s.id = t.serie_id WHERE t.escola_id = ? ORDER BY t.deleted_at IS NOT NULL, t.id',
            [$escolaId]
        );
        $soNome = [];
        foreach ($candidatos as $c) {
            if (self::chave(self::rotulo($c['serie'], $c['nome'])) === $procurado) {
                return (int) $c['id'];
            }
            if ($serie === '' && Str::lower(trim((string) $c['nome'])) === Str::lower($turma)) {
                $soNome[] = $c;
            }
        }
        if (count($soNome) > 1) {
            throw new \InvalidArgumentException(
                "A turma '$turma' existe em mais de uma série (" . implode(', ', array_map(static fn ($c) => self::rotulo($c['serie'], $c['nome']), $soNome)) . '). Informe a série.'
            );
        }
        return $soNome === [] ? null : (int) $soNome[0]['id'];
    }

    /** Série pela descrição ("6 ano" encontra "6º Ano"); cria se não existir. */
    public static function serieId(string $descricao): int
    {
        $descricao = trim($descricao);
        [$normal, $resto] = self::separar($descricao);
        if ($normal !== null && $resto === '') {
            $descricao = $normal;
        }
        foreach (Db::all('SELECT id, descricao FROM series ORDER BY id') as $s) {
            if (self::chave((string) $s['descricao']) === self::chave($descricao)) {
                return (int) $s['id'];
            }
        }
        $now = Db::now();
        return Db::insert('series', ['descricao' => $descricao, 'created_at' => $now, 'updated_at' => $now]);
    }

    private static function chave(string $rotulo): string
    {
        [$serie, $resto] = self::separar($rotulo);
        return Str::lower(($serie ?? '') . '|' . preg_replace('/\s+/u', ' ', $resto));
    }

    private static function serie(string $numero, string $palavra): string
    {
        $palavra = Str::lower($palavra);
        $palavra = match (true) {
            str_starts_with($palavra, 's') => 'Série',
            str_starts_with($palavra, 'per') => 'Período',
            default => Str::upper(substr($palavra, 0, 1)) . substr($palavra, 1),
        };
        return (int) $numero . 'º ' . $palavra;
    }
}
