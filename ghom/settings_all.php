<?php
// settings_all.php
require_once __DIR__ . '/../../sercon/bootstrap.php';
$pdo = getProjectDBConnection('ghom');

$message = "";
$active_tab = 'activities'; // Default tab

// --- HANDLE FORM SUBMISSIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $active_tab = $_POST['tab'] ?? 'activities';

    try {
        // 1. HANDLE PROJECT ACTIVITIES (Complex: Category + Name)
        if ($active_tab === 'activities') {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO project_activities (category, name) VALUES (?, ?)");
                $stmt->execute([$_POST['category'], $_POST['name']]);
                $message = "✅ فعالیت جدید اضافه شد.";
            } elseif ($action === 'delete') {
                $stmt = $pdo->prepare("DELETE FROM project_activities WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $message = "🗑️ فعالیت حذف شد.";
            } elseif ($action === 'edit') {
                $stmt = $pdo->prepare("UPDATE project_activities SET category=?, name=? WHERE id=?");
                $stmt->execute([$_POST['category'], $_POST['name'], $_POST['id']]);
                $message = "✏️ فعالیت ویرایش شد.";
            }
        } 
        // 2. HANDLE SIMPLE CONSTANTS (Roles, Tools, etc.)
        else {
            $type_map = [
                'roles' => 'role', 'tools' => 'tool', 
                'materials' => 'material_cat', 'units' => 'unit'
            ];
            $db_type = $type_map[$active_tab] ?? '';

            if ($db_type) {
                if ($action === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO project_constants (type, name) VALUES (?, ?)");
                    $stmt->execute([$db_type, $_POST['name']]);
                    $message = "✅ آیتم جدید اضافه شد.";
                } elseif ($action === 'delete') {
                    $stmt = $pdo->prepare("DELETE FROM project_constants WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                    $message = "🗑️ آیتم حذف شد.";
                } elseif ($action === 'edit') {
                    $stmt = $pdo->prepare("UPDATE project_constants SET name=? WHERE id=?");
                    $stmt->execute([$_POST['name'], $_POST['id']]);
                    $message = "✏️ آیتم ویرایش شد.";
                }
            }
        }
    } catch (Exception $e) {
        if ($e->getCode() == 23000) {
            $message = "❌ خطا: این آیتم تکراری است.";
        } else {
            $message = "❌ خطا: " . $e->getMessage();
        }
    }
}

// --- FETCH DATA ---
// 1. Fetch Activities
$stmt = $pdo->query("SELECT * FROM project_activities ORDER BY category ASC, name ASC");
$raw_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
$grouped_activities = [];
foreach ($raw_activities as $act) $grouped_activities[$act['category']][] = $act;

// 2. Fetch Constants
$stmt = $pdo->query("SELECT * FROM project_constants ORDER BY name ASC");
$all_constants = $stmt->fetchAll(PDO::FETCH_ASSOC);
$constants_grouped = ['role'=>[], 'tool'=>[], 'material_cat'=>[], 'unit'=>[]];
foreach ($all_constants as $c) {
    if(isset($constants_grouped[$c['type']])) $constants_grouped[$c['type']][] = $c;
}

