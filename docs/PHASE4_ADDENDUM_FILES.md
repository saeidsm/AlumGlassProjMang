# Phase 4 — Addendum: File Deduplication System
# سیستم مدیریت یکپارچه فایل با حذف تکراری

> این بخش به فاز 4A اضافه می‌شود — **قبل** از ساخت chat upload API پیاده‌سازی شود
> چون تمام ماژول‌ها (chat, reports, permits, letters) از این سیستم استفاده خواهند کرد.

---

## معماری: Content-Addressable Storage (CAS)

```
آپلود فایل:
                                                   ┌─ بله → فقط reference جدید بساز (0 byte ذخیره)
  فایل → [SHA-256 hash] → آیا hash قبلاً وجود داره؟ ─┤
                                                   └─ نه  → فایل ذخیره شو + ثبت در DB

حذف فایل:
  حذف reference → ref_count-- → اگه ref_count = 0 → حذف فایل فیزیکی

ویرایش فایل:
  فایل ویرایش‌شده → hash جدید ≠ hash قدیم → فایل جدید ذخیره می‌شه
  reference قدیمی → ref_count-- (حذف فیزیکی اگه 0 شد)
```

### نتیجه عملی:
- ۵ کاربر یک PDF یکسان آپلود کنن → **۱ فایل** روی دیسک، ۵ reference
- کاربر فایل رو ویرایش کنه → hash عوض میشه → **نسخه جدید** ذخیره میشه
- فایل اصلی هنوز برای ۴ نفر دیگه وجود داره
- وقتی آخرین reference حذف بشه → فایل فیزیکی پاک میشه

---

## Database Schema

