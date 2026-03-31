<?php

declare(strict_types=1);

require __DIR__ . '/config/env.php';
lagoon_load_env();

$allowedOrigin = lagoon_env('CORS_ORIGIN', '*');
header('Access-Control-Allow-Origin: ' . $allowedOrigin);
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require __DIR__ . '/config/database.php';
require __DIR__ . '/helpers/JsonResponse.php';
require __DIR__ . '/helpers/Jwt.php';
require __DIR__ . '/helpers/Slots.php';
require __DIR__ . '/models/User.php';
require __DIR__ . '/models/Terrain.php';
require __DIR__ . '/models/Reservation.php';
require __DIR__ . '/middleware/AuthMiddleware.php';
require __DIR__ . '/controllers/AuthController.php';
require __DIR__ . '/controllers/TerrainController.php';
require __DIR__ . '/controllers/ReservationController.php';

try {
    require __DIR__ . '/routes/api.php';
} catch (Throwable $e) {
    error_log('Lagoon Padel API: ' . $e->getMessage());
    JsonResponse::error(500, 'Erreur serveur.', 'server_error');
}
