<?php
// public_html/pardis/daily_report_edit.php

require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';
secureSession();

if (!isLoggedIn()) {
    header('Location: /login.php?msg=login_required');
    exit();
}

$expected_project_key = 'pardis';
if (!isset($_SESSION['current_project_config_key']) || $_SESSION['current_project_config_key'] !== $expected_project_key) {
    header('Location: /select_project.php?msg=context_mismatch');
    exit();
}

$report_id = intval($_GET['id'] ?? 0);
if (!$report_id) {
    header('Location: daily_reports.php?msg=invalid_id');
    exit();
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['role'] ?? 'user';
$pdo = getProjectDBConnection('pardis');

// Fetch report data
$stmt = $pdo->prepare("
    SELECT dr.*, tt.arrival_time, tt.departure_time, tt.break_duration
    FROM daily_reports dr
    LEFT JOIN time_tracking tt ON dr.id = tt.report_id
    WHERE dr.id = ?
");
$stmt->execute([$report_id]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$report) {
    header('Location: daily_reports.php?msg=not_found');
    exit();
}

// Check edit permission
$can_edit = in_array($user_role, ['admin', 'superuser']) || 
            ($report['user_id'] == $user_id && $user_role !== 'user');

if (!$can_edit) {
    http_response_code(403);
    require 'Access_Denied.php';
    exit;
}

// Get activities
$stmt = $pdo->prepare("SELECT * FROM report_activities WHERE report_id = ? ORDER BY id");
$stmt->execute([$report_id]);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get attachments
$stmt = $pdo->prepare("SELECT * FROM report_attachments WHERE report_id = ? ORDER BY id");
$stmt->execute([$report_id]);
$attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "ویرایش گزارش روزانه";
require_once __DIR__ . '/header_pardis.php';

// Engineering roles
$engineering_roles = [
    'field_engineer' => 'اجرا',
    'designer' => 'طراح',
    'surveyor' => 'نقشه‌بردار',
    'control_engineer' => 'کنترل پروژه',
    'drawing_specialist' => 'شاپیست'
];
$projectData_php = [
    "پروژه دانشگاه خاتم پردیس" => [
        "ساختمان کتابخانه" => ['کرتین وال طبقه سوم', 'پهنه بتنی غرب و جنوب', 'ویندووال طبقه 1', 'ویندووال طبقه 2', 'ویندووال طبقه همکف', 'اسکای لایت', 'کاور بتنی ستونها', 'هندریل', 'نقشه‌های ساخت فلاشینگ، دودبند و سمنت بورد'],
        "ساختمان دانشکده کشاورزی" => ['ویندووال طبقه 3 غرب', 'کرتین وال طبقه 1 و 2 غرب', 'کرتین‌‌وال طبقه 2 و 3 غرب', 'ویندووال طبقه اول شمال بین محور B~F', 'کرتین‌وال طبقه دوم و سوم شمال بین محور C~F', 'ویندووال طبقه دوم شمال بین محور B~C', 'ویندووال طبقه سوم شمال بین محور A~B', 'کرتین‌وال طبقه 1و2 شمال بین محور A~B', 'ویندووال طبقه همکف شرق', 'ویندووال طبقه اول شرق بین محور 11 تا 18', 'کرتین وال طبقه 2 و 3 شرق بین محورهای 12 تا 17', 'کرتین‌وال طبقه 1 تا 3 شرق بین محورهای 11 تا 12', 'کرتین‌وال طبقه 1 تا 3 شرق بین محورهای 7 تا 8', 'کرتین‌وال طبقه 1 تا 2 شرق بین محورهای 2 تا 7', 'کرتین‌وال طبقه همکف تا سوم شرق میانی', 'ویندووال طبقه اول جنوب', 'ویندووال طبقه دوم جنوب', 'ویندووال طبقه سوم جنوب', 'نمای بتنی غرب', 'نمای آجری غرب', 'پنل‌های بتنی شرق', 'پنل‌های آجری شرق', 'پنل‌های آجری شمال', 'پنل‌های بتنی شمال', 'پنل‌های بتنی جنوب', 'پنل‌های آجری جنوب', 'نمای کلمپ ویو', 'هندریل', 'ویندووال داخلی طبقه 3', 'اسکای لایت'],
        "هر دو ساختمان" => [], "عمومی" => []
    ],
    'بیمارستان پردیس' => ['ساختمان اصلی' => [], 'ساختمان فرعی' => []],
    'پروژه آراد' => ['ساختمان اصلی' => []],
    'پروژه فرشته' => ['ساختمان اصلی' => []]
];
// Project locations
$project_locations = [
    'library' => [
        'name' => 'ساختمان کتابخانه',
        'options' => [
            'کرتین وال طبقه سوم',
            'پهنه بتنی غرب و جنوب',
            'ویندووال طبقه 1',
            'ویندووال طبقه 2',
            'ویندووال طبقه همکف',
            'اسکای لایت',
            'کاور بتنی ستونها',
            'هندریل',
            'نقشه‌های ساخت فلاشینگ، دودبند و سمنت بورد'
        ]
    ],
    'agriculture' => [
        'name' => 'ساختمان دانشکده کشاورزی',
        'options' => [
            'ویندووال طبقه 3 غرب',
            'کرتین وال طبقه 1 و 2 غرب',
            'کرتین‌‌وال طبقه 2 و 3 غرب',
            'ویندووال طبقه اول شمال بین محور B~F',
            'کرتین‌وال طبقه دوم و سوم شمال بین محور C~F',
            'ویندووال طبقه دوم شمال بین محور B~C',
            'ویندووال طبقه سوم شمال بین محور A~B',
            'کرتین‌وال طبقه 1و2 شمال بین محور A~B',
            'ویندووال طبقه همکف شرق',
            'ویندووال طبقه اول شرق بین محور 11 تا 18',
            'کرتین وال طبقه 2 و 3 شرق بین محورهای 12 تا 17',
            'کرتین‌وال طبقه 1 تا 3 شرق بین محورهای 11 تا 12',
            'کرتین‌وال طبقه 1 تا 3 شرق بین محورهای 7 تا 8',
            'کرتین‌وال طبقه 1 تا 2 شرق بین محورهای 2 تا 7',
            'کرتین‌وال طبقه همکف تا سوم شرق میانی',
            'ویندووال طبقه اول جنوب',
            'ویندووال طبقه دوم جنوب',
            'ویندووال طبقه سوم جنوب',
            'نمای بتنی غرب',
            'نمای آجری غرب',
            'پنل‌های بتنی شرق',
            'پنل‌های آجری شرق',
            'پنل‌های آجری شمال',
            'پنل‌های بتنی شمال',
            'پنل‌های بتنی جنوب',
            'پنل‌های آجری جنوب',
            'نمای کلمپ ویو',
            'هندریل',
            'ویندووال داخلی طبقه 3',
            'اسکای لایت'
        ]
    ],
    'both' => ['name' => 'هر دو ساختمان', 'options' => []],
    'general' => ['name' => 'عمومی', 'options' => []]
];

// Convert Gregorian to Jalali for display
function gregorian_to_jalali_input($date) {
    if (empty($date)) return '';
    $parts = explode('-', $date);
    if (count($parts) != 3) return $date;
    if (function_exists('gregorian_to_jalali')) {
        list($j_y, $j_m, $j_d) = gregorian_to_jalali($parts[0], $parts[1], $parts[2]);
        return sprintf('%04d/%02d/%02d', $j_y, $j_m, $j_d);
    }
    return $date;
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/pardis/assets/css/jalalidatepicker.min.css" />
    <script src="/pardis/assets/js/jalalidatepicker.min.js"></script>
    <style>
        body {
            font-family: 'Tahoma', 'Arial', sans-serif;
            background: #f8f9fa;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 16px 20px;
            font-weight: 600;
        }
        .activity-item {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            background: #f8f9fa;
        }
        .btn-custom {
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <!-- Back Button -->
        <div class="mb-3">
            <a href="daily_report_view.php?id=<?php echo $report_id; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-right"></i> بازگشت به مشاهده
            </a>
        </div>

        <!-- Edit Form -->
        <div class="row">
            <div class="col-lg-10 offset-lg-1">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-pencil-square"></i> ویرایش گزارش روزانه
                    </div>
                    <div class="card-body">
                        <form id="editReportForm" action="daily_report_update.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="report_id" value="<?php echo $report_id; ?>">
                            
                            <!-- Basic Information -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">تاریخ گزارش</label>
                                    <input type="text" class="form-control" name="report_date" 
                                           value="<?php echo gregorian_to_jalali_input($report['report_date']); ?>"
                                           data-jdp data-jdp-only-date required readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">نام مهندس</label>
                                    <input type="text" class="form-control" name="engineer_name" 
                                           value="<?php echo htmlspecialchars($report['engineer_name']); ?>" required>
                                </div>
                            </div>

                            <div class="row mb-3">
    <div class="col-md-12">
        <label class="form-label">نقش</label>
        <select class="form-select" name="role" required>
            <?php foreach ($engineering_roles as $key => $value): ?>
            <option value="<?php echo $key; ?>" <?php echo $report['role'] === $key ? 'selected' : ''; ?>>
                <?php echo $value; ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<!-- New Project/Building/Part Dropdowns -->
 

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">وضعیت آب و هوا</label>
                                    <select class="form-select" name="weather">
                                        <option value="clear" <?php echo $report['weather'] === 'clear' ? 'selected' : ''; ?>>آفتابی</option>
                                        <option value="cloudy" <?php echo $report['weather'] === 'cloudy' ? 'selected' : ''; ?>>ابری</option>
                                        <option value="rainy" <?php echo $report['weather'] === 'rainy' ? 'selected' : ''; ?>>بارانی</option>
                                        <option value="hot" <?php echo $report['weather'] === 'hot' ? 'selected' : ''; ?>>گرم</option>
                                        <option value="cold" <?php echo $report['weather'] === 'cold' ? 'selected' : ''; ?>>سرد</option>
                                        <option value="other" <?php echo $report['weather'] === 'other' ? 'selected' : ''; ?>>سایر</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
    <label class="form-label">ساعات کاری (محاسبه خودکار)</label>
    <input type="number" class="form-control" id="work_hours" name="work_hours" 
           value="<?php echo $report['work_hours']; ?>" step="0.1" readonly>
</div>
                                <div class="col-md-4">
                                    <label class="form-label">حادثه ایمنی</label>
                                    <select class="form-select" name="safety_incident">
                                        <option value="no" <?php echo $report['safety_incident'] === 'no' ? 'selected' : ''; ?>>خیر</option>
                                        <option value="yes" <?php echo $report['safety_incident'] === 'yes' ? 'selected' : ''; ?>>بله</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Time Tracking -->
                            <div class="card mb-3 bg-light">
    <div class="card-body">
        <h6 class="card-title"><i class="bi bi-clock"></i> زمان ورود و خروج</h6>
        <div class="row">
            <div class="col-md-5">
                <label class="form-label">زمان ورود</label>
                <input type="time" class="form-control" id="arrival_time" name="arrival_time" 
                       value="<?php echo $report['arrival_time'] ?? ''; ?>" onchange="calculateWorkHours()">
            </div>
            <div class="col-md-5">
                <label class="form-label">زمان خروج</label>
                <input type="time" class="form-control" id="departure_time" name="departure_time" 
                       value="<?php echo $report['departure_time'] ?? ''; ?>" onchange="calculateWorkHours()">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="had_lunch_break" name="had_lunch_break" 
                           <?php echo ($report['had_lunch_break'] ?? 0) ? 'checked' : ''; ?> onchange="calculateWorkHours()">
                    <label class="form-check-label" for="had_lunch_break">استراحت ناهار</label>
                </div>
            </div>
        </div>
    </div>
</div>


                            <!-- Activities Section -->
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="card-title mb-0"><i class="bi bi-list-task"></i> فعالیت‌های انجام شده</h6>
                                        <button type="button" class="btn btn-sm btn-primary btn-custom" onclick="addActivity()">
                                            <i class="bi bi-plus"></i> افزودن فعالیت
                                        </button>
                                    </div>
                                    <div id="activitiesContainer">
    <?php foreach ($activities as $index => $activity): ?>
         <?php
            // Logic to check if the saved part is a custom one
            $is_custom_part = false;
            if (!empty($activity['project_name']) && !empty($activity['building_name']) && isset($projectData_php[$activity['project_name']][$activity['building_name']])) {
                $standard_parts = $projectData_php[$activity['project_name']][$activity['building_name']];
                if (!in_array($activity['building_part'], $standard_parts)) {
                    $is_custom_part = true;
                }
            } else if (!empty($activity['building_part'])) {
                 // If the part exists but the project/building doesn't match our list, it's custom
                $is_custom_part = true;
            }
        ?>
    <div class="activity-item" id="activity-<?php echo $activity['id']; ?>">
        <input type="hidden" name="activities[<?php echo $activity['id']; ?>][id]" value="<?php echo $activity['id']; ?>">
        
        <!-- START: Per-Activity Location Editors -->
        <div class="row mb-3">
            <div class="col-md-4">
                <label class="form-label fw-bold">پروژه</label>
                <select class="form-select form-select-sm activity-project" name="activities[<?php echo $activity['id']; ?>][project_name]" data-activity-id="<?php echo $activity['id']; ?>">
                    <?php foreach ($projectData_php as $projectName => $buildings): ?>
                        <option value="<?php echo htmlspecialchars($projectName); ?>" <?php echo ($activity['project_name'] == $projectName) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($projectName); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">ساختمان</label>
                <select class="form-select form-select-sm activity-building" name="activities[<?php echo $activity['id']; ?>][building_name]" data-activity-id="<?php echo $activity['id']; ?>">
                    <?php // This part will be populated by JavaScript, but we can pre-populate the selected one
                    if (!empty($activity['project_name']) && isset($projectData_php[$activity['project_name']])) {
                        foreach ($projectData_php[$activity['project_name']] as $buildingName => $parts) {
                            $selected = ($activity['building_name'] == $buildingName) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($buildingName) . "' $selected>" . htmlspecialchars($buildingName) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">بخش</label>
                 <select class="form-select form-select-sm activity-part" name="activities[<?php echo $activity['id']; ?>][building_part]" data-activity-id="<?php echo $activity['id']; ?>" style="<?php echo $is_custom_part ? 'display: none;' : 'display: block;'; ?>" <?php echo $is_custom_part ? '' : 'required'; ?>>
                     <?php // Pre-populate parts for the selected building
                    if (!empty($activity['building_name']) && isset($projectData_php[$activity['project_name']][$activity['building_name']])) {
                        foreach ($projectData_php[$activity['project_name']][$activity['building_name']] as $part) {
                            $selected = ($activity['building_part'] == $part) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($part) . "' $selected>" . htmlspecialchars($part) . "</option>";
                        }
                    }
                    ?>
                </select>
               <input type="text" class="form-control form-control-sm mt-1 activity-custom-part" name="activities[<?php echo $activity['id']; ?>][custom_building_part]" placeholder="نام بخش دلخواه" style="<?php echo $is_custom_part ? 'display: block;' : 'display: none;'; ?>" value="<?php echo $is_custom_part ? htmlspecialchars($activity['building_part']) : ''; ?>" <?php echo $is_custom_part ? 'required' : ''; ?>> 
            </div>
        </div>
          <div class="row mb-3"><div class="col-12"><div class="form-check">
                <input class="form-check-input" type="checkbox" onchange="toggleCustomPart(this, '<?php echo $activity['id']; ?>')" <?php echo $is_custom_part ? 'checked' : ''; ?>>
                <label class="form-check-label small">بخش دلخواه (در لیست موجود نیست)</label>
            </div></div></div>

        <hr>
                                            <div class="row">
                                                <div class="col-md-6 mb-2">
                                                    <label class="form-label">شرح فعالیت</label>
                                                    <input type="text" class="form-control" 
                                                           name="activities[<?php echo $activity['id']; ?>][description]" 
                                                           value="<?php echo htmlspecialchars($activity['task_description']); ?>" required>
                                                </div>
                                                <div class="col-md-6 mb-2">
                                                    <label class="form-label">نوع فعالیت</label>
                                                    <input type="text" class="form-control" 
                                                           name="activities[<?php echo $activity['id']; ?>][type]" 
                                                           value="<?php echo htmlspecialchars($activity['task_type']); ?>">
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-3 mb-2">
                                                    <label class="form-label">درصد پیشرفت</label>
                                                    <input type="number" class="form-control" 
                                                           name="activities[<?php echo $activity['id']; ?>][progress]" 
                                                           value="<?php echo $activity['progress_percentage']; ?>" min="0" max="100">
                                                </div>
                                                <div class="col-md-3 mb-2">
                                                    <label class="form-label">وضعیت</label>
                                                    <select class="form-select" name="activities[<?php echo $activity['id']; ?>][status]">
                                                        <option value="in_progress" <?php echo $activity['status'] === 'in_progress' ? 'selected' : ''; ?>>در حال انجام</option>
                                                        <option value="completed" <?php echo $activity['status'] === 'completed' ? 'selected' : ''; ?>>تکمیل شده</option>
                                                        <option value="blocked" <?php echo $activity['status'] === 'blocked' ? 'selected' : ''; ?>>مسدود شده</option>
                                                        <option value="delayed" <?php echo $activity['status'] === 'delayed' ? 'selected' : ''; ?>>تاخیر دارد</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-3 mb-2">
                                                    <label class="form-label">ساعات صرف شده</label>
                                                    <input type="number" class="form-control" 
                                                           name="activities[<?php echo $activity['id']; ?>][hours]" 
                                                           value="<?php echo $activity['hours_spent']; ?>" step="0.5" min="0">
                                                </div>
                                                <div class="col-md-3 mb-2">
                                                    <label class="form-label">اولویت</label>
                                                    <select class="form-select" name="activities[<?php echo $activity['id']; ?>][priority]">
                                                        <option value="medium" <?php echo $activity['priority'] === 'medium' ? 'selected' : ''; ?>>متوسط</option>
                                                        <option value="low" <?php echo $activity['priority'] === 'low' ? 'selected' : ''; ?>>کم</option>
                                                        <option value="high" <?php echo $activity['priority'] === 'high' ? 'selected' : ''; ?>>زیاد</option>
                                                        <option value="urgent" <?php echo $activity['priority'] === 'urgent' ? 'selected' : ''; ?>>فوری</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-12">
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="removeActivity(<?php echo $activity['id']; ?>)">
                                                        <i class="bi bi-trash"></i> حذف
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Issues and Notes -->
                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-exclamation-triangle"></i> موانع و مشکلات</label>
                                <textarea class="form-control" name="issues_blockers" rows="3"><?php echo htmlspecialchars($report['issues_blockers']); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-calendar-check"></i> برنامه فردا</label>
                                <textarea class="form-control" name="next_day_plan" rows="3"><?php echo htmlspecialchars($report['next_day_plan']); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-chat-left-text"></i> یادداشت‌های عمومی</label>
                                <textarea class="form-control" name="general_notes" rows="2"><?php echo htmlspecialchars($report['general_notes']); ?></textarea>
                            </div>

                            <!-- Existing Attachments -->
                            <?php if (count($attachments) > 0): ?>
                            <div class="mb-3">
                                <label class="form-label">فایل‌های پیوست فعلی</label>
                                <div class="list-group">
                                    <?php foreach ($attachments as $att): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><i class="bi bi-file-earmark"></i> <?php echo htmlspecialchars($att['file_name']); ?></span>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="markForDeletion(<?php echo $att['id']; ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- New File Upload -->
                            <div class="mb-4">
                                <label class="form-label"><i class="bi bi-paperclip"></i> افزودن فایل جدید</label>
                                <input type="file" class="form-control" name="new_attachments[]" multiple accept="image/*,.pdf,.doc,.docx">
                                <small class="text-muted">حداکثر 5 فایل - هر فایل حداکثر 5MB</small>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success btn-lg btn-custom">
                                    <i class="bi bi-check-circle"></i> ذخیره تغییرات
                                </button>
                                <a href="daily_report_view.php?id=<?php echo $report_id; ?>" class="btn btn-outline-secondary">
                                    انصراف
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
 <script>
        // --- 1. GLOBAL VARIABLES AND DATA ---
      const projectData = <?php echo json_encode($projectData_php); ?>;

        let newActivityCount = 0;
        const deletedActivities = [];
        const deletedAttachments = [];
          function toggleCustomPart(checkbox, id) {
        // This function now works by finding elements relative to the checkbox
        const container = checkbox.closest('.activity-item');
        const customInput = container.querySelector('.activity-custom-part');
        const partSelect = container.querySelector('.activity-part');
        
        if (checkbox.checked) {
            partSelect.style.display = 'none';
            partSelect.required = false;
            customInput.style.display = 'block';
            customInput.required = true;
        } else {
            customInput.style.display = 'none';
            customInput.required = false;
            partSelect.style.display = 'block';
            partSelect.required = true;
        }
    }

function initializeCascadingDropdowns(container) {
        const projectSelect = container.querySelector('.activity-project');
        const buildingSelect = container.querySelector('.activity-building');
        const partSelect = container.querySelector('.activity-part');

        projectSelect.addEventListener('change', populateBuildings);
        buildingSelect.addEventListener('change', populateParts);

        function populateBuildings() {
            const selectedProject = projectSelect.value;
            buildingSelect.innerHTML = '<option value="">انتخاب...</option>';
            partSelect.innerHTML = '<option value="">...</option>';
            if (selectedProject && projectData[selectedProject]) {
                for (const buildingName in projectData[selectedProject]) {
                    buildingSelect.options[buildingSelect.options.length] = new Option(buildingName, buildingName);
                }
            }
        }

            function populateParts() {
            const selectedProject = projectSelect.value;
            const selectedBuilding = buildingSelect.value;
            partSelect.innerHTML = '<option value="">انتخاب...</option>';
            if (selectedProject && selectedBuilding && projectData[selectedProject][selectedBuilding]) {
                projectData[selectedProject][selectedBuilding].forEach(part => {
                    partSelect.options[partSelect.options.length] = new Option(part, part);
                });
            }
        }
    }
        // --- 2. SINGLE DOMCONTENTLOADED LISTENER FOR ALL INITIALIZATION ---
        document.addEventListener('DOMContentLoaded', function() {

            // Initialize the Jalali Date Picker first
            jalaliDatepicker.startWatch({
                persianDigits: true, autoShow: true, autoHide: true, hideAfterChange: true,
                date: true, time: false, zIndex: 2000
            });
 document.querySelectorAll('.activity-item').forEach(activityContainer => {
            initializeCascadingDropdowns(activityContainer);
        });
            // Then, set up the cascading dropdowns
           
        });

        // --- 3. GLOBAL FUNCTIONS for onclick events ---
         function addActivity() {
        newActivityCount++;
        const container = document.getElementById('activitiesContainer');
        const activityHtml = `
            <div class="activity-item" id="new-activity-${newActivityCount}">
                 <div class="row mb-3">
                    <div class="col-md-4"><label class="form-label fw-bold">پروژه</label><select class="form-select form-select-sm activity-project" name="new_activities[${newActivityCount}][project_name]">${Object.keys(projectData).map(p => `<option value="${p}">${p}</option>`).join('')}</select></div>
                    <div class="col-md-4"><label class="form-label fw-bold">ساختمان</label><select class="form-select form-select-sm activity-building" name="new_activities[${newActivityCount}][building_name]"></select></div>
                    <div class="col-md-4"><label class="form-label fw-bold">بخش</label><select class="form-select form-select-sm activity-part" name="new_activities[${newActivityCount}][building_part]"></select></div>
                </div><hr>
                <div class="row">
                    <div class="col-md-6 mb-2"><label class="form-label">شرح</label><input type="text" class="form-control" name="new_activities[${newActivityCount}][description]" required></div>
                    <div class="col-md-3 mb-2"><label class="form-label">پیشرفت</label><input type="number" class="form-control" name="new_activities[${newActivityCount}][progress]" value="0"></div>
                    <div class="col-md-3 mb-2"><label class="form-label">ساعات</label><input type="number" class="form-control" name="new_activities[${newActivityCount}][hours]"></div>
                    <div class="col-12 mt-2"><button type="button" class="btn btn-sm btn-danger" onclick="removeNewActivity(${newActivityCount})">حذف</button></div>
                </div>
            </div>`;
        container.insertAdjacentHTML('beforeend', activityHtml);
        
        // Set up the logic for the newly added activity
        const newActivityElement = document.getElementById(`new-activity-${newActivityCount}`);
        initializeCascadingDropdowns(newActivityElement);
        // Manually trigger population for the default project
        newActivityElement.querySelector('.activity-project').value = "پروژه دانشگاه خاتم پردیس";
        newActivityElement.querySelector('.activity-project').dispatchEvent(new Event('change'));
    }

    function removeActivity(id) {
        if (confirm('آیا از حذف این فعالیت اطمینان دارید؟')) {
            document.getElementById(`activity-${id}`).remove();
            const form = document.getElementById('editReportForm');
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'deleted_activities[]';
            input.value = id;
            form.appendChild(input);
        }
    }

        function removeActivity(id) {
            if (confirm('آیا از حذف این فعالیت اطمینان دارید؟')) {
                document.getElementById(`activity-${id}`).remove();
                const form = document.getElementById('editReportForm');
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'deleted_activities[]';
                input.value = id;
                form.appendChild(input);
            }
        }

        function removeNewActivity(id) {
            document.getElementById(`new-activity-${id}`).remove();
        }

        function markForDeletion(id) {
            if (confirm('آیا از حذف این فایل اطمینان دارید؟')) {
                const form = document.getElementById('editReportForm');
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'deleted_attachments[]';
                input.value = id;
                form.appendChild(input);
                event.target.closest('.list-group-item').style.display = 'none';
            }
        }
    </script>
</body>
</html>

<?php require_once __DIR__ . '/footer.php'; ?>