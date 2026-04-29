<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

/**
 * Configuration globale — surcharge via backend/.env ou variables d’environnement du serveur.
 */

$payzenMode = strtolower(lagoon_env('PAYZEN_MODE', 'test'));
$payzenProd = in_array($payzenMode, ['production', 'prod'], true);

// Identifiant boutique → login REST (sauf si PAYZEN_REST_USERNAME est défini explicitement).
$payzenShopId = trim(lagoon_env('PAYZEN_SHOP_ID', ''));

// Clé API REST (mot de passe « serveur » dans l’extranet) : test / prod ou une seule PAYZEN_API_KEY.
$payzenApiKey = '';
if ($payzenProd) {
    $payzenApiKey = trim(lagoon_env('PAYZEN_API_KEY_PROD', '')) ?: trim(lagoon_env('PAYZEN_API_KEY', ''));
} else {
    $payzenApiKey = trim(lagoon_env('PAYZEN_API_KEY_TEST', '')) ?: trim(lagoon_env('PAYZEN_API_KEY', ''));
}

$payzenRestUser = trim(lagoon_env('PAYZEN_REST_USERNAME', ''));
if ($payzenRestUser === '') {
    $payzenRestUser = $payzenShopId;
}

$payzenRestPass = trim(lagoon_env('PAYZEN_REST_PASSWORD', ''));
if ($payzenRestPass === '') {
    $payzenRestPass = $payzenApiKey;
}

// Clé publique page hébergée (ligne complète « boutique:testpublickey_… » dans le BO) — distincte de la clé API REST.
$payzenPublicKey = trim(lagoon_env('PAYZEN_PUBLIC_KEY', ''));

// HMAC notifications (IPN) : PAYZEN_HMAC_KEY générique ou _TEST / _PROD selon PAYZEN_MODE.
$payzenHmac = trim(lagoon_env('PAYZEN_HMAC_KEY', ''));
if ($payzenProd) {
    $payzenHmac = trim(lagoon_env('PAYZEN_HMAC_KEY_PROD', '')) ?: $payzenHmac;
} else {
    $payzenHmac = trim(lagoon_env('PAYZEN_HMAC_KEY_TEST', '')) ?: $payzenHmac;
}

// Doit refléter le réglage boutique « algorithme de signature » (SHA-1 ou SHA-256) pour kr-hash IPN.
$payzenSignAlgo = strtolower(trim(lagoon_env('PAYZEN_SIGN_ALGORITHM', 'sha256')));
if (!in_array($payzenSignAlgo, ['sha1', 'sha256'], true)) {
    $payzenSignAlgo = 'sha256';
}

return [
    'jwt_secret' => lagoon_env('LAGOON_JWT_SECRET', 'change-me-in-production-use-long-random-string'),
    'jwt_ttl_seconds' => (int) lagoon_env('LAGOON_JWT_TTL', '604800'), // 7 jours
    'price_per_slot_xpf' => (int) lagoon_env('LAGOON_PRICE_PER_SLOT_XPF', '5000'),
    /**
     * OSB Lyra — doc : https://secure.osb.pf/doc/fr-FR/
     * Saisie habituelle : PAYZEN_SHOP_ID + clés API test/prod ; clé publique + HMAC selon l’extranet.
     */
    'payzen' => [
        'mode' => lagoon_env('PAYZEN_MODE', 'test'),
        'shop_id' => $payzenShopId,
        'rest_create_payment_url' => lagoon_env(
            'PAYZEN_REST_CREATE_PAYMENT_URL',
            'https://api.secure.osb.pf/api-payment/V4/Charge/CreatePayment'
        ),
        'rest_username' => $payzenRestUser,
        'rest_password' => $payzenRestPass,
        'public_key' => $payzenPublicKey,
        'payment_page_url' => lagoon_env(
            'PAYZEN_PAYMENT_PAGE_URL',
            'https://secure.osb.pf/vads-payment/'
        ),
        'hmac_key' => $payzenHmac,
        /** sha1 ou sha256 — aligné sur la configuration boutique (notifications IPN). */
        'ipn_hash_algorithm' => $payzenSignAlgo,
        'pending_ttl_minutes' => (int) lagoon_env('PAYZEN_PENDING_TTL_MINUTES', '30'),
    ],
    'db' => [
        'host' => lagoon_env('DB_HOST', '127.0.0.1'),
        'port' => lagoon_env('DB_PORT', '3306'),
        'name' => lagoon_env('DB_NAME', 'lagoon_padel'),
        'user' => lagoon_env('DB_USER', 'root'),
        'pass' => lagoon_env('DB_PASS', ''),
        'charset' => 'utf8mb4',
    ],
];
