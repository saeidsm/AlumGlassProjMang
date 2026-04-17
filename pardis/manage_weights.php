<?php
// =================================================================
// 1. SETUP & AUTHORIZATION
// =================================================================
require_once __DIR__ . '/../../sercon/bootstrap.php';
secureSession();

$expected_project_key = 'pardis';
if (($_SESSION['current_project_config_key'] ?? null) !== $expected_project_key) {
    header('Location: /select_project.php?msg=context_mismatch');
    exit();
}
requireRole(['admin']);
function log_activitypardis($action, $details = '') { /* ... as is ... */ }

// =================================================================
// 2. DB CONNECTIONS & INITIALIZATION
// =================================================================
$pageTitle = "مدیریت وزن‌دهی فعالیت‌ها";
$message = '';
$error = '';
$projects = [];
$tasks = [];
$selected_project_id = $_GET['project_id'] ?? null;

try {
    $pdo = getProjectDBConnection('pardis');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");
} catch (PDOException $e) { die("A database error occurred."); }

// =================================================================
// 3. HANDLE POST REQUEST (SAVE WEIGHTS)
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cost_weights = $_POST['cost_weight'] ?? [];
    $time_weights = $_POST['time_weight'] ?? [];
    $hybrid_weights = $_POST['hybrid_weight'] ?? [];
    $project_id_posted = $_POST['project_id'] ?? 0;

    if ($project_id_posted && !empty($cost_weights)) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "UPDATE tasks SET cost_weight = ?, time_weight = ?, hybrid_weight = ? 
                 WHERE task_id = ? AND project_id = ?"
            );
            $updated_count = 0;
            foreach ($cost_weights as $task_id => $cost_weight) {
                $time_weight = $time_weights[$task_id] ?? 0;
                $hybrid_weight = $hybrid_weights[$task_id] ?? 0;
                
                $stmt->execute([
                    (float)$cost_weight,
                    (float)$time_weight,
                    (float)$hybrid_weight,
                    (int)$task_id,
                    (int)$project_id_posted
                ]);
                $updated_count++;
            }
            $pdo->commit();
            $message = "تعداد $updated_count رکورد با موفقیت به‌روزرسانی شد.";
            log_activitypardis('Weights Updated', "Project ID: {$project_id_posted}, Count: {$updated_count}");
            // Refresh the selected project ID to show the updated data
            $selected_project_id = $project_id_posted;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "خطا در ذخیره‌سازی: " . $e->getMessage();
        }
    } else {
        $error = "اطلاعاتی برای ذخیره ارسال نشده است.";
    }
}

// =================================================================
// 4. FETCH DATA FOR DISPLAY (GET REQUEST)
// =================================================================
try {
    $projects = $pdo->query("SELECT project_id, project_name FROM projects ORDER BY project_name")->fetchAll(PDO::FETCH_ASSOC);
    if ($selected_project_id) {
        $stmt_tasks = $pdo->prepare("SELECT task_id, wbs, task_name, cost_weight, time_weight, hybrid_weight FROM tasks WHERE project_id = ? ORDER BY wbs");
        $stmt_tasks->execute([$selected_project_id]);
        $tasks = $stmt_tasks->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $error = "خطا در دریافت اطلاعات: " . $e->getMessage();
}

// =================================================================
// 5. RENDER THE PAGE
// =================================================================
require_once __DIR__ . '/header.php';
?>
<div class="content-wrapper p-6">

    <?php if (!empty($message)): ?><div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert"><p><?= htmlspecialchars($message) ?></p></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert"><p><?= htmlspecialchars($error) ?></p></div><?php endif; ?>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <h1 class="text-2xl font-bold mb-4"><?= htmlspecialchars($pageTitle) ?></h1>

        <!-- Project Selection Form -->
        <form method="GET" action="manage_weights.php" class="mb-6">
            <label for="project_id" class="block text-sm font-medium text-gray-700 mb-1">لطفاً یک پروژه را انتخاب کنید:</label>
            <div class="flex items-center">
                <select name="project_id" id="project_id" class="mt-1 block w-full md:w-1/2 border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" onchange="this.form.submit()">
                    <option value="">-- انتخاب پروژه --</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?= $project['project_id'] ?>" <?= ($selected_project_id == $project['project_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($project['project_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <noscript><button type="submit" class="bg-blue-500 text-white py-2 px-4 rounded mr-2">نمایش</button></noscript>
            </div>
        </form>

        <?php if ($selected_project_id && !empty($tasks)): ?>
            <form method="POST" action="manage_weights.php?project_id=<?= $selected_project_id ?>">
                <?= csrfField() ?>
                <input type="hidden" name="project_id" value="<?= $selected_project_id ?>">
                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="min-w-full text-sm divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">WBS</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">نام فعالیت</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">وزن هزینه‌ای (ریال)</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">وزن زمانی</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">وزن ترکیبی</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($tasks as $task): ?>
                            <tr>
                                <td class="px-4 py-2 whitespace-nowrap font-mono"><?= htmlspecialchars($task['wbs']) ?></td>
                                <td class="px-4 py-2 whitespace-nowrap"><?= htmlspecialchars($task['task_name']) ?></td>
                                <td class="px-4 py-2">
                                    <input type="number" step="0.01" name="cost_weight[<?= $task['task_id'] ?>]" value="<?= htmlspecialchars($task['cost_weight']) ?>" class="w-full p-1 border rounded">
                                </td>
                                <td class="px-4 py-2">
                                    <input type="number" step="0.0001" name="time_weight[<?= $task['task_id'] ?>]" value="<?= htmlspecialchars($task['time_weight']) ?>" class="w-full p-1 border rounded">
                                </td>
                                <td class="px-4 py-2">
                                    <input type="number" step="0.0001" name="hybrid_weight[<?= $task['task_id'] ?>]" value="<?= htmlspecialchars($task['hybrid_weight']) ?>" class="w-full p-1 border rounded">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-6">
                    <button type="submit" class="bg-green-600 hover:bg-green-800 text-white font-bold py-2 px-4 rounded">
                        ذخیره تغییرات
                    </button>
                </div>
            </form>
        <?php elseif ($selected_project_id): ?>
            <p class="text-gray-600">هیچ فعالیتی برای این پروژه یافت نشد. لطفاً ابتدا از طریق صفحه ورود اطلاعات، برنامه زمانبندی را وارد کنید.</p>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/footer.php';
?>