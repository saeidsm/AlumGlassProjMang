<?php
/**
 * GLTF Optimizer & Progressive Loader for PHP
 * این اسکریپت فایل‌های بزرگ GLTF را به صورت تدریجی بارگذاری می‌کند
 * فایل: /pardis/gltf_optimizer.php
 */

class GLTFOptimizer {
    
    private $uploadDir = '/pardis/models/';
    private $optimizedDir = '/pardis/models/optimized/';
    private $maxChunkSize = 1024 * 1024; // 1MB chunks
    
    public function __construct() {
        // ایجاد دایرکتوری‌های مورد نیاز
        if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $this->optimizedDir)) {
            mkdir($_SERVER['DOCUMENT_ROOT'] . $this->optimizedDir, 0755, true);
        }
    }
    
    /**
     * بارگذاری تدریجی فایل (Progressive/Chunked Loading)
     */
    public function streamGLTF($filename) {
        $filepath = $_SERVER['DOCUMENT_ROOT'] . $this->uploadDir . $filename;
        
        if (!file_exists($filepath)) {
            http_response_code(404);
            die('File not found');
        }
        
        $filesize = filesize($filepath);
        $chunkNumber = isset($_GET['chunk']) ? (int)$_GET['chunk'] : 0;
        $offset = $chunkNumber * $this->maxChunkSize;
        
        // اگر chunk درخواستی بیشتر از حجم فایل است
        if ($offset >= $filesize) {
            http_response_code(416);
            die('Invalid chunk');
        }
        
        // تنظیم headerها
        header('Content-Type: model/gltf-binary');
        header('Content-Length: ' . min($this->maxChunkSize, $filesize - $offset));
        header('Accept-Ranges: bytes');
        header('X-Total-Chunks: ' . ceil($filesize / $this->maxChunkSize));
        header('X-Current-Chunk: ' . $chunkNumber);
        header('X-File-Size: ' . $filesize);
        
        // بارگذاری chunk
        $file = fopen($filepath, 'rb');
        fseek($file, $offset);
        echo fread($file, $this->maxChunkSize);
        fclose($file);
    }
    
    /**
     * فشرده‌سازی GLTF با PHP (Basic optimization)
     */
    public function optimizeGLTF($inputFile) {
        $filepath = $_SERVER['DOCUMENT_ROOT'] . $this->uploadDir . $inputFile;
        
        if (!file_exists($filepath)) {
            return ['error' => 'File not found'];
        }
        
        $content = file_get_contents($filepath);
        $data = json_decode($content, true);
        
        if (!$data) {
            return ['error' => 'Invalid GLTF file'];
        }
        
        // حذف اطلاعات اضافی
        if (isset($data['extras'])) {
            unset($data['extras']);
        }
        
        // حذف extensionsUsed غیرضروری
        if (isset($data['extensionsUsed'])) {
            $data['extensionsUsed'] = array_filter($data['extensionsUsed'], function($ext) {
                return in_array($ext, ['KHR_draco_mesh_compression', 'KHR_materials_pbrSpecularGlossiness']);
            });
        }
        
        // ذخیره فایل بهینه‌شده
        $outputFile = $this->optimizedDir . pathinfo($inputFile, PATHINFO_FILENAME) . '-optimized.gltf';
        $outputPath = $_SERVER['DOCUMENT_ROOT'] . $outputFile;
        
        file_put_contents($outputPath, json_encode($data, JSON_UNESCAPED_SLASHES));
        
        $originalSize = filesize($filepath);
        $optimizedSize = filesize($outputPath);
        $reduction = round((($originalSize - $optimizedSize) / $originalSize) * 100, 2);
        
        return [
            'success' => true,
            'original_size' => $this->formatBytes($originalSize),
            'optimized_size' => $this->formatBytes($optimizedSize),
            'reduction' => $reduction . '%',
            'output_file' => $outputFile
        ];
    }
    
    /**
     * تبدیل GLTF به GLB (Binary format - کوچکتر)
     */
    public function convertToGLB($gltfFile) {
        // این نیاز به کتابخانه خارجی دارد
        // پیشنهاد: استفاده از Node.js script
        $command = "gltf-pipeline -i {$gltfFile} -o {$gltfFile}.glb -b";
        exec($command, $output, $returnCode);
        
        return $returnCode === 0;
    }
    
    /**
     * دریافت اطلاعات فایل GLTF
     */
    public function getFileInfo($filename) {
        $filepath = $_SERVER['DOCUMENT_ROOT'] . $this->uploadDir . $filename;
        
        if (!file_exists($filepath)) {
            return ['error' => 'File not found'];
        }
        
        $size = filesize($filepath);
        $content = file_get_contents($filepath);
        $data = json_decode($content, true);
        
        $info = [
            'filename' => $filename,
            'size' => $this->formatBytes($size),
            'size_bytes' => $size,
            'type' => pathinfo($filename, PATHINFO_EXTENSION),
            'meshes' => isset($data['meshes']) ? count($data['meshes']) : 0,
            'materials' => isset($data['materials']) ? count($data['materials']) : 0,
            'textures' => isset($data['textures']) ? count($data['textures']) : 0,
            'nodes' => isset($data['nodes']) ? count($data['nodes']) : 0,
            'animations' => isset($data['animations']) ? count($data['animations']) : 0,
        ];
        
        return $info;
    }
    
    /**
     * فرمت کردن سایز فایل
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

// مثال استفاده:

// برای stream کردن فایل
if (isset($_GET['stream']) && isset($_GET['file'])) {
    $optimizer = new GLTFOptimizer();
    $optimizer->streamGLTF($_GET['file']);
    exit;
}

// برای دریافت اطلاعات فایل
if (isset($_GET['info']) && isset($_GET['file'])) {
    header('Content-Type: application/json');
    $optimizer = new GLTFOptimizer();
    echo json_encode($optimizer->getFileInfo($_GET['file']));
    exit;
}

// برای بهینه‌سازی فایل
if (isset($_POST['optimize']) && isset($_POST['file'])) {
    header('Content-Type: application/json');
    $optimizer = new GLTFOptimizer();
    echo json_encode($optimizer->optimizeGLTF($_POST['file']));
    exit;
}
?>