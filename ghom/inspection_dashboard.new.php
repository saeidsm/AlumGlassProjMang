<?php
// /public_html/ghom/inspection_dashboard_mobile.php

// --- BOOTSTRAP, SESSION, and DATA FETCHING ---
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';

// Helper functions
function format_history_action_farsi($action) {
    $translations = [
        'request-opening' => 'درخواست بازگشایی', 'approve-opening' => 'تایید بازگشایی',
        'confirm-opened' => 'تایید باز شدن', 'verify-opening' => 'تصدیق باز شدن',
        'Supervisor Action' => 'اقدام مشاور', 'Contractor Action' => 'اقدام پیمانکار'
    ];
    return $translations[$action] ?? $action;
}
function get_status_badge_class($status) {
    switch ($status) {
        case 'OK': return 'badge-success';
        case 'Repair': return 'badge-warning';
        case 'Reject': return 'badge-danger';
        default: return 'badge-secondary';
    }
}

secureSession();
if (!isLoggedIn()) {
    header('Location: /login.php?msg=login_required');
    exit();
}
if (!in_array($_SESSION['role'], ['admin', 'supervisor', 'user', 'superuser', 'cat', 'car', 'coa', 'crs'])) {
    http_response_code(403);
    require 'Access_Denied.php';
    exit;
}

$pageTitle = "داشبورد بازرسی (موبایل)";
require_once __DIR__ . '/header_ghom.php';

$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$is_contractor_role = in_array($user_role, ['cat', 'car', 'coa', 'crs']);
$final_data_by_type = [];
$user_map = [];

