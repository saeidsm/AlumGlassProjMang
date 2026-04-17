<?php
/**
 * includes/pagination.php
 *
 * Reusable server-side pagination helper.
 *
 * Basic usage:
 *   require_once __DIR__ . '/../../includes/pagination.php';
 *   $result = paginate($pdo, "SELECT * FROM reports WHERE project_id = ?", [$projectId], 25);
 *   foreach ($result['data'] as $row) { ... }
 *   echo renderPagination($result, '/pardis/reports.php');
 *
 * The SQL statement must NOT end with a semicolon, LIMIT, or OFFSET clause —
 * the helper appends those itself. The caller is responsible for parameter
 * binding exactly as with prepare/execute.
 */

if (!function_exists('paginate')) {
    /**
     * Run a paginated query and return the page payload.
     *
     * @param PDO    $pdo
     * @param string $query   Base SELECT without LIMIT/OFFSET/trailing semicolon.
     * @param array  $params  Positional or named bindings for $query.
     * @param int    $perPage Rows per page (clamped to [1, 200]).
     * @param int|null $page  Optional explicit page; default reads $_GET['page'].
     * @return array{data:array,total:int,page:int,per_page:int,total_pages:int}
     */
    function paginate(PDO $pdo, string $query, array $params = [], int $perPage = 25, ?int $page = null): array {
        $perPage = max(1, min(200, $perPage));
        $page = $page ?? (int)($_GET['page'] ?? 1);
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        // Count total using a subquery — works for any SELECT.
        $countSql = "SELECT COUNT(*) FROM ($query) AS _paginate_count";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Fetch current page — LIMIT/OFFSET values are cast to int for safety
        // (MySQL does not accept them as bound parameters in all drivers).
        $pageSql = rtrim($query, "; \t\n\r\0\x0B")
                 . ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
        $pageStmt = $pdo->prepare($pageSql);
        $pageStmt->execute($params);
        $data = $pageStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data'        => $data,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $total > 0 ? (int) ceil($total / $perPage) : 1,
        ];
    }
}

if (!function_exists('renderPagination')) {
    /**
     * Render Bootstrap-agnostic pagination HTML. Styled by .ag-pagination
     * classes in /assets/css/global.css.
     *
     * Preserves existing query-string arguments except `page`.
     */
    function renderPagination(array $result, string $baseUrl): string {
        $totalPages = $result['total_pages'] ?? 1;
        if ($totalPages <= 1) {
            return '';
        }

        $currentPage = (int) ($result['page'] ?? 1);
        $total = (int) ($result['total'] ?? 0);

        // Preserve existing GET parameters (minus page).
        $queryParams = $_GET;
        unset($queryParams['page']);

        $buildUrl = function (int $p) use ($baseUrl, $queryParams): string {
            $params = array_merge($queryParams, ['page' => $p]);
            $separator = str_contains($baseUrl, '?') ? '&' : '?';
            // If baseUrl already has query string, we still rebuild from the merged set.
            $qs = http_build_query($params);
            $cleanBase = strtok($baseUrl, '?');
            return $cleanBase . '?' . $qs;
        };

        $esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $html = '<nav class="ag-pagination" aria-label="صفحه‌بندی"><ul>';

        if ($currentPage > 1) {
            $html .= '<li><a href="' . $esc($buildUrl($currentPage - 1)) . '">قبلی</a></li>';
        }

        $start = max(1, $currentPage - 2);
        $end = min($totalPages, $currentPage + 2);
        if ($start > 1) {
            $html .= '<li><a href="' . $esc($buildUrl(1)) . '">1</a></li>';
            if ($start > 2) {
                $html .= '<li class="ag-pagination-gap"><span>…</span></li>';
            }
        }
        for ($i = $start; $i <= $end; $i++) {
            $cls = $i === $currentPage ? ' class="active"' : '';
            $html .= '<li' . $cls . '><a href="' . $esc($buildUrl($i)) . '">' . $i . '</a></li>';
        }
        if ($end < $totalPages) {
            if ($end < $totalPages - 1) {
                $html .= '<li class="ag-pagination-gap"><span>…</span></li>';
            }
            $html .= '<li><a href="' . $esc($buildUrl($totalPages)) . '">' . $totalPages . '</a></li>';
        }

        if ($currentPage < $totalPages) {
            $html .= '<li><a href="' . $esc($buildUrl($currentPage + 1)) . '">بعدی</a></li>';
        }

        $html .= '</ul>';
        $html .= '<span class="ag-pagination-info">صفحه ' . $currentPage
               . ' از ' . $totalPages
               . ' (مجموع: ' . $total . ')</span>';
        $html .= '</nav>';
        return $html;
    }
}
