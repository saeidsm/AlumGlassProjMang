/**
 * Auto-apply data-label attributes to <td> cells based on matching <th>
 * headers, so tables tagged `.ag-table-responsive` become card-view on
 * phones without authors having to annotate every cell.
 *
 * Usage:
 *   <table class="ag-table-responsive">
 *     <thead><tr><th>نام</th><th>تاریخ</th></tr></thead>
 *     <tbody>... </tbody>
 *   </table>
 *   <script src="/assets/js/responsive-tables.js" defer></script>
 *
 * Idempotent — safe to call multiple times or to invoke on dynamically
 * added tables via `window.AG?.enhanceTables()`.
 */

(function () {
    function enhance(root = document) {
        const tables = root.querySelectorAll('table.ag-table-responsive');
        tables.forEach(enhanceTable);
    }

    function enhanceTable(table) {
        const headers = [...table.querySelectorAll('thead th')].map((th) => th.textContent.trim());
        if (!headers.length) return;
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach((row) => {
            [...row.children].forEach((td, i) => {
                if (td.tagName !== 'TD') return;
                if (!td.hasAttribute('data-label') && headers[i] != null) {
                    td.setAttribute('data-label', headers[i]);
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => enhance());
    } else {
        enhance();
    }

    window.AG = window.AG || {};
    window.AG.enhanceTables = enhance;

    // Observe dynamically inserted tables
    if ('MutationObserver' in window) {
        const obs = new MutationObserver((mutations) => {
            for (const m of mutations) {
                m.addedNodes.forEach((n) => {
                    if (n.nodeType !== 1) return;
                    if (n.matches?.('table.ag-table-responsive')) enhanceTable(n);
                    n.querySelectorAll?.('table.ag-table-responsive').forEach(enhanceTable);
                });
            }
        });
        obs.observe(document.body, { childList: true, subtree: true });
    }
})();
