<?php

declare(strict_types=1);

namespace Vise\Support;

/**
 * Gera planilhas .xlsx (Excel/LibreOffice/Google Planilhas) sem dependências.
 *
 * Célula: valor escalar ou XlsxWriter::c($valor, 'estilos separados por espaço').
 * Estilos: title, subtitle, kpi, header, bold, muted, wrap, center, border,
 * percent (0–1), int, decimal, text (formato Texto), date / datetime ('Y-m-d' / 'Y-m-d H:i'),
 * alert, warn, ok, light (cores de fundo).
 */
final class XlsxWriter
{
    /** @var list<array{nome: string, linhas: list<list<mixed>>, opcoes: array<string, mixed>}> */
    private array $abas = [];
    /** @var array<string, int> */
    private array $fontes = [];
    /** @var array<string, int> */
    private array $preenchimentos = [];
    /** @var array<string, int> */
    private array $xfs = [];

    public function __construct(private string $autor = 'VISE-MT')
    {
    }

    /** @return array{v: mixed, s: string} */
    public static function c(mixed $valor, string $estilo): array
    {
        return ['v' => $valor, 's' => $estilo];
    }

    /**
     * @param list<list<mixed>> $linhas
     * @param array{larguras?: array<int, float>, congelar?: int, filtro?: int, mesclar?: list<string>} $opcoes
     *        congelar = nº de linhas fixas no topo; filtro = nº da linha (1-based) com autofiltro
     */
    public function aba(string $nome, array $linhas, array $opcoes = []): self
    {
        $this->abas[] = ['nome' => $this->nomeUnico($nome), 'linhas' => $linhas, 'opcoes' => $opcoes];
        return $this;
    }

