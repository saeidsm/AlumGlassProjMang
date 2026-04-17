<?php
// contractor_logo_manager.php - Manage Contractor Logos
require_once __DIR__ . '/../../sercon/bootstrap.php';
secureSession();
require_once __DIR__ . '/header.php';

$pdo = getProjectDBConnection('ghom');
$user_role = $_SESSION['role'];

// Only admins can manage logos
if (!in_array($user_role, ['admin', 'superuser'])) {
    die('دسترسی غیرمجاز');
}

// Load contractors from config
$config_path = __DIR__ . '/assets/js/allinone.json';
$contractor_list = [];
if (file_exists($config_path)) {
    $json = json_decode(file_get_contents($config_path), true);
    if (isset($json['regions'])) {
        foreach ($json['regions'] as $r) {
            if (!empty($r['contractor'])) {
                $contractor_list[] = $r['contractor'];
            }
        }
        $contractor_list = array_unique($contractor_list);
        sort($contractor_list);
    }
}

// Handle upload
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logo_file'])) {
    $contractor_name = $_POST['contractor_name'] ?? '';
    
    if (empty($contractor_name)) {
        $message = '<div class="alert alert-danger">نام پیمانکار را انتخاب کنید</div>';
    } elseif (empty($_FILES['logo_file']['tmp_name'])) {
        $message = '<div class="alert alert-danger">فایل لوگو را انتخاب کنید</div>';
    } else {
        $upload_dir = __DIR__ . '/uploads/contractor_logos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Validate file type
        $allowed = ['image/png', 'image/jpeg', 'image/jpg'];
        $file_type = $_FILES['logo_file']['type'];
        
        if (!in_array($file_type, $allowed)) {
            $message = '<div class="alert alert-danger">فقط فایل‌های PNG و JPG مجاز هستند</div>';
        } else {
            // Sanitize filename
            $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $contractor_name);
            $filename = $safe_name . '.png';
            $filepath = $upload_dir . $filename;
            
            // Convert to PNG and resize if needed
            $source_image = null;
            if ($file_type === 'image/jpeg' || $file_type === 'image/jpg') {
                $source_image = imagecreatefromjpeg($_FILES['logo_file']['tmp_name']);
            } elseif ($file_type === 'image/png') {
                $source_image = imagecreatefrompng($_FILES['logo_file']['tmp_name']);
            }
            
            if ($source_image) {
                // Get dimensions
                $width = imagesx($source_image);
                $height = imagesy($source_image);
                
                // Resize if too large (max 300px width)
                if ($width > 300) {
                    $new_width = 300;
                    $new_height = ($height / $width) * $new_width;
                    $resized = imagecreatetruecolor($new_width, $new_height);
                    
                    // Preserve transparency
                    imagealphablending($resized, false);
                    imagesavealpha($resized, true);
                    
                    imagecopyresampled($resized, $source_image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                    imagepng($resized, $filepath);
                    imagedestroy($resized);
                } else {
                    imagepng($source_image, $filepath);
                }
                
                imagedestroy($source_image);
                $message = '<div class="alert alert-success">✅ لوگو با موفقیت آپلود شد</div>';
            } else {
                $message = '<div class="alert alert-danger">خطا در پردازش تصویر</div>';
            }
        }
    }
}

