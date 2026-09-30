<?php

declare(strict_types=1);

namespace Vise\Support;

/** Leitura unificada de planilhas enviadas pelo usuário (.xlsx ou .csv). */
final class Planilha
{
    /**
     * @return array{cabecalho: list<string>, linhas: array<int, list<string>>, formato: string}
     *         linhas indexadas pelo número da linha no arquivo (cabeçalho excluído)
     */
    public static function ler(string $conteudo): array
    {
        if ($conteudo === '' || trim($conteudo) === '') {
            throw new \RuntimeException('O arquivo está vazio.');
        }
        if (str_starts_with($conteudo, "\xD0\xCF\x11\xE0")) {
            throw new \RuntimeException('Formato .xls (Excel 97-2003) não é aceito. No Excel, use "Salvar como" → Pasta de Trabalho do Excel (.xlsx) ou CSV.');
        }
        if (str_starts_with($conteudo, "PK\x03\x04")) {
            $linhas = XlsxReader::ler($conteudo);
            $formato = 'xlsx';
        } else {
            $linhas = self::csv($conteudo);
            $formato = 'csv';
        }

        $cabecalho = null;
        $dados = [];
        foreach ($linhas as $numero => $valores) {
            $vazia = trim(implode('', $valores)) === '';
            if ($cabecalho === null) {
                if (!$vazia) {
                    $cabecalho = array_map(static fn ($v) => trim((string) $v), $valores);
                }
                continue;
            }
            if (!$vazia) {
                $dados[$numero] = array_map(static fn ($v) => trim((string) $v), $valores);
            }
        }
        if ($cabecalho === null) {
            throw new \RuntimeException('O arquivo está vazio.');
        }
        return ['cabecalho' => $cabecalho, 'linhas' => $dados, 'formato' => $formato];
    }

    /** Normaliza um nome de coluna: minúsculas, sem acentos, espaços e hífens viram "_". */
    public static function chave(string $texto): string
    {
        $texto = Str::lower(trim($texto));
        $texto = strtr($texto, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'ê' => 'e', 'è' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'º' => 'o', 'ª' => 'a',
        ]);
        $texto = (string) preg_replace('/[^a-z0-9]+/', '_', $texto);
        return trim($texto, '_');
    }

    /** @return array<int, list<string>> */
    private static function csv(string $conteudo): array
    {
        if (str_starts_with($conteudo, "\xEF\xBB\xBF")) {
            $conteudo = substr($conteudo, 3);
        } elseif (function_exists('mb_check_encoding') && !mb_check_encoding($conteudo, 'UTF-8')) {
            // CSV salvo pelo Excel no Windows costuma vir em Windows-1252.
            $conteudo = (string) mb_convert_encoding($conteudo, 'UTF-8', 'Windows-1252');
        }
        $amostra = substr($conteudo, 0, 4096);
        $delimitador = substr_count($amostra, ';') > substr_count($amostra, ',') ? ';' : ',';
        if (substr_count($amostra, "\t") > max(substr_count($amostra, ';'), substr_count($amostra, ','))) {
            $delimitador = "\t";
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $conteudo);
        rewind($handle);
        $linhas = [];
        $numero = 0;
        while (($valores = fgetcsv($handle, 0, $delimitador, '"', '')) !== false) {
            $numero++;
            if ($valores === [null]) {
                continue;
            }
            $linhas[$numero] = array_map(static fn ($v) => (string) $v, $valores);
        }
        fclose($handle);
        return $linhas;
    }
}
