<?php
/**
 * Cleanup orphaned files (ref_count = 0 but still present on disk or in DB).
 * Intended for a nightly cron job:
 *   0 2 * * * php /path/to/scripts/cleanup_files.php >> /path/to/logs/file_cleanup.log 2>&1
 */

require_once __DIR__ . '/../sercon/bootstrap.php';
require_once __DIR__ . '/../shared/services/FileService.php';

$pdo = getCommonDBConnection();
$fs = new FileService($pdo);

$deleted = $fs->cleanupOrphans();
$stats = $fs->getStats();

printf(
    "[%s] orphans_cleaned=%d unique_files=%d total_refs=%d disk_bytes=%d\n",
    date('c'),
    $deleted,
    $stats['unique_files'],
    $stats['total_references'],
    $stats['total_disk_bytes']
);
