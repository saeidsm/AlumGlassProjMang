<?php
// public_html/pardis/daily_reports.php

function isMobileDevice()
{
    return preg_match(
        "/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i",
        $_SERVER["HTTP_USER_AGENT"]
    );
}

if (isMobileDevice() && !isset($_GET['view'])) {
    header('Location: daily_report_mobile.php');
    exit();
}

require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';

secureSession();

if (!isLoggedIn()) {
    header('Location: /login.php?msg=login_required');
    exit();
}

if (!in_array($_SESSION['role'], ['admin', 'superuser', 'user', 'supervisor', 'planner'])) {
    http_response_code(403);
    require 'Access_Denied.php';
    exit;
}

$expected_project_key = 'pardis';

if (!isset($_SESSION['current_project_config_key']) || $_SESSION['current_project_config_key'] !== $expected_project_key) {
    logError("User ID " . ($_SESSION['user_id'] ?? 'N/A') . " tried to access pardis project page without correct session context.");
    header('Location: /select_project.php?msg=context_mismatch');
    exit();
}

$user_role = $_SESSION['role'] ?? 'guest';
$user_id = $_SESSION['user_id'] ?? 0;

// Get user's full name from common database
$user_name = 'کاربر';
try {
    $commonPdo = getCommonDBConnection();
    $stmt = $commonPdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $first_name = trim($user['first_name'] ?? '');
        $last_name = trim($user['last_name'] ?? '');

        if (!empty($first_name) && !empty($last_name)) {
            $user_name = $first_name . ' ' . $last_name;
        } elseif (!empty($first_name)) {
            $user_name = $first_name;
        } elseif (!empty($last_name)) {
            $user_name = $last_name;
        } else {
            $user_name = $_SESSION['name'] ?? 'کاربر';
        }
    }
} catch (Exception $e) {
    logError("Error fetching user name: " . $e->getMessage());
    $user_name = $_SESSION['name'] ?? 'کاربر';
}

$pageTitle = "گزارش روزانه - پروژه دانشگاه خاتم پردیس";
function isMobileDevices() {
    // A simple but effective check for common mobile user agents
    return preg_match(
        "/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i",
        $_SERVER["HTTP_USER_AGENT"]
    );
}

// If a mobile device is detected, redirect to the mobile page and stop script execution
if (isMobileDevices()) {
    // Make sure the path to your mobile page is correct
    require_once __DIR__ . '/header_p_mobile.php';

}
else{require_once __DIR__ . '/header_pardis.php';

}

// Role mapping for engineers
$engineering_roles = [
    'field_engineer' => 'مهندس اجرا',
    'designer' => 'طراح',
    'surveyor' => 'نقشه‌بردار',
    'control_engineer' => 'مهندس کنترل پروژه',
    'drawing_specialist' => 'شاپ'
];
$assignable_roles = [
    'admin' => 'مدیر',
    'superuser'=> 'مدیر',
    'supervisor' => 'سرپرست',
    'designer' => 'طراح'
];
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="/pardis/assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/pardis/assets/css/jalalidatepicker.min.css" />
    <script src="/pardis/assets/js/jalalidatepicker.min.js"></script>
    <style>
        body {
            font-family: 'Tahoma', 'Arial', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 12px 24px;
        }

        .nav-tabs .nav-link:hover {
            color: #0d6efd;
            border: none;
        }

        .nav-tabs .nav-link.active {
            color: #0d6efd;
            border: none;
            border-bottom: 3px solid #0d6efd;
            background: none;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 16px 20px;
            font-weight: 600;
        }

        .stat-card {
            border-right: 4px solid;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card.primary {
            border-right-color: #0d6efd;
        }

        .stat-card.success {
            border-right-color: #198754;
        }

        .stat-card.warning {
            border-right-color: #ffc107;
        }

        .stat-card.danger {
            border-right-color: #dc3545;
        }

        .btn-custom {
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
        }

        .badge-status {
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 500;
        }

        .progress-bar-custom {
            height: 25px;
            border-radius: 12px;
            font-weight: 600;
        }

        .jdp-wrapper {
            font-family: 'Tahoma', 'Arial', sans-serif !important;
            z-index: 9999 !important;
        }

        .jdp-month,
        .jdp-year {
            font-family: 'Tahoma', 'Arial', sans-serif !important;
        }
        .calendar-container {
    padding: 20px;
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 5px;
}

.calendar-header {
    text-align: center;
    padding: 10px;
    font-weight: bold;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
}

.calendar-day {
    min-height: 100px;
    padding: 8px;
    border: 1px solid #dee2e6;
    background: white;
    position: relative;
    cursor: pointer;
    transition: all 0.2s;
}

.calendar-day:hover {
    background: #f8f9fa;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.calendar-day.today {
    background: #e3f2fd;
    border-color: #2196F3;
    font-weight: bold;
}

.calendar-day.other-month {
    opacity: 0.3;
    background: #fafafa;
}

.calendar-day.has-report {
    background: #c8e6c9;
    border-color: #4caf50;
}

.calendar-day-number {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 5px;
}

.calendar-day-reports {
    font-size: 11px;
}

.report-badge {
    display: inline-block;
    padding: 2px 6px;
    margin: 2px;
    border-radius: 3px;
    background: #2196F3;
    color: white;
    font-size: 10px;
}
 .fc-event-success {
            background-color: #198754;
            border-color: #157347;
        }
        /* Style for Unfinished & Assigned Tasks */
        .fc-event-warning {
            background-color: #ffc107;
            border-color: #d39e00;
            color: #000 !important; /* Dark text for better readability on yellow */
        }
        /* Style for Missed Report background */
        .fc-event-missed-day {
            background-color: rgba(220, 53, 69, 0.15); /* A light, non-intrusive red */
        }
        .jalali-day-number { font-weight: bold; }
    </style>
</head>

<body>
    <div class="container-fluid mt-4">
          <div id="submission-notification-area" class="mb-4"></div>
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="mb-3">
                    <i class="bi bi-clipboard-check text-primary"></i>
                    سیستم گزارش روزانه مهندسین
                </h2>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs mb-4" id="reportTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="submit-tab" data-bs-toggle="tab" data-bs-target="#submit" type="button">
                    <i class="bi bi-plus-circle"></i> ثبت گزارش جدید
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="list-tab" data-bs-toggle="tab" data-bs-target="#list" type="button">
                    <i class="bi bi-list-ul"></i> لیست گزارش‌ها
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#dashboard" type="button">
                    <i class="bi bi-graph-up"></i> داشبورد
                </button>
            </li>
<?php if (in_array($user_role, ['admin', 'superuser'])): ?>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="analytics-tab" data-bs-toggle="tab" 
                data-bs-target="#analytics" type="button">
            <i class="bi bi-bar-chart-line"></i> تحلیل‌های عمومی
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="building-analytics-tab" data-bs-toggle="tab" 
                data-bs-target="#building-analytics" type="button">
            <i class="bi bi-building"></i> تحلیل ساختمان‌ها
        </button>
    </li>
<?php endif; ?>
            <li class="nav-item" role="presentation">
   <li class="nav-item" role="presentation">
    <button class="nav-link" id="tasks-tab" data-bs-toggle="tab" data-bs-target="#tasks" type="button">
        <i class="bi bi-list-check"></i> مدیریت کارها
        <span class="badge bg-danger" id="overdueTasksBadge" style="display:none;">0</span>
        <span class="badge bg-warning" id="assignedTasksBadge" style="display:none;">0</span>
    </button>
</li>
<li class="nav-item" role="presentation">
    <button class="nav-link" id="issues-tab" data-bs-toggle="tab" data-bs-target="#issues-management" type="button">
        <i class="bi bi-shield-exclamation"></i> مدیریت مشکلات
    </button>
</li>
<li class="nav-item" role="presentation">
    <button class="nav-link" id="calendar-tab" data-bs-toggle="tab" 
            data-bs-target="#calendar-view" type="button">
        <i class="bi bi-calendar3"></i> تقویم گزارش‌ها
    </button>
</li>
        </ul> <!-- This closes the nav-tabs ul -->

        <!-- Tab Content -->
        <div class="tab-content" id="reportTabsContent">

            <!-- Submit Report Tab -->
            <div class="tab-pane fade show active" id="submit" role="tabpanel">
                <div class="row">
                    <div class="col-lg-8 offset-lg-2">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-file-earmark-plus"></i> ثبت گزارش روزانه
                            </div>
                            <div class="card-body">
                                <form id="dailyReportForm" action="daily_report_submit.php" method="POST" enctype="multipart/form-data">
                                    <?= csrfField() ?>

                                    <!-- Basic Information -->
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">تاریخ گزارش</label>
                                            <input type="text" class="form-control" id="report_date" name="report_date"
                                                data-jdp data-jdp-only-date placeholder="انتخاب تاریخ" required readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">نام مهندس</label>
                                            <input type="text" class="form-control" name="engineer_name" value="<?php echo htmlspecialchars($user_name); ?>" required>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">نقش</label>
                                            <select class="form-select" name="role" id="roleSelect" required>
                                                <option value="">انتخاب کنید...</option>
                                                <?php foreach ($engineering_roles as $key => $value): ?>
                                                    <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">محل فعالیت</label>
                                            <input type="text" class="form-control" name="location"
                                               value="پردیس"
                                             placeholder="مثال: ساختمان A - طبقه 2"
                                            required>
                                        </div>
                                    </div>
                                  <!-- Weather Integration -->

<script src="/pardis/assets/js/persian-date.min.js"></script>
<script>
            const projectData = {
            "پروژه دانشگاه خاتم پردیس": {
            "ساختمان کتابخانه": [
            'کرتین وال طبقه سوم', 'پهنه بتنی غرب و جنوب', 'ویندووال طبقه 1', 'ویندووال طبقه 2',
            'ویندووال طبقه همکف', 'اسکای لایت', 'کاور بتنی ستونها', 'هندریل',
            'نقشه‌های ساخت فلاشینگ، دودبند و سمنت بورد'
            ],
            "ساختمان دانشکده کشاورزی": [
            'ویندووال طبقه 3 غرب', 'کرتین وال طبقه 1 و 2 غرب', 'کرتین‌‌وال طبقه 2 و 3 غرب',
            'ویندووال طبقه اول شمال بین محور B~F', 'کرتین‌وال طبقه دوم و سوم شمال بین محور C~F',
            'ویندووال طبقه دوم شمال بین محور B~C', 'ویندووال طبقه سوم شمال بین محور A~B',
            'کرتین‌وال طبقه 1و2 شمال بین محور A~B', 'ویندووال طبقه همکف شرق',
            'ویندووال طبقه اول شرق بین محور 11 تا 18', 'کرتین وال طبقه 2 و 3 شرق بین محورهای 12 تا 17',
            'کرتین‌وال طبقه 1 تا 3 شرق بین محورهای 11 تا 12', 'کرتین‌وال طبقه 1 تا 3 شرق بین محورهای 7 تا 8',
            'کرتین‌وال طبقه 1 تا 2 شرق بین محورهای 2 تا 7', 'کرتین‌وال طبقه همکف تا سوم شرق میانی',
            'ویندووال طبقه اول جنوب', 'ویندووال طبقه دوم جنوب', 'ویندووال طبقه سوم جنوب',
            'نمای بتنی غرب', 'نمای آجری غرب', 'پنل‌های بتنی شرق', 'پنل‌های آجری شرق',
            'پنل‌های آجری شمال', 'پنل‌های بتنی شمال', 'پنل‌های بتنی جنوب', 'پنل‌های آجری جنوب',
            'نمای کلمپ ویو', 'هندریل', 'ویندووال داخلی طبقه 3', 'اسکای لایت'
            ],
            "هر دو ساختمان": [], // Empty array will trigger custom input
            "عمومی": [] // Empty array will trigger custom input
            }

            // You can add more projects here in the future
            };

 document.addEventListener('DOMContentLoaded', function() {
        // Initialize Jalali DatePicker - THIS IS THE PART YOU WERE LOOKING FOR
        jalaliDatepicker.startWatch({
            persianDigits: true, autoShow: true, autoHide: true, hideAfterChange: true,
            date: true, time: false, zIndex: 2000
        });

        // Add the first activity row automatically
        addActivity();
loadPendingTasks();
      loadPreviousDayPlan();  
 checkSubmissionStatus(); 
  checkMissedSubmissions();
        loadReportsList();
        loadTaskCounts();
        calculateWorkHours();
        
        // Add event listeners for filters and tabs
        document.getElementById('filterDate')?.addEventListener('change', loadReportsList);
        document.getElementById('filterRole')?.addEventListener('change', loadReportsList);
        document.getElementById('dashboard-tab')?.addEventListener('shown.bs.tab', loadDashboardData);
    });

function updateProjectLocations() {
    const projectSelect = document.getElementById('projectBuilding');
    const locationSelect = document.getElementById('locationSelect');
    const selectedProject = projectSelect.value;

    locationSelect.innerHTML = '<option value="">انتخاب کنید...</option>';

    if (selectedProject && projectLocations[selectedProject]) {
        const options = projectLocations[selectedProject].options;
        options.forEach(option => {
            const opt = document.createElement('option');
            opt.value = option;
            opt.textContent = option;
            locationSelect.appendChild(opt);
        });

        if (options.length === 0) {
            document.getElementById('customLocationCheck').checked = true;
            toggleCustomLocation();
        }
    }
}

function loadPreviousDayPlan() {
    fetch('daily_report_api.php?action=get_previous_day_plan')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.tasks && data.tasks.length > 0) {
                showPlannedTasksModal(data.tasks);
            }
        })
        .catch(error => console.error('Error loading previous day plan:', error));
}

