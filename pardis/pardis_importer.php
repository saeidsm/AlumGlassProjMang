<?php
// =================================================================
// 1. SETUP & AUTHORIZATION
// =================================================================
require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';
secureSession();

$expected_project_key = 'pardis';
if (($_SESSION['current_project_config_key'] ?? null) !== $expected_project_key) {
    header('Location: /select_project.php?msg=context_mismatch');
    exit();
}

if (!isLoggedIn()) {
    header('Location: /login.php');
    exit();
}

$allowed_roles = ['admin', 'superuser', 'planner'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    exit('Access Denied. You do not have permission for this page.');
}


if (isset($_GET['action']) && $_GET['action'] === 'download_sample') {
    $fileName = 'sample_tasks_import.csv';

    // Set headers to force download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');

    // Define the header row data
    $header_row = [
        'WBS', 'Task Name', 'Duration', 'Start', 'Finish', 'Outline Level', 'Summary', 
        'جبهه کاری', 'توزیع نقش', 'نما/زون', 'نوع نما', 'مصالح', 'سامری', 'قراردادی'
    ];
    
    // Open php://output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for better Excel compatibility with Persian characters
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write the header row to the CSV
    fputcsv($output, $header_row);

    // Close the stream
    fclose($output);

    // Stop the script execution
    exit();
}
// Function to log activity
function log_activitypardis($action, $details = '') {
    try {
        $common_pdo = getCommonDBConnection();
        $stmt = $common_pdo->prepare(
            "INSERT INTO activity_log (user_id, username, project_id, activity_type, action, details) 
             VALUES (:user_id, :username, :project_id, :activity_type, :action, :details)"
        );
        $stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':username' => $_SESSION['username'],
            ':project_id' => $_SESSION['current_project_id'],
            ':activity_type' => 'Pardis Task Import',
            ':action' => $action,
            ':details' => $details
        ]);
    } catch (Exception $e) {
        logError("Failed to log activity: " . $e->getMessage());
    }
}

// =================================================================
// 2. DB CONNECTIONS & INITIALIZATION
// =================================================================
$pageTitle = "مدیریت پروژه پردیس";
$message = '';
$error = '';
$preview_data = [];
$projects = [];
$header_row = [];

// Determine current view based on GET parameter, default to 'projects'
$current_view = $_GET['view'] ?? 'projects';
if (!in_array($current_view, ['projects', 'importer'])) {
    $current_view = 'projects';
}


try {
    $pdo = getProjectDBConnection('pardis');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");
} catch (PDOException $e) {
    logError("Pardis DB connection error: " . $e->getMessage());
    die("A database error occurred.");
}