```sql
-- Central file storage — one row per unique file content
CREATE TABLE IF NOT EXISTS `file_store` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `hash` CHAR(64) NOT NULL,                      -- SHA-256 hex
  `disk_path` VARCHAR(500) NOT NULL,              -- Physical path on server
  `original_name` VARCHAR(255) NOT NULL,          -- First uploader's filename
  `mime_type` VARCHAR(100) NOT NULL,
  `file_size` BIGINT UNSIGNED NOT NULL,           -- Bytes
  `ref_count` INT UNSIGNED NOT NULL DEFAULT 1,    -- How many references point here
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `last_referenced_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY idx_hash (hash),
  INDEX idx_mime (mime_type),
  INDEX idx_ref (ref_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- References from different modules to stored files
CREATE TABLE IF NOT EXISTS `file_references` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `file_store_id` INT UNSIGNED NOT NULL,
  `module` VARCHAR(50) NOT NULL,                  -- 'chat', 'daily_report', 'permit', 'letter', etc.
  `entity_type` VARCHAR(50) NOT NULL,             -- 'message', 'report_photo', 'permit_scan', etc.
  `entity_id` INT UNSIGNED NOT NULL,              -- ID of the message/report/permit
  `uploaded_by` INT NOT NULL,                     -- User ID
  `display_name` VARCHAR(255) DEFAULT NULL,       -- Custom name user gave this file
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_file (file_store_id),
  INDEX idx_entity (module, entity_type, entity_id),
  INDEX idx_user (uploaded_by),
  FOREIGN KEY (file_store_id) REFERENCES file_store(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## PHP Implementation

### `shared/services/FileService.php`:

```php
<?php
/**
 * Content-Addressable File Storage Service
 * 
 * Deduplicates files by SHA-256 hash. If the same file content
 * is uploaded multiple times, only one copy is stored on disk.
 * 
 * Usage:
 *   $fs = new FileService($pdo);
 *   
 *   // Upload (auto-deduplicates)
 *   $result = $fs->store($_FILES['doc'], 'chat', 'message', $messageId, $userId);
 *   // Returns: ['file_id' => 5, 'ref_id' => 12, 'url' => '/storage/ab/cd/abcd1234...pdf', 'is_duplicate' => true]
 *   
 *   // Delete reference (physical file only deleted when ref_count reaches 0)
 *   $fs->removeReference($refId);
 *   
 *   // Get file info
 *   $info = $fs->getByReference($refId);
 *   // Returns: ['url' => '...', 'original_name' => '...', 'mime_type' => '...', 'size' => 12345]
 */

class FileService {
    private PDO $pdo;
    private string $storageRoot;
    private array $allowedMimes;
    private int $maxFileSize;
    
    public function __construct(PDO $pdo, ?string $storageRoot = null) {
        $this->pdo = $pdo;
        $this->storageRoot = $storageRoot ?: ($_SERVER['DOCUMENT_ROOT'] . '/storage');
        $this->maxFileSize = (int)(getenv('UPLOAD_MAX_SIZE') ?: 52428800); // 50MB default
        $this->allowedMimes = [
            // Images
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
            // Documents
            'application/pdf', 
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            // Archives
            'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed',
            // Audio
            'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/webm', 'audio/mp4',
            // Video
            'video/mp4', 'video/webm',
            // Text
            'text/plain', 'text/csv',
        ];
    }
    
    /**
     * Store a file with deduplication.
     * If the exact same content already exists, just creates a new reference.
     */
    public function store(
        array $uploadedFile,    // $_FILES['field']
        string $module,         // 'chat', 'daily_report', 'permit', etc.
        string $entityType,     // 'message', 'report_photo', etc.
        int $entityId,          // ID of the parent entity
        int $userId,            // Who uploaded
        ?string $displayName = null
    ): array {
        // 1. Validate
        $this->validate($uploadedFile);
        
        // 2. Calculate hash of file content
        $hash = hash_file('sha256', $uploadedFile['tmp_name']);
        $mime = mime_content_type($uploadedFile['tmp_name']);
        $size = $uploadedFile['size'];
        $originalName = $displayName ?: basename($uploadedFile['name']);
        
        // 3. Check if this exact content already exists
        $stmt = $this->pdo->prepare("SELECT id, disk_path FROM file_store WHERE hash = ?");
        $stmt->execute([$hash]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $isDuplicate = false;
        
        if ($existing) {
            // File content already exists — just increment ref_count
            $fileStoreId = $existing['id'];
            $diskPath = $existing['disk_path'];
            $isDuplicate = true;
            
            $this->pdo->prepare(
                "UPDATE file_store SET ref_count = ref_count + 1, last_referenced_at = NOW() WHERE id = ?"
            )->execute([$fileStoreId]);
            
            // Clean up the temp upload — we don't need to store it again
            // (tmp_name is auto-cleaned by PHP, but explicit is better)
            
        } else {
            // New unique file — store on disk
            $diskPath = $this->generatePath($hash, $originalName);
            $fullPath = $this->storageRoot . '/' . $diskPath;
            
            // Create directory structure
            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            // Move file
            if (!move_uploaded_file($uploadedFile['tmp_name'], $fullPath)) {
                throw new RuntimeException('Failed to store file on disk');
            }
            
            // Insert into file_store
            $stmt = $this->pdo->prepare(
                "INSERT INTO file_store (hash, disk_path, original_name, mime_type, file_size) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$hash, $diskPath, $originalName, $mime, $size]);
            $fileStoreId = $this->pdo->lastInsertId();
        }
        
        // 4. Create reference
        $stmt = $this->pdo->prepare(
            "INSERT INTO file_references (file_store_id, module, entity_type, entity_id, uploaded_by, display_name) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$fileStoreId, $module, $entityType, $entityId, $userId, $originalName]);
        $refId = $this->pdo->lastInsertId();
        
        return [
            'file_id' => (int)$fileStoreId,
            'ref_id' => (int)$refId,
            'hash' => $hash,
            'url' => '/storage/' . $diskPath,
            'original_name' => $originalName,
            'mime_type' => $mime,
            'size' => (int)$size,
            'is_duplicate' => $isDuplicate,
        ];
    }
    
    /**
     * Store from a raw string/buffer (for voice recordings, generated files, etc.)
     */
    public function storeFromString(
        string $content,
        string $filename,
        string $mimeType,
        string $module,
        string $entityType,
        int $entityId,
        int $userId
    ): array {
        // Write to temp file and use store()
        $tmp = tempnam(sys_get_temp_dir(), 'ag_');
        file_put_contents($tmp, $content);
        
        $fakeUpload = [
            'tmp_name' => $tmp,
            'name' => $filename,
            'size' => strlen($content),
            'error' => UPLOAD_ERR_OK,
        ];
        
        try {
            // Skip move_uploaded_file check
            $hash = hash('sha256', $content);
            $stmt = $this->pdo->prepare("SELECT id, disk_path FROM file_store WHERE hash = ?");
            $stmt->execute([$hash]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                $this->pdo->prepare("UPDATE file_store SET ref_count = ref_count + 1, last_referenced_at = NOW() WHERE id = ?")->execute([$existing['id']]);
                $fileStoreId = $existing['id'];
                $diskPath = $existing['disk_path'];
                $isDuplicate = true;
            } else {
                $diskPath = $this->generatePath($hash, $filename);
                $fullPath = $this->storageRoot . '/' . $diskPath;
                $dir = dirname($fullPath);
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                rename($tmp, $fullPath);
                
                $stmt = $this->pdo->prepare("INSERT INTO file_store (hash, disk_path, original_name, mime_type, file_size) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$hash, $diskPath, $filename, $mimeType, strlen($content)]);
                $fileStoreId = $this->pdo->lastInsertId();
                $isDuplicate = false;
            }
            
            $stmt = $this->pdo->prepare("INSERT INTO file_references (file_store_id, module, entity_type, entity_id, uploaded_by, display_name) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$fileStoreId, $module, $entityType, $entityId, $userId, $filename]);
            
            return [
                'file_id' => (int)$fileStoreId,
                'ref_id' => (int)$this->pdo->lastInsertId(),
                'hash' => $hash,
                'url' => '/storage/' . $diskPath,
                'is_duplicate' => $isDuplicate,
            ];
        } finally {
            @unlink($tmp);
        }
    }
    
    /**
     * Remove a reference. Deletes physical file only when ref_count reaches 0.
     */
    public function removeReference(int $refId): bool {
        $this->pdo->beginTransaction();
        try {
            // Get the file_store_id
            $stmt = $this->pdo->prepare("SELECT file_store_id FROM file_references WHERE id = ?");
            $stmt->execute([$refId]);
            $fileStoreId = $stmt->fetchColumn();
            
            if (!$fileStoreId) {
                $this->pdo->rollBack();
                return false;
            }
            
            // Delete the reference
            $this->pdo->prepare("DELETE FROM file_references WHERE id = ?")->execute([$refId]);
            
            // Decrement ref_count
            $this->pdo->prepare("UPDATE file_store SET ref_count = ref_count - 1 WHERE id = ?")->execute([$fileStoreId]);
            
            // Check if ref_count is now 0
            $stmt = $this->pdo->prepare("SELECT ref_count, disk_path FROM file_store WHERE id = ?");
            $stmt->execute([$fileStoreId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row && (int)$row['ref_count'] <= 0) {
                // Delete physical file
                $fullPath = $this->storageRoot . '/' . $row['disk_path'];
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
                // Delete DB record
                $this->pdo->prepare("DELETE FROM file_store WHERE id = ?")->execute([$fileStoreId]);
            }
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Get file info by reference ID.
     */
    public function getByReference(int $refId): ?array {
        $stmt = $this->pdo->prepare("
            SELECT fs.hash, fs.disk_path, fs.mime_type, fs.file_size, fs.ref_count,
                   fr.display_name, fr.module, fr.entity_type, fr.entity_id, fr.uploaded_by, fr.created_at
            FROM file_references fr
            JOIN file_store fs ON fr.file_store_id = fs.id
            WHERE fr.id = ?
        ");
        $stmt->execute([$refId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        
        $row['url'] = '/storage/' . $row['disk_path'];
        return $row;
    }
    
    /**
     * Get all files for an entity (e.g., all photos for a daily report).
     */
    public function getForEntity(string $module, string $entityType, int $entityId): array {
        $stmt = $this->pdo->prepare("
            SELECT fr.id as ref_id, fs.hash, fs.disk_path, fs.mime_type, fs.file_size,
                   fr.display_name, fr.uploaded_by, fr.created_at,
                   u.first_name, u.last_name
            FROM file_references fr
            JOIN file_store fs ON fr.file_store_id = fs.id
            LEFT JOIN users u ON fr.uploaded_by = u.id
            WHERE fr.module = ? AND fr.entity_type = ? AND fr.entity_id = ?
            ORDER BY fr.created_at
        ");
        $stmt->execute([$module, $entityType, $entityId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['url'] = '/storage/' . $row['disk_path'];
        }
        return $rows;
    }
    
    /**
     * Get storage statistics.
     */
    public function getStats(): array {
        $total = $this->pdo->query("SELECT COUNT(*), SUM(file_size), SUM(ref_count) FROM file_store")->fetch(PDO::FETCH_NUM);
        $byModule = $this->pdo->query("
            SELECT fr.module, COUNT(*) as refs, COUNT(DISTINCT fr.file_store_id) as unique_files
            FROM file_references fr GROUP BY fr.module
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'unique_files' => (int)$total[0],
            'total_disk_bytes' => (int)$total[1],
            'total_references' => (int)$total[2],
            'space_saved_bytes' => (int)$total[1] * ((int)$total[2] - (int)$total[0]) / max(1, (int)$total[0]),
            'by_module' => $byModule,
        ];
    }
    
    /**
     * Cleanup orphaned files (ref_count = 0 but file still on disk).
     * Run as a cron job: php scripts/cleanup_files.php
     */
    public function cleanupOrphans(): int {
        $stmt = $this->pdo->query("SELECT id, disk_path FROM file_store WHERE ref_count <= 0");
        $deleted = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $fullPath = $this->storageRoot . '/' . $row['disk_path'];
            if (file_exists($fullPath)) unlink($fullPath);
            $this->pdo->prepare("DELETE FROM file_store WHERE id = ?")->execute([$row['id']]);
            $deleted++;
        }
        return $deleted;
    }
    
    // --- Private helpers ---
    
    private function validate(array $file): void {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload error: ' . $file['error']);
        }
        if ($file['size'] > $this->maxFileSize) {
            throw new RuntimeException('File too large: ' . $file['size'] . ' bytes (max: ' . $this->maxFileSize . ')');
        }
        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, $this->allowedMimes)) {
            throw new RuntimeException('File type not allowed: ' . $mime);
        }
    }
    
    /**
     * Generate a sharded disk path from hash.
     * Hash: abcdef1234567890...
     * Path: ab/cd/abcdef1234567890....pdf
     * 
     * This distributes files across 256 × 256 = 65,536 directories
     * preventing any single directory from having too many files.
     */
    private function generatePath(string $hash, string $originalName): string {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $shard1 = substr($hash, 0, 2);
        $shard2 = substr($hash, 2, 2);
        return $shard1 . '/' . $shard2 . '/' . $hash . '.' . $ext;
    }
}
```

### Integration with Chat API:

In `chat/api/messages.php`, replace direct file handling with FileService:

```php
// BEFORE (current plan):
// move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $filename);
// $filePath = '/chat/uploads/' . $filename;

// AFTER (with FileService):
require_once __DIR__ . '/../../shared/services/FileService.php';
$fileService = new FileService($pdo);
$result = $fileService->store($_FILES['file'], 'chat', 'message', $msgId, $userId);
$filePath = $result['url'];
// If $result['is_duplicate'] → no new disk space used!
```

### Integration with all other modules:

Same pattern for daily reports, permits, letters, etc.:

```php
// Daily report photo upload
$result = $fileService->store($_FILES['photo'], 'daily_report', 'photo', $reportId, $userId);

// Permit signed scan
$result = $fileService->store($_FILES['scan'], 'permit', 'signed_scan', $permitId, $userId);

// Letter attachment
$result = $fileService->store($_FILES['attachment'], 'letter', 'attachment', $letterId, $userId);
```

### File serving (`storage/serve.php`):

```php
<?php
/**
 * Secure file serving endpoint.
 * URL: /storage/ab/cd/abcdef1234....pdf
 * 
 * Verifies user is authenticated before serving files.
 * Sets proper caching headers (files are immutable — same hash = same content).
 */
require_once __DIR__ . '/../sercon/bootstrap.php';
secureSession();
if (!isLoggedIn()) { http_response_code(401); exit('Auth required'); }

$requestPath = $_SERVER['PATH_INFO'] ?? '';
$requestPath = ltrim($requestPath, '/');

// Validate path format: XX/XX/hash.ext
if (!preg_match('#^[a-f0-9]{2}/[a-f0-9]{2}/[a-f0-9]{64}\.\w{1,10}$#', $requestPath)) {
    http_response_code(400);
    exit('Invalid file path');
}

$fullPath = __DIR__ . '/' . $requestPath;

if (!file_exists($fullPath)) {
    http_response_code(404);
    exit('File not found');
}

// Security: ensure path doesn't escape storage directory
$realPath = realpath($fullPath);
$storageDir = realpath(__DIR__);
if (strpos($realPath, $storageDir) !== 0) {
    http_response_code(403);
    exit('Access denied');
}

// Immutable caching — same hash always means same content
$mime = mime_content_type($fullPath);
$size = filesize($fullPath);
$etag = '"' . basename($requestPath, '.' . pathinfo($requestPath, PATHINFO_EXTENSION)) . '"';

header('Content-Type: ' . $mime);
header('Content-Length: ' . $size);
header('Cache-Control: public, max-age=31536000, immutable');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');

// Handle If-None-Match (304 Not Modified)
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304);
    exit;
}

// For images/PDFs: inline. For others: download
$disposition = in_array($mime, ['image/jpeg','image/png','image/gif','image/webp','application/pdf']) 
    ? 'inline' : 'attachment';
header('Content-Disposition: ' . $disposition);

readfile($fullPath);
```

### Apache `.htaccess` for storage routing:

```apache
# In storage/.htaccess
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.*)$ serve.php/$1 [L,QSA]
```

### Migration script for existing files:

```php
<?php
/**
 * scripts/migrate_existing_files.php
 * 
 * Migrates existing uploaded files into the new CAS storage.
 * Run once after deploying the file deduplication system.
 * 
 * Usage: php scripts/migrate_existing_files.php [--dry-run]
 */
require_once __DIR__ . '/../sercon/bootstrap.php';
require_once __DIR__ . '/../shared/services/FileService.php';

$dryRun = in_array('--dry-run', $argv);
$pdo = getCommonDBConnection();
$fs = new FileService($pdo);

$dirs = [
    'ghom/uploads/permits' => ['module' => 'permit', 'entity_type' => 'permit_file'],
    'ghom/uploads/signed_permits' => ['module' => 'permit', 'entity_type' => 'signed_scan'],
    'pardis/uploads' => ['module' => 'pardis', 'entity_type' => 'upload'],
    'chat/uploads' => ['module' => 'chat', 'entity_type' => 'message'],
];

$stats = ['scanned' => 0, 'unique' => 0, 'duplicate' => 0, 'saved_bytes' => 0];

foreach ($dirs as $dir => $meta) {
    $fullDir = $_SERVER['DOCUMENT_ROOT'] . '/' . $dir;
    if (!is_dir($fullDir)) continue;
    
    $files = glob($fullDir . '/*');
    foreach ($files as $file) {
        if (is_dir($file)) continue;
        $stats['scanned']++;
        
        $hash = hash_file('sha256', $file);
        $existing = $pdo->prepare("SELECT id FROM file_store WHERE hash = ?");
        $existing->execute([$hash]);
        
        if ($existing->fetchColumn()) {
            $stats['duplicate']++;
            $stats['saved_bytes'] += filesize($file);
            echo ($dryRun ? "[DRY] " : "") . "DUPLICATE: $file\n";
        } else {
            $stats['unique']++;
            echo ($dryRun ? "[DRY] " : "") . "NEW: $file\n";
        }
        
        if (!$dryRun) {
            // ... actual migration logic
        }
    }
}

echo "\nResults:\n";
echo "  Scanned: {$stats['scanned']}\n";
echo "  Unique: {$stats['unique']}\n";
echo "  Duplicate: {$stats['duplicate']}\n";
echo "  Space savings: " . round($stats['saved_bytes'] / 1048576, 2) . " MB\n";
```

### Cron job for cleanup:

```bash
# Every night at 2 AM — clean up orphaned files
0 2 * * * php /path/to/scripts/cleanup_files.php >> /path/to/logs/file_cleanup.log 2>&1
```

### `.env` additions:

```env
# File Storage
STORAGE_ROOT=/path/to/storage
UPLOAD_MAX_SIZE=52428800
```

---

## Commits for this addition (add to Phase 4A):

```
feat(files): create FileService with SHA-256 content-addressable storage
feat(files): create file_store and file_references DB tables
feat(files): create secure file serving endpoint (storage/serve.php)
feat(files): create existing file migration script
refactor(chat): integrate FileService for chat file uploads
docs: add file deduplication architecture to ARCHITECTURE.md
```

---

## Integration order in Phase 4A:

```
1. Create FileService + DB tables         ← NEW (do this FIRST)
2. Create WebSocket server
3. Create Chat PHP API (using FileService for uploads)
4. Create Chat frontend
5. ...rest of Phase 4A
```