/**
 * Creates and displays the modal for the "Tomorrow's Plan" tasks.
 */
function showPlannedTasksModal(tasks) {
    const modalHtml = `
        <div class="modal fade" id="plannedTasksModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title"><i class="bi bi-calendar-check"></i> برنامه شما از روز قبل</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>این موارد را از برنامه دیروز خود ثبت کرده‌اید. موارد مورد نظر را برای افزودن به گزارش امروز انتخاب کنید:</p>
                        <ul class="list-group" id="plannedTasksList">
                            ${tasks.map((task, index) => `
                                <li class="list-group-item">
                                    <input class="form-check-input me-2" type="checkbox" value="" id="planned_task_${index}">
                                    <label class="form-check-label" for="planned_task_${index}">
                                        ${task}
                                    </label>
                                </li>
                            `).join('')}
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">نادیده گرفتن</button>
                        <button type="button" class="btn btn-primary" onclick="addSelectedPlannedTasks()">افزودن به گزارش</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Ensure no old modal exists before adding a new one
    const existingModal = document.getElementById('plannedTasksModal');
    if (existingModal) existingModal.remove();

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modalEl = new bootstrap.Modal(document.getElementById('plannedTasksModal'));
    modalEl.show();
}
function addSelectedPlannedTasks() {
    const checkboxes = document.querySelectorAll('#plannedTasksList input[type="checkbox"]:checked');
    
    checkboxes.forEach(checkbox => {
        const taskDescription = checkbox.nextElementSibling.textContent.trim();
        
        // Call the existing function to create a new activity block
        addActivity(); 
        
        // Get the description input of the newly created activity and fill it
        const newActivityDescriptionInput = document.querySelector(`#activity-${activityCount} input[name*="[description]"]`);
        if (newActivityDescriptionInput) {
            newActivityDescriptionInput.value = taskDescription;
        }
    });

    // Hide the modal after adding the tasks
    const modalInstance = bootstrap.Modal.getInstance(document.getElementById('plannedTasksModal'));
    if (modalInstance) {
        modalInstance.hide();
    }
}
                                        function toggleCustomLocation() {
                                            const checkbox = document.getElementById('customLocationCheck');
                                            const customInput = document.getElementById('customLocation');
                                            const locationSelect = document.getElementById('locationSelect');

                                            if (checkbox.checked) {
                                                customInput.style.display = 'block';
                                                customInput.required = true;
                                                locationSelect.required = false;
                                                locationSelect.disabled = true;
                                            } else {
                                                customInput.style.display = 'none';
                                                customInput.required = false;
                                                locationSelect.required = true;
                                                locationSelect.disabled = false;
                                            }
                                        }

                                        // Set default project on load
                                        document.addEventListener('DOMContentLoaded', function() {
    const projectSelect = document.getElementById('projectName');
    const buildingSelect = document.getElementById('buildingName');
    const partSelect = document.getElementById('buildingPart');
    const customPartCheck = document.getElementById('customPartCheck');
    const customPartInput = document.getElementById('customBuildingPart');

    // Only proceed if elements exist
    if (buildingSelect && partSelect) {
        // Set initial disabled states
        buildingSelect.disabled = true;
        partSelect.disabled = true;
    }

    // Add event listeners only if elements exist
    if (projectSelect) {
        // Add default option
        projectSelect.options[0] = new Option('انتخاب پروژه...', '');
        
        // Add project options
        for (const projectName in projectData) {
            projectSelect.options[projectSelect.options.length] = new Option(projectName, projectName);
        }
        
        // Set default value
        const defaultProject = "پروژه دانشگاه خاتم پردیس";
        projectSelect.value = defaultProject;

        // Event listener for project change
        projectSelect.addEventListener('change', function() {
            if (buildingSelect && partSelect) {
                const selectedProject = this.value;
                buildingSelect.innerHTML = '<option value="">انتخاب ساختمان...</option>';
                partSelect.innerHTML = '<option value="">ابتدا ساختمان را انتخاب کنید...</option>';
                buildingSelect.disabled = !selectedProject;
                partSelect.disabled = true;

                if (selectedProject && projectData[selectedProject]) {
                    const buildings = projectData[selectedProject];
                    for (const buildingName in buildings) {
                        buildingSelect.options[buildingSelect.options.length] = new Option(buildingName, buildingName);
                    }
                }
            }
        });
    }

    if (buildingSelect) {
        buildingSelect.addEventListener('change', function() {
            // Building change handler code...
        });
    }

    if (customPartCheck) {
        customPartCheck.addEventListener('change', toggleCustomPart);
    }
});
                                    </script>
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label">وضعیت آب و هوا</label>
                                            <select class="form-select" name="weather" id="dailyReportWeatherSelect">
                                                <option value="clear">آفتابی</option>
                                                <option value="cloudy">ابری</option>
                                                <option value="rainy">بارانی</option>
                                                <option value="hot">گرم</option>
                                                <option value="cold">سرد</option>
                                                <option value="other">سایر</option>
                                            </select>
                                        </div>
                                       <div class="col-md-4">
                                            <label class="form-label">ساعات کاری (محاسبه خودکار)</label>
                                            <input type="number" class="form-control" id="work_hours" name="work_hours" value="8" step="0.1" readonly>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">حادثه ایمنی</label>
                                            <select class="form-select" name="safety_incident">
                                                <option value="no">خیر</option>
                                                <option value="yes">بله</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Time Tracking -->
                                    <div class="card mb-3 bg-light">
    <div class="card-body">
        <h6 class="card-title"><i class="bi bi-clock"></i> ثبت زمان ورود و خروج</h6>
        <div class="row">
            <div class="col-md-5">
                <label class="form-label">زمان ورود</label>
                <input type="time" class="form-control" id="arrival_time" name="arrival_time" value="08:00" onchange="calculateWorkHours()">
            </div>
            <div class="col-md-5">
                <label class="form-label">زمان خروج</label>
                <input type="time" class="form-control" id="departure_time" name="departure_time" value="17:00" onchange="calculateWorkHours()">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="had_lunch_break" name="had_lunch_break" onchange="calculateWorkHours()">
                    <label class="form-check-label" for="had_lunch_break">
                        استراحت ناهار
                    </label>
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
                                                <!-- Activities will be added here -->
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Issues and Notes -->
                                   <div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="card-title mb-0"><i class="bi bi-exclamation-triangle-fill text-danger"></i> ثبت مشکلات و موانع</h6>
            <button type="button" class="btn btn-sm btn-danger btn-custom" onclick="addIssue()">
                <i class="bi bi-plus"></i> افزودن مشکل
            </button>
        </div>
        <div id="issuesContainer">
            <!-- New issues will be added here by JavaScript -->
        </div>
    </div>
</div>
                                    <div class="mb-3">
                                        <label class="form-label"><i class="bi bi-calendar-check"></i> برنامه فردا</label>
                                        <textarea class="form-control" name="next_day_plan" rows="3" placeholder="برنامه کاری فردا را شرح دهید..."></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label"><i class="bi bi-chat-left-text"></i> یادداشت‌های عمومی</label>
                                        <textarea class="form-control" name="general_notes" rows="2" placeholder="سایر یادداشت‌ها..."></textarea>
                                    </div>

                                    <!-- File Upload -->
                                    <div class="mb-4">
                                        <label class="form-label"><i class="bi bi-paperclip"></i> پیوست فایل (تصاویر، اسناد)</label>
                                        <input type="file" class="form-control" name="attachments[]" multiple accept="image/*,.pdf,.doc,.docx">
                                        <small class="text-muted">حداکثر 5 فایل - هر فایل حداکثر 5MB</small>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary btn-lg btn-custom">
                                            <i class="bi bi-send"></i> ثبت گزارش
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- List Reports Tab -->
            <div class="tab-pane fade" id="list" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-list-ul"></i> گزارش‌های ثبت شده</span>
                            <div>
                                <input type="text" class="form-control form-control-sm d-inline-block" id="filterDate" data-jdp readonly placeholder="انتخاب تاریخ" style="width: auto;">
                                <select class="form-select form-select-sm d-inline-block ms-2" id="filterRole" style="width: auto;">
                                    <option value="">همه نقش‌ها</option>
                                    <?php foreach ($engineering_roles as $key => $value): ?>
                                        <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="reportsListContainer">
                            <div class="text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">در حال بارگذاری...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dashboard Tab -->
            <div class="tab-pane fade" id="dashboard" role="tabpanel">
                <div class="row mb-4">
                    <!-- Corrected Stat Cards with correct IDs -->
                    <div class="col-md-3">
                        <div class="card stat-card primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-muted">کل گزارش‌ها</h6>
                                        <h3 class="mb-0" id="totalReports">-</h3>
                                    </div>
                                    <i class="bi bi-file-text text-primary" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-muted">گزارش‌های من</h6>
                                        <h3 class="mb-0" id="myReports">-</h3>
                                    </div>
                                    <i class="bi bi-person-check text-success" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-muted">میانگین پیشرفت</h6>
                                        <h3 class="mb-0" id="avgProgress">-</h3>
                                    </div>
                                    <i class="bi bi-graph-up text-warning" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card danger">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-muted">مشکلات فعال</h6>
                                        <h3 class="mb-0" id="activeIssues">-</h3>
                                    </div>
                                    <i class="bi bi-exclamation-triangle text-danger" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">نمودار پیشرفت هفتگی</div>
                            <div class="card-body">
                                <canvas id="weeklyProgressChart" height="80"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">توزیع گزارش‌ها</div>
                            <div class="card-body">
                                <canvas id="roleDistributionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analytics Tab (Admin only) -->
            <?php if (in_array($user_role, ['admin', 'superuser'])): ?>
    <!-- General Analytics Tab -->
    <div class="tab-pane fade" id="analytics" role="tabpanel">
        <div class="card">
            <div class="card-body p-0">
                <iframe src="analytics.php" 
                        style="width: 100%; height: 1200px; border: none; display: block;" 
                        title="تحلیل‌های عمومی"
                        id="analyticsFrame"></iframe>
            </div>
        </div>
    </div>
    
    <!-- Building Analytics Tab -->
    <div class="tab-pane fade" id="building-analytics" role="tabpanel">
        <div class="card">
            <div class="card-body p-0">
                <iframe src="analytics_buildings.php" 
                        style="width: 100%; height: 1200px; border: none; display: block;" 
                        title="تحلیل ساختمان‌ها"
                        id="buildingAnalyticsFrame"></iframe>
            </div>
        </div>
    </div>
