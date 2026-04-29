<?php

declare(strict_types=1);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';

// Cas nginx / PHP-FPM : /index.php/api/... ou PATH_INFO
if (str_starts_with($path, '/index.php')) {
    $path = substr($path, strlen('/index.php')) ?: '/';
    $path = rtrim($path, '/') ?: '/';
}
if ($path === '/' && !empty($_SERVER['PATH_INFO'])) {
    $path = rtrim((string) $_SERVER['PATH_INFO'], '/') ?: '/';
}

// Retirer le préfixe /api (répété si baseUrl du client duplique /api)
$base = '/api';
while (str_starts_with($path, $base)) {
    $path = substr($path, strlen($base)) ?: '/';
    $path = rtrim($path, '/') ?: '/';
}

// GET / ou GET /api/ — racine du domaine (navigateur) : pas de route métier sur /
if ($method === 'GET' && $path === '/') {
    JsonResponse::send(200, [
        'service' => 'Lagoon Padel API',
        'status' => 'ok',
        'endpoints' => '/api (ex. GET /api/terrains)',
    ]);
    return;
}

$config = lagoon_config();
$pdo = lagoon_pdo();

$userModel = new User($pdo);
$terrainModel = new Terrain($pdo);
$reservationModel = new Reservation($pdo);
$blocksModel = new AvailabilityBlocks($pdo);
$authMw = new AuthMiddleware($config);
$adminMw = new AdminMiddleware($authMw, $userModel);

$authController = new AuthController($pdo, $config, $userModel);
$terrainController = new TerrainController($terrainModel, $reservationModel, $blocksModel);
$reservationController = new ReservationController($pdo, $config, $reservationModel, $terrainModel, $blocksModel, $authMw, $userModel);
$adminController = new AdminController($userModel, $reservationModel, $terrainModel, $blocksModel);
$paymentController = new PaymentController($reservationModel, $config);

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
if ($method === 'POST' && $path === '/payment/payzen/ipn') {
    $paymentController->payzenIpn();
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

if ($method === 'GET' && $path === '/admin/users') {
    $adminMw->requireAdminUserId();
    $adminController->users();
    return;
}
if ($method === 'GET' && $path === '/admin/reservations') {
    $adminMw->requireAdminUserId();
    $adminController->reservations();
    return;
}
if ($method === 'DELETE' && preg_match('#^/admin/reservations/(\d+)$#', $path, $m)) {
    $adminMw->requireAdminUserId();
    $adminController->deleteReservation($m[1]);
    return;
}
if ($method === 'GET' && $path === '/admin/terrain-day-blocks') {
    $adminMw->requireAdminUserId();
    $adminController->listTerrainDayBlocks();
    return;
}
if ($method === 'POST' && $path === '/admin/terrain-day-blocks') {
    $adminMw->requireAdminUserId();
    $adminController->createTerrainDayBlock();
    return;
}
if ($method === 'DELETE' && preg_match('#^/admin/terrain-day-blocks/(\d+)$#', $path, $m)) {
    $adminMw->requireAdminUserId();
    $adminController->deleteTerrainDayBlock($m[1]);
    return;
}
if ($method === 'GET' && $path === '/admin/slot-blocks') {
    $adminMw->requireAdminUserId();
    $adminController->listSlotBlocks();
    return;
}
if ($method === 'POST' && $path === '/admin/slot-blocks') {
    $adminMw->requireAdminUserId();
    $adminController->createSlotBlock();
    return;
}
if ($method === 'DELETE' && preg_match('#^/admin/slot-blocks/(\d+)$#', $path, $m)) {
    $adminMw->requireAdminUserId();
    $adminController->deleteSlotBlock($m[1]);
    return;
}

JsonResponse::error(404, 'Route introuvable.', 'not_found');
