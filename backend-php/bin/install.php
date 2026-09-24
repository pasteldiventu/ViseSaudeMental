<?php

declare(strict_types=1);

/*
 * Uso:
 *   php bin/install.php                         cria/atualiza as tabelas
 *   php bin/install.php --seed                  + dados de demonstração
 *   php bin/install.php --admin=email --password=segredo   cria/atualiza admin geral
 *   php bin/install.php --wait=60               aguarda o MySQL subir (Docker)
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__) . '/src/bootstrap.php';

use Vise\Db;
use Vise\Install\Installer;
use Vise\Install\Seed;

$options = getopt('', ['seed', 'admin:', 'password:', 'name:', 'wait:']);

$wait = (int) ($options['wait'] ?? 0);
for ($attempt = 1; ; $attempt++) {
    try {
        Db::pdo();
        break;
    } catch (PDOException $error) {
        if ($attempt >= max(1, $wait / 2)) {
            fwrite(STDERR, 'Não foi possível conectar ao MySQL: ' . $error->getMessage() . PHP_EOL);
            exit(1);
        }
        echo "Aguardando MySQL (tentativa $attempt)..." . PHP_EOL;
        sleep(2);
        Db::disconnect();
    }
}

foreach (Installer::migrate() as $line) {
    echo $line . PHP_EOL;
}
if (isset($options['admin'])) {
    echo Installer::upsertSuperuser((string) $options['admin'], (string) ($options['password'] ?? ''), (string) ($options['name'] ?? 'Administrador')) . PHP_EOL;
}
if (isset($options['seed'])) {
    Seed::demo();
    echo 'Dados de demonstração criados.' . PHP_EOL;
}