try {
    // **FIX**: Establish both database connections
    $pdo = getProjectDBConnection('ghom');
    $common_pdo = getCommonDBConnection(); // Use this for the 'users' table
    $pdo->exec("SET NAMES 'utf8mb4'");

    // **FIX**: Main SQL query no longer joins with the users table. It just gets the user_id.
    $sql = "
    SELECT 
        i.inspection_id, i.element_id, i.part_name, i.stage_id, i.user_id,
        s.stage AS stage_name,
        i.contractor_status, i.overall_status,
        e.element_type, e.plan_file, e.zone_name, e.contractor, e.block, e.axis_span, e.floor_level,
        i.history_log, i.pre_inspection_log,
        (SELECT COUNT(*) FROM inspection_data id WHERE id.inspection_id = i.inspection_id AND id.item_value LIKE '{%\"lines\"%}') as has_drawing
    FROM inspections i
    JOIN elements e ON i.element_id = e.element_id
    JOIN inspection_stages s ON i.stage_id = s.stage_id
    ";

    $params = [];
    if ($is_contractor_role) {
        // **FIX**: Use the $common_pdo connection to get user's company
        $user_stmt = $common_pdo->prepare("SELECT company FROM users WHERE id = ?");
        $user_stmt->execute([$user_id]);
        $user_company = $user_stmt->fetchColumn();
        if ($user_company) {
            $sql .= " WHERE e.contractor = :contractor_company";
            $params[':contractor_company'] = $user_company;
        } else {
            $sql .= " WHERE 1=0";
        }
    }
    $sql .= " ORDER BY i.element_id, i.part_name, i.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $all_inspection_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Collect all unique user IDs from all logs and main inspection records
    $all_user_ids = [];
    foreach ($all_inspection_data as $row) {
        if (!empty($row['user_id'])) $all_user_ids[$row['user_id']] = true;
        $logs = array_merge(json_decode($row['history_log'] ?? '[]', true), json_decode($row['pre_inspection_log'] ?? '[]', true));
        foreach ($logs as $log) {
            if (!empty($log['user_id'])) $all_user_ids[$log['user_id']] = true;
        }
    }

    // **FIX**: Fetch all user data in one query using the $common_pdo connection
    if (!empty($all_user_ids)) {
        $user_ids_list = array_keys($all_user_ids);
        $placeholders = implode(',', array_fill(0, count($user_ids_list), '?'));
        $user_sql = "SELECT id, CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE id IN ($placeholders)";
        $user_stmt = $common_pdo->prepare($user_sql);
        $user_stmt->execute($user_ids_list);
        $user_map = $user_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    $grouped_inspections = [];
    foreach ($all_inspection_data as $row) {
        $unique_key = $row['element_id'] . '::' . $row['part_name'];
        if (!isset($grouped_inspections[$unique_key])) {
            $grouped_inspections[$unique_key] = ['main_data' => $row, 'history' => []];
        }
        $grouped_inspections[$unique_key]['history'][] = $row;
    }

    foreach ($grouped_inspections as $key => $group) {
        $element_type = $group['main_data']['element_type'];
        if (!isset($final_data_by_type[$element_type])) $final_data_by_type[$element_type] = [];
        $final_data_by_type[$element_type][$key] = $group;
    }
} catch (Exception $e) {
    error_log("DB Error in inspection_dashboard_mobile.php: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
          @font-face {
            font-family: "Samim";
            src: url("/ghom/assets/fonts/Samim-FD.woff2") format("woff2"),
                url("/ghom/assets/fonts/Samim-FD.woff") format("woff"),
                url("/ghom/assets/fonts/Samim-FD.ttf") format("truetype");
        }
        :root {
            --primary: #3498db; --background: #f4f7f6; --surface: #ffffff;
            --text-primary: #2c3e50; --text-secondary: #7f8c8d; --border: #e0e0e0;
            --shadow: 0 2px 5px rgba(0,0,0,0.08);
        }
        body { font-family: "Samim", sans-serif; background-color: var(--background); margin: 0; }
        .container { padding: 1rem; }
        .card { background: var(--surface); border-radius: 8px; box-shadow: var(--shadow); margin-bottom: 1rem; padding: 1rem; }
        .card-header { display: flex; justify-content: space-between; align-items: center; cursor: pointer; }
        .card-title { font-size: 1.25rem; font-weight: bold; color: var(--text-primary); }
        .card-content { display: none; padding-top: 1rem; border-top: 1px solid var(--border); margin-top: 1rem; }
        .card.active .card-content { display: block; }
        .card.active .icon-toggle { transform: rotate(180deg); }
        .icon-toggle { transition: transform 0.3s; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { font-weight: bold; display: block; margin-bottom: 0.5rem; }
        .form-group select, .form-group input { width: 100%; padding: 0.75rem; border-radius: 6px; border: 1px solid var(--border); font-size: 1rem; }
        .btn { padding: 0.5rem 1rem; border-radius: 6px; border: none; color: white; cursor: pointer; }
        .results-info { margin: 1rem 0; font-weight: bold; text-align: center; }
        .inspection-card { padding: 1rem; border: 1px solid var(--border); border-radius: 8px; margin-bottom: 1rem; }
        .inspection-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .element-name { font-size: 1.1rem; font-weight: bold; }
        .element-name a { color: var(--primary); text-decoration: none; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.8rem; color: white; }
        .details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; font-size: 0.9rem; color: var(--text-secondary); }
        .history-toggle { color: var(--primary); cursor: pointer; text-align: center; margin-top: 1rem; padding-top: 0.5rem; border-top: 1px dashed var(--border); }
        .history-details { display: none; margin-top: 1rem; }
        .history-timeline { list-style: none; padding: 0; }
        .history-timeline li { margin-bottom: 0.5rem; }
        .card-actions { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border); display: flex; gap: 0.5rem; }
        .action-btn { background-color: var(--text-secondary); font-size: 0.85rem; padding: 0.5rem 0.75rem; }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>

        <!-- Filters Accordion -->
        <div class="card" id="filters-card">
            <div class="card-header">
                <h2 class="card-title"><i class="fas fa-filter"></i> فیلترها و جستجو</h2>
                <i class="fas fa-chevron-down icon-toggle"></i>
            </div>
            <div class="card-content">
                <div class="form-group">
                    <label>جستجو بر اساس نام المان:</label>
                    <input type="text" id="text-filter" placeholder="مثال: Z01-CU-...">
                </div>
                <div class="form-group">
                    <label>فیلتر وضعیت:</label>
                    <select id="status-filter">
                        <option value="">همه وضعیت‌ها</option>
                        <option value="تایید شده">تایید شده</option>
                        <option value="نیاز به تعمیر">نیاز به تعمیر</option>
                        <option value="رد شده">رد شده</option>
                        <option value="منتظر بازرسی مجدد">منتظر بازرسی مجدد</option>
                        <option value="آماده بازرسی">آماده بازرسی</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="card">
            <div class="form-group">
                <label for="type-select">انتخاب نوع المان:</label>
                <select id="type-select">
                    <?php foreach ($final_data_by_type as $type => $data): ?>
                        <option value="<?php echo htmlspecialchars($type); ?>">
                            <?php echo htmlspecialchars($type); ?> (<?php echo count($data); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="results-info"><span id="visible-count">0</span> از <span id="total-count">0</span> مورد نمایش داده می‌شود</div>
            <div id="data-container">
                <?php foreach ($final_data_by_type as $type => $inspections_group): ?>
                    <div class="element-type-container" id="container-<?php echo htmlspecialchars($type); ?>" style="display: none;">
                        <?php foreach ($inspections_group as $unique_key => $group): ?>
                            <?php
                                $main_row = $group['main_data'];
                                $history = $group['history'];
                                $inspector_name = $user_map[$main_row['user_id']] ?? 'ناشناس';
                                $element_display_name = $main_row['element_id'] . (!empty($main_row['part_name']) ? ' - ' . $main_row['part_name'] : '');
                                $element_link_id = $main_row['element_id'] . (!empty($main_row['part_name']) ? '-' . $main_row['part_name'] : '');
                                $deep_link = sprintf("/ghom/index.php?plan=%s&element_id=%s", urlencode($main_row['plan_file']), urlencode($element_link_id));
                                $final_status_text = 'در حال اجرا';
                                if ($main_row['overall_status'] === 'OK') $final_status_text = 'تایید شده';
                                elseif ($main_row['overall_status'] === 'Repair') $final_status_text = 'نیاز به تعمیر';
                                elseif ($main_row['overall_status'] === 'Reject') $final_status_text = 'رد شده';
                                elseif ($main_row['contractor_status'] === 'Opening Approved') $final_status_text = 'منتظر بازرسی مجدد';
                                elseif ($main_row['contractor_status'] === 'Pre-Inspection Complete') $final_status_text = 'آماده بازرسی';
                            ?>
                            <div class="inspection-card" data-status="<?php echo htmlspecialchars($final_status_text); ?>" data-name="<?php echo htmlspecialchars($element_display_name); ?>">
                                <div class="inspection-header">
                                    <div class="element-name"><a href="<?php echo $deep_link; ?>" target="_blank"><?php echo htmlspecialchars($element_display_name); ?></a></div>
                                    <span class="status-badge" style="background-color: <?php echo get_status_badge_class($main_row['overall_status']) === 'badge-success' ? '#28a745' : ($main_row['overall_status'] === 'Reject' ? '#dc3545' : '#6c757d'); ?>"><?php echo htmlspecialchars($final_status_text); ?></span>
                                </div>
                                <div class="details-grid">
                                    <div><strong>مرحله:</strong> <?php echo htmlspecialchars($main_row['stage_name']); ?></div>
                                    <div><strong>بلوک:</strong> <?php echo htmlspecialchars($main_row['block']); ?></div>
                                    <div><strong>پیمانکار:</strong> <?php echo htmlspecialchars($main_row['contractor']); ?></div>
                                    <div><strong>بازرس:</strong> <?php echo htmlspecialchars($inspector_name); ?></div>
                                </div>
                                <div class="card-actions">
                                    <a href="/ghom/view_element_history.php?element_id=<?php echo urlencode($main_row['element_id']); ?>&part_name=<?php echo urlencode($main_row['part_name'] ?? ''); ?>" class="btn action-btn" target="_blank">
                                        <i class="fas fa-history"></i> تاریخچه کامل
                                    </a>
                                </div>
                                <div class="history-toggle" onclick="toggleHistory(this)">نمایش تاریخچه سریع</div>
                                <div class="history-details">
                                    <ul class="history-timeline">
                                        <?php foreach($history as $history_item): ?>
                                            <?php $logs = json_decode($history_item['history_log'] ?? '[]', true); ?>
                                            <?php foreach($logs as $log): ?>
                                                <li>
                                                    <strong><?php echo htmlspecialchars($history_item['stage_name']); ?>:</strong> <?php echo format_history_action_farsi($log['action']); ?>
                                                    <br><small><?php echo jdate('Y/m/d H:i', strtotime($log['timestamp'])); ?> - <?php echo htmlspecialchars($user_map[$log['user_id']] ?? 'ناشناس'); ?></small>
                                                </li>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const typeSelect = document.getElementById('type-select');
            const dataContainer = document.getElementById('data-container');
            const textFilter = document.getElementById('text-filter');
            const statusFilter = document.getElementById('status-filter');
            const visibleCountEl = document.getElementById('visible-count');
            const totalCountEl = document.getElementById('total-count');

            function switchTab() {
                const selectedType = typeSelect.value;
                document.querySelectorAll('.element-type-container').forEach(c => c.style.display = 'none');
                const activeContainer = document.getElementById(`container-${selectedType}`);
                if (activeContainer) {
                    activeContainer.style.display = 'block';
                }
                applyFilters();
            }

            function applyFilters() {
                const selectedType = typeSelect.value;
                const activeContainer = document.getElementById(`container-${selectedType}`);
                if (!activeContainer) return;

                const textValue = textFilter.value.toLowerCase();
                const statusValue = statusFilter.value;
                
                let visibleCount = 0;
                const allCards = activeContainer.querySelectorAll('.inspection-card');
                
                allCards.forEach(card => {
                    const nameMatch = card.dataset.name.toLowerCase().includes(textValue);
                    const statusMatch = !statusValue || card.dataset.status === statusValue;
                    
                    if (nameMatch && statusMatch) {
                        card.style.display = 'block';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                visibleCountEl.textContent = visibleCount;
                totalCountEl.textContent = allCards.length;
            }

            window.toggleHistory = function(element) {
                const details = element.nextElementSibling;
                if (details) {
                    details.style.display = details.style.display === 'block' ? 'none' : 'block';
                    element.textContent = details.style.display === 'block' ? 'پنهان کردن تاریخچه' : 'نمایش تاریخچه سریع';
                }
            }
            
            // Accordion for filters
            document.getElementById('filters-card').addEventListener('click', function(e) {
                if(e.target.closest('.card-header')) {
                    this.classList.toggle('active');
                }
            });

            // Event Listeners
            typeSelect.addEventListener('change', switchTab);
            textFilter.addEventListener('input', applyFilters);
            statusFilter.addEventListener('change', applyFilters);
            
            // Initial load
            switchTab();
        });
    </script>
</body>
</html>
