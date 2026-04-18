<?php
/**
 * Content-Addressable File Storage Service.
 *
 * Deduplicates uploads by SHA-256 content hash. Identical uploads share
 * a single on-disk copy, tracked by reference count. When the last
 * reference to a file is removed, the physical file is deleted.
 *
 * Tables: file_store (one row per unique content), file_references
 * (one row per module/entity that points to a stored file).
 *
 * Usage:
 *   $fs = new FileService($pdo);
 *   $res = $fs->store($_FILES['doc'], 'chat', 'message', $messageId, $userId);
 *   // ['file_id' => …, 'ref_id' => …, 'url' => '/storage/ab/cd/<hash>.pdf', ...]
 *
 *   $fs->removeReference($refId);
 *   $info = $fs->getByReference($refId);
 *   $files = $fs->getForEntity('chat', 'message', $messageId);
 */

class FileService
{
    private PDO $pdo;
    private string $storageRoot;
    private array $allowedMimes;
    private int $maxFileSize;

    public function __construct(PDO $pdo, ?string $storageRoot = null)
    {
        $this->pdo = $pdo;
        $this->storageRoot = $storageRoot ?: (getenv('STORAGE_ROOT') ?: (__DIR__ . '/../../storage'));
        $this->maxFileSize = (int)(getenv('UPLOAD_MAX_SIZE') ?: 52428800);
        $this->allowedMimes = [
            // Images
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
            // Documents
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            // Archives
            'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed',
            'application/x-zip-compressed',
            // Audio
            'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/webm', 'audio/mp4', 'audio/x-wav',
            // Video
            'video/mp4', 'video/webm', 'video/quicktime',
            // Text
            'text/plain', 'text/csv',
        ];
    }

    /**
     * Store an uploaded file with deduplication.
     *
     * @param array  $uploadedFile $_FILES['field'] array
     * @param string $module       'chat', 'daily_report', 'permit', ...
     * @param string $entityType   'message', 'photo', 'scan', ...
     * @param int    $entityId     ID of the parent entity
     * @param int    $userId       Uploader user id
     * @param string|null $displayName optional override for display name
     * @return array{
     *   file_id:int, ref_id:int, hash:string, url:string,
     *   original_name:string, mime_type:string, size:int, is_duplicate:bool
     * }
     */
    public function store(
        array $uploadedFile,
        string $module,
        string $entityType,
        int $entityId,
        int $userId,
        ?string $displayName = null
    ): array {
        $this->validate($uploadedFile);

        $tmp = $uploadedFile['tmp_name'];
        $hash = hash_file('sha256', $tmp);
        $mime = $this->detectMime($tmp);
        $size = (int)$uploadedFile['size'];
        $originalName = basename($uploadedFile['name']);

        $stmt = $this->pdo->prepare('SELECT id, disk_path FROM file_store WHERE hash = ?');
        $stmt->execute([$hash]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        $isDuplicate = false;

        if ($existing) {
            $fileStoreId = (int)$existing['id'];
            $diskPath = $existing['disk_path'];
            $isDuplicate = true;

            $this->pdo->prepare(
                'UPDATE file_store SET ref_count = ref_count + 1, last_referenced_at = NOW() WHERE id = ?'
            )->execute([$fileStoreId]);
        } else {
            $diskPath = $this->generatePath($hash, $originalName);
            $fullPath = $this->storageRoot . '/' . $diskPath;

            $dir = dirname($fullPath);
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException('Failed to create storage directory');
            }

            if (!is_uploaded_file($tmp)) {
                throw new RuntimeException('Not a valid uploaded file');
            }
            if (!move_uploaded_file($tmp, $fullPath)) {
                throw new RuntimeException('Failed to store file on disk');
            }
            @chmod($fullPath, 0644);

            $stmt = $this->pdo->prepare(
                'INSERT INTO file_store (hash, disk_path, original_name, mime_type, file_size) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$hash, $diskPath, $originalName, $mime, $size]);
            $fileStoreId = (int)$this->pdo->lastInsertId();
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO file_references (file_store_id, module, entity_type, entity_id, uploaded_by, display_name) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$fileStoreId, $module, $entityType, $entityId, $userId, $displayName ?: $originalName]);
        $refId = (int)$this->pdo->lastInsertId();

        return [
            'file_id' => $fileStoreId,
            'ref_id' => $refId,
            'hash' => $hash,
            'url' => '/storage/' . $diskPath,
            'original_name' => $displayName ?: $originalName,
            'mime_type' => $mime,
            'size' => $size,
            'is_duplicate' => $isDuplicate,
        ];
    }

    /**
     * Store raw content (voice recording, generated file) with deduplication.
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
        $size = strlen($content);
        if ($size > $this->maxFileSize) {
            throw new RuntimeException('Content too large');
        }
        if (!in_array($mimeType, $this->allowedMimes, true)) {
            throw new RuntimeException('Content type not allowed: ' . $mimeType);
        }

        $hash = hash('sha256', $content);

        $stmt = $this->pdo->prepare('SELECT id, disk_path FROM file_store WHERE hash = ?');
        $stmt->execute([$hash]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        $isDuplicate = false;

        if ($existing) {
            $fileStoreId = (int)$existing['id'];
            $diskPath = $existing['disk_path'];
            $isDuplicate = true;
            $this->pdo->prepare(
                'UPDATE file_store SET ref_count = ref_count + 1, last_referenced_at = NOW() WHERE id = ?'
            )->execute([$fileStoreId]);
        } else {
            $diskPath = $this->generatePath($hash, $filename);
            $fullPath = $this->storageRoot . '/' . $diskPath;
            $dir = dirname($fullPath);
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException('Failed to create storage directory');
            }
            if (file_put_contents($fullPath, $content) === false) {
                throw new RuntimeException('Failed to write file');
            }
            @chmod($fullPath, 0644);

            $stmt = $this->pdo->prepare(
                'INSERT INTO file_store (hash, disk_path, original_name, mime_type, file_size) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$hash, $diskPath, $filename, $mimeType, $size]);
            $fileStoreId = (int)$this->pdo->lastInsertId();
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO file_references (file_store_id, module, entity_type, entity_id, uploaded_by, display_name) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$fileStoreId, $module, $entityType, $entityId, $userId, $filename]);

        return [
            'file_id' => $fileStoreId,
            'ref_id' => (int)$this->pdo->lastInsertId(),
            'hash' => $hash,
            'url' => '/storage/' . $diskPath,
            'original_name' => $filename,
            'mime_type' => $mimeType,
            'size' => $size,
            'is_duplicate' => $isDuplicate,
        ];
    }

    /**
     * Remove a reference; deletes physical file when ref_count reaches 0.
     */
    public function removeReference(int $refId): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT file_store_id FROM file_references WHERE id = ?');
            $stmt->execute([$refId]);
            $fileStoreId = $stmt->fetchColumn();

