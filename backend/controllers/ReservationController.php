<?php

declare(strict_types=1);

final class ReservationController
{
    public function __construct(
        private PDO $pdo,
        private array $config,
        private Reservation $reservations,
        private Terrain $terrains,
        private AuthMiddleware $auth
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

        $price = (int) $this->config['price_per_slot_xpf'];

        try {
            $this->pdo->beginTransaction();
            if ($this->reservations->existsForSlot($terrainId, $date, $startNorm, $endNorm)) {
                $this->pdo->rollBack();
                JsonResponse::error(409, 'Ce créneau n\'est plus disponible.', 'slot_taken');
                return;
            }
            $newId = $this->reservations->create($userId, $terrainId, $date, $startNorm, $endNorm, $price);
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

        JsonResponse::send(201, [
            'id' => $newId,
            'terrain_id' => $terrainId,
            'date' => $date,
            'start_time' => substr($startNorm, 0, 5),
            'end_time' => substr($endNorm, 0, 5),
            'price' => $price,
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