// =================================================================
// 3. HANDLE POST REQUESTS
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Add New Project ---
    if (isset($_POST['action']) && $_POST['action'] === 'add_project') {
        $project_name = trim($_POST['project_name']);
        $project_code = trim($_POST['project_code']);
        $start_date_persian = trim($_POST['start_date_persian']);
        $finish_date_persian = trim($_POST['finish_date_persian']);
        $description = trim($_POST['description'] ?? ''); 
        // --- Date Conversion using jdf.php ---
        $start_date_gregorian = null;
    if (!empty($start_date_persian)) {
        // FIX: Replace both '/' and '-' with a standard separator before exploding
        $normalized_date = str_replace('/', '-', $start_date_persian);
        $parts = explode('-', $normalized_date);
        if (count($parts) === 3) {
            $start_date_gregorian = jalali_to_gregorian((int)$parts[0], (int)$parts[1], (int)$parts[2], '-');
        }
    }

       $finish_date_gregorian = null;
    if (!empty($finish_date_persian)) {
        // FIX: Also apply to finish date
        $normalized_date = str_replace('/', '-', $finish_date_persian);
        $parts = explode('-', $normalized_date);
        if (count($parts) === 3) {
            $finish_date_gregorian = jalali_to_gregorian((int)$parts[0], (int)$parts[1], (int)$parts[2], '-');
        }
    }
        // --- End Date Conversion ---

          if (!empty($project_name)) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO projects (project_name, project_code, start_date, finish_date, description) 
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $project_name, 
                $project_code, 
                $start_date_gregorian, 
                $finish_date_gregorian, 
                $description
            ]);
            $message = "پروژه '{$project_name}' با موفقیت ایجاد شد.";
            log_activitypardis('Project Created', "Name: {$project_name}, Code: {$project_code}");
        } catch (PDOException $e) {
            $error = "خطا در ایجاد پروژه: " . $e->getMessage();
        }
    } else {
        $error = "نام پروژه نمی‌تواند خالی باشد.";
    }
    $current_view = 'projects';
}


 if (isset($_POST['action']) && $_POST['action'] === 'update_project') {
        $project_id = $_POST['project_id'] ?? 0;
        $project_name = trim($_POST['project_name']);
        $project_code = trim($_POST['project_code']);
        $start_date_persian = trim($_POST['start_date_persian']);
        $finish_date_persian = trim($_POST['finish_date_persian']);
        $description = trim($_POST['description'] ?? '');

        // Date Conversion
        $start_date_gregorian = null;
        if (!empty($start_date_persian)) {
           $normalized_date = str_replace('/', '-', $start_date_persian);
        $parts = explode('-', $normalized_date);
            if(count($parts) === 3) $start_date_gregorian = jalali_to_gregorian((int)$parts[0], (int)$parts[1], (int)$parts[2], '-');
        }
        
        $finish_date_gregorian = null;
        if (!empty($finish_date_persian)) {
            $normalized_date = str_replace('/', '-', $finish_date_persian);
            $parts = explode('-', $normalized_date);
            if (count($parts) === 3) {
            $finish_date_gregorian = jalali_to_gregorian((int)$parts[0], (int)$parts[1], (int)$parts[2], '-');
        }
        }

        if (!empty($project_name) && !empty($project_id)) {
            try {
                $stmt = $pdo->prepare(
                    "UPDATE projects SET project_name = ?, project_code = ?, start_date = ?, finish_date = ?, description = ? 
                     WHERE project_id = ?"
                );
                $stmt->execute([$project_name, $project_code, $start_date_gregorian, $finish_date_gregorian, $description, $project_id]);
                $message = "پروژه با موفقیت ویرایش شد.";
                log_activitypardis('Project Updated', "ID: {$project_id}, Name: {$project_name}");
            } catch (PDOException $e) {
                $error = "خطا در ویرایش پروژه: " . $e->getMessage();
            }
        } else {
            $error = "اطلاعات پروژه ناقص است.";
        }
        $current_view = 'projects';
    }
     if (isset($_POST['action']) && $_POST['action'] === 'delete_project') {
        $project_id = $_POST['project_id'] ?? 0;
        if (!empty($project_id)) {
            try {
                // You might want to check for related tasks before deleting
                $stmt = $pdo->prepare("DELETE FROM projects WHERE project_id = ?");
                $stmt->execute([$project_id]);
                $message = "پروژه با موفقیت حذف شد.";
                log_activitypardis('Project Deleted', "ID: {$project_id}");
            } catch (PDOException $e) {
                $error = "خطا در حذف پروژه. ممکن است این پروژه دارای فعالیت‌های ثبت شده باشد.";
            }
        } else {
            $error = "شناسه پروژه نامعتبر است.";
        }
        $current_view = 'projects';
    }
    // --- Process CSV Upload ---
    if (isset($_POST['action']) && in_array($_POST['action'], ['preview_csv', 'import_csv'])) {
        $current_view = 'importer'; // Switch to importer tab after action
        $project_id = $_POST['project_id'] ?? null;
        if (!$project_id || !isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $error = "لطفاً یک پروژه را انتخاب کرده و یک فایل CSV معتبر آپلود کنید.";
        } else {
            // Store uploaded file temporarily to use for both preview and import
            $tmp_name = $_FILES['csv_file']['tmp_name'];
            $dest_path = sys_get_temp_dir() . '/' . session_id() . '_' . $_FILES['csv_file']['name'];
            move_uploaded_file($tmp_name, $dest_path);

            $handle = fopen($dest_path, "r");
            $header_row = fgetcsv($handle); 
            
            $row_count = 0;
            while (($data = fgetcsv($handle)) !== FALSE && $row_count < 10) {
                $preview_data[] = $data;
                $row_count++;
            }
            fclose($handle);

            if ($_POST['action'] === 'import_csv') {
                $pdo->beginTransaction();
                try {
                    $handle = fopen($dest_path, "r");
                    fgetcsv($handle); // Skip header

                    $tasks_data = [];
                    $wbs_to_task_id_map = [];
                    $imported_count = 0;
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO tasks (project_id, wbs, task_name, baseline_duration, baseline_start_date, baseline_finish_date, outline_level, is_summary, work_front, role_distribution, facade_zone, facade_type, materials, summary_notes, contract_notes)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    while (($data = fgetcsv($handle)) !== FALSE) {
                        $start_date = !empty(trim($data[3])) ? date('Y-m-d', strtotime(str_replace('/', '-', trim($data[3])))) : null;
                        $finish_date = !empty(trim($data[4])) ? date('Y-m-d', strtotime(str_replace('/', '-', trim($data[4])))) : null;

                        $stmt->execute([
                            $project_id, trim($data[0]), trim($data[1]), (float)trim($data[2]), $start_date, $finish_date, (int)trim($data[5]),
                            (strtolower(trim($data[6])) === 'yes' ? 1 : 0),
                            trim($data[7] ?? ''), trim($data[8] ?? ''), trim($data[9] ?? ''), trim($data[10] ?? ''), 
                            trim($data[11] ?? ''), trim($data[12] ?? ''), trim($data[13] ?? '')
                        ]);
                        
                        $new_task_id = $pdo->lastInsertId();
                        $wbs = trim($data[0]);
                        $tasks_data[] = ['task_id' => $new_task_id, 'wbs' => $wbs];
                        $wbs_to_task_id_map[$wbs] = $new_task_id;
                        $imported_count++;
                    }
                    fclose($handle);

                    // Pass 2: Update parent_task_id
                    $update_stmt = $pdo->prepare("UPDATE tasks SET parent_task_id = ? WHERE task_id = ?");
                    foreach ($tasks_data as $task) {
                        $wbs_parts = explode('.', $task['wbs']);
                        if (count($wbs_parts) > 1) {
                            array_pop($wbs_parts);
                            $parent_wbs = implode('.', $wbs_parts);
                            if (isset($wbs_to_task_id_map[$parent_wbs])) {
                                $update_stmt->execute([$wbs_to_task_id_map[$parent_wbs], $task['task_id']]);
                            }
                        }
                    }

                    $pdo->commit();
                    $message = "عملیات موفقیت‌آمیز بود. تعداد $imported_count فعالیت با موفقیت وارد شد.";
                    log_activitypardis('Tasks Imported', "Project ID: {$project_id}, Count: {$imported_count}, File: {$_FILES['csv_file']['name']}");
                    $preview_data = []; // Clear preview
                    unlink($dest_path); // Delete temp file
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "خطا در پردازش فایل: " . $e->getMessage();
                    logError("Task import failed for project ID {$project_id}. Error: {$e->getMessage()}");
                }
            }
        }
    }
}