<?php endif; ?>


<div class="tab-pane fade" id="tasks" role="tabpanel">
    
    <!-- Task Management Sub-tabs -->
    <ul class="nav nav-pills mb-3" id="taskSubTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="unfinished-subtab" data-bs-toggle="pill" 
                    data-bs-target="#unfinished-tasks" type="button">
                <i class="bi bi-clock-history"></i> کارهای ناتمام
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="assigned-subtab" data-bs-toggle="pill" 
                    data-bs-target="#assigned-tasks" type="button">
                <i class="bi bi-person-check"></i> کارهای تخصیص یافته
            </button>
        </li>
        <?php if (in_array($user_role, ['admin', 'superuser', 'coa'])): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="assign-new-subtab" data-bs-toggle="pill" 
                    data-bs-target="#assign-new-task" type="button">
                <i class="bi bi-plus-circle"></i> تخصیص کار جدید
            </button>
        </li>
        <?php endif; ?>
    </ul>

    <div class="tab-content" id="taskSubTabContent">
        
        <!-- Unfinished Tasks Tab -->
        <div class="tab-pane fade show active" id="unfinished-tasks" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-clock-history"></i> کارهای ناتمام (قابل انتقال به گزارش بعدی)</span>
                        <div>
                            <select class="form-select form-select-sm d-inline-block" id="unfinishedStatusFilter" style="width: auto;">
                                <option value="">همه وضعیت‌ها</option>
                                <option value="in_progress">در حال انجام</option>
                                <option value="blocked">مسدود</option>
                                <option value="delayed">تاخیر دارد</option>
                                <option value="not_started">شروع نشده</option>
                            </select>
                            <select class="form-select form-select-sm d-inline-block ms-2" id="unfinishedPriorityFilter" style="width: auto;">
                                <option value="">همه اولویت‌ها</option>
                                <option value="urgent">فوری</option>
                                <option value="high">زیاد</option>
                                <option value="medium">متوسط</option>
                                <option value="low">کم</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div id="unfinishedTasksContainer">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">در حال بارگذاری...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assigned Tasks Tab -->
        <div class="tab-pane fade" id="assigned-tasks" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-person-check"></i> کارهای تخصیص یافته به من</span>
                        <select class="form-select form-select-sm" id="assignedStatusFilter" style="width: auto;">
                            <option value="">همه</option>
                            <option value="assigned">تخصیص داده شده</option>
                            <option value="in_progress">در حال انجام</option>
                            <option value="completed">تکمیل شده</option>
                            <option value="cancelled">لغو شده</option>
                        </select>
                    </div>
                </div>
                <div class="card-body">
                    <div id="assignedTasksContainer">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">در حال بارگذاری...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assign New Task Tab (Admin Only) -->
        <?php if (in_array($user_role, ['admin', 'superuser', 'coa'])): ?>
        <div class="tab-pane fade" id="assign-new-task" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-plus-circle"></i> تخصیص کار جدید به مهندس
                </div>
                <div class="card-body">
                    <form id="assignTaskForm">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">انتخاب مهندس <span class="text-danger">*</span></label>
                                <select class="form-select" name="assigned_to_user_id" id="assignToUser" required>
                                    <option value="">انتخاب کنید...</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">نام پروژه <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="project_name" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">نام ساختمان</label>
                                <input type="text" class="form-control" name="building_name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">بخش ساختمان</label>
                                <input type="text" class="form-control" name="building_part">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">شرح کار <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="task_description" rows="3" required></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">نوع فعالیت</label>
                                <input type="text" class="form-control" name="activity_type" placeholder="مثال: طراحی، بازدید">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">اولویت</label>
                                <select class="form-select" name="priority">
                                    <option value="medium">متوسط</option>
                                    <option value="low">کم</option>
                                    <option value="high">زیاد</option>
                                    <option value="urgent">فوری</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ساعات تخمینی</label>
                                <input type="number" class="form-control" name="estimated_hours" step="0.5" min="0">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">تاریخ تخمینی تکمیل</label>
                                <input type="text" class="form-control" name="estimated_completion_date" 
                                       data-jdp data-jdp-only-date readonly placeholder="انتخاب تاریخ">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">مهلت انجام</label>
                                <input type="text" class="form-control" name="due_date" 
                                       data-jdp data-jdp-only-date readonly placeholder="انتخاب تاریخ">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">یادداشت‌ها</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send"></i> تخصیص کار
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
    </div>
</div>
<div class="tab-pane fade" id="issues-management" role="tabpanel">
    <div class="card">
        <div class="card-header">
            <span><i class="bi bi-shield-exclamation"></i> لیست کل مشکلات</span>
        </div>
        <div class="card-body">
            <div id="issuesDashboardContainer">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">در حال بارگذاری...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="tab-pane fade" id="calendar-view" role="tabpanel">
    <div class="card">

        <div class="card-body">
            <!-- FullCalendar will be rendered inside this div -->
            <div id="fullcalendar-container"></div>
        </div>
    </div>
</div>






    <!-- Engineer Statistics Table -->
    <!-- Engineer Statistics Table -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-people"></i> آمار مهندسین (30 روز گذشته)
                    <?php if (!in_array($user_role, ['admin', 'superuser', 'coa'])): ?>
                        <small class="text-light ms-2">(فقط گزارش‌های شما)</small>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>نام مهندس</th>
                                    <th>تعداد گزارش‌ها</th>
                                    <th>تعداد روزها</th>
                                    <th>میانگین پیشرفت</th>
                                    <th>آخرین گزارش</th>
                                </tr>
                            </thead>
                            <tbody id="engineerStatsTable">
                                <tr>
                                    <td colspan="5" class="text-center">
                                        <div class="spinner-border spinner-border-sm" role="status">
                                            <span class="visually-hidden">در حال بارگذاری...</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>

<script src="/pardis/assets/fullcalendar-6.1.17/package/index.global.min.js"></script>
    <script>
        // --- GLOBAL VARIABLES ---
        let activityCount = 0;
        let weeklyChart = null;
        let roleChart = null;
let currentCalendarMonth;


        // --- SINGLE DOMCONTENTLOADED LISTENER ---
        document.addEventListener('DOMContentLoaded', function() {

            // Initialize Jalali DatePicker
            jalaliDatepicker.startWatch({
                persianDigits: true,
                autoShow: true,
                autoHide: true,
                hideAfterChange: true,
                date: true,
                time: false,
                zIndex: 2000
            });
  if (window.weatherIntegration) {

        // 2. Save a copy of its original display function so we don't break the header widget.
        const originalDisplayWeather = window.weatherIntegration.displayWeather.bind(window.weatherIntegration);

        // 3. Replace the display function with a new, extended version.
        window.weatherIntegration.displayWeather = function(weather) {
            
            // 3a. IMPORTANT: Call the original function first to ensure the header widget still updates.
            originalDisplayWeather(weather);

            // 3b. Now, add our new functionality for the daily reports page.
            const dailySelect = document.getElementById('dailyReportWeatherSelect');
            if (dailySelect && weather.simple_category) {
                // Find the dropdown on this page and set its value.
                dailySelect.value = weather.simple_category;
            }
        };

        // 4. The weather might have loaded before this script ran.
        //    Trigger one more fetch to ensure our new, extended function gets called with the weather data.
        window.weatherIntegration.fetchWeather();
        
    } else {
        console.warn("Header weather widget (window.weatherIntegration) not found. Weather will not be auto-selected.");
    }
            // Add the first activity row on the submit form
            if (document.getElementById('activitiesContainer')) {
                addActivity();
            }

calculateWorkHours();
            loadReportsList();
loadTaskCounts();
            // Add event listeners for filters
            document.getElementById('filterDate')?.addEventListener('change', loadReportsList);
            document.getElementById('filterRole')?.addEventListener('change', loadReportsList);

            // Add listener to reload dashboard data when its tab is shown
            const dashboardTab = document.getElementById('dashboard-tab');
            if (dashboardTab) {
                dashboardTab.addEventListener('shown.bs.tab', function(event) {
                    console.log('Dashboard tab shown');
                    loadDashboardData();
                });
            }
        });
