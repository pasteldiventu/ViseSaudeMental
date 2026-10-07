<?php

declare(strict_types=1);

namespace Vise\Services;

use Vise\Admin\Resources;
use Vise\Db;
use Vise\Support\Str;
use Vise\Support\Tempo;

/** Recorte usado pelo painel e pelos relatórios. Datas são locais (APP_TIMEZONE), no formato AAAA-MM-DD. */
final class FiltrosRelatorio
{
    public const PERIODOS = ['7' => 'Últimos 7 dias', '30' => 'Últimos 30 dias', '90' => 'Últimos 90 dias', '365' => 'Últimos 12 meses', 'tudo' => 'Todo o período'];

    public function __construct(
        public ?int $escolaId = null,
        public ?int $turmaId = null,
        public ?int $serieId = null,
        public ?string $turno = null,
        public ?string $sexo = null,
        public ?int $questionarioId = null,
        public ?int $aplicacaoId = null,
        public ?string $inicio = null,
        public ?string $fim = null,
        public string $periodo = 'tudo',
    ) {
    }

    /** @param array<string, mixed> $q parâmetros da URL ou do JSON */
    public static function deQuery(array $q, string $periodoPadrao = 'tudo'): self
    {
        $int = static function (string $chave) use ($q): ?int {
            $valor = $q[$chave] ?? null;
            if (is_int($valor)) {
                return $valor > 0 ? $valor : null;
            }
            return (is_string($valor) && ctype_digit($valor) && (int) $valor > 0) ? (int) $valor : null;
        };
        $turno = is_string($q['turno'] ?? null) && isset(Resources::TURNOS[$q['turno']]) ? $q['turno'] : null;
        $sexo = is_string($q['sexo'] ?? null) && trim($q['sexo']) !== '' ? Str::sub(Str::lower(trim($q['sexo'])), 0, 30) : null;

        $inicio = Tempo::dataValida($q['inicio'] ?? null);
        $fim = Tempo::dataValida($q['fim'] ?? null);
        $periodo = is_string($q['periodo'] ?? null) && $q['periodo'] !== '' ? $q['periodo'] : ($inicio !== null || $fim !== null ? 'personalizado' : $periodoPadrao);
        if ($periodo !== 'personalizado') {
            $periodo = isset(self::PERIODOS[$periodo]) ? $periodo : $periodoPadrao;
            $inicio = null;
            $fim = null;
            if ($periodo !== 'tudo') {
                $fim = Tempo::hoje();
                $inicio = (new \DateTimeImmutable($fim))->modify('-' . ((int) $periodo - 1) . ' days')->format('Y-m-d');
            }
        } elseif ($inicio !== null && $fim !== null && $inicio > $fim) {
            [$inicio, $fim] = [$fim, $inicio];
        }

        return new self($int('escola_id'), $int('turma_id'), $int('serie_id'), $turno, $sexo, $int('questionario_id'), $int('aplicacao_id'), $inicio, $fim, $periodo);
    }

    /** Descarta ids que o usuário não enxerga (evita expor nomes alheios no cabeçalho do relatório). */
    public function noEscopo(\Vise\Admin\Ctx $ctx): self
    {
        $ok = static fn (string $recurso, ?int $id): ?int => $id !== null && Resources::inScope($recurso, $id, $ctx, false) ? $id : null;
        $this->escolaId = $ok('escolas', $this->escolaId);
        $this->turmaId = $ok('turmas', $this->turmaId);
        $this->serieId = $ok('series', $this->serieId);
        $this->questionarioId = $ok('questionarios', $this->questionarioId);
        $this->aplicacaoId = $ok('aplicacoes', $this->aplicacaoId);
        return $this;
    }

    /** @return array<string, string> */
    public function query(): array
    {
        $q = [
            'escola_id' => $this->escolaId, 'turma_id' => $this->turmaId, 'serie_id' => $this->serieId, 'turno' => $this->turno,
            'sexo' => $this->sexo, 'questionario_id' => $this->questionarioId, 'aplicacao_id' => $this->aplicacaoId, 'periodo' => $this->periodo,
        ];
        if ($this->periodo === 'personalizado') {
            $q['inicio'] = $this->inicio;
            $q['fim'] = $this->fim;
        }
        return array_map('strval', array_filter($q, static fn ($v) => $v !== null && $v !== ''));
    }

    public function inicioUtc(): ?string
    {
        return $this->inicio === null ? null : Tempo::limiteUtc($this->inicio);
    }

    public function fimUtc(): ?string
    {
        return $this->fim === null ? null : Tempo::limiteUtc($this->fim, true);
    }

    public function periodoTexto(): string
    {
        if ($this->periodo !== 'personalizado' && $this->periodo !== 'tudo') {
            return self::PERIODOS[$this->periodo] . ' (' . self::dataBr($this->inicio) . ' a ' . self::dataBr($this->fim) . ')';
        }
        if ($this->inicio === null && $this->fim === null) {
            return 'Todo o período';
        }
        if ($this->inicio !== null && $this->fim !== null) {
            return self::dataBr($this->inicio) . ' a ' . self::dataBr($this->fim);
        }
        return $this->inicio !== null ? 'A partir de ' . self::dataBr($this->inicio) : 'Até ' . self::dataBr($this->fim);
    }

    /** @return list<array{0: string, 1: string}> filtros aplicados em texto (para cabeçalhos de relatório) */
    public function descricao(): array
    {
        $itens = [['Período', $this->periodoTexto()]];
        $nome = static fn (string $sql, int $id): string => (string) (Db::value($sql, [$id]) ?? '#' . $id);
        if ($this->escolaId !== null) {
            $itens[] = ['Escola', $nome('SELECT nome FROM escolas WHERE id = ?', $this->escolaId)];
        }
        if ($this->turmaId !== null) {
            $itens[] = ['Turma', $nome("SELECT CONCAT_WS(' ', s.descricao, t.nome) FROM turmas t LEFT JOIN series s ON s.id = t.serie_id WHERE t.id = ?", $this->turmaId)];
        }
        if ($this->serieId !== null) {
            $itens[] = ['Série', $nome('SELECT descricao FROM series WHERE id = ?', $this->serieId)];
        }
        if ($this->turno !== null) {
            $itens[] = ['Turno', Resources::TURNOS[$this->turno] ?? $this->turno];
        }
        if ($this->sexo !== null) {
            $itens[] = ['Sexo', ucfirst($this->sexo)];
        }
        if ($this->questionarioId !== null) {
            $itens[] = ['Questionário', $nome("SELECT CONCAT(nome, ' (v', versao, ')') FROM questionarios WHERE id = ?", $this->questionarioId)];
        }
        if ($this->aplicacaoId !== null) {
            $itens[] = ['Aplicação', $nome("SELECT CONCAT('#', id, IF(codigo_sala IS NULL, '', CONCAT(' · sala ', codigo_sala))) FROM aplicacoes_questionario WHERE id = ?", $this->aplicacaoId)];
        }
        return $itens;
    }

    private static function dataBr(?string $data): string
    {
        return $data === null ? '' : date('d/m/Y', (int) strtotime($data));
    }
}
