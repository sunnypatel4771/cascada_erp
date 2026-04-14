<?php
/**
 * PHP built-in server router for CodeIgniter/Perfex.
 *
 * Usage:
 *   php -S 127.0.0.1:8080 -t . router.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

// Serve the requested resource as-is if it exists.
$path = __DIR__ . $uri;
if ($uri !== '/' && file_exists($path) && !is_dir($path)) {
    return false;
}

// Otherwise, route everything through the front controller.
require __DIR__ . '/index.php';