function loadTaskCounts() {
    // Load unfinished tasks count
    fetch('daily_report_api.php?action=unfinished_tasks')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.overdue_count > 0) {
                const badge = document.getElementById('overdueTasksBadge');
                if (badge) {
                    badge.textContent = data.overdue_count;
                    badge.style.display = 'inline-block';
                }
            }
        });
    
    // Load assigned tasks count
    fetch('daily_report_api.php?action=assigned_tasks')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.pending_count > 0) {
                const badge = document.getElementById('assignedTasksBadge');
                if (badge) {
                    badge.textContent = data.pending_count;
                    badge.style.display = 'inline-block';
                }
            }
        });
}
        // --- DATA LOADING FUNCTIONS ---

        function loadDashboardData() {
            console.log('Loading dashboard data...'); // DEBUG
            fetch('daily_report_api.php?action=dashboard')
                .then(response => {
                    console.log('Response status:', response.status); // DEBUG
                    return response.json();
                })
                .then(data => {
                    console.log('Data parsed:', data); // DEBUG
                    updateDashboard(data);
                })
                .catch(error => {
                    console.error('Error loading dashboard:', error);
                    alert('خطا در بارگذاری داشبورد');
                });
        }

        function loadReportsList() {
            const date = document.getElementById('filterDate')?.value || '';
            const role = document.getElementById('filterRole')?.value || '';

            fetch(`daily_report_api.php?action=list&date=${date}&role=${role}`)
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('reportsListContainer');
                    if (data.reports && data.reports.length > 0) {
                        let html = '<div class="table-responsive"><table class="table table-hover"><thead><tr><th>تاریخ</th><th>مهندس</th><th>نقش</th><th>فعالیت‌ها</th><th>پیشرفت</th><th>عملیات</th></tr></thead><tbody>';
                        data.reports.forEach(report => {
                            const viewBtn = `<a href="daily_report_view.php?id=${report.id}" class="btn btn-sm btn-info" title="مشاهده"><i class="bi bi-eye"></i></a>`;
                            const editBtn = report.can_edit ? `<a href="daily_report_edit.php?id=${report.id}" class="btn btn-sm btn-warning ms-1" title="ویرایش"><i class="bi bi-pencil"></i></a>` : '';
                            const deleteBtn = report.can_delete ? `<button onclick="deleteReport(${report.id})" class="btn btn-sm btn-danger ms-1" title="حذف"><i class="bi bi-trash"></i></button>` : '';

                            html += `<tr>
                        <td>${report.date_fa || report.report_date}</td>
                        <td>${report.engineer_name}</td>
                        <td><span class="badge bg-primary">${report.role_fa}</span></td>
                        <td>${report.activities_count || 0}</td>
                        <td>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar" role="progressbar" style="width: ${report.avg_progress || 0}%">
                                    ${Math.round(report.avg_progress || 0)}%
                                </div>
                            </div>
                        </td>
                        <td>${viewBtn}${editBtn}${deleteBtn}</td>
                    </tr>`;
                        });
                        html += '</tbody></table></div>';
                        container.innerHTML = html;
                    } else {
                        container.innerHTML = '<div class="alert alert-info">گزارشی یافت نشد</div>';
                    }
                })
                .catch(error => {
                    console.error('Error loading reports:', error);
                    document.getElementById('reportsListContainer').innerHTML = '<div class="alert alert-danger">خطا در بارگذاری گزارش‌ها</div>';
                });
        }

        function deleteReport(reportId) {
            if (!confirm('آیا از حذف این گزارش اطمینان دارید؟')) {
                return;
            }

            fetch('daily_report_delete.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `report_id=${reportId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('گزارش با موفقیت حذف شد');
                        loadReportsList();
                        loadDashboardData(); // Refresh dashboard stats
                    } else {
                        alert('خطا در حذف گزارش: ' + (data.message || 'خطای ناشناخته'));
                    }
                })
                .catch(error => {
                    console.error('Error deleting report:', error);
                    alert('خطا در حذف گزارش');
                });
        }
        // --- NEW DATA DISPLAY & CHARTING FUNCTION ---

        function updateDashboard(data) {
            console.log('Dashboard data received:', data); // DEBUG

            // Update Stat Cards
            document.getElementById('totalReports').textContent = data.total_reports || 0;
            document.getElementById('myReports').textContent = data.my_reports || 0;
            document.getElementById('avgProgress').textContent = (data.avg_progress || 0) + '%';
            document.getElementById('activeIssues').textContent = data.active_issues || 0;

            console.log('Stat cards updated'); // DEBUG

            // Update Weekly Chart
            const weeklyCtx = document.getElementById('weeklyProgressChart');
            if (weeklyCtx) {
                if (window.weeklyChart) window.weeklyChart.destroy();
                window.weeklyChart = new Chart(weeklyCtx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: data.weekly_chart.labels || [],
                        datasets: [{
                            label: 'میانگین پیشرفت (%)',
                            data: data.weekly_chart.data || [],
                            borderColor: '#0d6efd',
                            tension: 0.1,
                            fill: true,
                            backgroundColor: 'rgba(13, 110, 253, 0.1)'
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100
                            }
                        }
                    }
                });
                console.log('Weekly chart updated'); // DEBUG
            }

            // Update Role Chart
            const roleCtx = document.getElementById('roleDistributionChart');
            if (roleCtx) {
                if (window.roleChart) window.roleChart.destroy();
                window.roleChart = new Chart(roleCtx.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: data.role_chart.labels || [],
                        datasets: [{
                            data: data.role_chart.data || [],
                            backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b']
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
                console.log('Role chart updated'); // DEBUG
            }

            // Update Engineer Statistics Table
            const engineerTable = document.getElementById('engineerStatsTable');
            if (engineerTable) {
                if (data.engineer_stats && data.engineer_stats.length > 0) {
                    let tableHtml = '';
                    data.engineer_stats.forEach(stat => {
                        tableHtml += `
                    <tr>
                        <td><strong>${stat.engineer_name}</strong></td>
                        <td><span class="badge bg-primary">${stat.report_count}</span></td>
                        <td><span class="badge bg-info">${stat.days_count}</span></td>
                        <td>
                            <div class="progress" style="height: 20px; min-width: 80px;">
                                <div class="progress-bar" role="progressbar" style="width: ${stat.avg_progress}%">
                                    ${stat.avg_progress}%
                                </div>
                            </div>
                        </td>
                        <td>${stat.last_report_date_fa || stat.last_report_date}</td>
                    </tr>
                `;
                    });
                    engineerTable.innerHTML = tableHtml;
                    console.log('Engineer table updated with', data.engineer_stats.length, 'rows'); // DEBUG
                } else {
                    engineerTable.innerHTML = '<tr><td colspan="5" class="text-center">داده‌ای یافت نشد</td></tr>';
                    console.log('No engineer stats found'); // DEBUG
                }
            }
        }
        // --- ACTIVITY MANAGEMENT FUNCTIONS ---
function getDefaultActivityType(role) {
    const roleDefaults = {
        'field_engineer': 'اجرایی',
        'designer': 'مهندسی',
        'surveyor': 'نقشه برداری',
        'control_engineer': 'گزارش/زمانبندی',
        'drawing_specialist': 'مهندسی'
    };
    return roleDefaults[role] || '';
}
  function addActivity() {
        activityCount++;
        const container = document.getElementById('activitiesContainer');
        if (!container) return;
        const roleSelect = document.getElementById('roleSelect');
    const defaultType = getDefaultActivityType(roleSelect.value);
        const activityHtml = `
            <div class="activity-item border rounded p-3 mb-3 bg-light" id="activity-${activityCount}">
                <div class="row mb-2">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">پروژه</label>
                        <select class="form-select form-select-sm activity-project" name="activities[${activityCount}][project_name]" data-id="${activityCount}" required>
                            ${Object.keys(projectData).map(p => `<option value="${p}">${p}</option>`).join('')}
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">ساختمان</label>
                        <select class="form-select form-select-sm activity-building" name="activities[${activityCount}][building_name]" data-id="${activityCount}" required></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">بخش ساختمان</label>
                        <!-- The standard dropdown -->
                        <select class="form-select form-select-sm activity-part" name="activities[${activityCount}][building_part]" data-id="${activityCount}" required></select>
                        <!-- The hidden text input for custom parts -->
                        <input type="text" class="form-control form-control-sm mt-1 activity-custom-part" name="activities[${activityCount}][custom_building_part]" data-id="${activityCount}" placeholder="نام بخش دلخواه را وارد کنید" style="display: none;">
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-12">
                         <div class="form-check">
                            <input class="form-check-input" type="checkbox" onchange="toggleCustomPart(this, ${activityCount})">
                            <label class="form-check-label small">
                                بخش دلخواه (در لیست موجود نیست)
                            </label>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-8 mb-2">
                        <label class="form-label">شرح فعالیت</label>
                        <input type="text" class="form-control" name="activities[${activityCount}][description]" required>
                    </div>
                   <div class="col-md-4 mb-2">
                    <label class="form-label">نوع فعالیت</label>
                    <input type="text" class="form-control" name="activities[${activityCount}][type]" value="${defaultType}">
                </div>
                </div>
                <div class="row">
                    <div class="col-md-3"><label class="form-label">پیشرفت</label><input type="number" class="form-control" name="activities[${activityCount}][progress]" value="0" min="0" max="100"></div>
                    <div class="col-md-3"><label class="form-label">وضعیت</label><select class="form-select" name="activities[${activityCount}][status]"><option value="in_progress">در حال انجام</option><option value="completed">تکمیل</option><option value="blocked">مسدود</option></select></div>
                    <div class="col-md-3"><label class="form-label">ساعات</label><input type="number" class="form-control" name="activities[${activityCount}][hours]" step="0.5" min="0"></div>
                    <div class="col-md-3 d-flex align-items-end"><button type="button" class="btn btn-sm btn-danger w-100" onclick="removeActivity(${activityCount})"><i class="bi bi-trash"></i> حذف</button></div>
                </div>
            </div>`;
        container.insertAdjacentHTML('beforeend', activityHtml);
        
        // Initialize the dropdowns for the new activity
        setupCascadingDropdowns(activityCount);
    }
    document.getElementById('roleSelect')?.addEventListener('change', function() {
    const defaultType = getDefaultActivityType(this.value);
    document.querySelectorAll('input[name$="[type]"]').forEach(input => {
        if (!input.value) { // Only update if empty
            input.value = defaultType;
        }
    });
});
    function toggleCustomPart(checkbox, id) {
                                                    const customInput = document.querySelector(`.activity-custom-part[data-id="${id}"]`);
                                                    const partSelect = document.querySelector(`.activity-part[data-id="${id}"]`);
                                                    
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
    function setupCascadingDropdowns(id) {
        const defaultProject = "پروژه دانشگاه خاتم پردیس";
        const projectSelect = document.querySelector(`.activity-project[data-id="${id}"]`);
        const buildingSelect = document.querySelector(`.activity-building[data-id="${id}"]`);
        const partSelect = document.querySelector(`.activity-part[data-id="${id}"]`);

        // Set default project and trigger building population
        projectSelect.value = defaultProject;
        populateBuildings();

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

        function removeActivity(id) {
            document.getElementById(`activity-${id}`).remove();
        }

        function loadPendingTasks() {
    fetch('daily_report_api.php?action=pending_tasks')
        .then(response => response.json())
        .then(data => {
            if (data.tasks && data.tasks.length > 0) {
                showPendingTasksModal(data.tasks);
            }
        })
        .catch(error => console.error('Error loading pending tasks:', error));
}

// This function builds and shows the modal HTML
function showPendingTasksModal(tasks) {
    const modal = `
        <div class="modal fade" id="pendingTasksModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> کارهای ناتمام (${tasks.length})</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="alert alert-info">این کارها از روزهای قبل ناتمام مانده‌اند. می‌توانید آنها را به گزارش امروز اضافه کنید:</p>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>انتخاب</th><th>تاریخ</th><th>شرح فعالیت</th><th>پیشرفت</th><th>وضعیت</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${tasks.map(task => `
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="form-check-input pending-task-checkbox" 
                                                    data-task='${JSON.stringify(task)}'>
                                            </td>
                                            <td>${task.report_date_fa}</td>
                                            
                                            <!-- CORRECTED PART HERE -->
                                            <td>${task.task_description}</td> 
                                            
                                            <td>
                                                <div class="progress" style="width:100px; height:20px;">
                                                    <div class="progress-bar" style="width:${task.progress_percentage}%">${task.progress_percentage}%</div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge ${getStatusBadge(task.completion_status)}">${getStatusText(task.completion_status)}</span>
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                        <button type="button" class="btn btn-primary" onclick="addSelectedPendingTasks()">افزودن موارد انتخاب شده</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Clean up any old modal before showing a new one
    const existingModal = document.getElementById('pendingTasksModal');
    if(existingModal) {
        existingModal.remove();
    }

    document.body.insertAdjacentHTML('beforeend', modal);
    const modalEl = new bootstrap.Modal(document.getElementById('pendingTasksModal'));
    modalEl.show();
}


 function addSelectedPendingTasks() {
    const checkboxes = document.querySelectorAll('.pending-task-checkbox:checked');
    checkboxes.forEach(checkbox => {
        const task = JSON.parse(checkbox.dataset.task);
        addCarryoverActivity(task);
    });
    // Hide the modal after adding the tasks
    const modalInstance = bootstrap.Modal.getInstance(document.getElementById('pendingTasksModal'));
    if (modalInstance) {
        modalInstance.hide();
    }
}

        function addCarryoverActivity(task) {
    activityCount++;
    const container = document.getElementById('activitiesContainer');
    const activityHtml = `
        <div class="activity-item border rounded p-3 mb-3 bg-light position-relative" id="activity-${activityCount}">
            <span class="badge bg-warning position-absolute top-0 end-0 m-2"><i class="bi bi-arrow-right-circle"></i> ادامه از ${task.report_date_fa}</span>
            <input type="hidden" name="activities[${activityCount}][is_carryover]" value="1">
            <input type="hidden" name="activities[${activityCount}][parent_activity_id]" value="${task.id}">
            
            <div class="row">
                <div class="col-md-6 mb-2">
                    <label class="form-label">شرح فعالیت</label>
                    
                    <!-- CORRECTED PART HERE -->
                    <input type="text" class="form-control" name="activities[${activityCount}][description]" value="${task.task_description}" required>
                </div>
                <div class="col-md-6 mb-2">
                    <label class="form-label">نوع فعالیت</label>

                    <!-- CORRECTED PART HERE -->
                    <input type="text" class="form-control" name="activities[${activityCount}][type]" value="${task.task_type || ''}">
                </div>
            </div>
            <div class="row">
                <div class="col-md-3 mb-2">
                    <label class="form-label">درصد پیشرفت</label>
                    <input type="number" class="form-control" name="activities[${activityCount}][progress]" min="${task.progress_percentage}" max="100" value="${task.progress_percentage}">
                    <small class="text-muted">قبلی: ${task.progress_percentage}%</small>
                </div>
                <!-- ... other fields ... -->
            </div>
            <div class="row"><div class="col-12"><button type="button" class="btn btn-sm btn-danger" onclick="removeActivity(${activityCount})"><i class="bi bi-trash"></i> حذف</button></div></div>
        </div>`;
    container.insertAdjacentHTML('beforeend', activityHtml);
    jalaliDatepicker.startWatch(); // Re-initialize datepicker for any new fields
}

        function getStatusBadge(status) {
    const badges = {
        'in_progress': 'bg-primary',
        'completed': 'bg-success',
        'blocked': 'bg-danger',
        'delayed': 'bg-warning',
        'not_started': 'bg-secondary'
    };
    return badges[status] || 'bg-secondary';
}

function getStatusText(status) {
    const texts = {
        'in_progress': 'در حال انجام',
        'completed': 'تکمیل شده',
        'blocked': 'مسدود',
        'delayed': 'تاخیر دارد',
        'not_started': 'شروع نشده'
    };
    return texts[status] || status;
}

// Call this when submit tab is shown
document.getElementById('submit-tab')?.addEventListener('shown.bs.tab', function() {
    loadPendingTasks();
    loadPreviousDayPlan();
});
function loadUsersForAssignment() {
    fetch('get_users_list.php')
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('assignToUser');
            if (select && data.users) {
                data.users.forEach(user => {
                    const option = document.createElement('option');
                    option.value = user.id;
                    option.textContent = `${user.first_name} ${user.last_name} (${user.role})`;
                    select.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error loading users:', error));
}
function calculateWorkHours() {
    const arrivalInput = document.getElementById('arrival_time');
    const departureInput = document.getElementById('departure_time');
    const lunchCheck = document.getElementById('had_lunch_break');
    const hoursInput = document.getElementById('work_hours');

    if (!arrivalInput.value || !departureInput.value) {
        hoursInput.value = 0;
        return;
    }

    // Create date objects to calculate the difference
    const startTime = new Date('1970-01-01T' + arrivalInput.value + 'Z');
    const endTime = new Date('1970-01-01T' + departureInput.value + 'Z');

    // Calculate difference in milliseconds
    let diff = endTime.getTime() - startTime.getTime();

    // If the end time is on the next day (e.g., night shift)
    if (diff < 0) {
        diff += 24 * 60 * 60 * 1000; // Add 24 hours in milliseconds
    }
    
    // Convert milliseconds to hours
    let hours = diff / (1000 * 60 * 60);

    // Subtract lunch break if checked
    if (lunchCheck.checked) {
        hours -= 1;
    }

    // Ensure hours are not negative and format to 2 decimal places
    hoursInput.value = Math.max(0, hours).toFixed(2);
}

// Load unfinished tasks
function loadUnfinishedTasks() {
    const statusFilter = document.getElementById('unfinishedStatusFilter')?.value || '';
    const priorityFilter = document.getElementById('unfinishedPriorityFilter')?.value || '';
    
    fetch(`daily_report_api.php?action=unfinished_tasks&status=${statusFilter}&priority=${priorityFilter}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('unfinishedTasksContainer');
            
            if (!data.success) {
                container.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
                return;
            }
            
            if (!data.tasks || data.tasks.length === 0) {
                container.innerHTML = '<div class="alert alert-info">کار ناتمامی یافت نشد! 🎉</div>';
                return;
            }
            
            // Update badge
            const overdueCount = data.overdue_count || 0;
            const badge = document.getElementById('overdueTasksBadge');
            if (badge) {
                if (overdueCount > 0) {
                    badge.textContent = overdueCount;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            }
            
            let html = `
                <div class="alert alert-warning">
                    <i class="bi bi-info-circle"></i>
                    <strong>مجموع: ${data.total}</strong> کار ناتمام | 
                    <span class="text-danger"><strong>${data.overdue_count}</strong> دارای تاخیر</span> | 
                    <strong>${data.blocked_count}</strong> مسدود شده
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>انتخاب</th>
                                <th>تاریخ</th>
                                <th>پروژه/ساختمان</th>
                                <th>شرح کار</th>
                                <th>پیشرفت</th>
                                <th>وضعیت</th>
                                <th>اولویت</th>
                                <th>تاخیر</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>`;
            
            data.tasks.forEach(task => {
                const statusBadge = getStatusBadgeClass(task.completion_status);
                const priorityBadge = getPriorityBadgeClass(task.priority);
                const overdueClass = task.is_overdue ? 'table-danger' : '';
                
                html += `
                    <tr class="${overdueClass}">
                        <td>
                            <input type="checkbox" class="form-check-input carry-task-checkbox" 
                                   data-task-id="${task.id}" data-task='${JSON.stringify(task).replace(/'/g, "&apos;")}'>
                        </td>
                        <td><small>${task.report_date_fa}</small></td>
                        <td>
                            <div><strong>${task.project_name || '-'}</strong></div>
                            <small class="text-muted">${task.building_name || ''} ${task.building_part || ''}</small>
                        </td>
                        <td>
                            <div>${task.task_description}</div>
                            ${task.activity_type ? `<small class="text-muted">${task.activity_type}</small>` : ''}
                        </td>
                        <td>
                            <div class="progress" style="height: 20px; min-width: 80px;">
                                <div class="progress-bar ${task.progress_percentage < 30 ? 'bg-danger' : task.progress_percentage < 70 ? 'bg-warning' : 'bg-success'}" 
                                     style="width: ${task.progress_percentage}%">
                                    ${task.progress_percentage}%
                                </div>
                            </div>
                        </td>
                        <td><span class="badge ${statusBadge}">${getStatusText(task.completion_status)}</span></td>
                        <td><span class="badge ${priorityBadge}">${getPriorityText(task.priority)}</span></td>
                        <td>
                            ${task.is_overdue ? `<span class="badge bg-danger">${task.days_overdue} روز</span>` : 
                              task.estimated_completion_date_fa ? `<small>${task.estimated_completion_date_fa}</small>` : '-'}
                        </td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="viewTaskTimeline(${task.id})" title="تاریخچه">
                                <i class="bi bi-clock-history"></i>
                            </button>
                            ${task.blocked_reason ? `
                            <button class="btn btn-sm btn-warning" onclick="showBlockedReason('${task.blocked_reason.replace(/'/g, "&apos;")}')" title="دلیل مسدودی">
                                <i class="bi bi-exclamation-triangle"></i>
                            </button>` : ''}
                        </td>
                    </tr>`;
            });
            
            html += `
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <button class="btn btn-primary" onclick="carrySelectedTasks()">
                        <i class="bi bi-arrow-right-circle"></i> افزودن موارد انتخاب شده به گزارش جدید
                    </button>
                </div>`;
            
            container.innerHTML = html;
        })
        .catch(error => {
            console.error('Error loading unfinished tasks:', error);
            document.getElementById('unfinishedTasksContainer').innerHTML = 
                '<div class="alert alert-danger">خطا در بارگذاری کارهای ناتمام</div>';
        });
}

// Load assigned tasks
function loadAssignedTasks() {
    const statusFilter = document.getElementById('assignedStatusFilter')?.value || '';
    
    fetch(`daily_report_api.php?action=assigned_tasks&status=${statusFilter}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('assignedTasksContainer');
            
            if (!data.success) {
                container.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
                return;
            }
            
            if (!data.tasks || data.tasks.length === 0) {
                container.innerHTML = '<div class="alert alert-info">کار تخصیص یافته‌ای وجود ندارد</div>';
                return;
            }
            
            let html = `
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    <strong>مجموع: ${data.total}</strong> کار | 
                    <span class="text-warning"><strong>${data.pending_count}</strong> در انتظار شروع</span> | 
                    <span class="text-danger"><strong>${data.overdue_count}</strong> دارای تاخیر</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>تخصیص دهنده</th>
                                <th>پروژه/ساختمان</th>
                                <th>شرح کار</th>
                                <th>اولویت</th>
                                <th>مهلت</th>
                                <th>وضعیت</th>
                                <th>پیشرفت</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>`;
            
            data.tasks.forEach(task => {
                const priorityBadge = getPriorityBadgeClass(task.priority);
                const statusBadge = getAssignedStatusBadge(task.status);
                const overdueClass = task.is_overdue ? 'table-danger' : '';
                
                html += `
                    <tr class="${overdueClass}">
                        <td>
                            <div><strong>${task.assigned_by_name}</strong></div>
                            <small class="text-muted">${task.created_at_fa}</small>
                        </td>
                        <td>
                            <div><strong>${task.project_name}</strong></div>
                            <small class="text-muted">${task.building_name || ''} ${task.building_part || ''}</small>
                        </td>
                        <td>
                            <div>${task.task_description}</div>
                            ${task.activity_type ? `<small class="text-muted">${task.activity_type}</small>` : ''}
                            ${task.notes ? `<div class="mt-1"><small><i class="bi bi-info-circle"></i> ${task.notes}</small></div>` : ''}
                        </td>
                        <td><span class="badge ${priorityBadge}">${getPriorityText(task.priority)}</span></td>
                        <td>
                            ${task.due_date_fa || '-'}
                            ${task.is_overdue ? `<br><span class="badge bg-danger">${task.days_overdue} روز تاخیر</span>` : ''}
                        </td>
                        <td><span class="badge ${statusBadge}">${getAssignedStatusText(task.status)}</span></td>
                        <td>
                            ${task.has_started ? `
                                <div class="progress" style="height: 20px; min-width: 60px;">
                                    <div class="progress-bar" style="width: ${task.progress_percentage || 0}%">
                                        ${task.progress_percentage || 0}%
                                    </div>
                                </div>
                            ` : '<small class="text-muted">شروع نشده</small>'}
                        </td>
                        <td>
                            ${!task.has_started && task.status === 'assigned' ? `
                            <button class="btn btn-sm btn-success" onclick="startAssignedTask(${task.id})" title="شروع کار">
                                <i class="bi bi-play-circle"></i> شروع
                            </button>
                            ` : ''}
                            <button class="btn btn-sm btn-primary" onclick="updateTaskStatus(${task.id}, '${task.status}')" title="تغییر وضعیت">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </td>
                    </tr>`;
            });
            
            html += '</tbody></table></div>';
            container.innerHTML = html;
        })
        .catch(error => {
            console.error('Error loading assigned tasks:', error);
            document.getElementById('assignedTasksContainer').innerHTML = 
                '<div class="alert alert-danger">خطا در بارگذاری کارهای تخصیص یافته</div>';
        });
}

// Carry selected tasks to new report
function carrySelectedTasks() {
    const checkboxes = document.querySelectorAll('.carry-task-checkbox:checked');
    if (checkboxes.length === 0) {
        alert('لطفاً حداقل یک کار را انتخاب کنید');
        return;
    }
    
    // Switch to submit tab
    const submitTab = document.getElementById('submit-tab');
    submitTab.click();
    
    // Add tasks to activities
    setTimeout(() => {
        checkboxes.forEach(checkbox => {
            const task = JSON.parse(checkbox.dataset.task);
            addCarryoverActivity(task);
        });
        alert(`${checkboxes.length} کار به گزارش جدید اضافه شد`);
    }, 300);
}

// Helper function - already exists in your code but ensuring it's available
function addCarryoverActivity(task) {
    activityCount++;
    const container = document.getElementById('activitiesContainer');
    if (!container) return;
    
    const activityHtml = `
        <div class="activity-item border rounded p-3 mb-3 bg-light position-relative" id="activity-${activityCount}">
            <span class="badge bg-warning position-absolute top-0 end-0 m-2">
                <i class="bi bi-arrow-right-circle"></i> ادامه از ${task.report_date_fa}
            </span>
            <input type="hidden" name="activities[${activityCount}][is_carryover]" value="1">
            <input type="hidden" name="activities[${activityCount}][parent_activity_id]" value="${task.id}">
            
            <div class="row">
                <div class="col-md-6 mb-2">
                    <label class="form-label">شرح فعالیت</label>
                    <input type="text" class="form-control" name="activities[${activityCount}][description]" 
                        value="${task.task_description}" required>
                </div>
                <div class="col-md-6 mb-2">
                    <label class="form-label">نوع فعالیت</label>
                    <input type="text" class="form-control" name="activities[${activityCount}][type]"
                        value="${task.activity_type || ''}">
                </div>
            </div>
            <div class="row">
                <div class="col-md-3 mb-2">
                    <label class="form-label">درصد پیشرفت</label>
                    <input type="number" class="form-control" name="activities[${activityCount}][progress]" 
                        min="${task.progress_percentage}" max="100" value="${task.progress_percentage}">
                    <small class="text-muted">قبلی: ${task.progress_percentage}%</small>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">وضعیت</label>
                    <select class="form-select" name="activities[${activityCount}][status]">
                        <option value="in_progress" ${task.completion_status === 'in_progress' ? 'selected' : ''}>در حال انجام</option>
                        <option value="completed">تکمیل شده</option>
                        <option value="blocked" ${task.completion_status === 'blocked' ? 'selected' : ''}>مسدود شده</option>
                        <option value="delayed" ${task.completion_status === 'delayed' ? 'selected' : ''}>تاخیر دارد</option>
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">ساعات امروز</label>
                    <input type="number" class="form-control" name="activities[${activityCount}][hours]" 
                        step="0.5" min="0" placeholder="ساعت کار امروز">
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">ساعات تخمینی باقیمانده</label>
                    <input type="number" class="form-control" name="activities[${activityCount}][estimated_hours]" 
                        step="0.5" min="0" value="${task.remaining_hours || ''}">
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <button type="button" class="btn btn-sm btn-danger" onclick="removeActivity(${activityCount})">
                        <i class="bi bi-trash"></i> حذف
                    </button>
                </div>
            </div>
        </div>`;
    container.insertAdjacentHTML('beforeend', activityHtml);
    jalaliDatepicker.startWatch();
}

// View task timeline
function viewTaskTimeline(activityId) {
    fetch(`daily_report_api.php?action=task_timeline&activity_id=${activityId}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert(data.message);
                return;
            }
            showTimelineModal(data);
        })
        .catch(error => {
            console.error('Error loading timeline:', error);
            alert('خطا در بارگذاری تاریخچه کار');
        });
}
function getStatusBadgeClass(status) {
    const badges = {
        'in_progress': 'bg-primary',
        'completed': 'bg-success',
        'blocked': 'bg-danger',
        'delayed': 'bg-warning',
        'not_started': 'bg-secondary'
    };
    return badges[status] || 'bg-secondary';
}
function getPriorityBadgeClass(priority) {
    const badges = {
        'urgent': 'bg-danger',
        'high': 'bg-warning',
        'medium': 'bg-info',
        'low': 'bg-secondary'
    };
    return badges[priority] || 'bg-info';
}

function getPriorityText(priority) {
    const texts = {
        'urgent': 'فوری',
        'high': 'زیاد',
        'medium': 'متوسط',
        'low': 'کم'
    };
    return texts[priority] || priority;
}

function getAssignedStatusBadge(status) {
    const badges = {
        'assigned': 'bg-warning',
        'in_progress': 'bg-primary',
        'completed': 'bg-success',
        'cancelled': 'bg-secondary'
    };
    return badges[status] || 'bg-secondary';
}

function getAssignedStatusText(status) {
    const texts = {
        'assigned': 'تخصیص داده شده',
        'in_progress': 'در حال انجام',
        'completed': 'تکمیل شده',
        'cancelled': 'لغو شده'
    };
    return texts[status] || status;
}

// Show blocked reason in modal
function showBlockedReason(reason) {
    const modalHtml = `
        <div class="modal fade" id="blockedReasonModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title">
                            <i class="bi bi-exclamation-triangle"></i> دلیل مسدودی
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">${reason}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                    </div>
                </div>
            </div>
        </div>`;
    
    // Remove existing modal if any
    const existing = document.getElementById('blockedReasonModal');
    if (existing) existing.remove();
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('blockedReasonModal'));
    modal.show();
}


// Show timeline modal
function showTimelineModal(data) {
    const task = data.task;
    const timeline = data.timeline;
    const metrics = data.metrics;
    
    let timelineHtml = '<div class="timeline">';
    timeline.forEach((entry, index) => {
        const progressChange = entry.progress_change || 0;
        const changeClass = progressChange > 0 ? 'text-success' : progressChange < 0 ? 'text-danger' : 'text-muted';
        
        timelineHtml += `
            <div class="timeline-item mb-3 pb-3 border-bottom">
                <div class="d-flex justify-content-between">
                    <strong>${entry.report_date_fa}</strong>
                    <span class="badge bg-secondary">${entry.engineer_name}</span>
                </div>
                <div class="mt-2">
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar" style="width: ${entry.progress_percentage}%">
                            ${entry.progress_percentage}%
                        </div>
                    </div>
                    ${progressChange != 0 ? `<small class="${changeClass}">تغییر: ${progressChange > 0 ? '+' : ''}${progressChange}%</small>` : ''}
                </div>
                <div class="mt-1">
                    <small>وضعیت: <span class="badge ${getStatusBadgeClass(entry.completion_status)}">${getStatusText(entry.completion_status)}</span></small>
                    ${entry.hours_spent ? `<small class="ms-2">ساعات: ${entry.hours_spent}</small>` : ''}
                </div>
                ${entry.blocked_reason ? `<div class="mt-1"><small class="text-warning"><i class="bi bi-exclamation-triangle"></i> ${entry.blocked_reason}</small></div>` : ''}
            </div>`;
    });
    timelineHtml += '</div>';
    
    const modalHtml = `
        <div class="modal fade" id="timelineModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-clock-history"></i> تاریخچه کار
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <strong>${task.task_description}</strong>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <small class="text-muted">کل ورودی‌ها</small>
                                <h5>${metrics.total_entries}</h5>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">کل ساعات</small>
                                <h5>${metrics.total_hours}</h5>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">دوره زمانی (روز)</small>
                                <h5>${Math.ceil(metrics.days_span)}</h5>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">پیشرفت فعلی</small>
                                <h5>${metrics.current_progress}%</h5>
                            </div>
                        </div>
                        
                        <hr>
                        <h6 class="mb-3">تاریخچه پیشرفت:</h6>
                        ${timelineHtml}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                    </div>
                </div>
            </div>
        </div>`;
    
    // Remove existing modal if any
    const existing = document.getElementById('timelineModal');
    if (existing) existing.remove();
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('timelineModal'));
    modal.show();
}
function startAssignedTask(taskId) {
    if (!confirm('آیا می‌خواهید این کار را شروع کنید؟ این کار به صورت خودکار به گزارش امروز اضافه خواهد شد.')) return;
    
    // Get task details first
    fetch(`daily_report_api.php?action=get_task_details&task_id=${taskId}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert('خطا در دریافت اطلاعات کار');
                return;
            }
            
            const task = data.task;
            
            // Update task status to in_progress
            const formData = new FormData();
            formData.append('task_id', taskId);
            formData.append('status', 'in_progress');
            formData.append('notes', 'شروع کار');
            
            fetch('daily_report_api.php?action=update_task_status', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(updateData => {
                if (updateData.success) {
                    // Switch to submit tab
                    document.getElementById('submit-tab').click();
                    
                    // Add task as activity
                    setTimeout(() => {
                        addAssignedTaskActivity(task, taskId);
                        alert('کار شروع شد و به گزارش امروز اضافه شد');
                        loadAssignedTasks(); // Refresh the assigned tasks list
                        loadTaskCounts(); // Update badges
                    }, 300);
                } else {
                    alert('خطا: ' + (updateData.message || 'خطایی رخ داد'));
                }
            });
        })
        .catch(error => {
            console.error('Error:', error);
            alert('خطا در شروع کار');
        });
}
document.getElementById('issues-tab')?.addEventListener('shown.bs.tab', loadIssuesDashboard);


// REPLACE your old loadIssuesDashboard function with this one

function gregorian_to_jalali_short(gregorianDateString) {
    // Return a fallback if the date is null, undefined, or not a string
    if (typeof gregorianDateString !== 'string' || !gregorianDateString.includes('-')) {
        return '---';
    }

    // Check if the persianDate library (from persian-date.min.js) is available
    if (typeof persianDate === 'undefined') {
        console.error("persian-date.min.js is not loaded. Cannot convert date.");
        return gregorianDateString; // Return the original string as a fallback
    }

    try {
        // Manually parse the 'YYYY-MM-DD' string to avoid browser issues
        const dateParts = gregorianDateString.split('-').map(Number);
        // Use the reliable array constructor: new persianDate([YYYY, MM, DD])
        return new persianDate(dateParts).format('YYYY/MM/DD');
    } catch (e) {
        console.error("Error converting date:", gregorianDateString, e);
        return gregorianDateString; // Return original on error
    }
}

function loadIssuesDashboard() {
    fetch('daily_report_api.php?action=get_all_issues')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('issuesDashboardContainer');
            if (!data.success || !data.issues) {
                container.innerHTML = '<div class="alert alert-danger">خطا در بارگذاری مشکلات.</div>';
                return;
            }

            if (data.issues.length === 0) {
                container.innerHTML = '<div class="alert alert-success">هیچ مشکل فعالی یافت نشد.</div>';
                return;
            }

            let tableHtml = `<div class="table-responsive"><table class="table table-hover">
                <thead>
                    <tr>
                        <th>تاریخ گزارش</th>
                        <th>گزارش دهنده</th>
                        <th>شرح مشکل</th>
                        <th>ارجاع به</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>`;

            data.issues.forEach(issue => {
                const isAdmin = <?php echo json_encode(in_array($user_role, ['admin', 'superuser', 'coa'])); ?>;
                let actionButtons = '';
                if (isAdmin && issue.status === 'open') {
                    actionButtons = `
                        <button class="btn btn-sm btn-success" onclick="updateIssueStatus(${issue.id}, 'resolved')" title="حل شد"><i class="bi bi-check-lg"></i></button>
                        <button class="btn btn-sm btn-warning" onclick="showSetDueDateModal(${issue.id})" title="تعیین تاریخ پیگیری"><i class="bi bi-calendar-plus"></i></button>
                    `;
                }

                // ===== FIX: Convert Gregorian dates to Jalali =====
                let reportDateFa = '---';
                let dueDateFa = '---';
                
                // Convert report_date to Jalali
                if (issue.report_date && typeof persianDate !== 'undefined') {
                    try {
                        const dateParts = issue.report_date.split('-').map(Number);
                        reportDateFa = new persianDate(dateParts).format('YYYY/MM/DD');
                    } catch (e) {
                        console.error("Error converting report_date:", issue.report_date, e);
                        reportDateFa = issue.report_date; // Fallback
                    }
                }
                
                // Convert due_date to Jalali if exists
                if (issue.due_date && typeof persianDate !== 'undefined') {
                    try {
                        const dateParts = issue.due_date.split('-').map(Number);
                        dueDateFa = new persianDate(dateParts).format('YYYY/MM/DD');
                    } catch (e) {
                        console.error("Error converting due_date:", issue.due_date, e);
                        dueDateFa = issue.due_date; // Fallback
                    }
                }
                
                const dueDateFaHtml = dueDateFa !== '---' && issue.due_date ? 
                    `<br><small class="text-muted">پیگیری: ${dueDateFa}</small>` : '';

                tableHtml += `
                    <tr class="${issue.status === 'open' ? 'table-warning' : ''}">
                        <td>${reportDateFa}</td>
                        <td>${issue.reporter_name}</td>
                        <td>${issue.issue_description}</td>
                        <td><span class="badge bg-secondary">${assignableRoles[issue.assignee_role] || issue.assignee_role}</span></td>
                        <td>
                            <span class="badge ${issue.status === 'open' ? 'bg-danger' : 'bg-success'}">
                                ${issue.status === 'open' ? 'باز' : 'حل شده'}
                            </span>
                            ${dueDateFaHtml}
                        </td>
                        <td><div class="d-flex gap-1">${actionButtons}</div></td>
                    </tr>`;
            });

            tableHtml += `</tbody></table></div>`;
            container.innerHTML = tableHtml;
        })
        .catch(error => {
            console.error('Error loading issues:', error);
            document.getElementById('issuesDashboardContainer').innerHTML = 
                '<div class="alert alert-danger">خطا در بارگذاری مشکلات</div>';
        });
}


function showSetDueDateModal(issueId) {
    const modalHtml = `
        <div class="modal fade" id="setDueDateModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-calendar-plus"></i> تعیین تاریخ پیگیری</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="dueDateForm">
                        <div class="modal-body">
                            <input type="hidden" name="issue_id" value="${issueId}">
                            <div class="form-group">
                                <label for="due_date_input">تاریخ پیگیری</label>
                                <input type="text" class="form-control" id="due_date_input" name="due_date" 
                                       data-jdp data-jdp-only-date readonly placeholder="انتخاب تاریخ...">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                            <button type="submit" class="btn btn-primary">ذخیره</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>`;
    
    const existingModal = document.getElementById('setDueDateModal');
    if (existingModal) existingModal.remove();
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    jalaliDatepicker.startWatch({ selector: '#due_date_input' }); // Initialize datepicker on the new input
    
    const modal = new bootstrap.Modal(document.getElementById('setDueDateModal'));
    modal.show();
    
    document.getElementById('dueDateForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('daily_report_api.php?action=set_issue_due_date', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    modal.hide();
                    loadIssuesDashboard();
                } else {
                    alert('خطا: ' + (data.message || 'خطای ناشناخته'));
                }
            });
    });
}

