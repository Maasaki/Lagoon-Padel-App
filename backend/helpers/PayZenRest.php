<?php

declare(strict_types=1);

/**
 * Création de paiement Lyra Collect (OSB Lyra / PayZen) via REST Charge/CreatePayment.
 *
 * @see https://secure.osb.pf/doc/fr-FR/ — documentation OSB Polynésie
 */
final class PayZenRest
{
    /**
     * @param array<string, mixed> $payzen Config lagoon_config()['payzen']
     * @return array{formToken: string}
     */
    public static function createPayment(
        array $payzen,
        int $amountXpf,
        string $orderId,
        string $customerEmail
    ): array {
        $endpoint = (string) ($payzen['rest_create_payment_url'] ?? '');
        $user = (string) ($payzen['rest_username'] ?? '');
        $pass = (string) ($payzen['rest_password'] ?? '');
        if ($endpoint === '' || $user === '' || $pass === '') {
            throw new RuntimeException('PayZen REST non configuré (identifiants API).');
        }

        $payload = [
            'amount' => $amountXpf,
            'currency' => 'XPF',
            'orderId' => $orderId,
            'customer' => [
                'email' => $customerEmail,
            ],
            'formAction' => 'PAYMENT',
        ];

        $auth = base64_encode($user . ':' . $pass);
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $raw = self::httpPostJson($endpoint, $auth, $body);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Réponse PayZen invalide.');
        }

        $status = (string) ($decoded['status'] ?? '');
        if ($status !== 'SUCCESS') {
            $msg = (string) ($decoded['answer']['errorMessage'] ?? $decoded['answer']['detailedErrorMessage'] ?? $decoded['_type'] ?? 'erreur PayZen');
            throw new RuntimeException('PayZen : ' . $msg);
        }

        $answer = $decoded['answer'] ?? null;
        if (!is_array($answer)) {
            throw new RuntimeException('Réponse PayZen sans answer.');
        }

        $token = (string) ($answer['formToken'] ?? '');
        if ($token === '') {
            throw new RuntimeException('Réponse PayZen sans formToken.');
        }

        return ['formToken' => $token];
    }

    /**
     * URL de la page de paiement hébergée (navigateur externe, sans WebView).
     *
     * @param array<string, mixed> $payzen
     */
    public static function paymentPageUrl(array $payzen, string $formToken): string
    {
        $base = rtrim((string) ($payzen['payment_page_url'] ?? ''), '?&');
        $pub = (string) ($payzen['public_key'] ?? '');
        if ($base === '' || $pub === '') {
            throw new RuntimeException('PayZen : PAYZEN_PAYMENT_PAGE_URL ou PAYZEN_PUBLIC_KEY manquant.');
        }

        return $base
            . (str_contains($base, '?') ? '&' : '?')
            . 'kr-public-key=' . rawurlencode($pub)
            . '&kr-form-token=' . rawurlencode($formToken);
    }

    private static function httpPostJson(string $url, string $basicAuth, string $jsonBody): string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                throw new RuntimeException('Impossible d’initialiser cURL.');
            }
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Basic ' . $basicAuth,
                ],
                CURLOPT_POSTFIELDS => $jsonBody,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
            ]);
            $raw = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($raw === false) {
                throw new RuntimeException('PayZen REST : ' . $err);
            }
            if ($code < 200 || $code >= 300) {
                throw new RuntimeException('PayZen REST HTTP ' . $code . ' : ' . $raw);
            }

            return $raw;
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAuthorization: Basic {$basicAuth}\r\n",
                'content' => $jsonBody,
                'timeout' => 30,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            throw new RuntimeException('Impossible de contacter PayZen REST.');
        }

        return $raw;
    }
}
