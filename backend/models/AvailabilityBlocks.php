<?php

declare(strict_types=1);

final class AvailabilityBlocks
{
    public function __construct(private PDO $pdo)
    {
    }

    public function isTerrainDayBlocked(int $terrainId, string $dateYmd): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM terrain_day_blocks WHERE terrain_id = :tid AND block_date = :d LIMIT 1'
        );
        $stmt->execute(['tid' => $terrainId, 'd' => $dateYmd]);
        return $stmt->fetchColumn() !== false;
    }

    /** @return list<string> start_time values HH:MM:SS matching slot definitions */
    public function blockedSlotStartTimes(int $terrainId, string $dateYmd): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT start_time FROM slot_blocks WHERE terrain_id = :tid AND block_date = :d'
        );
        $stmt->execute(['tid' => $terrainId, 'd' => $dateYmd]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_map(static fn ($t) => (string) $t, $rows);
    }

    /** @return list<array<string, mixed>> */
    public function listTerrainDayBlocks(?int $terrainId = null): array
    {
        if ($terrainId !== null) {
            $stmt = $this->pdo->prepare(
                'SELECT b.id, b.terrain_id, t.name AS terrain_name, b.block_date, b.created_at
                 FROM terrain_day_blocks b
                 INNER JOIN terrains t ON t.id = b.terrain_id
                 WHERE b.terrain_id = :tid
                 ORDER BY b.block_date DESC, b.id DESC'
            );
            $stmt->execute(['tid' => $terrainId]);
            return $stmt->fetchAll();
        }
        $stmt = $this->pdo->query(
            'SELECT b.id, b.terrain_id, t.name AS terrain_name, b.block_date, b.created_at
             FROM terrain_day_blocks b
             INNER JOIN terrains t ON t.id = b.terrain_id
             ORDER BY b.block_date DESC, b.id DESC'
        );
        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function listSlotBlocks(?int $terrainId = null): array
    {
        if ($terrainId !== null) {
            $stmt = $this->pdo->prepare(
                'SELECT b.id, b.terrain_id, t.name AS terrain_name, b.block_date, b.start_time, b.end_time, b.created_at
                 FROM slot_blocks b
                 INNER JOIN terrains t ON t.id = b.terrain_id
                 WHERE b.terrain_id = :tid
                 ORDER BY b.block_date DESC, b.start_time ASC, b.id DESC'
            );
            $stmt->execute(['tid' => $terrainId]);
            return $stmt->fetchAll();
        }
        $stmt = $this->pdo->query(
            'SELECT b.id, b.terrain_id, t.name AS terrain_name, b.block_date, b.start_time, b.end_time, b.created_at
             FROM slot_blocks b
             INNER JOIN terrains t ON t.id = b.terrain_id
             ORDER BY b.block_date DESC, b.start_time ASC, b.id DESC'
        );
        return $stmt->fetchAll();
    }

    public function createTerrainDay(int $terrainId, string $dateYmd): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO terrain_day_blocks (terrain_id, block_date, created_at) VALUES (:tid, :d, NOW())'
        );
        $stmt->execute(['tid' => $terrainId, 'd' => $dateYmd]);
        return (int) $this->pdo->lastInsertId();
    }

    public function deleteTerrainDay(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM terrain_day_blocks WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function createSlotBlock(int $terrainId, string $dateYmd, string $startTime, string $endTime): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO slot_blocks (terrain_id, block_date, start_time, end_time, created_at)
             VALUES (:tid, :d, :st, :et, NOW())'
        );
        $stmt->execute([
            'tid' => $terrainId,
            'd' => $dateYmd,
            'st' => $startTime,
            'et' => $endTime,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function deleteSlotBlock(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM slot_blocks WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