function updateIssueStatus(issueId, newStatus) {
    if (!confirm('آیا از تغییر وضعیت این مورد اطمینان دارید؟')) return;

    const formData = new FormData();
    formData.append('issue_id', issueId);
    formData.append('status', newStatus);

    fetch('daily_report_api.php?action=update_issue_status', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                loadIssuesDashboard(); // Refresh the list
            } else {
                alert('خطا: ' + data.message);
            }
        });
}

function addAssignedTaskActivity(task, assignedTaskId) {
    activityCount++;
    const container = document.getElementById('activitiesContainer');
    if (!container) return;
    
    const activityHtml = `
        <div class="activity-item border rounded p-3 mb-3 bg-light position-relative" id="activity-${activityCount}">
            <span class="badge bg-info position-absolute top-0 end-0 m-2">
                <i class="bi bi-person-check"></i> کار تخصیص یافته
            </span>
            <input type="hidden" name="activities[${activityCount}][assigned_task_id]" value="${assignedTaskId}">
            
            <div class="row">
                <div class="col-md-8 mb-2">
                    <label class="form-label">شرح فعالیت</label>
                    <input type="text" class="form-control" name="activities[${activityCount}][description]" 
                        value="${task.task_description}" required>
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label">نوع فعالیت</label>
                    <input type="text" class="form-control" name="activities[${activityCount}][type]"
                        value="${task.task_type || ''}">
                </div>
            </div>
            <div class="row">
                <div class="col-md-3 mb-2">
                    <label class="form-label">درصد پیشرفت</label>
                    <input type="number" class="form-control" name="activities[${activityCount}][progress]" 
                        min="0" max="100" value="0">
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">وضعیت</label>
                    <select class="form-select" name="activities[${activityCount}][status]">
                        <option value="in_progress" selected>در حال انجام</option>
                        <option value="completed">تکمیل شده</option>
                        <option value="blocked">مسدود شده</option>
                        <option value="delayed">تاخیر دارد</option>
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">ساعات امروز</label>
                    <input type="number" class="form-control" name="activities[${activityCount}][hours]" 
                        step="0.5" min="0" placeholder="ساعت کار امروز">
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">اولویت</label>
                    <select class="form-select" name="activities[${activityCount}][priority]">
                        <option value="urgent" ${task.priority === 'urgent' ? 'selected' : ''}>فوری</option>
                        <option value="high" ${task.priority === 'high' ? 'selected' : ''}>زیاد</option>
                        <option value="medium" ${task.priority === 'medium' ? 'selected' : ''}>متوسط</option>
                        <option value="low" ${task.priority === 'low' ? 'selected' : ''}>کم</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <button type="button" class="btn btn-sm btn-danger" onclick="removeActivity(${activityCount})">
                        <i class="bi bi-trash"></i> حذف
                    </button>
                </div>
            </div>
        </div>`;
    container.insertAdjacentHTML('beforeend', activityHtml);
    jalaliDatepicker.startWatch();
}


