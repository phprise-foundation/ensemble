<?php

declare(strict_types=1);

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestPath = is_string($requestPath) ? $requestPath : '/';
$publicDirectory = __DIR__;
$appDirectory = $publicDirectory . '/app';
$appFile = realpath($appDirectory . '/index.html');

if ($requestPath === '/' || $requestPath === '/app' || $requestPath === '/app/') {
    if ($appFile !== false && ! str_starts_with($requestPath, '/api')) {
        readfile($appFile);

        return;
    }
    header('Location: /app/');

    return;
}

if (str_starts_with($requestPath, '/app/')) {
    $file = realpath($appDirectory . substr($requestPath, 4));
    if ($file !== false && str_starts_with($file, $appDirectory) && is_file($file)) {
        return false;
    }
    if ($appFile !== false) {
        readfile($appFile);

        return;
    }
    http_response_code(404);
    echo "The app build is missing: run `npm run build` inside frontend/.\n";

    return;
}

$file = realpath($publicDirectory . $requestPath);

if ($file !== false && str_starts_with($file, $publicDirectory) && is_file($file)) {
    return false;
}

require $publicDirectory . '/index.php';
