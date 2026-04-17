<?php
// public_html/pardis/daily_report_mobile.php

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

// Updated engineering roles to match desktop
$engineering_roles = [
    'field_engineer' => 'مهندس اجرا',
    'designer' => 'طراح',
    'surveyor' => 'نقشه‌بردار',
    'control_engineer' => 'مهندس کنترل پروژه',
    'drawing_specialist' => 'شاپ'
];

// Updated assignable roles for issues
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>گزارش روزانه - موبایل</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/pardis/assets/css/jalalidatepicker.min.css" />
    <script src="/pardis/assets/js/jalalidatepicker.min.js"></script>
    <style>
        body {
            font-family: 'Tahoma', 'Arial', sans-serif;
            background: #f5f5f5;
            padding-bottom: 70px;
        }
        .mobile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-section {
            background: white;
            margin: 10px;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .section-title {
            font-size: 1.1em;
            font-weight: 600;
            color: #667eea;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        .activity-item-mobile {
            background: #f8f9fa;
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 8px;
            border-right: 3px solid #667eea;
        }
        .issue-item-mobile {
            background: #fff3cd;
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 8px;
            border-right: 3px solid #ffc107;
        }
        .btn-add-activity, .btn-add-issue {
            width: 100%;
            border-radius: 8px;
            padding: 12px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .btn-submit-mobile {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 15px;
            background: white;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            z-index: 999;
        }
        .quick-time-btn {
            padding: 8px 12px;
            font-size: 0.9em;
            border-radius: 6px;
            margin: 2px;
        }
        .camera-upload {
            border: 2px dashed #667eea;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .camera-upload:hover {
            background: #f8f9fa;
            border-color: #764ba2;
        }
        .preview-image {
            max-width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            margin: 5px;
        }
        .carryover-badge {
            background: #ffc107;
            color: #000;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            margin-bottom: 8px;
            display: inline-block;
        }
        .planned-task-badge {
            background: #17a2b8;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            margin-bottom: 8px;
            display: inline-block;
        }
        .alert-mobile {
            margin: 10px;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0"><i class="bi bi-clipboard-check"></i> گزارش روزانه</h5>
                <small><?php echo htmlspecialchars($user_name); ?></small>
            </div>
            <div>
                <a href="daily_reports.php?view=desktop" class="btn btn-light btn-sm me-2">
                    <i class="bi bi-display"></i>
                </a>
                <a href="daily_reports.php" class="btn btn-light btn-sm">
                    <i class="bi bi-list-ul"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Submission Status Alert -->
    <div id="submission-notification-area-mobile"></div>

    <!-- Success/Error Messages -->
    <?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-<?php echo $_GET['msg'] === 'success' ? 'success' : 'danger'; ?> alert-mobile">
        <?php echo $_GET['msg'] === 'success' ? 'گزارش با موفقیت ثبت شد' : 'خطا در ثبت گزارش'; ?>
    </div>
    <?php endif; ?>

    <!-- Form -->
    <form id="mobileReportForm" action="daily_report_submit.php" method="POST" enctype="multipart/form-data">
        
        <!-- Basic Info Section -->
        <div class="form-section">
            <div class="section-title">
                <i class="bi bi-info-circle"></i> اطلاعات پایه
            </div>
            <div class="mb-3">
                <label class="form-label">تاریخ</label>
                <input type="text" class="form-control" name="report_date" id="report_date"
                    data-jdp data-jdp-only-date placeholder="انتخاب تاریخ" required readonly>
            </div>
            <div class="mb-3">
                <label class="form-label">نام</label>
                <input type="text" class="form-control" name="engineer_name" value="<?php echo htmlspecialchars($user_name); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">نقش</label>
                <select class="form-select" name="role" id="roleSelectMobile" required>
                    <option value="">انتخاب کنید...</option>
                    <?php foreach ($engineering_roles as $key => $value): ?>
                    <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label">محل فعالیت</label>
                <input type="text" class="form-control" name="location" value="پردیس" 
                    placeholder="مثال: ساختمان A - طبقه 2" required>
            </div>
        </div>

        <!-- Time Section -->
        <div class="form-section">
            <div class="section-title">
                <i class="bi bi-clock"></i> زمان‌بندی
            </div>
            <div class="row mb-3">
                <div class="col-6">
                    <label class="form-label">ورود</label>
                    <input type="time" class="form-control" id="arrivalTime" name="arrival_time" 
                        value="08:00" onchange="calculateWorkHoursMobile()">
                    <button type="button" class="btn btn-sm btn-outline-primary quick-time-btn mt-2" 
                        onclick="setCurrentTime('arrivalTime')">
                        <i class="bi bi-clock"></i> اکنون
                    </button>
                </div>
                <div class="col-6">
                    <label class="form-label">خروج</label>
                    <input type="time" class="form-control" id="departureTime" name="departure_time" 
                        value="17:00" onchange="calculateWorkHoursMobile()">
                    <button type="button" class="btn btn-sm btn-outline-primary quick-time-btn mt-2" 
                        onclick="setCurrentTime('departureTime')">
                        <i class="bi bi-clock"></i> اکنون
                    </button>
                </div>
            </div>
            <div class="row">
                <div class="col-6">
                    <label class="form-label">ساعات کار (محاسبه خودکار)</label>
                    <input type="number" class="form-control" id="work_hours_mobile" name="work_hours" 
                        value="8" step="0.1" readonly>
                </div>
                <div class="col-6">
                    <label class="form-label">آب و هوا</label>
                    <select class="form-select" name="weather" id="mobileReportWeatherSelect">
                        <option value="clear">آفتابی</option>
                        <option value="cloudy">ابری</option>
                        <option value="rainy">بارانی</option>
                        <option value="hot">گرم</option>
                        <option value="cold">سرد</option>
                        <option value="other">سایر</option>
                    </select>
                </div>
            </div>
            <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" id="had_lunch_break_mobile" 
                    name="had_lunch_break" onchange="calculateWorkHoursMobile()">
                <label class="form-check-label" for="had_lunch_break_mobile">
                    استراحت ناهار
                </label>
            </div>
        </div>

        <!-- Activities Section -->
        <div class="form-section">
            <div class="section-title">
                <i class="bi bi-list-task"></i> فعالیت‌های امروز
            </div>
            <div id="activitiesContainer"></div>
            <button type="button" class="btn btn-primary btn-add-activity" onclick="addActivityMobile()">
                <i class="bi bi-plus-circle"></i> افزودن فعالیت
            </button>
        </div>

        <!-- Issues Section (Updated) -->
        <div class="form-section">
            <div class="section-title">
                <i class="bi bi-exclamation-triangle-fill text-danger"></i> ثبت مشکلات و موانع
            </div>
            <div id="issuesContainerMobile"></div>
            <button type="button" class="btn btn-danger btn-add-issue" onclick="addIssueMobile()">
                <i class="bi bi-plus"></i> افزودن مشکل
            </button>
        </div>

        <!-- Tomorrow's Plan -->
        <div class="form-section">
            <div class="section-title">
                <i class="bi bi-calendar-check"></i> برنامه فردا
            </div>
            <textarea class="form-control" name="next_day_plan" rows="3" 
                placeholder="برنامه کاری فردا چیست؟"></textarea>
        </div>

        <!-- General Notes -->
        <div class="form-section">
            <div class="section-title">
                <i class="bi bi-chat-left-text"></i> یادداشت‌های عمومی
            </div>
            <textarea class="form-control" name="general_notes" rows="2" 
                placeholder="سایر یادداشت‌ها..."></textarea>
        </div>

        <!-- Photos/Attachments -->
        <div class="form-section">
            <div class="section-title">
                <i class="bi bi-camera"></i> عکس‌ها و فایل‌ها
            </div>
            <div class="camera-upload" onclick="document.getElementById('fileInput').click()">
                <i class="bi bi-camera" style="font-size: 2em; color: #667eea;"></i>
                <p class="mb-0 mt-2">کلیک کنید یا عکس بگیرید</p>
                <small class="text-muted">حداکثر 5 فایل - هر فایل حداکثر 5MB</small>
                <input type="file" id="fileInput" name="attachments[]" multiple 
                    accept="image/*,.pdf,.doc,.docx" style="display: none;" onchange="previewFiles(this)">
            </div>
            <div id="filePreview" class="mt-3"></div>
        </div>

        <!-- Safety -->
        <div class="form-section">
            <div class="section-title">
                <i class="bi bi-shield-check"></i> ایمنی
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="safetyIncident" 
                    name="safety_incident" value="yes">
                <label class="form-check-label" for="safetyIncident">
                    حادثه ایمنی رخ داده است
                </label>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="btn-submit-mobile">
            <button type="submit" class="btn btn-success w-100 btn-lg">
                <i class="bi bi-send"></i> ثبت گزارش
            </button>
        </div>
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/pardis/assets/js/persian-date.min.js"></script>
    <script>
        // Project data structure matching desktop
        const projectData = {
            "پروژه دانشگاه خاتم پردیس": {
                "ساختمان کتابخانه": [
                    'کرتین وال طبقه سوم', 'پهنه بتنی غرب و جنوب', 'ویندووال طبقه 1', 'ویندووال طبقه 2',
                    'ویندووال طبقه همکف', 'اسکای لایت', 'کاور بتنی ستونها', 'هندریل',
                    'نقشه‌های ساخت فلاشینگ، دودبند و سمنت بورد'
                ],
                "ساختمان دانشکده کشاورزی": [
                    'ویندووال طبقه 3 غرب', 'کرتین وال طبقه 1 و 2 غرب', 'کرتین‌وال طبقه 2 و 3 غرب',
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
                "هر دو ساختمان": [],
                "عمومی": []
            }
        };

        const assignableRoles = <?php echo json_encode($assignable_roles); ?>;
        let activityCountMobile = 0;
        let issueCountMobile = 0;

        // Initialize on page load
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
            
            // Weather integration
            if (window.weatherIntegration) {
                const originalDisplayWeather = window.weatherIntegration.displayWeather.bind(window.weatherIntegration);
                window.weatherIntegration.displayWeather = function(weather) {
                    originalDisplayWeather(weather);
                    const mobileSelect = document.getElementById('mobileReportWeatherSelect');
                    if (mobileSelect && weather.simple_category) {
                        mobileSelect.value = weather.simple_category;
                    }
                };
                window.weatherIntegration.fetchWeather();
            }

            // Load pending tasks and previous day plan
            loadPendingTasksMobile();
            loadPreviousDayPlanMobile();
            
            // Check submission status
            checkSubmissionStatusMobile();
            checkMissedSubmissionsMobile();
            
            // Calculate work hours
            calculateWorkHoursMobile();
        });

        // Get default activity type based on role
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

        // Role change handler for default activity type
        document.getElementById('roleSelectMobile')?.addEventListener('change', function() {
            const defaultType = getDefaultActivityType(this.value);
            document.querySelectorAll('input[name*="[type]"]').forEach(input => {
                if (!input.value) {
                    input.value = defaultType;
                }
            });
        });

        // Calculate work hours
        function calculateWorkHoursMobile() {
            const arrivalInput = document.getElementById('arrivalTime');
            const departureInput = document.getElementById('departureTime');
            const lunchCheck = document.getElementById('had_lunch_break_mobile');
            const hoursInput = document.getElementById('work_hours_mobile');

            if (!arrivalInput.value || !departureInput.value) {
                hoursInput.value = 0;
                return;
            }

            const startTime = new Date('1970-01-01T' + arrivalInput.value + 'Z');
            const endTime = new Date('1970-01-01T' + departureInput.value + 'Z');

            let diff = endTime.getTime() - startTime.getTime();
            if (diff < 0) {
                diff += 24 * 60 * 60 * 1000;
            }
            
            let hours = diff / (1000 * 60 * 60);
            if (lunchCheck.checked) {
                hours -= 1;
            }

            hoursInput.value = Math.max(0, hours).toFixed(2);
        }

        // Add activity for mobile with project structure
        function addActivityMobile() {
            activityCountMobile++;
            const roleSelect = document.getElementById('roleSelectMobile');
            const defaultType = getDefaultActivityType(roleSelect.value);
            
            const container = document.getElementById('activitiesContainer');
            const html = `
                <div class="activity-item-mobile" id="activity-mobile-${activityCountMobile}">
                    <div class="mb-2">
                        <label class="form-label small">پروژه</label>
                        <select class="form-select form-select-sm activity-project-mobile" 
                            name="activities[${activityCountMobile}][project_name]" 
                            data-id="${activityCountMobile}" required>
                            ${Object.keys(projectData).map(p => `<option value="${p}">${p}</option>`).join('')}
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">ساختمان</label>
                        <select class="form-select form-select-sm activity-building-mobile" 
                            name="activities[${activityCountMobile}][building_name]" 
                            data-id="${activityCountMobile}" required></select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">بخش ساختمان</label>
                        <select class="form-select form-select-sm activity-part-mobile" 
                            name="activities[${activityCountMobile}][building_part]" 
                            data-id="${activityCountMobile}" required></select>
                        <input type="text" class="form-control form-control-sm mt-1 activity-custom-part-mobile" 
                            name="activities[${activityCountMobile}][custom_building_part]" 
                            data-id="${activityCountMobile}" 
                            placeholder="نام بخش دلخواه را وارد کنید" style="display: none;">
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" 
                                onchange="toggleCustomPartMobile(this, ${activityCountMobile})">
                            <label class="form-check-label small">
                                بخش دلخواه (در لیست موجود نیست)
                            </label>
                        </div>
                    </div>
                    <div class="mb-2">
                        <input type="text" class="form-control form-control-sm" 
                               name="activities[${activityCountMobile}][description]" 
                               placeholder="شرح فعالیت" required>
                    </div>
                    <div class="mb-2">
                        <input type="text" class="form-control form-control-sm" 
                               name="activities[${activityCountMobile}][type]" 
                               value="${defaultType}"
                               placeholder="نوع فعالیت">
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <input type="number" class="form-control form-control-sm" 
                                   name="activities[${activityCountMobile}][progress]" 
                                   placeholder="% پیشرفت" min="0" max="100" value="0">
                        </div>
                        <div class="col-6">
                            <select class="form-select form-select-sm" 
                                name="activities[${activityCountMobile}][status]">
                                <option value="in_progress">در حال انجام</option>
                                <option value="completed">تکمیل</option>
                                <option value="blocked">مسدود</option>
                                <option value="delayed">تاخیر دارد</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="number" class="form-control form-control-sm" 
                                   name="activities[${activityCountMobile}][hours]" 
                                   placeholder="ساعت" step="0.5" min="0">
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-sm btn-danger w-100" 
                                    onclick="removeActivityMobile(${activityCountMobile})">
                                <i class="bi bi-trash"></i> حذف
                            </button>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
            
            // Setup cascading dropdowns
            setupCascadingDropdownsMobile(activityCountMobile);
        }

        // Setup cascading dropdowns for mobile
        function setupCascadingDropdownsMobile(id) {
            const defaultProject = "پروژه دانشگاه خاتم پردیس";
            const projectSelect = document.querySelector(`.activity-project-mobile[data-id="${id}"]`);
            const buildingSelect = document.querySelector(`.activity-building-mobile[data-id="${id}"]`);
            const partSelect = document.querySelector(`.activity-part-mobile[data-id="${id}"]`);

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

        // Toggle custom part input for mobile
        function toggleCustomPartMobile(checkbox, id) {
            const customInput = document.querySelector(`.activity-custom-part-mobile[data-id="${id}"]`);
            const partSelect = document.querySelector(`.activity-part-mobile[data-id="${id}"]`);
            
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

        // Remove activity
        function removeActivityMobile(id) {
            document.getElementById(`activity-mobile-${id}`).remove();
        }

        // Add issue for mobile
        function addIssueMobile() {
            issueCountMobile++;
            const container = document.getElementById('issuesContainerMobile');
            
            let optionsHtml = '';
            for (const role in assignableRoles) {
                optionsHtml += `<option value="${role}">${assignableRoles[role]}</option>`;
            }

            const html = `
                <div class="issue-item-mobile" id="issue-mobile-${issueCountMobile}">
                    <div class="mb-2">
                        <label class="form-label small">شرح مشکل</label>
                        <textarea class="form-control form-control-sm" 
                            name="issues[${issueCountMobile}][description]" 
                            rows="2" required></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">ارجاع به</label>
                        <select class="form-select form-select-sm" 
                            name="issues[${issueCountMobile}][assignee_role]" required>
                            ${optionsHtml}
                        </select>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger w-100" 
                        onclick="removeIssueMobile(${issueCountMobile})">
                        <i class="bi bi-trash"></i> حذف
                    </button>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
        }

        // Remove issue
        function removeIssueMobile(id) {
            document.getElementById(`issue-mobile-${id}`).remove();
        }

        // Load pending tasks from previous reports
        function loadPendingTasksMobile() {
            fetch('daily_report_api.php?action=pending_tasks')
                .then(response => response.json())
                .then(data => {
                    if (data.tasks && data.tasks.length > 0) {
                        showPendingTasksModalMobile(data.tasks);
                    }
                })
                .catch(error => console.error('Error loading pending tasks:', error));
        }

        // Show pending tasks modal for mobile
        function showPendingTasksModalMobile(tasks) {
            const modal = `
                <div class="modal fade" id="pendingTasksModalMobile" tabindex="-1">
                    <div class="modal-dialog modal-fullscreen">
                        <div class="modal-content">
                            <div class="modal-header bg-warning">
                                <h5 class="modal-title">
                                    <i class="bi bi-exclamation-triangle"></i> 
                                    کارهای ناتمام (${tasks.length})
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p class="alert alert-info">
                                    این کارها از روزهای قبل ناتمام مانده‌اند. 
                                    می‌توانید آنها را به گزارش امروز اضافه کنید:
                                </p>
                                <div class="list-group">
                                    ${tasks.map((task, index) => `
                                        <label class="list-group-item">
                                            <input class="form-check-input me-2 pending-task-checkbox-mobile" 
                                                type="checkbox" data-task='${JSON.stringify(task)}'>
                                            <div>
                                                <div class="fw-bold">${task.task_description}</div>
                                                <small class="text-muted">${task.report_date_fa}</small>
                                                <div class="progress mt-1" style="height: 15px;">
                                                    <div class="progress-bar" style="width: ${task.progress_percentage}%">
                                                        ${task.progress_percentage}%
                                                    </div>
                                                </div>
                                            </div>
                                        </label>
                                    `).join('')}
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    نادیده گرفتن
                                </button>
                                <button type="button" class="btn btn-primary" 
                                    onclick="addSelectedPendingTasksMobile()">
                                    افزودن موارد انتخاب شده
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            const existingModal = document.getElementById('pendingTasksModalMobile');
            if (existingModal) existingModal.remove();

            document.body.insertAdjacentHTML('beforeend', modal);
            const modalEl = new bootstrap.Modal(document.getElementById('pendingTasksModalMobile'));
            modalEl.show();
        }

        // Add selected pending tasks
        function addSelectedPendingTasksMobile() {
            const checkboxes = document.querySelectorAll('.pending-task-checkbox-mobile:checked');
            checkboxes.forEach(checkbox => {
                const task = JSON.parse(checkbox.dataset.task);
                addCarryoverActivityMobile(task);
            });
            
            const modalInstance = bootstrap.Modal.getInstance(
                document.getElementById('pendingTasksModalMobile')
            );
            if (modalInstance) {
                modalInstance.hide();
            }
        }

        // Add carryover activity
        function addCarryoverActivityMobile(task) {
            activityCountMobile++;
            const container = document.getElementById('activitiesContainer');
            const html = `
                <div class="activity-item-mobile" id="activity-mobile-${activityCountMobile}">
                    <span class="carryover-badge">
                        <i class="bi bi-arrow-right-circle"></i> ادامه از ${task.report_date_fa}
                    </span>
                    <input type="hidden" name="activities[${activityCountMobile}][is_carryover]" value="1">
                    <input type="hidden" name="activities[${activityCountMobile}][parent_activity_id]" value="${task.id}">
                    
                    <div class="mb-2">
                        <label class="form-label small">شرح فعالیت</label>
                        <input type="text" class="form-control form-control-sm" 
                            name="activities[${activityCountMobile}][description]" 
                            value="${task.task_description}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">نوع فعالیت</label>
                        <input type="text" class="form-control form-control-sm" 
                            name="activities[${activityCountMobile}][type]" 
                            value="${task.task_type || ''}">
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small">پیشرفت</label>
                            <input type="number" class="form-control form-control-sm" 
                                name="activities[${activityCountMobile}][progress]" 
                                min="${task.progress_percentage}" max="100" 
                                value="${task.progress_percentage}">
                            <small class="text-muted">قبلی: ${task.progress_percentage}%</small>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">وضعیت</label>
                            <select class="form-select form-select-sm" 
                                name="activities[${activityCountMobile}][status]">
                                <option value="in_progress" ${task.completion_status === 'in_progress' ? 'selected' : ''}>
                                    در حال انجام
                                </option>
                                <option value="completed">تکمیل شده</option>
                                <option value="blocked" ${task.completion_status === 'blocked' ? 'selected' : ''}>
                                    مسدود شده
                                </option>
                                <option value="delayed" ${task.completion_status === 'delayed' ? 'selected' : ''}>
                                    تاخیر دارد
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-2">
                        <input type="number" class="form-control form-control-sm" 
                            name="activities[${activityCountMobile}][hours]" 
                            placeholder="ساعات امروز" step="0.5" min="0">
                    </div>
                    <button type="button" class="btn btn-sm btn-danger w-100" 
                        onclick="removeActivityMobile(${activityCountMobile})">
                        <i class="bi bi-trash"></i> حذف
                    </button>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
        }

        // Load previous day plan
        function loadPreviousDayPlanMobile() {
            fetch('daily_report_api.php?action=get_previous_day_plan')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.tasks && data.tasks.length > 0) {
                        showPlannedTasksModalMobile(data.tasks);
                    }
                })
                .catch(error => console.error('Error loading previous day plan:', error));
        }

        // Show planned tasks modal
        function showPlannedTasksModalMobile(tasks) {
            const modal = `
                <div class="modal fade" id="plannedTasksModalMobile" tabindex="-1">
                    <div class="modal-dialog modal-fullscreen">
                        <div class="modal-content">
                            <div class="modal-header bg-info text-white">
                                <h5 class="modal-title">
                                    <i class="bi bi-calendar-check"></i> برنامه شما از روز قبل
                                </h5>
                                <button type="button" class="btn-close btn-close-white" 
                                    data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p class="alert alert-info">
                                    این موارد را از برنامه دیروز خود ثبت کرده‌اید. 
                                    موارد مورد نظر را برای افزودن به گزارش امروز انتخاب کنید:
                                </p>
                                <div class="list-group">
                                    ${tasks.map((task, index) => `
                                        <label class="list-group-item">
                                            <input class="form-check-input me-2 planned-task-checkbox-mobile" 
                                                type="checkbox" data-task="${task}">
                                            <span>${task}</span>
                                        </label>
                                    `).join('')}
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    نادیده گرفتن
                                </button>
                                <button type="button" class="btn btn-primary" 
                                    onclick="addSelectedPlannedTasksMobile()">
                                    افزودن به گزارش
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            const existingModal = document.getElementById('plannedTasksModalMobile');
            if (existingModal) existingModal.remove();

            document.body.insertAdjacentHTML('beforeend', modal);
            const modalEl = new bootstrap.Modal(document.getElementById('plannedTasksModalMobile'));
            modalEl.show();
        }

        // Add selected planned tasks
        function addSelectedPlannedTasksMobile() {
            const checkboxes = document.querySelectorAll('.planned-task-checkbox-mobile:checked');
            
            checkboxes.forEach(checkbox => {
                const taskDescription = checkbox.dataset.task;
                addActivityMobile();
                
                const newActivityDescriptionInput = document.querySelector(
                    `#activity-mobile-${activityCountMobile} input[name*="[description]"]`
                );
                if (newActivityDescriptionInput) {
                    newActivityDescriptionInput.value = taskDescription;
                }
            });

            const modalInstance = bootstrap.Modal.getInstance(
                document.getElementById('plannedTasksModalMobile')
            );
            if (modalInstance) {
                modalInstance.hide();
            }
        }

        // Check submission status
        function checkSubmissionStatusMobile() {
            fetch('daily_report_api.php?action=check_submission_status')
                .then(response => response.json())
                .then(data => {
                    const notificationArea = document.getElementById('submission-notification-area-mobile');
                    if (!notificationArea) return;

                    if (data.status === 'pending') {
                        const alertHtml = `
                            <div class="alert alert-warning alert-mobile alert-dismissible fade show">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <strong>توجه:</strong> شما هنوز گزارش امروز را ثبت نکرده‌اید.
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        `;
                        notificationArea.innerHTML = alertHtml;
                    } else {
                        notificationArea.innerHTML = '';
                    }
                })
                .catch(error => console.error('Error checking submission status:', error));
        }

        // Check missed submissions
        function checkMissedSubmissionsMobile() {
            fetch('daily_report_api.php?action=check_missed_reports')
                .then(response => response.json())
                .then(data => {
                    const notificationArea = document.getElementById('submission-notification-area-mobile');
                    if (data.success && data.missed_dates && data.missed_dates.length > 0) {
                        const dates_string = data.missed_dates.join(', ');
                        const alertHtml = `
                            <div class="alert alert-danger alert-mobile alert-dismissible fade show">
                                <i class="bi bi-calendar-x-fill"></i>
                                <strong>هشدار:</strong> شما برای روزهای کاری گذشته (${dates_string}) 
                                گزارشی ثبت نکرده‌اید.
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        `;
                        notificationArea.insertAdjacentHTML('afterbegin', alertHtml);
                    }
                })
                .catch(error => console.error('Error checking missed submissions:', error));
        }

        // Set current time
        function setCurrentTime(inputId) {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            document.getElementById(inputId).value = `${hours}:${minutes}`;
        }

        // Preview uploaded files
        function previewFiles(input) {
            const preview = document.getElementById('filePreview');
            preview.innerHTML = '';
            
            if (input.files) {
                Array.from(input.files).forEach((file, index) => {
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            img.className = 'preview-image';
                            preview.appendChild(img);
                        };
                        reader.readAsDataURL(file);
                    } else {
                        const fileInfo = document.createElement('div');
                        fileInfo.className = 'alert alert-info p-2 mb-2';
                        fileInfo.innerHTML = `<i class="bi bi-file-earmark"></i> ${file.name}`;
                        preview.appendChild(fileInfo);
                    }
                });
            }
        }

        // Form validation
        document.getElementById('mobileReportForm').addEventListener('submit', function(e) {
            const activities = document.querySelectorAll('.activity-item-mobile');
            if (activities.length === 0) {
                e.preventDefault();
                alert('لطفاً حداقل یک فعالیت اضافه کنید');
                return false;
            }
        });

        // Prevent accidental page leave
        let formChanged = false;
        document.getElementById('mobileReportForm').addEventListener('change', function() {
            formChanged = true;
        });

        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        document.getElementById('mobileReportForm').addEventListener('submit', function() {
            formChanged = false;
        });
    </script>
</body>
</html>