function checkSubmissionStatus() {
    fetch('daily_report_api.php?action=check_submission_status')
        .then(response => response.json())
        .then(data => {
            const notificationArea = document.getElementById('submission-notification-area');
            if (!notificationArea) return;

            // If the status is 'pending', show the alert
            if (data.status === 'pending') {
                const alertHtml = `
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <strong>توجه:</strong> شما هنوز گزارش امروز را ثبت نکرده‌اید.
                        <button type="button" class="btn btn-sm btn-primary ms-3" onclick="goToSubmitTab()">ثبت گزارش</button>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
                notificationArea.innerHTML = alertHtml;
            } else {
                // Otherwise, make sure the area is empty
                notificationArea.innerHTML = '';
            }
        })
        .catch(error => {
            console.error('Error checking submission status:', error);
        });
}
function checkMissedSubmissions() {
    fetch('daily_report_api.php?action=check_missed_reports')
        .then(response => response.json())
        .then(data => {
            const notificationArea = document.getElementById('submission-notification-area');
            if (data.success && data.missed_dates && data.missed_dates.length > 0) {
                const dates_string = data.missed_dates.join(', ');
                const alertHtml = `
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-calendar-x-fill"></i>
                        <strong>هشدار:</strong> شما برای روزهای کاری گذشته (${dates_string}) گزارشی ثبت نکرده‌اید.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
                // Prepend this so it appears before the "today" warning
                notificationArea.insertAdjacentHTML('afterbegin', alertHtml);
            }
        })
        .catch(error => console.error('Error checking missed submissions:', error));
}
function goToSubmitTab() {
    const submitTab = document.getElementById('submit-tab');
    if (submitTab) {
        new bootstrap.Tab(submitTab).show();
    }
}