// Get existing logos
$logo_dir = __DIR__ . '/uploads/contractor_logos/';
$existing_logos = [];
if (is_dir($logo_dir)) {
    $files = scandir($logo_dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'png') {
            $existing_logos[] = [
                'file' => $file,
                'path' => '/ghom/uploads/contractor_logos/' . $file,
                'name' => str_replace(['_', '.png'], [' ', ''], $file)
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        @font-face {
            font-family: "Samim";
            src: url("/ghom/assets/fonts/Samim-FD.woff2") format("woff2"),
                url("/ghom/assets/fonts/Samim-FD.woff") format("woff"),
                url("/ghom/assets/fonts/Samim-FD.ttf") format("truetype");
        }
        
        * {
            font-family: "Samim", Tahoma, Arial, sans-serif !important;
        }
        
        .logo-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .logo-card img {
            max-width: 100%;
            max-height: 80px;
            object-fit: contain;
            margin-bottom: 10px;
        }
        
        .logo-card h6 {
            font-size: 0.9rem;
            margin: 0;
            color: #495057;
        }
    </style>
</head>
<body class="bg-light">

<div class="container-fluid mt-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-primary">
            <i class="fas fa-image"></i> مدیریت لوگوی پیمانکاران
        </h3>
        <a href="daily_reports_dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> بازگشت
        </a>
    </div>

    <?php echo $message; ?>

    <!-- Upload Form -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                <i class="fas fa-upload"></i> آپلود لوگوی جدید
            </h5>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">نام پیمانکار</label>
                        <select name="contractor_name" class="form-select" required>
                            <option value="">انتخاب کنید...</option>
                            <?php foreach ($contractor_list as $contractor): ?>
                                <option value="<?php echo htmlspecialchars($contractor); ?>">
                                    <?php echo htmlspecialchars($contractor); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">فایل لوگو (PNG یا JPG)</label>
                        <input type="file" name="logo_file" class="form-control" 
                               accept="image/png,image/jpeg,image/jpg" required>
                        <small class="text-muted">حداکثر عرض: 300 پیکسل | فرمت نهایی: PNG</small>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-check"></i> آپلود لوگو
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Existing Logos -->
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0">
                <i class="fas fa-images"></i> لوگوهای موجود (<?php echo count($existing_logos); ?>)
            </h5>
        </div>
        <div class="card-body">
            <?php if (empty($existing_logos)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> هیچ لوگویی ثبت نشده است
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($existing_logos as $logo): ?>
                        <div class="col-md-2 col-sm-3 col-6">
                            <div class="logo-card">
                                <img src="<?php echo $logo['path']; ?>" 
                                     alt="<?php echo $logo['name']; ?>">
                                <h6><?php echo $logo['name']; ?></h6>
                                <button class="btn btn-sm btn-danger mt-2" 
                                        onclick="deleteLogo('<?php echo $logo['file']; ?>')">
                                    <i class="fas fa-trash"></i> حذف
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Usage Instructions -->
    <div class="card shadow-sm mt-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">
                <i class="fas fa-question-circle"></i> راهنمای استفاده
            </h5>
        </div>
        <div class="card-body">
            <ol>
                <li>نام پیمانکار را از لیست انتخاب کنید</li>
                <li>فایل لوگو را آپلود کنید (فرمت PNG یا JPG)</li>
                <li>لوگو به صورت خودکار در گزارشات روزانه آن پیمانکار نمایش داده می‌شود</li>
                <li>لوگو در صفحه چاپ در قسمت سمت راست هدر نمایش داده می‌شود</li>
                <li>برای تغییر لوگو، ابتدا لوگوی قبلی را حذف کنید و سپس لوگوی جدید را آپلود کنید</li>
            </ol>
            <div class="alert alert-warning mb-0">
                <i class="fas fa-exclamation-triangle"></i> 
                توصیه می‌شود از تصاویر با پس‌زمینه شفاف (PNG) استفاده کنید
            </div>
        </div>
    </div>
</div>

<script>
    async function deleteLogo(filename) {
        if (!confirm('آیا از حذف این لوگو اطمینان دارید؟')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('filename', filename);
        
        try {
            const response = await fetch('/ghom/api/manage_logos.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('✅ لوگو حذف شد');
                location.reload();
            } else {
                alert('❌ خطا: ' + result.message);
            }
        } catch (error) {
            alert('خطا در حذف لوگو');
            console.error(error);
        }
    }
</script>

</body>
</html>
<?php require_once 'footer.php'; ?>