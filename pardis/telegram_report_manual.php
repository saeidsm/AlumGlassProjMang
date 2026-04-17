<?php
// public_html/pardis/telegram_report_manual.php
// Manual trigger for testing or sending reports on-demand

require_once __DIR__ . '/../../sercon/bootstrap.php';
secureSession();

// Only admins can manually trigger
if (!isLoggedIn() || !in_array($_SESSION['role'] ?? '', ['admin', 'superuser'])) {
    http_response_code(403);
    die('Access Denied');
}
$pageTitle = "ارسال دستی گزارشات به تلگرام";
require_once __DIR__ . '/header_pardis.php';

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ارسال دستی گزارش تلگرام</title>
    <link href="/pardis/assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Tahoma', 'Arial', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 16px 20px;
            font-weight: 600;
        }
        #result {
            max-height: 500px;
            overflow-y: auto;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            white-space: pre-wrap;
            font-family: monospace;
            font-size: 0.9rem;
        }
        .spinner-border {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-telegram"></i> ارسال دستی گزارش روزانه به تلگرام
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            این صفحه برای ارسال دستی گزارش‌های روزانه به تلگرام استفاده می‌شود.
                            می‌توانید برای تست یا ارسال فوری گزارش از این صفحه استفاده کنید.
                        </div>
                        
                        <form id="sendReportForm">
                            <div class="mb-3">
                                <label class="form-label">تاریخ گزارش</label>
                                <select class="form-select" name="report_date" required>
                                    <option value="today">امروز</option>
                                    <option value="yesterday">دیروز</option>
                                    <option value="custom">تاریخ دلخواه</option>
                                </select>
                            </div>
                            
                            <div class="mb-3" id="customDateDiv" style="display: none;">
                                <label class="form-label">تاریخ میلادی (YYYY-MM-DD)</label>
                                <input type="date" class="form-control" name="custom_date" max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="include_images" id="includeImages" checked>
                                    <label class="form-check-label" for="includeImages">
                                        شامل تصاویر
                                    </label>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg" id="sendBtn">
                                    <i class="bi bi-send"></i> ارسال به تلگرام
                                    <span class="spinner-border spinner-border-sm ms-2" role="status"></span>
                                </button>
                            </div>
                        </form>
                        
                        <hr>
                        
                        <div id="resultContainer" style="display: none;">
                            <h6>نتیجه:</h6>
                            <div id="result"></div>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header">راهنما</div>
                    <div class="card-body">
                        <h6>نحوه راه‌اندازی ارسال خودکار:</h6>
                        <ol>
                            <li>از @BotFather در تلگرام یک ربات بسازید و توکن آن را کپی کنید</li>
                            <li>ربات را به گروه مورد نظر اضافه کنید</li>
                            <li>ID گروه را از @userinfobot یا @RawDataBot دریافت کنید</li>
                            <li>فایل <code>telegram_config.php</code> را ویرایش کنید و توکن و ID را وارد کنید</li>
                            <li>برای تست از این صفحه استفاده کنید</li>
                            <li>برای ارسال خودکار، یک Cron Job تنظیم کنید</li>
                        </ol>
                        
                        <h6 class="mt-3">نمونه Cron Job:</h6>
                        <code class="d-block bg-light p-2 rounded">
                            0 18 * * * /usr/bin/php /path/to/public_html/pardis/send_daily_telegram_report.php
                        </code>
                        <small class="text-muted">هر روز ساعت 18:00 اجرا می‌شود</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="/pardis/assets/js/jquery-3.6.0.min.js"></script>
    <script src="/pardis/assets/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelector('select[name="report_date"]').addEventListener('change', function() {
            const customDiv = document.getElementById('customDateDiv');
            if (this.value === 'custom') {
                customDiv.style.display = 'block';
            } else {
                customDiv.style.display = 'none';
            }
        });

        document.getElementById('sendReportForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('sendBtn');
            const spinner = btn.querySelector('.spinner-border');
            const resultContainer = document.getElementById('resultContainer');
            const resultDiv = document.getElementById('result');
            
            // Show loading
            btn.disabled = true;
            spinner.style.display = 'inline-block';
            resultDiv.innerHTML = 'در حال ارسال...\n';
            resultContainer.style.display = 'block';
            
            const formData = new FormData(this);
            
            fetch('telegram_report_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = '✅ گزارش با موفقیت ارسال شد!\n\n';
                    resultDiv.innerHTML += 'تعداد گزارش‌ها: ' + data.reports_count + '\n';
                    resultDiv.innerHTML += 'تعداد پیام‌های ارسالی: ' + data.messages_sent + '\n';
                    if (data.images_sent) {
                        resultDiv.innerHTML += 'تعداد تصاویر ارسالی: ' + data.images_sent + '\n';
                    }
                    resultDiv.innerHTML += '\nزمان: ' + data.timestamp;
                } else {
                    resultDiv.innerHTML = '❌ خطا: ' + data.message;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = '❌ خطای شبکه: ' + error.message;
            })
            .finally(() => {
                btn.disabled = false;
                spinner.style.display = 'none';
            });
        });
    </script>
</body>
</html>