let issueCount = 0;
const assignableRoles = <?php echo json_encode($assignable_roles); ?>;

function addIssue() {
    issueCount++;
    const container = document.getElementById('issuesContainer');
    
    let optionsHtml = '';
    for (const role in assignableRoles) {
        optionsHtml += `<option value="${role}">${assignableRoles[role]}</option>`;
    }

    const issueHtml = `
        <div class="issue-item border rounded p-3 mb-3 bg-light" id="issue-${issueCount}">
            <div class="row">
                <div class="col-md-8 mb-2">
                    <label class="form-label">شرح مشکل</label>
                    <textarea class="form-control" name="issues[${issueCount}][description]" rows="2" required></textarea>
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label">ارجاع به</label>
                    <select class="form-select" name="issues[${issueCount}][assignee_role]" required>
                        ${optionsHtml}
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeIssue(${issueCount})">
                        <i class="bi bi-trash"></i> حذف
                    </button>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', issueHtml);
}

function removeIssue(id) {
    const issueElement = document.getElementById(`issue-${id}`);
    if (issueElement) {
        issueElement.remove();
    }
}
// Update task status
function updateTaskStatus(taskId, currentStatus) {
    const modalHtml = `
        <div class="modal fade" id="updateStatusModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-pencil"></i> تغییر وضعیت کار
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="statusUpdateForm">
                        <div class="modal-body">
                            <input type="hidden" name="task_id" value="${taskId}">
                            
                            <div class="mb-3">
                                <label class="form-label">وضعیت جدید <span class="text-danger">*</span></label>
                                <select class="form-select" name="status" required>
                                    <option value="assigned" ${currentStatus === 'assigned' ? 'selected' : ''}>تخصیص داده شده</option>
                                    <option value="in_progress" ${currentStatus === 'in_progress' ? 'selected' : ''}>در حال انجام</option>
                                    <option value="completed">تکمیل شده</option>
                                    <option value="cancelled">لغو شده</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">یادداشت</label>
                                <textarea class="form-control" name="notes" rows="3" 
                                    placeholder="دلیل تغییر وضعیت یا توضیحات..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                            <button type="submit" class="btn btn-primary">ذخیره</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>`;
    
    // Remove existing modal if any
    const existing = document.getElementById('updateStatusModal');
    if (existing) existing.remove();
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('updateStatusModal'));
    modal.show();
    
    // Handle form submission
    document.getElementById('statusUpdateForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('daily_report_api.php?action=update_task_status', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message || 'وضعیت با موفقیت به‌روز شد');
                modal.hide();
                loadAssignedTasks(); // Reload the list
            } else {
                alert('خطا: ' + (data.message || 'خطایی رخ داد'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('خطا در به‌روزرسانی وضعیت');
        });
    });
}

