<?php

/**
 * UserRepository — queries the users table of the common database.
 */
class UserRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Batch fetch by IDs — replaces loops that did one SELECT per user.
     *
     * @param int[] $ids
     * @return array<int, array>  userId => user row
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id IN ($placeholders)");
        $stmt->execute($ids);

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int) $row['id']] = $row;
        }
        return $out;
    }

    public function getActive(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM users WHERE status = 'active' ORDER BY full_name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActiveByRole(string $role): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM users WHERE status = 'active' AND role = ? ORDER BY full_name"
        );
        $stmt->execute([$role]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
