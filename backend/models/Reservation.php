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
             AND start_time = :st AND end_time = :et
             AND (
               payment_status = \'paid\'
               OR (payment_status = \'pending\' AND payment_expires_at IS NOT NULL AND payment_expires_at > NOW())
             )
             LIMIT 1'
        );
        $stmt->execute([
            'tid' => $terrainId,
            'd' => $date,
            'st' => $startTime,
            'et' => $endTime,
        ]);
        return $stmt->fetchColumn() !== false;
    }

    public function create(
        int $userId,
        int $terrainId,
        string $date,
        string $startTime,
        string $endTime,
        int $priceXpf,
        string $paymentStatus,
        string $paymentExpiresAt,
        string $payzenOrderId
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO reservations (user_id, terrain_id, `date`, start_time, end_time, price, payment_status, payment_expires_at, payzen_order_id, created_at)
             VALUES (:uid, :tid, :d, :st, :et, :price, :pstatus, :pexp, :poid, NOW())'
        );
        $stmt->execute([
            'uid' => $userId,
            'tid' => $terrainId,
            'd' => $date,
            'st' => $startTime,
            'et' => $endTime,
            'price' => $priceXpf,
            'pstatus' => $paymentStatus,
            'pexp' => $paymentExpiresAt,
            'poid' => $payzenOrderId,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function deleteByIdBare(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM reservations WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
    }

    /** Supprime les réservations « pending » dont le délai de paiement est dépassé. */
    public function cleanupExpiredPending(): void
    {
        $this->pdo->exec(
            "DELETE FROM reservations WHERE payment_status = 'pending'
             AND payment_expires_at IS NOT NULL AND payment_expires_at < NOW()"
        );
    }

    /** @return array<string, mixed>|null */
    public function findByPayzenOrderId(string $orderId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM reservations WHERE payzen_order_id = :oid LIMIT 1'
        );
        $stmt->execute(['oid' => $orderId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function markPaid(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE reservations SET payment_status = 'paid', payment_expires_at = NULL WHERE id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
    }

    public function markPaymentFailedIfPending(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE reservations SET payment_status = 'failed', payment_expires_at = NULL
             WHERE id = :id AND payment_status = 'pending' LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
    }

    /** @return list<array<string, mixed>> */
    public function listForUser(int $userId): array
    {
        $sql = 'SELECT r.id, r.terrain_id, t.name AS terrain_name, r.`date` AS booking_date, r.start_time, r.end_time, r.price,
                       r.payment_status, r.payment_expires_at, r.created_at
                FROM reservations r
                INNER JOIN terrains t ON t.id = r.terrain_id
                WHERE r.user_id = :uid
                  AND r.payment_status <> \'failed\'
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

    /** @return list<array<string, mixed>> */
    public function listAll(?string $fromDate = null, ?string $toDate = null, int $limit = 500): array
    {
        $sql = 'SELECT r.id, r.user_id, u.name AS user_name, u.email AS user_email,
                       r.terrain_id, t.name AS terrain_name, r.`date` AS booking_date,
                       r.start_time, r.end_time, r.price, r.payment_status, r.created_at
                FROM reservations r
                INNER JOIN users u ON u.id = r.user_id
                INNER JOIN terrains t ON t.id = r.terrain_id';
        $conds = [];
        $params = [];
        if ($fromDate !== null && $fromDate !== '') {
            $conds[] = 'r.`date` >= :fd';
            $params['fd'] = $fromDate;
        }
        if ($toDate !== null && $toDate !== '') {
            $conds[] = 'r.`date` <= :td';
            $params['td'] = $toDate;
        }
        if ($conds !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conds);
        }
        $sql .= ' ORDER BY r.`date` DESC, r.start_time DESC LIMIT ' . (int) $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.user_id, u.name AS user_name, u.email AS user_email,
                    r.terrain_id, t.name AS terrain_name, r.`date` AS booking_date,
                    r.start_time, r.end_time, r.price, r.created_at
             FROM reservations r
             INNER JOIN users u ON u.id = r.user_id
             INNER JOIN terrains t ON t.id = r.terrain_id
             WHERE r.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function deleteById(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM reservations WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /** @return list<string> */
    public function bookedStartTimesForTerrainDate(int $terrainId, string $date): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT start_time FROM reservations WHERE terrain_id = :tid AND `date` = :d
             AND (
               payment_status = \'paid\'
               OR (payment_status = \'pending\' AND payment_expires_at IS NOT NULL AND payment_expires_at > NOW())
             )'
        );
        $stmt->execute(['tid' => $terrainId, 'd' => $date]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_map(static fn ($t) => (string) $t, $rows);
    }
}