// Handle assign task form submission
document.getElementById('assignTaskForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('daily_report_api.php?action=assign_task', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message || 'کار با موفقیت تخصیص داده شد');
            this.reset();
            loadAssignedTasks(); // Reload assigned tasks list
        } else {
            alert('خطا: ' + (data.message || 'خطایی رخ داد'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('خطا در تخصیص کار');
    });
});

// Load data when tabs are shown
document.getElementById('unfinished-subtab')?.addEventListener('shown.bs.tab', function() {
    loadUnfinishedTasks();
});

document.getElementById('assigned-subtab')?.addEventListener('shown.bs.tab', function() {
    loadAssignedTasks();
});

document.getElementById('assign-new-subtab')?.addEventListener('shown.bs.tab', function() {
    loadUsersForAssignment();
    jalaliDatepicker.startWatch(); // Re-initialize for date fields
});

// Add filter listeners
document.getElementById('unfinishedStatusFilter')?.addEventListener('change', loadUnfinishedTasks);
document.getElementById('unfinishedPriorityFilter')?.addEventListener('change', loadUnfinishedTasks);
document.getElementById('assignedStatusFilter')?.addEventListener('change', loadAssignedTasks);

// Load tasks when tasks tab is shown
document.getElementById('tasks-tab')?.addEventListener('shown.bs.tab', function() {
    loadUnfinishedTasks();
});
function resizeIframe(iframe) {
    try {
        if (iframe.contentWindow.document.body) {
            iframe.style.height = iframe.contentWindow.document.body.scrollHeight + 50 + 'px';
        }
    } catch (e) {
        // Cross-origin restriction - keep fixed height
        console.log('Iframe uses fixed height');
    }
}
const DateConverter = {
    toGregorian: function(jy, jm, jd) { let sal_a, gy, gm, gd, days; jy += 1595; days = -355668 + 365 * jy + ~~(jy / 33) * 8 + ~~(((jy % 33) + 3) / 4) + jd + (jm < 7 ? (jm - 1) * 31 : (jm - 7) * 30 + 186); gy = 400 * ~~(days / 146097); days %= 146097; if (days > 36524) { gy += 100 * ~~(--days / 36524); days %= 36524; if (days >= 365) days++; } gy += 4 * ~~(days / 1461); days %= 1461; if (days > 365) { gy += ~~((days - 1) / 365); days = (days - 1) % 365; } gd = days + 1; sal_a = [0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31]; if ((gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0) sal_a[2] = 29; for (gm = 1; gm <= 12; gm++) { if (gd <= sal_a[gm]) break; gd -= sal_a[gm]; } return [gy, gm, gd]; },
    toJalaali: function(gy, gm, gd) { let g_d_m, jy, jd, gy2, days; g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334]; gy2 = gm > 2 ? gy + 1 : gy; days = 355666 + 365 * gy + ~~((gy2 + 3) / 4) - ~~((gy2 + 99) / 100) + ~~((gy2 + 399) / 400) + gd + g_d_m[gm - 1]; jy = -1595 + 33 * ~~(days / 12053); days %= 12053; jy += 4 * ~~(days / 1461); days %= 1461; if (days > 365) { jy += ~~((days - 1) / 365); days = (days - 1) % 365; } let jm = days < 186 ? 1 + ~~(days / 31) : 7 + ~~((days - 186) / 30); jd = 1 + (days < 186 ? days % 31 : (days - 186) % 30); return [jy, jm, jd]; },
};

// Global variable to hold the calendar instance
let calendar;
const JALALI_MONTH_NAMES = ["فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد", "شهریور", "مهر", "آبان", "آذر", "دی", "بهمن", "اسفند"];

// This event listener will fire when the calendar tab is shown
document.getElementById('calendar-tab')?.addEventListener('shown.bs.tab', function() {
    if (!calendar) {
        initializeFullCalendar();
    }
});

// Main function to fetch all data and initialize the calendar
async function initializeFullCalendar() {
    const calendarEl = document.getElementById('fullcalendar-container');

    try {
        const [eventsRes, holidaysRes] = await Promise.all([
            fetch('assets/js/all_events.json'),
            fetch('assets/js/holidays.json')
        ]);

        const allEventsData = await eventsRes.json();
        const holidaysData = await holidaysRes.json();
        
        const holidayDates = new Set(holidaysData.map(h => h.gregorian_date_str));
        const officialEvents = allEventsData.filter(e => !holidayDates.has(e.gregorian_date_str));

        calendar = new FullCalendar.Calendar(calendarEl, {
            locale: 'fa',
            direction: 'rtl',
            firstDay: 6,
            initialDate: new Date(),

            headerToolbar: {
                left: 'prev,next today', center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth,listWeek,listDay'
            },
            buttonText: {
                today: "امروز", month: "ماه", week: "هفته", day: "روز",
                listMonth: "لیست ماه", listWeek: "لیست هفته", listDay: "لیست روز"
            },
            
            eventSources: [
                { events: holidaysData.map(e => ({ title: e.event_description, start: e.gregorian_date_str, allDay: true, display: 'background', className: 'fc-event-danger' })) },
                { events: officialEvents.map(e => ({ title: e.event_description, start: e.gregorian_date_str, allDay: true, display: 'background', className: 'fc-event-light' })) },
                { url: 'daily_report_api.php?action=calendar_plans', className: 'fc-event-success' },
                { url: 'daily_report_api.php?action=calendar_unfinished_tasks', className: 'fc-event-warning' },
                { url: 'daily_report_api.php?action=calendar_assigned_tasks', className: 'fc-event-warning' },
                { url: 'daily_report_api.php?action=calendar_missed_days', className: 'fc-event-missed-day' },
                { url: 'daily_report_api.php?action=calendar_data' }
            ],

            eventClick: function(info) {
                info.jsEvent.preventDefault();
                if (info.event.url) {
                    if (info.event.url.startsWith('#')) {
                        const tabTrigger = document.querySelector(`button[data-bs-target="${info.event.url}"]`);
                        if (tabTrigger) new bootstrap.Tab(tabTrigger).show();
                    } else {
                        window.open(info.event.url, "_blank");
                    }
                }
            },

            noEventsContent: {
                html: `<div class='p-4 text-center text-muted'><i class='bi bi-calendar-x fs-2'></i><h5 class='mt-2'>هیچ رویدادی برای نمایش وجود ندارد.</h5></div>`
            },
            
            // --- THIS IS THE CORRECTED SECTION FOR JALALI TITLES ---
            // Removed the unsupported 'list...Format' options
            // Added a robust viewDidMount to handle all titles

            dayCellContent: function(arg) {
                const [jy, jm, jd] = DateConverter.toJalaali(arg.date.getFullYear(), arg.date.getMonth() + 1, arg.date.getDate());
                return { html: `<span class="jalali-day-number">${jd}</span>` };
            },

            viewDidMount: function(info) {
                // This function runs every time the view or date range changes.
                const titleEl = document.querySelector('.fc-toolbar-title');
                if (!titleEl) return;

                const start = info.view.currentStart;
                const end = info.view.currentEnd;
                const [startJy, startJm, startJd] = DateConverter.toJalaali(start.getFullYear(), start.getMonth() + 1, start.getDate());
                
                // For week/month views, the 'end' is exclusive, so we subtract a day to get the correct end date.
                const trueEnd = new Date(end.getTime() - (24 * 60 * 60 * 1000));
                const [endJy, endJm, endJd] = DateConverter.toJalaali(trueEnd.getFullYear(), trueEnd.getMonth() + 1, trueEnd.getDate());
                
                let newTitle = '';
                switch (info.view.type) {
                    case 'dayGridMonth':
                    case 'listMonth':
                        newTitle = `${JALALI_MONTH_NAMES[startJm - 1]} ${startJy}`;
                        break;
                    case 'timeGridWeek':
                    case 'listWeek':
                        if (startJm === endJm) {
                            newTitle = `هفته ${startJd}–${endJd} ${JALALI_MONTH_NAMES[startJm - 1]} ${startJy}`;
                        } else {
                             newTitle = `هفته ${startJd} ${JALALI_MONTH_NAMES[startJm - 1]} – ${endJd} ${JALALI_MONTH_NAMES[endJm - 1]} ${endJy}`;
                        }
                        break;
                    case 'timeGridDay':
                    case 'listDay':
                        newTitle = `${startJd} ${JALALI_MONTH_NAMES[startJm - 1]} ${startJy}`;
                        break;
                    default:
                        newTitle = info.view.title; // Fallback
                }
                titleEl.textContent = newTitle;
            }
        });

        calendar.render();
        
    } catch (error) {
        console.error("Error initializing calendar:", error);
        calendarEl.innerHTML = '<div class="alert alert-danger">خطا در بارگذاری تقویم.</div>';
    }
}
// Lazy load iframes when tabs are shown for better performance
document.getElementById('analytics-tab')?.addEventListener('shown.bs.tab', function() {
    const iframe = document.getElementById('analyticsFrame');
    if (iframe && !iframe.getAttribute('data-loaded')) {
        iframe.setAttribute('data-loaded', 'true');
        iframe.addEventListener('load', function() {
            resizeIframe(iframe);
        });
    }
});

document.getElementById('building-analytics-tab')?.addEventListener('shown.bs.tab', function() {
    const iframe = document.getElementById('buildingAnalyticsFrame');
    if (iframe && !iframe.getAttribute('data-loaded')) {
        iframe.setAttribute('data-loaded', 'true');
        iframe.addEventListener('load', function() {
            resizeIframe(iframe);
        });
    }
});



function showDayReports(dateStr, reports) {
    if (!reports || reports.length === 0) {
        return;
    }
    
    let reportsHtml = reports.map(report => `
        <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1">${report.engineer_name}</h6>
                    <small class="text-muted">${report.role_fa}</small>
                </div>
                <div>
                    <a href="daily_report_view.php?id=${report.id}" class="btn btn-sm btn-primary" target="_blank">
                        <i class="bi bi-eye"></i> مشاهده
                    </a>
                </div>
            </div>
            <div class="mt-2">
                <small><strong>فعالیت‌ها:</strong> ${report.activities_count || 0}</small>
                <div class="progress mt-1" style="height: 15px;">
                    <div class="progress-bar" style="width: ${report.avg_progress || 0}%">
                        ${Math.round(report.avg_progress || 0)}%
                    </div>
                </div>
            </div>
        </div>
    `).join('');
    
    const modalHtml = `
        <div class="modal fade" id="dayReportsModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-calendar-day"></i> گزارش‌های ${dateStr}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="list-group">
                            ${reportsHtml}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                    </div>
                </div>
            </div>
        </div>`;
    
    const existing = document.getElementById('dayReportsModal');
    if (existing) existing.remove();
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('dayReportsModal'));
    modal.show();
}



// Load calendar when tab is shown



    </script>

</body>

</html>

<?php require_once 'footer.php'; ?>