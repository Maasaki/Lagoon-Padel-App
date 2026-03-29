<?php

declare(strict_types=1);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';

// Retirer le préfixe si l'API est montée dans un sous-dossier
$base = '/api';
if (str_starts_with($path, $base)) {
    $path = substr($path, strlen($base)) ?: '/';
}
$path = rtrim($path, '/') ?: '/';

$config = lagoon_config();
$pdo = lagoon_pdo();

$userModel = new User($pdo);
$terrainModel = new Terrain($pdo);
$reservationModel = new Reservation($pdo);
$authMw = new AuthMiddleware($config);

$authController = new AuthController($pdo, $config, $userModel);
$terrainController = new TerrainController($terrainModel, $reservationModel);
$reservationController = new ReservationController($pdo, $config, $reservationModel, $terrainModel, $authMw);

if ($method === 'POST' && $path === '/register') {
    $authController->register();
    return;
}
if ($method === 'POST' && $path === '/login') {
    $authController->login();
    return;
}
if ($method === 'GET' && $path === '/terrains') {
    $terrainController->list();
    return;
}
if ($method === 'GET' && preg_match('#^/terrains/(\d+)/slots$#', $path, $m)) {
    $terrainController->slots($m[1]);
    return;
}
if ($method === 'POST' && $path === '/reservations') {
    $reservationController->create();
    return;
}
if ($method === 'GET' && $path === '/reservations/me') {
    $reservationController->mine();
    return;
}
if ($method === 'DELETE' && preg_match('#^/reservations/(\d+)$#', $path, $m)) {
    $reservationController->delete($m[1]);
    return;
}

JsonResponse::error(404, 'Route introuvable.', 'not_found');
