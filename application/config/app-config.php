<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Environment config loader.
 *
 * - Local dev: create `application/config/app-config.local.php` (ignored by git)
 * - Production: create `application/config/app-config.server.php` (ignored by git)
 *
 * This file stays tracked so `git pull` never removes the config entrypoint.
 */
$__local  = __DIR__ . '/app-config.local.php';
$__server = __DIR__ . '/app-config.server.php';

if (is_file($__local)) {
    require $__local;
    return;
}

if (is_file($__server)) {
    require $__server;
    return;
}

// Backward compatibility: if someone copied the sample to local/server, allow it.
$__sample = __DIR__ . '/app-config-sample.php';
if (is_file($__sample)) {
    require $__sample;
    return;
}

// Nothing found; show the standard "not installed" message.
$install_url = (isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) === 'on') ? 'https' : 'http';
$install_url .= '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$install_url .= rtrim(str_replace(basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')), '', (string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/') . '/';
$install_url .= 'install';

echo '<h1>Perfex CRM not installed</h1>';
echo '<p>1. To you use the automatic Perfex CRM installation tool click <a href="' . $install_url . '">here (' . $install_url . ')</a></p>';
echo '<p>2. Create application/config/app-config.server.php (production) or app-config.local.php (local) based on application/config/app-config-sample.php</p>';
exit();
