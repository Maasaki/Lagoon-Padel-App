<?php

declare(strict_types=1);

final class PaymentController
{
    public function __construct(
        private Reservation $reservations,
        private array $config
    ) {
    }

    /**
     * Notification instantanée PayZen / Lyra (POST formulaire kr-answer / kr-hash).
     * À déclarer comme URL de notification dans le back-office marchand.
     */
    public function payzenIpn(): void
    {
        $post = [];
        foreach ($_POST as $k => $v) {
            $post[(string) $k] = is_string($v) ? $v : '';
        }

        $payzen = $this->config['payzen'] ?? [];
        if (!PayZenIpn::verify($payzen, $post)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'INVALID';
            return;
        }

        $krAnswer = (string) ($post['kr-answer'] ?? $post['kr_answer'] ?? '');
        $answer = PayZenIpn::decodeAnswer($krAnswer);
        if ($answer === null) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'BAD_ANSWER';
            return;
        }

        $orderId = PayZenIpn::extractOrderId($answer);
        if ($orderId === null || $orderId === '') {
            http_response_code(400);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'NO_ORDER';
            return;
        }

        $row = $this->reservations->findByPayzenOrderId($orderId);
        if ($row === null) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'UNKNOWN_ORDER';
            return;
        }

        if (PayZenIpn::isPaymentSuccessful($answer)) {
            $this->reservations->markPaid((int) $row['id']);
        } else {
            $this->reservations->markPaymentFailedIfPending((int) $row['id']);
        }

        header('Content-Type: text/plain; charset=UTF-8');
        http_response_code(200);
        echo 'OK';
    }
}