    public function gerar(): string
    {
        if ($this->abas === []) {
            $this->aba('Planilha', [['Sem dados']]);
        }
        $this->fontes = ['<font><sz val="11"/><name val="Calibri"/><family val="2"/></font>' => 0];
        $this->preenchimentos = ['<fill><patternFill patternType="none"/></fill>' => 0, '<fill><patternFill patternType="gray125"/></fill>' => 1];
        $this->xfs = ['<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' => 0];

        $arquivos = [];
        $sheets = '';
        $rels = '';
        $nomesDefinidos = '';
        foreach ($this->abas as $i => $aba) {
            $n = $i + 1;
            $arquivos["xl/worksheets/sheet$n.xml"] = $this->planilha($aba, $i === 0);
            $sheets .= '<sheet name="' . self::xml($aba['nome']) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
            $rels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
            $faixa = $this->faixaFiltro($aba);
            if ($faixa !== null) {
                $nomesDefinidos .= '<definedName name="_xlnm._FilterDatabase" localSheetId="' . $i . '" hidden="1">\''
                    . self::xml(str_replace("'", "''", $aba['nome'])) . '\'!' . preg_replace('/([A-Z]+)(\d+)/', '\$$1\$$2', $faixa) . '</definedName>';
            }
        }
        $total = count($this->abas);
        $rels .= '<Relationship Id="rId' . ($total + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        $arquivos['xl/workbook.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<bookViews><workbookView/></bookViews><sheets>' . $sheets . '</sheets>'
            . ($nomesDefinidos === '' ? '' : '<definedNames>' . $nomesDefinidos . '</definedNames>')
            . '</workbook>';
        $arquivos['xl/_rels/workbook.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>';
        $arquivos['xl/styles.xml'] = $this->estilos();

        $overrides = '';
        for ($n = 1; $n <= $total; $n++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $agora = gmdate('Y-m-d\TH:i:s\Z');
        return Zip::montar([
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                . $overrides
                . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
                . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
                . '</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
                . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
                . '</Relationships>',
            'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
                . '<dc:creator>' . self::xml($this->autor) . '</dc:creator>'
                . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $agora . '</dcterms:created>'
                . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $agora . '</dcterms:modified>'
                . '</cp:coreProperties>',
            'docProps/app.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>VISE-MT</Application></Properties>',
        ] + $arquivos);
    }

    public static function coluna(int $indice): string
    {
        $letras = '';
        for ($n = $indice + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letras = chr(65 + ($n - 1) % 26) . $letras;
        }
        return $letras;
    }

    /** @param array{nome: string, linhas: list<list<mixed>>, opcoes: array<string, mixed>} $aba */
    private function planilha(array $aba, bool $primeira): string
    {
        $opcoes = $aba['opcoes'];
        $larguras = [];
        $maxColunas = 1;
        $dados = '';
        foreach ($aba['linhas'] as $r => $linha) {
            $numero = $r + 1;
            $celulas = '';
            foreach (array_values($linha) as $col => $celula) {
                [$valor, $estilo] = is_array($celula) && array_key_exists('v', $celula) ? [$celula['v'], (string) ($celula['s'] ?? '')] : [$celula, ''];
                $maxColunas = max($maxColunas, $col + 1);
                if ($valor === null || $valor === '') {
                    if ($estilo !== '') {
                        $celulas .= '<c r="' . self::coluna($col) . $numero . '" s="' . $this->xf($estilo) . '"/>';
                    }
                    continue;
                }
                $celulas .= $this->celula(self::coluna($col) . $numero, $valor, $estilo);
                if (!preg_match('/\b(title|subtitle)\b/', $estilo)) {
                    $larguras[$col] = max($larguras[$col] ?? 0, $this->larguraEstimada($valor, $estilo));
                }
            }
            $dados .= '<row r="' . $numero . '">' . $celulas . '</row>';
        }

        $cols = '';
        for ($col = 0; $col < $maxColunas; $col++) {
            $largura = $opcoes['larguras'][$col] ?? min(60, max(9, ($larguras[$col] ?? 8) + 2));
            $cols .= '<col min="' . ($col + 1) . '" max="' . ($col + 1) . '" width="' . round((float) $largura, 1) . '" customWidth="1"/>';
        }

        $congelar = (int) ($opcoes['congelar'] ?? 0);
        $painel = $congelar > 0
            ? '<pane ySplit="' . $congelar . '" topLeftCell="A' . ($congelar + 1) . '" activePane="bottomLeft" state="frozen"/>'
            : '';
        $faixa = $this->faixaFiltro($aba);
        $mesclas = '';
        foreach ($opcoes['mesclar'] ?? [] as $ref) {
            $mesclas .= '<mergeCell ref="' . self::xml($ref) . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            . '<sheetViews><sheetView workbookViewId="0"' . ($primeira ? ' tabSelected="1"' : '') . ' zoomScale="100">' . $painel . '</sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . '<cols>' . $cols . '</cols>'
            . '<sheetData>' . $dados . '</sheetData>'
            . ($faixa === null ? '' : '<autoFilter ref="' . $faixa . '"/>')
            . ($mesclas === '' ? '' : '<mergeCells count="' . count($opcoes['mesclar']) . '">' . $mesclas . '</mergeCells>')
            . '<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>'
            . '<pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="0"/>'
            . '<headerFooter><oddFooter>&amp;L&amp;8' . self::xml(str_replace('&', '&&', $aba['nome'])) . '&amp;R&amp;8Página &amp;P de &amp;N</oddFooter></headerFooter>'
            . '</worksheet>';
    }

    /** @param array{linhas: list<list<mixed>>, opcoes: array<string, mixed>} $aba */
    private function faixaFiltro(array $aba): ?string
    {
        $linha = (int) ($aba['opcoes']['filtro'] ?? 0);
        if ($linha <= 0 || !isset($aba['linhas'][$linha - 1])) {
            return null;
        }
        $colunas = max(1, count($aba['linhas'][$linha - 1]));
        $ultima = max($linha, count($aba['linhas']));
        return 'A' . $linha . ':' . self::coluna($colunas - 1) . $ultima;
    }

    private function celula(string $ref, mixed $valor, string $estilo): string
    {
        $s = $estilo === '' ? '' : ' s="' . $this->xf($estilo) . '"';
        if (is_bool($valor)) {
            $valor = $valor ? 'Sim' : 'Não';
        }
        if (preg_match('/\b(date|datetime)\b/', $estilo) && is_string($valor)) {
            $serial = self::serialData($valor);
            if ($serial !== null) {
                return '<c r="' . $ref . '"' . $s . '><v>' . $serial . '</v></c>';
            }
        }
        if (is_int($valor) || is_float($valor)) {
            if (is_float($valor) && !is_finite($valor)) {
                $valor = 0;
            }
            return '<c r="' . $ref . '"' . $s . '><v>' . (is_float($valor) ? rtrim(rtrim(sprintf('%.10F', $valor), '0'), '.') : $valor) . '</v></c>';
        }
        $texto = self::xml((string) $valor);
        return '<c r="' . $ref . '"' . $s . ' t="inlineStr"><is><t xml:space="preserve">' . $texto . '</t></is></c>';
    }

    private function larguraEstimada(mixed $valor, string $estilo): float
    {
        if (preg_match('/\bdatetime\b/', $estilo)) {
            return 16;
        }
        if (preg_match('/\bdate\b/', $estilo)) {
            return 11;
        }
        if (is_float($valor) || is_int($valor)) {
            return strpos($estilo, 'percent') !== false ? 7 : strlen((string) $valor);
        }
        $maior = 0;
        foreach (explode("\n", (string) $valor) as $parte) {
            $maior = max($maior, Str::len($parte));
        }
        return $maior * (preg_match('/\b(header|bold|kpi)\b/', $estilo) ? 1.15 : 1.0);
    }

    private static function serialData(string $valor): ?float
    {
        $valor = trim($valor);
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $formato) {
            $data = \DateTimeImmutable::createFromFormat('!' . $formato, $valor, new \DateTimeZone('UTC'));
            if ($data !== false && $data->format($formato) === $valor) {
                return round($data->getTimestamp() / 86400 + 25569, 6);
            }
        }
        return null;
    }

    private function xf(string $estilo): int
    {
        $tokens = array_flip(preg_split('/\s+/', trim($estilo)) ?: []);
        $tem = static fn (string $t): bool => isset($tokens[$t]);

        $negrito = $tem('bold') || $tem('title') || $tem('subtitle') || $tem('kpi') || $tem('header');
        $tamanho = $tem('title') ? 16 : ($tem('kpi') ? 14 : ($tem('subtitle') ? 12 : ($tem('muted') ? 10 : 11)));
        $cor = match (true) {
            $tem('header') => 'FFFFFFFF',
            $tem('title') => 'FF1E74C7',
            $tem('alert') => 'FF8A1F17',
            $tem('muted') => 'FF6B7280',
            $tem('subtitle'), $tem('kpi') => 'FF182433',
            default => null,
        };
        $fonte = '<font>' . ($negrito ? '<b/>' : '') . ($tem('muted') ? '<i/>' : '') . '<sz val="' . $tamanho . '"/>'
            . ($cor === null ? '' : '<color rgb="' . $cor . '"/>') . '<name val="Calibri"/><family val="2"/></font>';
        $fontId = $this->fontes[$fonte] ??= count($this->fontes);

        $fundo = match (true) {
            $tem('header') => 'FF1E74C7',
            $tem('alert') => 'FFFDECEA',
            $tem('warn') => 'FFFFF4D6',
            $tem('ok') => 'FFE3F6EA',
            $tem('light') => 'FFF1F5F9',
            default => null,
        };
        $fillId = 0;
        if ($fundo !== null) {
            $fill = '<fill><patternFill patternType="solid"><fgColor rgb="' . $fundo . '"/><bgColor indexed="64"/></patternFill></fill>';
            $fillId = $this->preenchimentos[$fill] ??= count($this->preenchimentos);
        }

        $borda = ($tem('header') || $tem('border')) ? 1 : 0;
        $formato = match (true) {
            $tem('text') => 49,
            $tem('percent') => 164,
            $tem('datetime') => 166,
            $tem('date') => 165,
            $tem('decimal') => 2,
            $tem('int') => 1,
            default => 0,
        };
        $alinhamento = '';
        if ($tem('wrap') || $tem('header') || $tem('center')) {
            $alinhamento = '<alignment' . ($tem('center') ? ' horizontal="center"' : '')
                . ' vertical="' . ($tem('header') ? 'center' : 'top') . '"' . (($tem('wrap') || $tem('header')) ? ' wrapText="1"' : '') . '/>';
        }
        $xf = '<xf numFmtId="' . $formato . '" fontId="' . $fontId . '" fillId="' . $fillId . '" borderId="' . $borda . '" xfId="0"'
            . ($formato ? ' applyNumberFormat="1"' : '') . ($fontId ? ' applyFont="1"' : '') . ($fillId ? ' applyFill="1"' : '')
            . ($borda ? ' applyBorder="1"' : '')
            . ($alinhamento === '' ? '/>' : ' applyAlignment="1">' . $alinhamento . '</xf>');
        return $this->xfs[$xf] ??= count($this->xfs);
    }

    private function estilos(): string
    {
        $borda = '<left style="thin"><color rgb="FFD1D5DB"/></left><right style="thin"><color rgb="FFD1D5DB"/></right>'
            . '<top style="thin"><color rgb="FFD1D5DB"/></top><bottom style="thin"><color rgb="FFD1D5DB"/></bottom><diagonal/>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="3"><numFmt numFmtId="164" formatCode="0.0%"/><numFmt numFmtId="165" formatCode="dd/mm/yyyy"/>'
            . '<numFmt numFmtId="166" formatCode="dd/mm/yyyy hh:mm"/></numFmts>'
            . '<fonts count="' . count($this->fontes) . '">' . implode('', array_keys($this->fontes)) . '</fonts>'
            . '<fills count="' . count($this->preenchimentos) . '">' . implode('', array_keys($this->preenchimentos)) . '</fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border>' . $borda . '</border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="' . count($this->xfs) . '">' . implode('', array_keys($this->xfs)) . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function nomeUnico(string $nome): string
    {
        $nome = trim((string) preg_replace('/[\[\]:*?\/\\\\]/', ' ', $nome)) ?: 'Planilha';
        $base = Str::sub($nome, 0, 31);
        $usados = array_column($this->abas, 'nome');
        $candidato = $base;
        for ($i = 2; in_array($candidato, $usados, true); $i++) {
            $candidato = Str::sub($base, 0, 28) . " ($i)";
        }
        return $candidato;
    }

    private static function xml(string $texto): string
    {
        $texto = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $texto);
        return htmlspecialchars($texto, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }
}
