# Phase 2 — Autonomous Execution Plan
# اجرای خودکار فاز ۲ — بدون نیاز به تأیید

> **حالت:** خودکار — بدون توقف بین زیرفازها
> **شروع از:** `main` branch (v0.2.0-security-hardened)
> **خروجی نهایی:** گزارش کامل در `docs/reports/phase2-final-report.md` + لینک دانلود

---

## ⚠️ CRITICAL INSTRUCTIONS — READ FIRST

1. **Do NOT wait for user approval between sub-phases.** Execute 2A → 2B → 2C → 2D → 2E sequentially.
2. **Each sub-phase gets its own branch** merged to `main` before starting the next.
3. **After EVERY file edit**, run `php -l <file>` to verify syntax. If PHP is not available, use regex/AST validation.
4. **If a sub-phase fails verification**, log the failure in the report and continue to the next sub-phase.
5. **After ALL sub-phases complete**, generate the final report and provide the download link.
6. **Do NOT deploy anywhere.** This is code-only — no server, no production.
7. **Update docs** (`TECH_DEBT.md`, `CHANGELOG.md`, `ARCHITECTURE.md`) at the end, once.

---

## Execution Flow

```
main (v0.2.0) 
  └─→ branch: claude/phase-2a-design-system
       └─→ merge to main
            └─→ branch: claude/phase-2b-header-unification  
                 └─→ merge to main
                      └─→ branch: claude/phase-2c-extract-inline-assets
                           └─→ merge to main
                                └─→ branch: claude/phase-2d-responsive-merge
                                     └─→ merge to main
                                          └─→ branch: claude/phase-2e-query-optimization
                                               └─→ merge to main
                                                    └─→ tag: v0.3.0-performance-ux
                                                         └─→ generate final report
```

---

## 2A — Design System & Global Assets

**Branch:** `claude/phase-2a-design-system`

### Create these files:

**1. `assets/css/design-system.css`**
Extract common colors, fonts, spacing from existing headers into CSS variables:
- Scan `ghom/header_ghom.php` and `pardis/header_pardis.php` for existing color values
- Create `:root` with `--ag-*` prefixed variables for: primary colors, semantic colors (success/danger/warning/info), neutrals, typography scale, spacing scale, border radii, shadows, sidebar/header dimensions, transitions
- Include Vazir font-face declaration

**2. `assets/css/global.css`**
Common component styles extracted from repeated patterns across files:
- `@import` the design-system.css
- Base reset (box-sizing, margin, padding)
- Body defaults (font-family from var, direction rtl, background, line-height)
- `.ag-card` — white card with radius and shadow (pattern repeated in 30+ files)
- `.ag-btn`, `.ag-btn-primary`, `.ag-btn-danger`, `.ag-btn-success` — button styles
- `.ag-badge`, `.ag-badge-success`, `.ag-badge-danger`, `.ag-badge-warning`, `.ag-badge-info` — status badges
- `.ag-table` — RTL-friendly table styling
- `.ag-spinner` — CSS-only loading spinner
- `.ag-toast` — notification toast (fixed position, auto-dismiss)
- `.ag-form-group` — form field wrapper
- `.ag-alert` — alert messages

**3. `assets/js/global.js`**
Common JavaScript utilities:
- `AG.toast(message, type, duration)` — show toast notification
- `AG.showLoading(selector)` / `AG.hideLoading(selector)` — loading overlay
- `AG.getCsrfToken()` — read from meta tag
- `AG.fetch(url, options)` — fetch wrapper with CSRF header
- `AG.confirm(message)` — Persian confirmation dialog
- `AG.formatNumber(num)` — add thousands separator
- `AG.formatPersianDate(dateStr)` — format date for display
- `AG.debounce(fn, ms)` — debounce utility
- `AG.autoSaveForm(formId, storageKey)` — auto-save form to localStorage
- `AG.restoreForm(formId, storageKey)` — restore saved form data

