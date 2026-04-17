<?php

/**
 * ElementRepository — queries the elements table of a project database.
 *
 * Encapsulates the most-reused element queries so pages and APIs no
 * longer embed raw SQL. Constructed with a PDO bound to the correct
 * project database (see getRepository() in bootstrap).
 */
class ElementRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(string $elementId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM elements WHERE element_id = ?');
        $stmt->execute([$elementId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT * FROM elements WHERE element_id IN ($placeholders)");
        $stmt->execute(array_values($ids));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByZone(string $zone): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM elements WHERE zone = ? ORDER BY element_id');
        $stmt->execute([$zone]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByType(string $type): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM elements WHERE element_type = ? ORDER BY element_id');
        $stmt->execute([$type]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStatusCounts(): array
    {
        $stmt = $this->pdo->query('SELECT status, COUNT(*) AS count FROM elements GROUP BY status');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus(string $elementId, string $status, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE elements SET status = ?, updated_by = ?, updated_at = NOW() WHERE element_id = ?'
        );
        return $stmt->execute([$status, $userId, $elementId]);
    }

    public function countByStatus(string $status): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM elements WHERE status = ?');
        $stmt->execute([$status]);
        return (int) $stmt->fetchColumn();
    }
}
