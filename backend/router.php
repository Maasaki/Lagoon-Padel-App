<?php

/**
 * Routeur pour le serveur PHP intégré :
 * php -S localhost:8080 router.php
 */
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path !== '/' && $path !== '/index.php' && file_exists(__DIR__ . $path) && !is_dir(__DIR__ . $path)) {
    return false;
}

require __DIR__ . '/index.php';
