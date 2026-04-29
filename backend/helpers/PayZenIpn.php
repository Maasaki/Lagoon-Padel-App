<?php

declare(strict_types=1);

/**
 * Vérification des notifications PayZen / Lyra (kr-answer, kr-hash).
 */
final class PayZenIpn
{
    /**
     * @param array<string, mixed> $payzen lagoon_config()['payzen']
     * @param array<string, string> $post $_POST comme tableau string
     */
    public static function verify(array $payzen, array $post): bool
    {
        $krAnswer = (string) ($post['kr-answer'] ?? $post['kr_answer'] ?? '');
        $krHash = (string) ($post['kr-hash'] ?? $post['kr_hash'] ?? '');
        $key = (string) ($payzen['hmac_key'] ?? '');
        if ($krAnswer === '' || $krHash === '' || $key === '') {
            return false;
        }

        $algo = strtolower(trim((string) ($payzen['ipn_hash_algorithm'] ?? 'sha256')));
        if (!in_array($algo, ['sha1', 'sha256'], true)) {
            $algo = 'sha256';
        }

        // Même construction que la doc PayZen / Lyra : base64(HMAC(algo, kr-answer, clé HMAC)).
        $expected = base64_encode(hash_hmac($algo, $krAnswer, $key, true));

        return hash_equals($expected, $krHash);
    }

    /**
     * Décode kr-answer (base64 JSON).
     *
     * @return array<string, mixed>|null
     */
    public static function decodeAnswer(string $krAnswerB64): ?array
    {
        $json = base64_decode($krAnswerB64, true);
        if ($json === false || $json === '') {
            return null;
        }
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    /**
     * Indique si le paiement est accepté selon la charge utile Lyra Collect.
     *
     * @param array<string, mixed> $answer
     */
    public static function isPaymentSuccessful(array $answer): bool
    {
        $detailed = strtoupper((string) ($answer['detailedStatus'] ?? ''));
        if (in_array($detailed, ['AUTHORISED', 'CAPTURED', 'PAID'], true)) {
            return true;
        }

        $transactions = $answer['transactions'] ?? null;
        if (is_array($transactions) && isset($transactions[0]) && is_array($transactions[0])) {
            $st = strtoupper((string) ($transactions[0]['detailedStatus'] ?? $transactions[0]['status'] ?? ''));
            if (in_array($st, ['AUTHORISED', 'CAPTURED', 'PAID'], true)) {
                return true;
            }
        }

        $orderStatus = strtoupper((string) ($answer['orderStatus'] ?? $answer['orderDetails']['orderStatus'] ?? ''));
        if ($orderStatus === 'PAID') {
            return true;
        }

        return false;
    }

    /**
     * Extrait orderId Lyra (notre référence envoyée au CreatePayment).
     *
     * @param array<string, mixed> $answer
     */
    public static function extractOrderId(array $answer): ?string
    {
        $od = $answer['orderDetails'] ?? null;
        if (is_array($od)) {
            $oid = trim((string) ($od['orderId'] ?? ''));
            if ($oid !== '') {
                return $oid;
            }
        }

        $oid = trim((string) ($answer['orderId'] ?? ''));
        return $oid !== '' ? $oid : null;
    }
}
