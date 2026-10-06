<?php

declare(strict_types=1);

/*
 * Importa o backup SQL do sistema anterior (escolas, turmas, alunos, questionários e respostas).
 *
 * Uso:
 *   php bin/importar-legado.php backup.sql                    simula e mostra o relatório (não grava nada)
 *   php bin/importar-legado.php backup.sql --aplicar          grava no banco
 *   php bin/importar-legado.php backup.sql --aplicar --pesquisador=email
 *        (dono dos questionários importados; padrão: primeiro administrador geral)
 *
 * Pode ser executado de novo sem duplicar: o que já foi importado é reaproveitado.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__) . '/src/bootstrap.php';

use Vise\Install\ImportLegado;

$arquivo = null;
$options = [];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--')) {
        [$nome, $valor] = array_pad(explode('=', substr($arg, 2), 2), 2, true);
        $options[$nome] = $valor;
    } else {
        $arquivo = $arg;
    }
}
if ($arquivo === null || !is_file($arquivo)) {
    fwrite(STDERR, "Uso: php bin/importar-legado.php backup.sql [--aplicar] [--pesquisador=email]\n");
    exit(1);
}

try {
    $res = ImportLegado::executar((string) file_get_contents($arquivo), isset($options['aplicar']), $options['pesquisador'] ?? null);
} catch (Throwable $erro) {
    fwrite(STDERR, 'Erro: ' . $erro->getMessage() . PHP_EOL);
    exit(1);
}

echo $res['aplicado'] ? "IMPORTAÇÃO CONCLUÍDA\n" : "SIMULAÇÃO (nada foi gravado; rode de novo com --aplicar)\n";
foreach ($res['resumo'] as $item => $quantidade) {
    printf("  %6d  %s\n", $quantidade, $item);
}
if ($res['avisos'] !== []) {
    echo "\nAvisos (" . count($res['avisos']) . "):\n";
    foreach ($res['avisos'] as $aviso) {
        echo "  - $aviso\n";
    }
}