### Commits:
```
style(global): create design-system.css with CSS custom properties
style(global): create global.css with shared component styles
feat(global): create global.js with utility functions (toast, loading, CSRF, form helpers)
```

### Merge to main, then continue.

---

## 2B — Header Unification

**Branch:** `claude/phase-2b-header-unification`

### Goal: Reduce 11 header files to 2 responsive headers (one per module) + 1 shared header.

### Step 1: Analyze current headers
```bash
# List all header includes to understand dependencies
grep -rn "include.*header\|require.*header" --include="*.php" | grep -v "includes/mpdf\|includes/libraries" | sort
```

### Step 2: Create unified ghom header

Create `ghom/header.php` (new unified responsive file):
1. Start from `ghom/header_ghom.php` as the base (it's the desktop version)
2. Extract the mobile-specific CSS from `ghom/header_ghom_mobile.php` and `ghom/header_m_ghom.php`
3. Wrap mobile CSS in `@media (max-width: 768px) { ... }`
4. Add hamburger menu toggle for mobile (from mobile headers)
5. Add `<link rel="stylesheet" href="/assets/css/design-system.css">` and `<link rel="stylesheet" href="/assets/css/global.css">` in `<head>`
6. Add `<meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">`
7. Add `<script src="/assets/js/global.js" defer></script>` before `</body>`
8. Add `<script src="/assets/js/csrf-injector.js" defer></script>` before `</body>`
9. Keep all role-based menu visibility logic intact
10. Keep weather widget if present

### Step 3: Create unified pardis header

Create `pardis/header.php` (new unified responsive file):
1. Same process as ghom, but starting from `pardis/header_pardis.php`
2. Merge mobile CSS from `pardis/header_pardis_mobile.php` and `pardis/header_p_mobile.php`

### Step 4: Update all includes

```bash
# Find and replace old header includes
# For ghom files:
grep -rn "header_ghom_mobile\|header_m_ghom\|header_ins\|header_mobile" ghom/ --include="*.php" -l
# Replace with: include 'header.php' (or appropriate relative path)

# For pardis files:
grep -rn "header_pardis_mobile\|header_p_mobile" pardis/ --include="*.php" -l
# Replace with: include 'header.php'
```

**IMPORTANT:** 
- `header_ghom.php` references should become `header.php` in ghom/
- `header_pardis.php` references should become `header.php` in pardis/
- Keep the old files temporarily as `header_ghom_legacy.php` etc. until all references are updated
- Then delete the legacy files

### Step 5: Delete deprecated headers
After confirming no remaining references:
```
DELETE: ghom/header_ghom.php (replaced by ghom/header.php)
DELETE: ghom/header_ghom_mobile.php
DELETE: ghom/header_m_ghom.php
DELETE: ghom/header_ins.php
DELETE: ghom/header_mobile.php
DELETE: pardis/header_pardis.php (replaced by pardis/header.php)
DELETE: pardis/header_pardis_mobile.php
DELETE: pardis/header_p_mobile.php
DELETE: header_m_ghom.php (root level)
```

Keep: `header_common.php` (if used by non-module pages), `ghom/footer.php`, `pardis/footer.php`, `footer_common.php`

### Commits:
```
refactor(ghom): create unified responsive header.php replacing 4 header files
refactor(pardis): create unified responsive header.php replacing 3 header files
refactor(global): update all PHP includes to reference new unified headers
chore(global): delete 9 deprecated header files
```

### Verification:
```bash
# Must return 0 — no references to old headers
grep -rn "header_ghom_mobile\|header_m_ghom\|header_ins\|header_ghom1\|header_mobile" --include="*.php" | grep -v "# \|// \|/\*" | wc -l
grep -rn "header_pardis_mobile\|header_p_mobile" --include="*.php" | grep -v "# \|// \|/\*" | wc -l
```

### Merge to main, then continue.

---

## 2C — Inline CSS/JS Extraction

**Branch:** `claude/phase-2c-extract-inline-assets`

### Goal: Extract inline `<style>` and `<script>` blocks from the 10 largest PHP files into external files.

### Target files (ordered by size, largest first):

| # | File | Size | Extract to |
|---|------|------|-----------|
| 1 | `pardis/daily_reports.php` | 143KB | `pardis/assets/css/daily_reports.css` + `pardis/assets/js/daily_reports.js` |
| 2 | `pardis/packing_list_viewer.php` | 125KB | `pardis/assets/css/packing_list_viewer.css` + `pardis/assets/js/packing_list_viewer.js` |
| 3 | `messages.php` | 100KB | `assets/css/messages.css` + `assets/js/messages.js` |
| 4 | `pardis/meeting_minutes_form.php` | 90KB | `pardis/assets/css/meeting_minutes.css` + `pardis/assets/js/meeting_minutes.js` |
| 5 | `pardis/letters.php` | 74KB | `pardis/assets/css/letters.css` + `pardis/assets/js/letters.js` |
| 6 | `pardis/index.php` | 73KB | `pardis/assets/css/index.css` + `pardis/assets/js/index.js` |
| 7 | `ghom/viewer.php` | 72KB | `ghom/assets/css/viewer.css` + `ghom/assets/js/viewer.js` |
| 8 | `ghom/reports.php` | 76KB | `ghom/assets/css/reports.css` + `ghom/assets/js/reports.js` |
| 9 | `ghom/index.php` | 67KB | `ghom/assets/css/index.css` + `ghom/assets/js/index.js` |
| 10 | `admin.php` | 67KB | `assets/css/admin.css` + `assets/js/admin.js` |

### Extraction method for each file:

```
For each target file:
  1. Read the file
  2. Find all <style>...</style> blocks
     - Concatenate their contents into {module}/assets/css/{name}.css
     - Replace the <style> blocks with: <link rel="stylesheet" href="/{module}/assets/css/{name}.css">
     - Place the <link> in <head> section (only one link, even if multiple style blocks)
  3. Find all <script>...</script> blocks (WITHOUT src attribute)
     - If the script contains PHP tags (<?php, <?=):
       → Extract only the PHP-variable parts into a small inline CONFIG block:
         <script>const PAGE_CONFIG = <?= json_encode([...]) ?>;</script>
       → Move the rest of the JS to the external file
     - If the script is pure JS:
       → Move entirely to external file
     - Concatenate into {module}/assets/js/{name}.js
     - Replace with: <script src="/{module}/assets/js/{name}.js" defer></script>
     - Place the <script> before </body>
  4. Verify: php -l {file}
  5. Record before/after file size
```

### ⚠️ Important rules:
- **DO NOT extract `<script src="...">`** — those are already external and stay as-is
- **PHP variables in JS** — The most common pattern is:
  ```php
  <script>
  var reportId = <?= $report_id ?>;
  var csrfToken = '<?= $_SESSION['csrf_token'] ?>';
  // ... 200 lines of pure JS ...
  </script>
  ```
  Convert to:
  ```php
  <script>
  const PAGE_CONFIG = <?= json_encode([
      'reportId' => $report_id,
      'csrfToken' => $_SESSION['csrf_token'] ?? '',
      // add other PHP vars used in JS
  ]) ?>;
  </script>
  <script src="/pardis/assets/js/daily_reports.js" defer></script>
  ```
  Then in the external JS file, reference `PAGE_CONFIG.reportId` instead of the old variable.

- **jQuery document.ready** — Keep the wrapping `$(document).ready(function() { ... })` or `$(function() { ... })` in the external file.

- **Multiple `<style>` blocks** — Some files have 2-3 style blocks. Merge them all into one CSS file.

### Commits (one per file):
```
perf(pardis): extract inline CSS/JS from daily_reports.php
perf(pardis): extract inline CSS/JS from packing_list_viewer.php
perf(global): extract inline CSS/JS from messages.php
perf(pardis): extract inline CSS/JS from meeting_minutes_form.php
perf(pardis): extract inline CSS/JS from letters.php and index.php
perf(ghom): extract inline CSS/JS from viewer.php and reports.php
perf(ghom): extract inline CSS/JS from index.php
perf(global): extract inline CSS/JS from admin.php
```

### Verification:
```bash
# Count remaining inline styles/scripts (should be significantly reduced)
STYLES_BEFORE=147
STYLES_AFTER=$(grep -rn '<style>' --include="*.php" | grep -v "includes/mpdf\|includes/libraries\|includes/TCPDF" | wc -l)
echo "Inline <style> blocks: $STYLES_BEFORE → $STYLES_AFTER"

SCRIPTS_BEFORE=150
SCRIPTS_AFTER=$(grep -rn '<script>' --include="*.php" | grep -v "includes/mpdf\|includes/libraries\|src=\|csrf-injector\|global.js\|PAGE_CONFIG" | wc -l)
echo "Inline <script> blocks: $SCRIPTS_BEFORE → $SCRIPTS_AFTER"

# Verify extracted files exist
ls -la pardis/assets/css/daily_reports.css pardis/assets/js/daily_reports.js
ls -la ghom/assets/css/viewer.css ghom/assets/js/viewer.js
ls -la assets/css/messages.css assets/js/messages.js
```

### Merge to main, then continue.

---

## 2D — Desktop/Mobile Page Merger

**Branch:** `claude/phase-2d-responsive-merge`

### Goal: Merge `*_mobile.php` pages into their desktop counterparts with responsive CSS.

### Target files:

| Mobile File | Merge Into | Strategy |
|-------------|-----------|----------|
| `ghom/contractor_batch_update_mobile.php` (1682 lines) | `ghom/contractor_batch_update.php` (293 lines) | Mobile is larger — use mobile as base, add desktop styles |
| `ghom/inspection_dashboard_mobile.php` (639 lines) | `ghom/inspection_dashboard.php` (861 lines) | Desktop is larger — add mobile responsive CSS |
| `ghom/reports_mobile.php` (531 lines) | `ghom/reports.php` (1772 lines) | Desktop is larger — add mobile responsive CSS |
| `ghom/mobile.php` (159 lines) | Redirect to `ghom/index.php` | Simple redirect |
| `ghom/contractmibile.php` (819 lines) | Check if duplicate of contractor_batch_update_mobile | Likely duplicate — delete |
| `pardis/daily_report_mobile.php` (1033 lines) | `pardis/daily_report_form_ps.php` (1593 lines) | Add mobile responsive CSS |
| `pardis/mobile.php` (110 lines) | Redirect to `pardis/index.php` | Simple redirect |
| `pardis/mobile_plan.php` (102 lines) | `pardis/plans.php` (451 lines) | Add mobile responsive CSS |
| `pardis/viewer_3d_mobile.php` (373 lines) | `pardis/viewer_3d.php` (700 lines) | Add mobile responsive CSS |
| `messages_mobile.php` (133 lines) | `messages.php` | Add mobile responsive CSS |

### Method for each merge:

```
For each mobile/desktop pair:
  1. Compare PHP logic: diff the SQL queries and data processing
     - If identical: only CSS/layout differs → easy merge
     - If different: merge logic, keep both code paths with device detection
  2. Extract mobile-specific CSS
  3. Add to desktop file inside @media (max-width: 768px) { }
  4. If mobile has unique JS (touch events, swipe), add with `if (window.innerWidth <= 768)` guard
  5. Replace mobile file with redirect:
     <?php header('Location: ' . str_replace('_mobile', '', $_SERVER['SCRIPT_NAME'])); exit; ?>
     OR simply delete if no external links point to it
  6. Verify: php -l {desktop_file}
```

### Special case: `ghom/contractor_batch_update_mobile.php`
This file is 1682 lines vs desktop's 293. The mobile version is likely the more feature-rich one. Strategy:
1. Use mobile as the base
2. Add desktop layout CSS (wider sidebar, multi-column layout)
3. Save as the unified `contractor_batch_update.php`

### Commits:
```
refactor(ghom): merge inspection_dashboard_mobile into responsive inspection_dashboard
refactor(ghom): merge reports_mobile into responsive reports
refactor(ghom): merge contractor_batch_update_mobile into responsive version
refactor(pardis): merge daily_report_mobile into responsive daily_report_form_ps
refactor(pardis): merge viewer_3d_mobile into responsive viewer_3d
refactor(global): merge messages_mobile into responsive messages
chore(global): remove/redirect deprecated mobile-only pages
```

### Verification:
```bash
# Mobile files should not exist (or be redirects)
for f in ghom/inspection_dashboard_mobile.php ghom/reports_mobile.php \
         pardis/daily_report_mobile.php pardis/viewer_3d_mobile.php \
         messages_mobile.php; do
  if [ -f "$f" ]; then
    if grep -q "header.*Location" "$f"; then
      echo "✅ $f (redirect)"
    else
      echo "❌ $f still exists as full page"
    fi
  else
    echo "✅ $f deleted"
  fi
done
```

### Merge to main, then continue.

---

## 2E — N+1 Query Fixes & Pagination

**Branch:** `claude/phase-2e-query-optimization`

### Part 1: Fix N+1 Queries

**File: `pardis/daily_reports_dashboard_ps.php`**
Find the pattern where a query runs inside a foreach/while loop:
```php
// FIND patterns like:
// $rows = fetch all reports
// foreach ($rows as $row) {
//     $count = $pdo->query("SELECT COUNT(*) FROM ... WHERE report_id = {$row['id']}")->fetchColumn();
// }

// REPLACE with batch query BEFORE the loop:
// $ids = array_column($rows, 'id');
// $placeholders = implode(',', array_fill(0, count($ids), '?'));
// $stmt = $pdo->prepare("SELECT report_id, COUNT(*) as cnt FROM ... WHERE report_id IN ($placeholders) GROUP BY report_id");
// $stmt->execute($ids);
// $counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
// Then in loop: $count = $counts[$row['id']] ?? 0;
```

**File: `pardis/daily_report_form_ps.php`**
Fix the activity name lookup inside loop:
```php
// FIND: foreach loop that queries activity names one by one
// REPLACE: JOIN in the initial query, or batch fetch all activity names
```

**File: `pardis/weekly_report_ps.php`**
Fix personnel count query inside loop.

**File: `ghom/workshop_report.php`**
Fix user lookup query inside loop (the $commonConn->query with IN clause is already OK if $ids is built safely, but verify).

### Part 2: Create Pagination Helper

**Create `includes/pagination.php`:**
```php
<?php
/**
 * Server-side pagination helper
 * 
 * Usage:
 *   $result = paginate($pdo, "SELECT * FROM reports WHERE status = ?", ['active'], 25);
 *   // $result = ['data' => [...], 'total' => 150, 'page' => 1, 'per_page' => 25, 'total_pages' => 6]
 *   
 *   echo renderPagination($result, 'reports.php?status=active');
 */

function paginate(PDO $pdo, string $query, array $params = [], int $perPage = 25): array {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $perPage;
    
    // Count total rows
    $countSql = "SELECT COUNT(*) FROM ($query) AS _count_query";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    
    // Fetch current page
    $pageSql = "$query LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
    $pageStmt = $pdo->prepare($pageSql);
    $pageStmt->execute($params);
    $data = $pageStmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'data' => $data,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => (int)ceil($total / $perPage),
    ];
}

function renderPagination(array $result, string $baseUrl): string {
    if ($result['total_pages'] <= 1) return '';
    
    $separator = str_contains($baseUrl, '?') ? '&' : '?';
    $html = '<nav class="ag-pagination"><ul>';
    
    // Previous
    if ($result['page'] > 1) {
        $html .= '<li><a href="' . $baseUrl . $separator . 'page=' . ($result['page'] - 1) . '">قبلی</a></li>';
    }
    
    // Page numbers
    $start = max(1, $result['page'] - 2);
    $end = min($result['total_pages'], $result['page'] + 2);
    
    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $result['page'] ? ' class="active"' : '';
        $html .= '<li' . $active . '><a href="' . $baseUrl . $separator . 'page=' . $i . '">' . $i . '</a></li>';
    }
    
    // Next
    if ($result['page'] < $result['total_pages']) {
        $html .= '<li><a href="' . $baseUrl . $separator . 'page=' . ($result['page'] + 1) . '">بعدی</a></li>';
    }
    
    $html .= '</ul>';
    $html .= '<span class="ag-pagination-info">صفحه ' . $result['page'] . ' از ' . $result['total_pages'] . ' (مجموع: ' . $result['total'] . ')</span>';
    $html .= '</nav>';
    
    return $html;
}
```

### Part 3: Apply pagination to dashboards

**Target pages (apply `paginate()` function):**
1. `pardis/daily_reports_dashboard_ps.php` — the main dashboard query
2. `pardis/daily_reports.php` — report listing
3. `pardis/letters.php` — letter listing
4. `ghom/daily_reports_dashboard.php` — ghom dashboard
5. `ghom/reports.php` — ghom reports

For each:
1. Find the main listing query
2. Wrap with `paginate($pdo, $query, $params, 25)`
3. Replace direct `$rows` with `$result['data']`
4. Add `<?= renderPagination($result, $_SERVER['PHP_SELF'] . '?' . http_build_query(array_diff_key($_GET, ['page' => '']))) ?>` after the table

### Add pagination CSS to `assets/css/global.css`:
```css
.ag-pagination { display: flex; align-items: center; justify-content: center; gap: 0.5rem; margin: 1.5rem 0; flex-wrap: wrap; }
.ag-pagination ul { list-style: none; display: flex; gap: 0.25rem; padding: 0; margin: 0; }
.ag-pagination li a { display: block; padding: 0.4rem 0.8rem; border: 1px solid var(--ag-gray-300); border-radius: var(--ag-radius-sm); color: var(--ag-gray-700); text-decoration: none; font-size: var(--ag-font-size-sm); }
.ag-pagination li a:hover { background: var(--ag-gray-100); }
.ag-pagination li.active a { background: var(--ag-primary); color: white; border-color: var(--ag-primary); }
.ag-pagination-info { color: var(--ag-gray-500); font-size: var(--ag-font-size-xs); }
```

### Commits:
```
perf(pardis): fix N+1 queries in daily_reports_dashboard_ps and daily_report_form_ps
perf(pardis): fix N+1 query in weekly_report_ps
feat(global): create pagination helper (includes/pagination.php)
style(global): add pagination CSS to global.css
perf(pardis): add server-side pagination to daily_reports, letters, dashboard_ps
perf(ghom): add server-side pagination to daily_reports_dashboard and reports
```

### Verification:
```bash
# Pagination helper exists
test -f includes/pagination.php && echo "✅" || echo "❌"

# Key dashboards have LIMIT
for f in pardis/daily_reports_dashboard_ps.php pardis/daily_reports.php pardis/letters.php \
         ghom/daily_reports_dashboard.php ghom/reports.php; do
  if grep -q "paginate\|LIMIT" "$f" 2>/dev/null; then
    echo "✅ $f has pagination"
  else
    echo "⚠️  $f — no pagination found"
  fi
done
```

### Merge to main, then continue to final report.

---

## 📋 Post-Completion: Update Docs & Tag

After all 5 sub-phases are merged to main:

### 1. Update `docs/TECH_DEBT.md`

Mark these as resolved:
- TD-PERF-001 (Monolithic Files) → partially resolved
- TD-PERF-002 (Inline CSS/JS) → resolved
- TD-PERF-003 (No Pagination) → resolved
- TD-PERF-004 (N+1 Queries) → resolved
- TD-PERF-005 (No Compression) → resolved (in .htaccess from Phase 1)
- TD-ARCH-002 (Duplicate Headers) → resolved
- TD-UX-001 (Separate Mobile Pages) → resolved

### 2. Update `docs/CHANGELOG.md`

Add Phase 2 section with all changes.

### 3. Update `docs/ARCHITECTURE.md`

Update the project structure to reflect:
- New `assets/css/` and `assets/js/` structure
- New unified headers
- New `includes/pagination.php`
- Removal of mobile-specific files

### 4. Tag the release

```bash
git tag v0.3.0-performance-ux -m "Phase 2: Performance & UX optimization complete"
git push origin v0.3.0-performance-ux
```

---

## 📊 Final Report

**Create `docs/reports/phase2-final-report.md`** with this structure:

```markdown
# Phase 2 Final Report — Performance & UX Optimization
# گزارش نهایی فاز ۲

**Date:** [auto-generate]
**Version:** v0.3.0-performance-ux
**Branch history:** 5 sub-phase branches merged to main

---

## Executive Summary
[2-3 sentences about what was accomplished]

## Metrics

| Metric | Before Phase 2 | After Phase 2 | Improvement |
|--------|----------------|---------------|-------------|
| Header/footer files | 11 | [count] | [%] |
| Inline `<style>` blocks | 147 | [count] | [%] |
| Inline `<script>` blocks | 150 | [count] | [%] |
| Largest PHP file size | 143KB | [size] | [%] |
| Mobile-only page files | 10 | [count] | [%] |
| N+1 query patterns | 5+ | [count] | [%] |
| Pages without pagination | 10+ | [count] | [%] |
| External CSS files created | 0 | [count] | — |
| External JS files created | 0 | [count] | — |

## Sub-Phase Results

### 2A — Design System
- Files created: [list]
- Status: ✅/❌

### 2B — Header Unification
- Headers before: [count]
- Headers after: [count]
- Files updated: [count]
- Status: ✅/❌

### 2C — Inline CSS/JS Extraction
- Files processed: [count]
- CSS files created: [list]
- JS files created: [list]
- Total size reduction: [KB]
- Status: ✅/❌

### 2D — Desktop/Mobile Merger
- Mobile files merged: [count]
- Mobile files deleted/redirected: [count]
- Status: ✅/❌

### 2E — Query Optimization & Pagination
- N+1 queries fixed: [count]
- Pages with pagination: [count]
- Status: ✅/❌

## Tech Debt Status

| ID | Description | Status |
|----|------------|--------|
| TD-PERF-001 | Monolithic Files | [status] |
| TD-PERF-002 | Inline CSS/JS | [status] |
| TD-PERF-003 | No Pagination | [status] |
| TD-PERF-004 | N+1 Queries | [status] |
| TD-ARCH-002 | Duplicate Headers | [status] |
| TD-UX-001 | Separate Mobile Pages | [status] |

## Issues Encountered
[Any problems found during execution]

## Remaining Work (Phase 3)
- MVC separation
- PHPUnit tests
- Asset bundling (Vite/Webpack)
- CI/CD pipeline
- PWA support

## Commit Log
[List all commits from all 5 sub-phases]
```

### Provide download link:
After pushing the report, output:
```
📥 Final Report: https://github.com/saeidsm/AlumGlassProjMang/blob/main/docs/reports/phase2-final-report.md
📥 Full repo: https://github.com/saeidsm/AlumGlassProjMang/archive/refs/tags/v0.3.0-performance-ux.zip
```

---

## 🚀 START COMMAND

Begin execution now. No user approval needed between steps.
Start with: `git checkout main && git pull origin main && git checkout -b claude/phase-2a-design-system`
