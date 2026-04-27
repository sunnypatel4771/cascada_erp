<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Single-file environment config.
 *
 * Goal: a plain `git pull` deploy works on production without manually recreating config files.
 * Local can still override via `app-config.local.php` when needed.
 */
$__local = __DIR__ . '/app-config.local.php';
if (is_file($__local)) {
    require $__local;
    return;
}

$__host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$__is_prod = $__host === '3ware.com.mx' || str_ends_with($__host, '.3ware.com.mx');

if ($__is_prod) {
    define('APP_BASE_URL', 'https://3ware.com.mx/ramos/erp/');

    define('APP_ENC_KEY', '88cab9569aafa6e1a5e0dba6863bcedc');

    define('APP_DB_HOSTNAME', 'localhost');
    define('APP_DB_USERNAME', 'u447461315_ramos');
    define('APP_DB_PASSWORD', 'Ramoserp*246');
    define('APP_DB_NAME', 'u447461315_ramos');

    define('APP_DB_CHARSET', 'utf8mb4');
    define('APP_DB_COLLATION', 'utf8mb4_unicode_ci');

    define('SESS_DRIVER', 'database');
    define('SESS_SAVE_PATH', (defined('APP_DB_PREFIX') ? APP_DB_PREFIX : 'tbl') . 'sessions');
    define('APP_SESSION_COOKIE_SAME_SITE', 'Lax');

    define('APP_CSRF_PROTECTION', true);
    return;
}

// Local default (safe fallback).
define('APP_BASE_URL', 'http://127.0.0.1:8080/');
define('APP_ENC_KEY', '88cab9569aafa6e1a5e0dba6863bcedc');
define('APP_DB_HOSTNAME', '127.0.0.1');
define('APP_DB_USERNAME', 'root');
define('APP_DB_PASSWORD', '123456');
define('APP_DB_NAME', 'ranos-php01');
define('APP_DB_CHARSET', 'utf8mb4');
define('APP_DB_COLLATION', 'utf8mb4_unicode_ci');
define('SESS_DRIVER', 'database');
define('SESS_SAVE_PATH', (defined('APP_DB_PREFIX') ? APP_DB_PREFIX : 'tbl') . 'sessions');
define('APP_SESSION_COOKIE_SAME_SITE', 'Lax');
define('APP_CSRF_PROTECTION', true);
return;