// =================================================================
// 4. FETCH DATA FOR DISPLAY
// =================================================================
try {
    $projects = $pdo->query("SELECT * FROM projects ORDER BY project_id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "خطا در دریافت لیست پروژه‌ها: " . $e->getMessage();
}

// Include header file from the project's directory structure
require_once __DIR__ . '/header_pardis.php';
?>
    

<div class="content-wrapper p-6"> <!-- Main content wrapper -->

    <?php if (!empty($message)): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
        <p><?= htmlspecialchars($message) ?></p>
    </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
        <p><?= htmlspecialchars($error) ?></p>
    </div>
    <?php endif; ?>

    <!-- TABS -->
    <ul class="flex flex-wrap text-sm font-medium text-center text-gray-500 border-b border-gray-200 mb-4">
        <li class="mr-2">
            <a href="?view=projects" class="inline-block p-4 rounded-t-lg <?= $current_view === 'projects' ? 'bg-gray-100 text-blue-600 font-semibold' : 'hover:text-gray-600 hover:bg-gray-50'; ?>">مدیریت پروژه‌ها</a>
        </li>
        <li class="mr-2">
            <a href="?view=importer" class="inline-block p-4 rounded-t-lg <?= $current_view === 'importer' ? 'bg-gray-100 text-blue-600 font-semibold' : 'hover:text-gray-600 hover:bg-gray-50'; ?>">ورود اطلاعات برنامه زمانبندی</a>
        </li>
    </ul>

    <!-- ======================== PROJECTS MANAGEMENT VIEW ======================== -->
      <div id="projects-view" <?= $current_view !== 'projects' ? 'style="display:none;"' : '' ?>>
        <div class="mb-4">
            <button type="button" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-3 rounded text-sm" data-bs-toggle="modal" data-bs-target="#addProjectModal">
                افزودن پروژه جدید
            </button>
        </div>
        <h2 class="text-xl font-semibold mb-3">لیست پروژه‌های موجود</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">ID</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">نام پروژه</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">کد</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">تاریخ شروع</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">تاریخ پایان</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">عملیات</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($projects as $project): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap"><?= $project['project_id'] ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($project['project_name']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($project['project_code'] ?? '-') ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?= !empty($project['start_date']) ? jdate('Y/m/d', strtotime($project['start_date'])) : '-' ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?= !empty($project['finish_date']) ? jdate('Y/m/d', strtotime($project['finish_date'])) : '-' ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <button type="button" class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-1 px-2 rounded text-xs" onclick="openEditModal(<?= htmlspecialchars(json_encode($project)) ?>)">
                                ویرایش
                            </button>
                            <button type="button" class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded text-xs ml-2" onclick="deleteProject(<?= $project['project_id'] ?>)">
                                حذف
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>



    <!-- ======================== IMPORTER VIEW ======================== -->
    <div id="importer-view" <?= $current_view !== 'importer' ? 'style="display:none;"' : '' ?>>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <!-- Importer form, instructions, and preview table -->
            <h2 class="text-xl font-semibold mb-4">ورود دسته‌ای فعالیت‌ها از فایل CSV</h2>
            
            <div class="bg-blue-50 border-r-4 border-blue-400 p-4 mb-6">
                <h3 class="font-bold">راهنما</h3>
                <p>برای ورود اطلاعات، ابتدا پروژه مورد نظر را انتخاب کرده و سپس فایل CSV مطابق با فرمت استاندارد را بارگذاری کنید.</p>
                <p class="mt-2">فایل CSV شما باید دقیقاً شامل ستون‌های زیر و به همین ترتیب باشد:</p>
                <p class="mt-1 font-mono text-sm bg-gray-200 p-2 rounded">WBS,Task Name,Duration,Start,Finish,Outline Level,Summary,جبهه کاری,توزیع نقش,نما/زون,نوع نما,مصالح,سامری,قراردادی</p>
<a href="?action=download_sample" class="inline-block mt-3 text-blue-600 hover:text-blue-800 font-semibold">دانلود فایل نمونه CSV</a>
            </div>

            <form action="pardis_importer.php?view=importer" method="post" enctype="multipart/form-data">
                <?= csrfField() ?>
                <!-- Hidden input to keep track of the temporary file for import -->
                <?php if(!empty($preview_data)) echo '<input type="hidden" name="csv_file_path" value="'.htmlspecialchars($dest_path).'">'; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                    <div>
                        <label for="project_id" class="block text-sm font-medium text-gray-700 mb-1">۱. انتخاب پروژه:</label>
                        <select name="project_id" id="project_id" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option value="">-- یک پروژه انتخاب کنید --</option>
                            <?php foreach ($projects as $project): ?>
                            <option value="<?= $project['project_id'] ?>" <?= (isset($_POST['project_id']) && $_POST['project_id'] == $project['project_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($project['project_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="csv_file" class="block text-sm font-medium text-gray-700 mb-1">۲. انتخاب فایل CSV:</label>
                        <input type="file" name="csv_file" id="csv_file" accept=".csv" required class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>
                </div>

                <div class="flex items-center space-x-4 space-x-reverse">
                    <button type="submit" name="action" value="preview_csv" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">پیش‌نمایش</button>
                    <?php if(!empty($preview_data)): ?>
                    <button type="submit" name="action" value="import_csv" class="bg-green-600 hover:bg-green-800 text-white font-bold py-2 px-4 rounded">تایید و ورود نهایی اطلاعات</button>
                    <?php endif; ?>
                </div>
            </form>

            <?php if (!empty($preview_data)): ?>
            <div class="mt-8">
                <h3 class="text-lg font-semibold mb-3">پیش‌نمایش داده‌ها (حداکثر ۱۰ ردیف اول)</h3>
                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="min-w-full text-sm divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <?php foreach ($header_row as $col): ?>
                                <th class="px-4 py-2 text-right font-medium text-gray-600"><?= htmlspecialchars($col) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($preview_data as $row): ?>
                            <tr>
                                <?php foreach ($row as $cell): ?>
                                <td class="px-4 py-2 whitespace-nowrap"><?= htmlspecialchars($cell) ?></td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div> <!-- Close content-wrapper -->

<!-- Add Project Modal -->
<div class="modal fade" id="addProjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">افزودن پروژه جدید</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form method="POST" action="pardis_importer.php?view=projects">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add_project">
                    <div class="mb-3"><label class="form-label">نام پروژه</label><input type="text" name="project_name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">کد پروژه</label><input type="text" name="project_code" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">تاریخ شروع</label><input type="text" name="start_date_persian" class="form-control" data-jdp autocomplete="off"></div>
                    <div class="mb-3"><label class="form-label">تاریخ پایان</label><input type="text" name="finish_date_persian" class="form-control" data-jdp autocomplete="off"></div>
                    <div class="mb-3"><label class="form-label">توضیحات</label><textarea name="description" class="form-control"></textarea></div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button><button type="submit" class="btn btn-primary">ذخیره پروژه</button></div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Project Modal -->
<div class="modal fade" id="editProjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">ویرایش پروژه</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form id="editForm" method="POST" action="pardis_importer.php?view=projects">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_project">
                    <input type="hidden" name="project_id" id="edit_project_id">
                    <div class="mb-3"><label class="form-label">نام پروژه</label><input type="text" name="project_name" id="edit_project_name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">کد پروژه</label><input type="text" name="project_code" id="edit_project_code" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">تاریخ شروع</label><input type="text" name="start_date_persian" id="edit_start_date_persian" class="form-control" data-jdp autocomplete="off"></div>
                    <div class="mb-3"><label class="form-label">تاریخ پایان</label><input type="text" name="finish_date_persian" id="edit_finish_date_persian" class="form-control" data-jdp autocomplete="off"></div>
                    <div class="mb-3"><label class="form-label">توضیحات</label><textarea name="description" id="edit_description" class="form-control"></textarea></div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button><button type="submit" class="btn btn-primary">ذخیره تغییرات</button></div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Form for Deletion -->
<form id="deleteForm" method="POST" action="pardis_importer.php?view=projects" style="display: none;">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="delete_project">
    <input type="hidden" name="project_id" id="delete_project_id">
</form>


<!-- This simple script handles showing the correct tab content without a page reload for a smoother UX -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('a[href*="?view="]');
    const projectView = document.getElementById('projects-view');
    const importerView = document.getElementById('importer-view');

    // This part handles the visual switching of content without a full page reload if the user clicks a tab
    tabs.forEach(tab => {
        tab.addEventListener('click', function(event) {
            event.preventDefault(); // Prevent default link behavior
            
            const view = new URL(this.href).searchParams.get('view');
            
            // Update URL in browser bar without reloading
            window.history.pushState({}, '', `?view=${view}`);
            
            // Toggle active classes on tabs
            tabs.forEach(t => {
                t.classList.remove('bg-gray-100', 'text-blue-600', 'font-semibold');
                t.classList.add('hover:text-gray-600', 'hover:bg-gray-50');
            });
            this.classList.add('bg-gray-100', 'text-blue-600', 'font-semibold');
            this.classList.remove('hover:text-gray-600', 'hover:bg-gray-50');

            // Show/hide content divs
            if (view === 'projects') {
                projectView.style.display = 'block';
                importerView.style.display = 'none';
            } else {
                projectView.style.display = 'none';
                importerView.style.display = 'block';
            }
        });

    
   
    });
    
       jalaliDatepicker.startWatch({
        time: false, // We only need the date, not the time
        persianDigits: true, // Show numbers in Persian
        showEmptyBtn: true, // Show a button to clear the input
        autoHide: true, // Hide the picker after a date is selected
         zIndex: 1100 
    });
});
function openEditModal(project) {
    // Populate the edit form fields
    document.getElementById('edit_project_id').value = project.project_id;
    document.getElementById('edit_project_name').value = project.project_name;
    document.getElementById('edit_project_code').value = project.project_code;
    document.getElementById('edit_description').value = project.description;
    
    // Convert Gregorian date from DB to Persian for display
    if (project.start_date) {
        const startDate = new Date(project.start_date);
        const persianStartDate = new Intl.DateTimeFormat('fa-IR-u-nu-latn', { year: 'numeric', month: '2-digit', day: '2-digit' }).format(startDate).replace(/\//g, '-');
        document.getElementById('edit_start_date_persian').value = persianStartDate;
    } else {
        document.getElementById('edit_start_date_persian').value = '';
    }
    
    if (project.finish_date) {
        const finishDate = new Date(project.finish_date);
        const persianFinishDate = new Intl.DateTimeFormat('fa-IR-u-nu-latn', { year: 'numeric', month: '2-digit', day: '2-digit' }).format(finishDate).replace(/\//g, '-');
        document.getElementById('edit_finish_date_persian').value = persianFinishDate;
    } else {
        document.getElementById('edit_finish_date_persian').value = '';
    }

    // Show the modal
    const editModal = new bootstrap.Modal(document.getElementById('editProjectModal'));
    editModal.show();
}

function deleteProject(projectId) {
    if (confirm('آیا از حذف این پروژه مطمئن هستید؟ این عملیات غیرقابل بازگشت است.')) {
        document.getElementById('delete_project_id').value = projectId;
        document.getElementById('deleteForm').submit();
    }
}
</script>


<?php
// Include footer file which contains JS libraries
require_once __DIR__ . '/footer.php';
?>