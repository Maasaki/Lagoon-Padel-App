<?php

declare(strict_types=1);

final class TerrainController
{
    public function __construct(
        private Terrain $terrains,
        private Reservation $reservations,
        private AvailabilityBlocks $blocks
    ) {
    }

    public function list(): void
    {
        JsonResponse::send(200, ['data' => $this->terrains->all()]);
    }

    public function slots(string $idParam): void
    {
        if (!ctype_digit($idParam)) {
            JsonResponse::error(404, 'Terrain introuvable.', 'not_found');
            return;
        }
        $id = (int) $idParam;
        $terrain = $this->terrains->findById($id);
        if ($terrain === null) {
            JsonResponse::error(404, 'Terrain introuvable.', 'not_found');
            return;
        }

        $date = isset($_GET['date']) ? trim((string) $_GET['date']) : '';
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            JsonResponse::error(422, 'Paramètre date requis (YYYY-MM-DD).', 'validation_failed');
            return;
        }

        $d = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if ($d === false || $d->format('Y-m-d') !== $date) {
            JsonResponse::error(422, 'Date invalide.', 'validation_failed');
            return;
        }

        $this->reservations->cleanupExpiredPending();
        $booked = $this->reservations->bookedStartTimesForTerrainDate($id, $date);
        $bookedSet = array_flip($booked);
        $terrainDayBlocked = $this->blocks->isTerrainDayBlocked($id, $date);
        $blockedStarts = array_flip($this->blocks->blockedSlotStartTimes($id, $date));

        $slots = [];
        foreach (Slots::all() as $slot) {
            $start = $slot['start'];
            $available = !isset($bookedSet[$start]);
            if ($terrainDayBlocked) {
                $available = false;
            } elseif (isset($blockedStarts[$start])) {
                $available = false;
            }
            $slots[] = [
                'start_time' => substr($start, 0, 5),
                'end_time' => substr($slot['end'], 0, 5),
                'available' => $available,
            ];
        }

        JsonResponse::send(200, [
            'terrain' => $terrain,
            'date' => $date,
            'price_xpf' => lagoon_config()['price_per_slot_xpf'],
            'slots' => $slots,
        ]);
    }
}