            if (!$fileStoreId) {
                $this->pdo->rollBack();
                return false;
            }

            $this->pdo->prepare('DELETE FROM file_references WHERE id = ?')->execute([$refId]);
            $this->pdo->prepare('UPDATE file_store SET ref_count = GREATEST(ref_count - 1, 0) WHERE id = ?')->execute([$fileStoreId]);

            $stmt = $this->pdo->prepare('SELECT ref_count, disk_path FROM file_store WHERE id = ?');
            $stmt->execute([$fileStoreId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row && (int)$row['ref_count'] <= 0) {
                $fullPath = $this->storageRoot . '/' . $row['disk_path'];
                if (is_file($fullPath)) {
                    @unlink($fullPath);
                }
                $this->pdo->prepare('DELETE FROM file_store WHERE id = ?')->execute([$fileStoreId]);
            }

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function getByReference(int $refId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT fs.hash, fs.disk_path, fs.mime_type, fs.file_size, fs.ref_count,
                   fr.display_name, fr.module, fr.entity_type, fr.entity_id,
                   fr.uploaded_by, fr.created_at
            FROM file_references fr
            JOIN file_store fs ON fr.file_store_id = fs.id
            WHERE fr.id = ?
        ');
        $stmt->execute([$refId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['url'] = '/storage/' . $row['disk_path'];
        return $row;
    }

    public function getForEntity(string $module, string $entityType, int $entityId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT fr.id AS ref_id, fs.hash, fs.disk_path, fs.mime_type, fs.file_size,
                   fr.display_name, fr.uploaded_by, fr.created_at
            FROM file_references fr
            JOIN file_store fs ON fr.file_store_id = fs.id
            WHERE fr.module = ? AND fr.entity_type = ? AND fr.entity_id = ?
            ORDER BY fr.created_at
        ');
        $stmt->execute([$module, $entityType, $entityId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['url'] = '/storage/' . $row['disk_path'];
        }
        return $rows;
    }

    public function getStats(): array
    {
        $row = $this->pdo->query('SELECT COUNT(*) AS files, COALESCE(SUM(file_size), 0) AS total_bytes, COALESCE(SUM(ref_count), 0) AS refs FROM file_store')
            ->fetch(PDO::FETCH_ASSOC);
        $byModule = $this->pdo->query('
            SELECT module, COUNT(*) AS refs, COUNT(DISTINCT file_store_id) AS unique_files
            FROM file_references GROUP BY module
        ')->fetchAll(PDO::FETCH_ASSOC);

        $uniqueFiles = (int)$row['files'];
        $totalRefs = (int)$row['refs'];
        $diskBytes = (int)$row['total_bytes'];
        $dedupFactor = max(1, $uniqueFiles);
        $spaceSaved = $uniqueFiles > 0 ? (int)($diskBytes * ($totalRefs - $uniqueFiles) / $dedupFactor) : 0;

        return [
            'unique_files' => $uniqueFiles,
            'total_disk_bytes' => $diskBytes,
            'total_references' => $totalRefs,
            'space_saved_bytes' => $spaceSaved,
            'by_module' => $byModule,
        ];
    }

    public function cleanupOrphans(): int
    {
        $rows = $this->pdo->query('SELECT id, disk_path FROM file_store WHERE ref_count <= 0')
            ->fetchAll(PDO::FETCH_ASSOC);
        $deleted = 0;
        foreach ($rows as $row) {
            $fullPath = $this->storageRoot . '/' . $row['disk_path'];
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
            $this->pdo->prepare('DELETE FROM file_store WHERE id = ?')->execute([$row['id']]);
            $deleted++;
        }
        return $deleted;
    }

    // ── private helpers ──

    private function validate(array $file): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload error code: ' . ($file['error'] ?? -1));
        }
        if ((int)$file['size'] <= 0) {
            throw new RuntimeException('Empty file');
        }
        if ((int)$file['size'] > $this->maxFileSize) {
            throw new RuntimeException('File too large (' . $file['size'] . ' > ' . $this->maxFileSize . ')');
        }
        $mime = $this->detectMime($file['tmp_name']);
        if (!in_array($mime, $this->allowedMimes, true)) {
            throw new RuntimeException('File type not allowed: ' . $mime);
        }
    }

    private function detectMime(string $path): string
    {
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($path);
            if ($mime) {
                return $mime;
            }
        }
        return mime_content_type($path) ?: 'application/octet-stream';
    }

    private function generatePath(string $hash, string $originalName): string
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!preg_match('/^[a-z0-9]{1,10}$/', $ext)) {
            $ext = 'bin';
        }
        return substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash . '.' . $ext;
    }
}
