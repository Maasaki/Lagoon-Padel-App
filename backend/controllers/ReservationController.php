<?php

declare(strict_types=1);

final class ReservationController
{
    public function __construct(
        private PDO $pdo,
        private array $config,
        private Reservation $reservations,
        private Terrain $terrains,
        private AvailabilityBlocks $blocks,
        private AuthMiddleware $auth,
        private User $users
    ) {
    }

    public function create(): void
    {
        $userId = $this->auth->requireUserId();

        $raw = file_get_contents('php://input') ?: '';
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            JsonResponse::error(400, 'JSON invalide', 'invalid_json');
            return;
        }
        if (!is_array($data)) {
            JsonResponse::error(400, 'Corps de requête invalide', 'invalid_body');
            return;
        }

        $terrainId = isset($data['terrain_id']) ? (int) $data['terrain_id'] : 0;
        $date = trim((string) ($data['date'] ?? ''));
        $startRaw = trim((string) ($data['start_time'] ?? ''));
        $endRaw = trim((string) ($data['end_time'] ?? ''));

        if ($terrainId < 1 || $date === '' || $startRaw === '' || $endRaw === '') {
            JsonResponse::error(422, 'terrain_id, date, start_time et end_time sont requis.', 'validation_failed');
            return;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            JsonResponse::error(422, 'Format de date invalide (YYYY-MM-DD).', 'validation_failed');
            return;
        }

        $d = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if ($d === false || $d->format('Y-m-d') !== $date) {
            JsonResponse::error(422, 'Date invalide.', 'validation_failed');
            return;
        }

        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        if ($date < $today) {
            JsonResponse::error(422, 'Impossible de réserver sur une date passée.', 'validation_failed');
            return;
        }

        $startNorm = Slots::normalizeTime($startRaw);
        $endNorm = Slots::normalizeTime($endRaw);
        if ($startNorm === '' || $endNorm === '') {
            JsonResponse::error(422, 'Horaires invalides.', 'validation_failed');
            return;
        }

        if (!Slots::isValidSlot($startNorm, $endNorm)) {
            JsonResponse::error(422, 'Créneau non reconnu (1h30, 07h30–18h00).', 'invalid_slot');
            return;
        }

        if ($this->terrains->findById($terrainId) === null) {
            JsonResponse::error(404, 'Terrain introuvable.', 'not_found');
            return;
        }

        if ($this->blocks->isTerrainDayBlocked($terrainId, $date)) {
            JsonResponse::error(422, 'Ce terrain est fermé ce jour.', 'terrain_blocked');
            return;
        }
        $blockedStarts = $this->blocks->blockedSlotStartTimes($terrainId, $date);
        $blockedFlip = array_flip($blockedStarts);
        if (isset($blockedFlip[$startNorm])) {
            JsonResponse::error(422, 'Ce créneau est bloqué.', 'slot_blocked');
            return;
        }

        $pz = $this->config['payzen'] ?? [];
        $restUser = trim((string) ($pz['rest_username'] ?? ''));
        $restPass = trim((string) ($pz['rest_password'] ?? ''));
        $pubKey = trim((string) ($pz['public_key'] ?? ''));
        if ($restUser === '' || $restPass === '' || $pubKey === '') {
            $detail = [];
            if ($restUser === '') {
                $detail[] = 'PAYZEN_SHOP_ID (ou PAYZEN_REST_USERNAME)';
            }
            if ($restPass === '') {
                $detail[] = 'PAYZEN_API_KEY_TEST / PAYZEN_API_KEY_PROD (ou PAYZEN_API_KEY selon PAYZEN_MODE)';
            }
            if ($pubKey === '') {
                $detail[] = 'PAYZEN_PUBLIC_KEY (ligne complète du type boutique:testpublickey_… dans l’extranet OSB)';
            }
            JsonResponse::error(
                503,
                'Paiement PayZen incomplet sur le serveur : ' . implode(', ', $detail) . '.',
                'payzen_not_configured'
            );
            return;
        }

        $userRow = $this->users->findById($userId);
        $email = strtolower(trim((string) ($userRow['email'] ?? '')));
        if ($email === '') {
            JsonResponse::error(422, 'Email du compte requis pour le paiement.', 'email_required');
            return;
        }

        $price = (int) $this->config['price_per_slot_xpf'];
        $ttlMin = max(5, (int) ($pz['pending_ttl_minutes'] ?? 30));
        $expiresAt = (new DateTimeImmutable('now'))->modify("+{$ttlMin} minutes")->format('Y-m-d H:i:s');
        $orderId = 'LR' . bin2hex(random_bytes(16));

        $this->reservations->cleanupExpiredPending();

        $newId = 0;
        try {
            $this->pdo->beginTransaction();
            if ($this->reservations->existsForSlot($terrainId, $date, $startNorm, $endNorm)) {
                $this->pdo->rollBack();
                JsonResponse::error(409, 'Ce créneau n\'est plus disponible.', 'slot_taken');
                return;
            }
            $newId = $this->reservations->create(
                $userId,
                $terrainId,
                $date,
                $startNorm,
                $endNorm,
                $price,
                'pending',
                $expiresAt,
                $orderId
            );
            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate')) {
                JsonResponse::error(409, 'Ce créneau n\'est plus disponible.', 'slot_taken');
                return;
            }
            JsonResponse::error(500, 'Erreur serveur.', 'server_error');
            return;
        }

        try {
            $tokenAnswer = PayZenRest::createPayment($pz, $price, $orderId, $email);
            $paymentUrl = PayZenRest::paymentPageUrl($pz, $tokenAnswer['formToken']);
        } catch (Throwable $e) {
            error_log('PayZen CreatePayment: ' . $e->getMessage());
            $this->reservations->deleteByIdBare($newId);
            JsonResponse::error(502, 'Impossible de démarrer le paiement sécurisé.', 'payment_init_failed');
            return;
        }

        JsonResponse::send(201, [
            'id' => $newId,
            'terrain_id' => $terrainId,
            'date' => $date,
            'start_time' => substr($startNorm, 0, 5),
            'end_time' => substr($endNorm, 0, 5),
            'price' => $price,
            'payment_status' => 'pending',
            'payment_expires_at' => $expiresAt,
            'payzen_mode' => (string) ($pz['mode'] ?? 'test'),
            'payment_url' => $paymentUrl,
        ]);
    }

    public function mine(): void
    {
        $userId = $this->auth->requireUserId();
        $list = $this->reservations->listForUser($userId);
        $out = [];
        foreach ($list as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'terrain_id' => (int) $row['terrain_id'],
                'terrain_name' => $row['terrain_name'],
                'date' => $row['booking_date'],
                'start_time' => substr((string) $row['start_time'], 0, 5),
                'end_time' => substr((string) $row['end_time'], 0, 5),
                'price' => (int) $row['price'],
                'payment_status' => (string) $row['payment_status'],
                'payment_expires_at' => $row['payment_expires_at'],
                'created_at' => $row['created_at'],
            ];
        }
        JsonResponse::send(200, ['data' => $out]);
    }

    public function delete(string $idParam): void
    {
        $userId = $this->auth->requireUserId();
        if (!ctype_digit($idParam)) {
            JsonResponse::error(404, 'Réservation introuvable.', 'not_found');
            return;
        }
        $id = (int) $idParam;
        if ($this->reservations->findByIdForUser($id, $userId) === null) {
            JsonResponse::error(404, 'Réservation introuvable.', 'not_found');
            return;
        }
        $this->reservations->deleteForUser($id, $userId);
        JsonResponse::send(204, []);
    }
}
