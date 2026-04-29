<?php

declare(strict_types=1);

final class User
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(string $name, string $email, string $passwordHash): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (name, email, password, created_at) VALUES (:name, :email, :password, NOW())'
        );
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password' => $passwordHash,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => strtolower(trim($email))]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, email, is_admin, created_at FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function isAdmin(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT is_admin FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $v = $stmt->fetchColumn();
        return $v !== false && (int) $v === 1;
    }

    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, name, email, is_admin, created_at FROM users ORDER BY id ASC'
        );
        return $stmt->fetchAll();
    }

    public function emailExists(string $email): bool
    {
        return $this->findByEmail($email) !== null;
    }
}