// Helper function to render simple list tables
function renderSimpleTable($items, $tabName, $title) {
    echo '<div class="card shadow-sm mb-4"><div class="card-header bg-secondary text-white fw-bold">'.$title.'</div><div class="card-body">';
    // Add Form
    echo '<form method="POST" class="row g-2 mb-4 align-items-end">';
    echo '<input type="hidden" name="tab" value="'.$tabName.'"><input type="hidden" name="action" value="add">';
    echo '<div class="col-md-9"><input type="text" name="name" class="form-control" placeholder="نام جدید..." required></div>';
    echo '<div class="col-md-3"><button type="submit" class="btn btn-success w-100">+ افزودن</button></div></form>';
    // List
    echo '<div style="max-height:400px;overflow-y:auto"><table class="table table-striped table-hover align-middle mb-0"><tbody>';
    foreach($items as $item) {
        echo '<tr><form method="POST"><input type="hidden" name="tab" value="'.$tabName.'"><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="'.$item['id'].'">';
        echo '<td><input type="text" name="name" class="form-control form-control-sm border-0 bg-transparent" value="'.htmlspecialchars($item['name']).'"></td>';
        echo '<td class="text-end" style="width:100px;">
              <button type="submit" class="btn btn-sm btn-link text-primary"><i class="fas fa-save"></i></button>
              <button type="button" class="btn btn-sm btn-link text-danger" onclick="deleteItem(this, \''.$tabName.'\', '.$item['id'].')"><i class="fas fa-trash"></i></button>
              </td></form></tr>';
    }
    echo '</tbody></table></div></div></div>';
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تنظیمات جامع سیستم</title>
    <link href="/ghom/assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container my-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold text-primary"><i class="fas fa-cogs"></i> تنظیمات لیست‌ها</h3>
            <a href="daily_reports_dashboard.php" class="btn btn-outline-secondary">بازگشت به لیست</a>
        </div>

        <?php if($message): ?>
            <div class="alert alert-info alert-dismissible fade show"><?= $message ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <!-- TABS HEADER -->
        <ul class="nav nav-tabs mb-3" id="settingTabs" role="tablist">
            <li class="nav-item"><button class="nav-link <?= $active_tab=='activities'?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#tab-act" type="button">🔨 فعالیت‌های اجرایی</button></li>
            <li class="nav-item"><button class="nav-link <?= $active_tab=='roles'?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#tab-roles" type="button">👷 سمت‌ها (نیروی انسانی)</button></li>
            <li class="nav-item"><button class="nav-link <?= $active_tab=='tools'?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#tab-tools" type="button">🏗️ ماشین‌آلات و ابزار</button></li>
            <li class="nav-item"><button class="nav-link <?= $active_tab=='materials'?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#tab-mat" type="button">🧱 دسته‌بندی مصالح</button></li>
            <li class="nav-item"><button class="nav-link <?= $active_tab=='units'?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#tab-units" type="button">📏 واحدها</button></li>
        </ul>

        <!-- TABS CONTENT -->
        <div class="tab-content">
            
            <!-- 1. ACTIVITIES TAB -->
            <div class="tab-pane fade <?= $active_tab=='activities'?'show active':'' ?>" id="tab-act">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">مدیریت فعالیت‌ها و دسته‌بندی‌ها</div>
                    <div class="card-body">
                        <!-- Add Activity Form -->
                        <form method="POST" class="row g-3 mb-4 border-bottom pb-4">
                            <input type="hidden" name="tab" value="activities">
                            <input type="hidden" name="action" value="add">
                            <div class="col-md-4">
                                <label class="small fw-bold">دسته‌بندی (Category)</label>
                                <input type="text" name="category" list="catList" class="form-control" required placeholder="مثلاً: کرتین وال...">
                                <datalist id="catList"><?php foreach(array_keys($grouped_activities) as $cat) echo "<option value='$cat'>"; ?></datalist>
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold">نام فعالیت</label>
                                <input type="text" name="name" class="form-control" required placeholder="مثلاً: نصب براکت">
                            </div>
                            <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-success w-100">افزودن</button></div>
                        </form>

                        <!-- Activity List -->
                        <div class="accordion" id="actAccordion">
                            <?php foreach($grouped_activities as $cat => $items): $safeCat = md5($cat); ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#c-<?= $safeCat ?>">
                                            <?= htmlspecialchars($cat) ?> <span class="badge bg-secondary ms-2"><?= count($items) ?></span>
                                        </button>
                                    </h2>
                                    <div id="c-<?= $safeCat ?>" class="accordion-collapse collapse" data-bs-parent="#actAccordion">
                                        <div class="accordion-body p-0">
                                            <table class="table table-sm table-hover mb-0">
                                                <tbody>
                                                    <?php foreach($items as $item): ?>
                                                    <tr>
                                                        <form method="POST">
                                                            <input type="hidden" name="tab" value="activities">
                                                            <input type="hidden" name="action" value="edit">
                                                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                            <input type="hidden" name="category" value="<?= htmlspecialchars($item['category']) ?>"> <!-- Keep cat same, or add input to change -->
                                                            <td class="ps-4"><input type="text" name="name" class="form-control form-control-sm border-0 bg-transparent" value="<?= htmlspecialchars($item['name']) ?>"></td>
                                                            <td class="text-end" style="width:100px;">
                                                                <button type="submit" class="btn btn-sm btn-link text-primary" title="ذخیره"><i class="fas fa-save"></i></button>
                                                                <button type="button" class="btn btn-sm btn-link text-danger" onclick="deleteItem(this, 'activities', <?= $item['id'] ?>)"><i class="fas fa-trash"></i></button>
                                                            </td>
                                                        </form>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. ROLES TAB -->
            <div class="tab-pane fade <?= $active_tab=='roles'?'show active':'' ?>" id="tab-roles">
                <?= renderSimpleTable($constants_grouped['role'], 'roles', 'لیست سمت‌های پرسنل') ?>
            </div>

            <!-- 3. TOOLS TAB -->
            <div class="tab-pane fade <?= $active_tab=='tools'?'show active':'' ?>" id="tab-tools">
                <?= renderSimpleTable($constants_grouped['tool'], 'tools', 'لیست ماشین‌آلات و ابزار') ?>
            </div>

            <!-- 4. MATERIALS TAB -->
            <div class="tab-pane fade <?= $active_tab=='materials'?'show active':'' ?>" id="tab-mat">
                <?= renderSimpleTable($constants_grouped['material_cat'], 'materials', 'لیست دسته‌بندی مصالح') ?>
            </div>

            <!-- 5. UNITS TAB -->
            <div class="tab-pane fade <?= $active_tab=='units'?'show active':'' ?>" id="tab-units">
                <?= renderSimpleTable($constants_grouped['unit'], 'units', 'لیست واحدهای اندازه‌گیری') ?>
            </div>

        </div>
    </div>

    <!-- Hidden Delete Form -->
    <form id="deleteForm" method="POST" style="display:none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="tab" id="delTab">
        <input type="hidden" name="id" id="delId">
    </form>

    <script src="/ghom/assets/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteItem(btn, tab, id) {
            if(confirm('آیا از حذف این آیتم اطمینان دارید؟')) {
                document.getElementById('delTab').value = tab;
                document.getElementById('delId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>