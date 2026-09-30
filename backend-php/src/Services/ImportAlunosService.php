<?php

declare(strict_types=1);

namespace Vise\Services;

/** Importação de alunos (CSV ou XLSX). Mantida por compatibilidade; a lógica está em ImportacaoService. */
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
        $resultado = ImportacaoService::importar('alunos', $conteudo, $escolasPermitidas);
        $erros = [];
        foreach ($resultado['linhas'] as $linha) {
            if ($linha['status'] === 'erro') {
                $erros[] = "Linha {$linha['linha']}: {$linha['mensagem']}";
            }
        }
        return ['success' => $resultado['criados'] + $resultado['atualizados'], 'errors' => $erros];
    }
}
