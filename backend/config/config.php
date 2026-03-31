<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

/**
 * Configuration globale — surcharge via backend/.env ou variables d’environnement du serveur.
 */
return [
    'jwt_secret' => lagoon_env('LAGOON_JWT_SECRET', 'change-me-in-production-use-long-random-string'),
    'jwt_ttl_seconds' => (int) lagoon_env('LAGOON_JWT_TTL', '604800'), // 7 jours
    'price_per_slot_xpf' => 5000,
    'db' => [
        'host' => lagoon_env('DB_HOST', '127.0.0.1'),
        'port' => lagoon_env('DB_PORT', '3306'),
        'name' => lagoon_env('DB_NAME', 'lagoon_padel'),
        'user' => lagoon_env('DB_USER', 'root'),
        'pass' => lagoon_env('DB_PASS', ''),
        'charset' => 'utf8mb4',
    ],
];
