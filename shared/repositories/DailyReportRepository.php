<?php

/**
 * DailyReportRepository — queries the daily_reports tables of a project database.
 *
 * Provides batch-fetch helpers to replace N+1 patterns that previously
 * loaded child tables per report inside a loop.
 */
class DailyReportRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM daily_reports WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByDateRange(string $from, string $to, array $filters = []): array
    {
        $sql = 'SELECT * FROM daily_reports WHERE report_date BETWEEN ? AND ?';
        $params = [$from, $to];

        if (!empty($filters['user_id'])) {
            $sql .= ' AND submitted_by = ?';
            $params[] = (int) $filters['user_id'];
        }
        $sql .= ' ORDER BY report_date DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getWithPagination(array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT SQL_CALC_FOUND_ROWS * FROM daily_reports WHERE 1=1';
        $params = [];

        if (!empty($filters['from'])) {
            $sql .= ' AND report_date >= ?';
            $params[] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND report_date <= ?';
            $params[] = $filters['to'];
        }
        if (!empty($filters['user_id'])) {
            $sql .= ' AND submitted_by = ?';
            $params[] = (int) $filters['user_id'];
        }

        $sql .= " ORDER BY report_date DESC LIMIT $perPage OFFSET $offset";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total = (int) $this->pdo->query('SELECT FOUND_ROWS()')->fetchColumn();

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }

    public function getPersonnel(int $reportId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM daily_report_personnel WHERE report_id = ?');
        $stmt->execute([$reportId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMachinery(int $reportId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM daily_report_machinery WHERE report_id = ?');
        $stmt->execute([$reportId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMaterials(int $reportId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM daily_report_materials WHERE report_id = ?');
        $stmt->execute([$reportId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActivities(int $reportId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM daily_report_activities WHERE report_id = ?');
        $stmt->execute([$reportId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Batch fetch activity counts for many reports — replaces N+1 loops.
     *
     * @param int[] $reportIds
     * @return array<int,int>  reportId => activityCount
     */
    public function getActivityCountsByReportIds(array $reportIds): array
    {
        if (empty($reportIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($reportIds), '?'));
        $sql = "SELECT report_id, COUNT(*) AS c
                  FROM daily_report_activities
                 WHERE report_id IN ($placeholders)
              GROUP BY report_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_map('intval', $reportIds));

        $counts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[(int) $row['report_id']] = (int) $row['c'];
        }
        // Fill missing with zero for deterministic callers
        foreach ($reportIds as $id) {
            $counts[(int) $id] = $counts[(int) $id] ?? 0;
        }
        return $counts;
    }
}
