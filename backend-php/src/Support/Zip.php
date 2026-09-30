<?php

declare(strict_types=1);

namespace Vise\Support;

/**
 * ZIP mínimo (sem a extensão zip), suficiente para arquivos .xlsx.
 * Grava com deflate quando a zlib existe; lê entradas "stored" e "deflate".
 */
final class Zip
{
    private const LIMITE_DESCOMPACTADO = 64 * 1024 * 1024;

    /** @param array<string, string> $arquivos nome => conteúdo */
    public static function montar(array $arquivos): string
    {
        [$hora, $data] = self::dataDos(time());
        $corpo = '';
        $central = '';
        foreach ($arquivos as $nome => $conteudo) {
            $crc = crc32($conteudo);
            $tamanho = strlen($conteudo);
            $compactado = function_exists('gzdeflate') ? gzdeflate($conteudo, 6) : false;
            [$metodo, $dados] = ($compactado !== false && strlen($compactado) < $tamanho) ? [8, $compactado] : [0, $conteudo];
            $offset = strlen($corpo);
            $corpo .= "PK\x03\x04"
                . pack('vvvvvVVVvv', 20, 0x0800, $metodo, $hora, $data, $crc, strlen($dados), $tamanho, strlen($nome), 0)
                . $nome . $dados;
            $central .= "PK\x01\x02"
                . pack('vvvvvvVVVvvvvvVV', 20, 20, 0x0800, $metodo, $hora, $data, $crc, strlen($dados), $tamanho, strlen($nome), 0, 0, 0, 0, 0, $offset)
                . $nome;
        }
        $total = count($arquivos);
        return $corpo . $central . "PK\x05\x06" . pack('vvvvVVv', 0, 0, $total, $total, strlen($central), strlen($corpo), 0);
    }

    /**
     * @param callable(string): bool|null $filtro decide quais entradas descompactar
     * @return array<string, string>
     */
    public static function ler(string $binario, ?callable $filtro = null): array
    {
        $fim = strrpos($binario, "PK\x05\x06");
        if ($fim === false || strlen($binario) < $fim + 22) {
            throw new \RuntimeException('Arquivo compactado inválido ou corrompido.');
        }
        $eocd = unpack('vdisco/vdiscoCd/vqtdDisco/vqtd/Vtamanho/Voffset', substr($binario, $fim + 4, 16));
        $pos = (int) $eocd['offset'];
        $arquivos = [];
        for ($i = 0; $i < $eocd['qtd']; $i++) {
            if (substr($binario, $pos, 4) !== "PK\x01\x02") {
                throw new \RuntimeException('Arquivo compactado inválido ou corrompido.');
            }
            $c = unpack(
                'vfeito/vversao/vflags/vmetodo/vhora/vdata/Vcrc/Vcomp/Vtam/vnome/vextra/vcomentario/vdisco/vinterno/Vexterno/Voffset',
                substr($binario, $pos + 4, 42)
            );
            $nome = substr($binario, $pos + 46, $c['nome']);
            $pos += 46 + $c['nome'] + $c['extra'] + $c['comentario'];
            if (str_ends_with($nome, '/') || ($filtro !== null && !$filtro($nome))) {
                continue;
            }
            $local = unpack('vnome/vextra', substr($binario, $c['offset'] + 26, 4));
            $dados = substr($binario, $c['offset'] + 30 + $local['nome'] + $local['extra'], $c['comp']);
            if ($c['metodo'] === 0) {
                $arquivos[$nome] = $dados;
            } elseif ($c['metodo'] === 8 && function_exists('gzinflate')) {
                $conteudo = @gzinflate($dados, self::LIMITE_DESCOMPACTADO);
                if ($conteudo === false) {
                    throw new \RuntimeException('Não foi possível descompactar o arquivo.');
                }
                $arquivos[$nome] = $conteudo;
            } else {
                throw new \RuntimeException('Formato de compactação não suportado.');
            }
        }
        return $arquivos;
    }

    /** @return array{0: int, 1: int} */
    private static function dataDos(int $timestamp): array
    {
        $d = getdate($timestamp);
        $hora = ($d['hours'] << 11) | ($d['minutes'] << 5) | intdiv($d['seconds'], 2);
        $data = (max(0, $d['year'] - 1980) << 9) | ($d['mon'] << 5) | $d['mday'];
        return [$hora, $data];
    }
}
