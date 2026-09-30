<?php

declare(strict_types=1);

namespace Vise\Support;

/** Lê a primeira aba de um .xlsx como texto (datas viram AAAA-MM-DD). */
final class XlsxReader
{
    private const FORMATOS_DATA_NATIVOS = [14, 15, 16, 17, 18, 19, 20, 21, 22, 27, 30, 36, 45, 46, 47, 50, 57];

    /** @return array<int, list<string>> linhas indexadas pelo número da linha no Excel */
    public static function ler(string $binario): array
    {
        $arquivos = Zip::ler($binario, static fn (string $nome) => str_starts_with($nome, 'xl/') && !str_starts_with($nome, 'xl/media/'));
        $planilha = self::primeiraAba($arquivos);
        if ($planilha === null) {
            throw new \RuntimeException('A planilha não tem nenhuma aba legível.');
        }
        $textos = self::textosCompartilhados($arquivos['xl/sharedStrings.xml'] ?? null);
        $estilosData = self::estilosDeData($arquivos['xl/styles.xml'] ?? null);

        $xml = self::xml($planilha);
        $linhas = [];
        $proxima = 1;
        foreach ($xml->sheetData->row ?? [] as $row) {
            $numero = isset($row['r']) ? (int) $row['r'] : $proxima;
            $proxima = $numero + 1;
            $valores = [];
            $proximaColuna = 0;
            foreach ($row->c as $c) {
                $coluna = isset($c['r']) ? self::indiceColuna((string) $c['r']) : $proximaColuna;
                $proximaColuna = $coluna + 1;
                $valores[$coluna] = self::valor($c, $textos, $estilosData);
            }
            if ($valores === []) {
                continue;
            }
            $lista = array_fill(0, max(array_keys($valores)) + 1, '');
            foreach ($valores as $coluna => $valor) {
                $lista[$coluna] = $valor;
            }
            $linhas[$numero] = $lista;
        }
        return $linhas;
    }

    /** @param array<string, string> $arquivos */
    private static function primeiraAba(array $arquivos): ?string
    {
        if (isset($arquivos['xl/workbook.xml'], $arquivos['xl/_rels/workbook.xml.rels'])) {
            $workbook = self::xml($arquivos['xl/workbook.xml']);
            $rels = self::xml($arquivos['xl/_rels/workbook.xml.rels']);
            $sheet = $workbook->sheets->sheet[0] ?? null;
            if ($sheet !== null) {
                $rid = (string) ($sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] ?? '');
                foreach ($rels->Relationship as $rel) {
                    if ((string) $rel['Id'] === $rid) {
                        $alvo = (string) $rel['Target'];
                        $caminho = str_starts_with($alvo, '/') ? ltrim($alvo, '/') : 'xl/' . $alvo;
                        if (isset($arquivos[$caminho])) {
                            return $arquivos[$caminho];
                        }
                    }
                }
            }
        }
        $abas = array_filter(array_keys($arquivos), static fn ($n) => preg_match('#^xl/worksheets/sheet\d+\.xml$#', $n));
        natsort($abas);
        return $abas === [] ? null : $arquivos[reset($abas)];
    }

    /** @return list<string> */
    private static function textosCompartilhados(?string $conteudo): array
    {
        if ($conteudo === null) {
            return [];
        }
        $textos = [];
        foreach (self::xml($conteudo)->si as $si) {
            $textos[] = self::textoRico($si);
        }
        return $textos;
    }

    /** @return array<int, true> índices de cellXfs que representam data */
    private static function estilosDeData(?string $conteudo): array
    {
        if ($conteudo === null) {
            return [];
        }
        $xml = self::xml($conteudo);
        $personalizados = [];
        foreach ($xml->numFmts->numFmt ?? [] as $fmt) {
            $codigo = (string) preg_replace('/"[^"]*"|\[[^\]]*\]|\\\\./', '', (string) $fmt['formatCode']);
            if (preg_match('/[dmyhs]/i', $codigo)) {
                $personalizados[(int) $fmt['numFmtId']] = true;
            }
        }
        $datas = [];
        $i = 0;
        foreach ($xml->cellXfs->xf ?? [] as $xf) {
            $id = (int) $xf['numFmtId'];
            if (in_array($id, self::FORMATOS_DATA_NATIVOS, true) || isset($personalizados[$id])) {
                $datas[$i] = true;
            }
            $i++;
        }
        return $datas;
    }

    /**
     * @param list<string> $textos
     * @param array<int, true> $estilosData
     */
    private static function valor(\SimpleXMLElement $c, array $textos, array $estilosData): string
    {
        $tipo = (string) ($c['t'] ?? 'n');
        $v = isset($c->v) ? (string) $c->v : '';
        switch ($tipo) {
            case 's':
                return trim($textos[(int) $v] ?? '');
            case 'inlineStr':
                return trim(isset($c->is) ? self::textoRico($c->is) : '');
            case 'b':
                return $v === '1' ? 'Sim' : 'Não';
            case 'str':
            case 'e':
                return trim($v);
        }
        if ($v === '' || !is_numeric($v)) {
            return trim($v);
        }
        $numero = (float) $v;
        if (isset($estilosData[(int) ($c['s'] ?? 0)]) && $numero > 0 && $numero < 2958466) {
            $segundos = (int) round(($numero - 25569) * 86400);
            $data = gmdate('Y-m-d', $segundos);
            return fmod($numero, 1.0) > 0.00001 ? gmdate('Y-m-d H:i', $segundos) : $data;
        }
        if (floor($numero) === $numero && abs($numero) < 1e15) {
            return sprintf('%.0f', $numero);
        }
        return rtrim(rtrim(sprintf('%.10F', $numero), '0'), '.');
    }

    private static function textoRico(\SimpleXMLElement $no): string
    {
        if (isset($no->t)) {
            return (string) $no->t;
        }
        $texto = '';
        foreach ($no->r as $r) {
            $texto .= (string) $r->t;
        }
        return $texto;
    }

    private static function indiceColuna(string $ref): int
    {
        $indice = 0;
        foreach (str_split((string) preg_replace('/[^A-Z]/', '', strtoupper($ref))) as $letra) {
            $indice = $indice * 26 + (ord($letra) - 64);
        }
        return max(0, $indice - 1);
    }

    private static function xml(string $conteudo): \SimpleXMLElement
    {
        if (
            preg_match('#xmlns:(\w+)="http://schemas\.openxmlformats\.org/(?:spreadsheetml/2006/main|package/2006/relationships)"#', $conteudo, $m)
            && !preg_match('#<\w+[^>]*\sxmlns="#', substr($conteudo, 0, 2000))
        ) {
            $conteudo = (string) preg_replace('#(</?)' . $m[1] . ':#', '$1', $conteudo);
            $conteudo = str_replace('xmlns:' . $m[1] . '=', 'xmlns=', $conteudo);
        }
        $anterior = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($conteudo, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);
        if ($xml === false) {
            throw new \RuntimeException('Conteúdo da planilha inválido.');
        }
        return $xml;
    }
}
