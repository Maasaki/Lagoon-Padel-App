<?php

declare(strict_types=1);

final class Reservation
{
    public function __construct(private PDO $pdo)
    {
    }

    public function existsForSlot(int $terrainId, string $date, string $startTime, string $endTime): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM reservations WHERE terrain_id = :tid AND `date` = :d
             AND start_time = :st AND end_time = :et LIMIT 1'
        );
        $stmt->execute([
            'tid' => $terrainId,
            'd' => $date,
            'st' => $startTime,
            'et' => $endTime,
        ]);
        return $stmt->fetchColumn() !== false;
    }

    public function create(int $userId, int $terrainId, string $date, string $startTime, string $endTime, int $priceXpf): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO reservations (user_id, terrain_id, `date`, start_time, end_time, price, created_at)
             VALUES (:uid, :tid, :d, :st, :et, :price, NOW())'
        );
        $stmt->execute([
            'uid' => $userId,
            'tid' => $terrainId,
            'd' => $date,
            'st' => $startTime,
            'et' => $endTime,
            'price' => $priceXpf,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listForUser(int $userId): array
    {
        $sql = 'SELECT r.id, r.terrain_id, t.name AS terrain_name, r.`date` AS booking_date, r.start_time, r.end_time, r.price, r.created_at
                FROM reservations r
                INNER JOIN terrains t ON t.id = r.terrain_id
                WHERE r.user_id = :uid
                ORDER BY r.`date` DESC, r.start_time DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findByIdForUser(int $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM reservations WHERE id = :id AND user_id = :uid LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'uid' => $userId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function deleteForUser(int $id, int $userId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM reservations WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => $id, 'uid' => $userId]);
        return $stmt->rowCount() > 0;
    }

    /** @return list<string> */
    public function bookedStartTimesForTerrainDate(int $terrainId, string $date): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT start_time FROM reservations WHERE terrain_id = :tid AND `date` = :d'
        );
        $stmt->execute(['tid' => $terrainId, 'd' => $date]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_map(static fn ($t) => (string) $t, $rows);
    }
}
