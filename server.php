<?php

$publicPath = __DIR__.DIRECTORY_SEPARATOR.'public';
$requestedPath = $publicPath.parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($requestedPath !== false) {
    $realRequestedPath = realpath($requestedPath);
    $realPublicPath = realpath($publicPath);

    if ($realRequestedPath !== false
        && $realPublicPath !== false
        && str_starts_with($realRequestedPath, $realPublicPath)
        && is_file($realRequestedPath)) {
        return false;
    }
}

require_once $publicPath.DIRECTORY_SEPARATOR.'index.php';
