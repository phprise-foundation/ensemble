<?php

declare(strict_types=1);

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestPath = is_string($requestPath) ? $requestPath : '/';
$publicDirectory = __DIR__;

if ($requestPath === '/') {
    readfile($publicDirectory . '/index.html');

    return;
}

$file = realpath($publicDirectory . $requestPath);

if ($file !== false && str_starts_with($file, $publicDirectory) && is_file($file)) {
    return false;
}

require $publicDirectory . '/index.php';
