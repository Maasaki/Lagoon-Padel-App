<?php

declare(strict_types=1);

/**
 * Configuration globale — surcharge possible via variables d'environnement.
 */
return [
    'jwt_secret' => getenv('LAGOON_JWT_SECRET') ?: 'change-me-in-production-use-long-random-string',
    'jwt_ttl_seconds' => (int) (getenv('LAGOON_JWT_TTL') ?: 604800), // 7 jours
    'price_per_slot_xpf' => 5000,
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'lagoon_padel',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
];
