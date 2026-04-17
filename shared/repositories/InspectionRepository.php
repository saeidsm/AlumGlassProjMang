<?php

/**
 * InspectionRepository — queries the inspections tables of a project database.
 */
class InspectionRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByElement(string $elementId, ?string $partName = null): array
    {
        if ($partName === null) {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM inspections WHERE element_id = ? ORDER BY inspection_date DESC'
            );
            $stmt->execute([$elementId]);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM inspections WHERE element_id = ? AND part_name = ? ORDER BY inspection_date DESC'
            );
            $stmt->execute([$elementId, $partName]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRecentByUser(int $userId, int $limit = 10): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM inspections WHERE inspector_id = ? ORDER BY inspection_date DESC LIMIT $limit"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStatsByStage(int $stageId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT status, COUNT(*) AS count FROM inspections WHERE stage_id = ? GROUP BY status'
        );
        $stmt->execute([$stageId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countByElement(string $elementId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM inspections WHERE element_id = ?');
        $stmt->execute([$elementId]);
        return (int) $stmt->fetchColumn();
    }
}
