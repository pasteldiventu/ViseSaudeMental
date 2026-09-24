<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

if (PHP_SAPI === 'cli-server') {
    $uriPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $static = realpath(__DIR__ . '/static');
    $file = realpath(__DIR__ . $uriPath);
    if ($static !== false && $file !== false && is_file($file) && str_starts_with($file, $static . DIRECTORY_SEPARATOR)) {
        return false;
    }
}

Vise\App::handle(Vise\Http\Request::capture())->send();
