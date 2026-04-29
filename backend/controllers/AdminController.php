<?php

declare(strict_types=1);

final class AdminController
{
    public function __construct(
        private User $users,
        private Reservation $reservations,
        private Terrain $terrains,
        private AvailabilityBlocks $blocks
    ) {
    }

    public function users(): void
    {
        $rows = $this->users->listAll();
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'email' => $row['email'],
                'is_admin' => (int) $row['is_admin'] === 1,
                'created_at' => $row['created_at'],
            ];
        }
        JsonResponse::send(200, ['data' => $out]);
    }

    public function reservations(): void
    {
        $from = isset($_GET['from']) ? trim((string) $_GET['from']) : '';
        $to = isset($_GET['to']) ? trim((string) $_GET['to']) : '';
        $fromOk = $from === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $from);
        $toOk = $to === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $to);
        if (!$fromOk || !$toOk) {
            JsonResponse::error(422, 'Paramètres from/to invalides (YYYY-MM-DD).', 'validation_failed');
            return;
        }
        $fromArg = $from !== '' ? $from : null;
        $toArg = $to !== '' ? $to : null;
        $rows = $this->reservations->listAll($fromArg, $toArg);
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'user_id' => (int) $row['user_id'],
                'user_name' => $row['user_name'],
                'user_email' => $row['user_email'],
                'terrain_id' => (int) $row['terrain_id'],
                'terrain_name' => $row['terrain_name'],
                'date' => $row['booking_date'],
                'start_time' => substr((string) $row['start_time'], 0, 5),
                'end_time' => substr((string) $row['end_time'], 0, 5),
                'price' => (int) $row['price'],
                'payment_status' => (string) ($row['payment_status'] ?? 'paid'),
                'created_at' => $row['created_at'],
            ];
        }
        JsonResponse::send(200, ['data' => $out]);
    }

    public function deleteReservation(string $idParam): void
    {
        if (!ctype_digit($idParam)) {
            JsonResponse::error(404, 'Réservation introuvable.', 'not_found');
            return;
        }
        $id = (int) $idParam;
        if ($this->reservations->findById($id) === null) {
            JsonResponse::error(404, 'Réservation introuvable.', 'not_found');
            return;
        }
        $this->reservations->deleteById($id);
        JsonResponse::send(204, []);
    }

    public function listTerrainDayBlocks(): void
    {
        $tid = null;
        if (isset($_GET['terrain_id']) && $_GET['terrain_id'] !== '') {
            if (!ctype_digit((string) $_GET['terrain_id'])) {
                JsonResponse::error(422, 'terrain_id invalide.', 'validation_failed');
                return;
            }
            $tid = (int) $_GET['terrain_id'];
        }
        $rows = $this->blocks->listTerrainDayBlocks($tid);
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'terrain_id' => (int) $row['terrain_id'],
                'terrain_name' => $row['terrain_name'],
                'block_date' => $row['block_date'],
                'created_at' => $row['created_at'],
            ];
        }
        JsonResponse::send(200, ['data' => $out]);
    }

    public function createTerrainDayBlock(): void
    {
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
        $date = trim((string) ($data['block_date'] ?? ($data['date'] ?? '')));
        if ($terrainId < 1 || $date === '') {
            JsonResponse::error(422, 'terrain_id et block_date sont requis.', 'validation_failed');
            return;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            JsonResponse::error(422, 'Date invalide (YYYY-MM-DD).', 'validation_failed');
            return;
        }
        if ($this->terrains->findById($terrainId) === null) {
            JsonResponse::error(404, 'Terrain introuvable.', 'not_found');
            return;
        }
        if ($this->blocks->isTerrainDayBlocked($terrainId, $date)) {
            JsonResponse::error(409, 'Ce jour est déjà bloqué pour ce terrain.', 'duplicate');
            return;
        }
        try {
            $newId = $this->blocks->createTerrainDay($terrainId, $date);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate')) {
                JsonResponse::error(409, 'Ce jour est déjà bloqué pour ce terrain.', 'duplicate');
                return;
            }
            JsonResponse::error(500, 'Erreur serveur.', 'server_error');
            return;
        }
        JsonResponse::send(201, ['id' => $newId, 'terrain_id' => $terrainId, 'block_date' => $date]);
    }

    public function deleteTerrainDayBlock(string $idParam): void
    {
        if (!ctype_digit($idParam)) {
            JsonResponse::error(404, 'Bloc introuvable.', 'not_found');
            return;
        }
        $id = (int) $idParam;
        if (!$this->blocks->deleteTerrainDay($id)) {
            JsonResponse::error(404, 'Bloc introuvable.', 'not_found');
            return;
        }
        JsonResponse::send(204, []);
    }

    public function listSlotBlocks(): void
    {
        $tid = null;
        if (isset($_GET['terrain_id']) && $_GET['terrain_id'] !== '') {
            if (!ctype_digit((string) $_GET['terrain_id'])) {
                JsonResponse::error(422, 'terrain_id invalide.', 'validation_failed');
                return;
            }
            $tid = (int) $_GET['terrain_id'];
        }
        $rows = $this->blocks->listSlotBlocks($tid);
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'terrain_id' => (int) $row['terrain_id'],
                'terrain_name' => $row['terrain_name'],
                'block_date' => $row['block_date'],
                'start_time' => substr((string) $row['start_time'], 0, 5),
                'end_time' => substr((string) $row['end_time'], 0, 5),
                'created_at' => $row['created_at'],
            ];
        }
        JsonResponse::send(200, ['data' => $out]);
    }

    public function createSlotBlock(): void
    {
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
        $date = trim((string) ($data['block_date'] ?? ($data['date'] ?? '')));
        $startRaw = trim((string) ($data['start_time'] ?? ''));
        $endRaw = trim((string) ($data['end_time'] ?? ''));
        if ($terrainId < 1 || $date === '' || $startRaw === '' || $endRaw === '') {
            JsonResponse::error(
                422,
                'terrain_id, block_date, start_time et end_time sont requis.',
                'validation_failed'
            );
            return;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            JsonResponse::error(422, 'Date invalide.', 'validation_failed');
            return;
        }
        $startNorm = Slots::normalizeTime($startRaw);
        $endNorm = Slots::normalizeTime($endRaw);
        if ($startNorm === '' || $endNorm === '' || !Slots::isValidSlot($startNorm, $endNorm)) {
            JsonResponse::error(422, 'Créneau non reconnu (1h30, horaires officiels).', 'invalid_slot');
            return;
        }
        if ($this->terrains->findById($terrainId) === null) {
            JsonResponse::error(404, 'Terrain introuvable.', 'not_found');
            return;
        }
        if ($this->blocks->isTerrainDayBlocked($terrainId, $date)) {
            JsonResponse::error(422, 'Le terrain est déjà fermé pour cette journée entière.', 'terrain_blocked');
            return;
        }
        try {
            $newId = $this->blocks->createSlotBlock($terrainId, $date, $startNorm, $endNorm);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate')) {
                JsonResponse::error(409, 'Ce créneau est déjà bloqué.', 'duplicate');
                return;
            }
            JsonResponse::error(500, 'Erreur serveur.', 'server_error');
            return;
        }
        JsonResponse::send(201, [
            'id' => $newId,
            'terrain_id' => $terrainId,
            'block_date' => $date,
            'start_time' => substr($startNorm, 0, 5),
            'end_time' => substr($endNorm, 0, 5),
        ]);
    }

    public function deleteSlotBlock(string $idParam): void
    {
        if (!ctype_digit($idParam)) {
            JsonResponse::error(404, 'Bloc introuvable.', 'not_found');
            return;
        }
        $id = (int) $idParam;
        if (!$this->blocks->deleteSlotBlock($id)) {
            JsonResponse::error(404, 'Bloc introuvable.', 'not_found');
            return;
        }
        JsonResponse::send(204, []);
    }
}
