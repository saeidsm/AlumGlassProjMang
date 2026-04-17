<?php

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/jdf.php';

class ProjectManager {
    private $pdo;
    private $user_id;
    
    public function __construct($pdo, $user_id) {
        if (!$pdo instanceof PDO) {
            throw new Exception('Database connection is required and must be a PDO object.');
        }
        $this->pdo = $pdo;
        $this->user_id = $user_id;
    }
    
    private function calculateTotalDays($start_date, $end_date) {
        if (empty($start_date) || empty($end_date)) return 0;
        try {
            list($sy, $sm, $sd) = explode('/', $start_date);
            list($ey, $em, $ed) = explode('/', $end_date);
            $start_timestamp = jmktime(0, 0, 0, $sm, $sd, $sy);
            $end_timestamp = jmktime(0, 0, 0, $em, $ed, $ey);
            if ($start_timestamp > $end_timestamp) return 0;
            $diff = $end_timestamp - $start_timestamp;
            return floor($diff / (60 * 60 * 24)) + 1;
        } catch (Exception $e) { return 0; }
    }
    
    private function addDaysToDate($date_string, $days) {
        if (empty($date_string) || !is_numeric($days) || $days <= 0) return $date_string;
        try {
            list($y, $m, $d) = explode('/', $date_string);
            $timestamp = jmktime(0, 0, 0, $m, $d, $y);
            $new_timestamp = $timestamp + (($days - 1) * 24 * 60 * 60);
            return jdate('Y/m/d', $new_timestamp);
        } catch (Exception $e) { return $date_string; }
    }
    
    
public function create($data) {
    // 1. Get settings first, as they are needed for adjustments.
    $weekends = [6]; // Default: Friday off
    $holidays = [];
    if (isset($data['settings'])) {
        $settings = json_decode($data['settings'], true);
        $weekends = $settings['weekends'] ?? [6];
        $holidays = $settings['holidays'] ?? [];
    }

    // 2. Adjust user-provided start and end dates BEFORE any calculations.
    if (!empty($data['start_date'])) {
        $data['start_date'] = $this->adjustDateToWorkingDay($data['start_date'], $weekends, $holidays);
    }
    if (!empty($data['end_date'])) {
        $data['end_date'] = $this->adjustDateToWorkingDay($data['end_date'], $weekends, $holidays);
    }

    // 3. Proceed with existing calculation logic using the now-clean dates.
    if (!empty($data['days_to_add']) && !empty($data['start_date']) && empty($data['end_date'])) {
        $data['end_date'] = $this->addWorkingDays($data['start_date'], (int)$data['days_to_add'], $weekends, $holidays);
    }
    
    $total_days = $this->countWorkingDays($data['start_date'], $data['end_date'], $weekends, $holidays);
    
    // 4. Save to the database.
    try {
        $sort_order = time();
        $sql = "INSERT INTO project_management (title, type, parent_id, sort_order, description, start_date, end_date, days_to_add, total_days, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute([
            $data['title'], $data['type'], empty($data['parent_id']) ? null : $data['parent_id'],
            $sort_order, $data['description'] ?? null, empty($data['start_date']) ? null : $data['start_date'],
            empty($data['end_date']) ? null : $data['end_date'], $data['days_to_add'] ?? 0,
            $total_days, $this->user_id
        ]);
        if ($result) return ['success' => true, 'message' => 'آیتم با موفقیت ایجاد شد', 'id' => $this->pdo->lastInsertId()];
        return ['success' => false, 'message' => 'خطا در ایجاد آیتم'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'خطا در پایگاه داده: ' . $e->getMessage()];
    }
}
    
public function update($data) {
    // 1. Get settings.
    $weekends = [6];
    $holidays = [];
    if (isset($data['settings'])) {
        $settings = json_decode($data['settings'], true);
        $weekends = $settings['weekends'] ?? [6];
        $holidays = $settings['holidays'] ?? [];
    }

    // 2. Adjust user-provided dates.
    if (!empty($data['start_date'])) {
        $data['start_date'] = $this->adjustDateToWorkingDay($data['start_date'], $weekends, $holidays);
    }
    if (!empty($data['end_date'])) {
        $data['end_date'] = $this->adjustDateToWorkingDay($data['end_date'], $weekends, $holidays);
    }
    
    // 3. Perform calculations.
    if (!empty($data['days_to_add']) && !empty($data['start_date'])) {
        $data['end_date'] = $this->addWorkingDays($data['start_date'], (int)$data['days_to_add'], $weekends, $holidays);
    }
    
    $total_days = $this->countWorkingDays($data['start_date'], $data['end_date'], $weekends, $holidays);
    
    // 4. Save to the database.
    try {
        $sql = "UPDATE project_management SET title = ?, type = ?, parent_id = ?, description = ?, start_date = ?, end_date = ?, days_to_add = ?, total_days = ?, updated_at = NOW() WHERE id = ? AND user_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute([
            $data['title'], $data['type'], empty($data['parent_id']) ? null : $data['parent_id'],
            $data['description'] ?? null, empty($data['start_date']) ? null : $data['start_date'],
            empty($data['end_date']) ? null : $data['end_date'], $data['days_to_add'] ?? 0,
            $total_days, $data['id'], $this->user_id
        ]);
        if ($result && $stmt->rowCount() > 0) return ['success' => true, 'message' => 'آیتم با موفقیت به‌روزرسانی شد'];
        return ['success' => false, 'message' => 'آیتم یافت نشد یا تغییری انجام نشد'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'خطا در پایگاه داده: ' . $e->getMessage()];
    }
}
    private function adjustDateToWorkingDay($date_str, $weekends, $holidays) {
        if (empty($date_str)) return $date_str;

        list($y, $m, $d) = explode('/', $date_str);
        $timestamp = jmktime(0, 0, 0, $m, $d, $y);
        
        // Loop as long as the current date is a non-working day
        while (
            in_array((int)jdate('w', $timestamp), $weekends) || 
            in_array(jdate('Y/m/d', $timestamp), $holidays)
        ) {
            // It's a non-working day, so add one day and check again.
            $timestamp += 86400; // 86400 seconds = 1 day
        }

        return jdate('Y/m/d', $timestamp);
    }
    
     public function bulk_Delete($ids) {
        if (!is_array($ids) || empty($ids)) {
            return ['success' => false, 'message' => 'هیچ شناسه‌ای برای حذف ارائه نشده است'];
        }

        // Use the same recursive helper to find all children of all selected items
        $allIdsToDelete = [];
        foreach ($ids as $id) {
            $this->_collectIdsToDelete($id, $allIdsToDelete);
        }
        
        // Remove duplicates in case a parent and its child were both selected
        $uniqueIds = array_unique($allIdsToDelete);
        if (empty($uniqueIds)) {
            return ['success' => true, 'message' => 'آیتمی برای حذف یافت نشد'];
        }

        $this->pdo->beginTransaction();
        try {
            $placeholders = implode(',', array_fill(0, count($uniqueIds), '?'));
            $sql = "DELETE FROM project_management WHERE id IN ($placeholders) AND user_id = ?";
            
            $params = array_values($uniqueIds); // Ensure keys are numeric for execute
            $params[] = $this->user_id;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            $this->pdo->commit();
            return ['success' => true, 'message' => 'آیتم‌های منتخب و زیرمجموعه‌هایشان با موفقیت حذف شدند'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'خطا در حذف گروهی از پایگاه داده'];
        }
    }
    
 public function reorder($ids) {
        if (!is_array($ids) || empty($ids)) {
            return ['success' => false, 'message' => 'اطلاعات ترتیب نامعتبر است'];
        }

        $this->pdo->beginTransaction();
        try {
            $sql = "UPDATE project_management SET sort_order = ? WHERE id = ? AND user_id = ?";
            $stmt = $this->pdo->prepare($sql);
            
            // Loop through the received IDs and update their sort_order
            // The index in the array becomes the new sort_order
            foreach ($ids as $index => $id) {
                $stmt->execute([$index, $id, $this->user_id]);
            }
            
            $this->pdo->commit();
            return ['success' => true, 'message' => 'ترتیب جدید با موفقیت ذخیره شد'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'خطا در ذخیره ترتیب جدید در پایگاه داده'];
        }
    }
    private function _collectIdsToDelete($parentId, &$idsToDelete) {
        // Add the parent ID to the list first
        $idsToDelete[] = $parentId;
        
        // Find all direct children of this parent
        $childSql = "SELECT id FROM project_management WHERE parent_id = ? AND user_id = ?";
        $childStmt = $this->pdo->prepare($childSql);
        $childStmt->execute([$parentId, $this->user_id]);
        $children = $childStmt->fetchAll(PDO::FETCH_ASSOC);

        // For each child, recursively call this function
        foreach ($children as $child) {
            $this->_collectIdsToDelete($child['id'], $idsToDelete);
        }
    }

    /**
     * Deletes an item and all its descendants.
     *
     * @param int $id The ID of the top-level item to delete.
     * @return array The result of the operation.
     */
    public function delete($id) {
        // Step 1: Collect all IDs to be deleted in a flat list.
        $idsToDelete = [];
        $this->_collectIdsToDelete($id, $idsToDelete);
        
        if (empty($idsToDelete)) {
            // This can happen if the ID doesn't exist.
            return ['success' => true, 'message' => 'آیتم برای حذف یافت نشد'];
        }

        // Step 2: Perform a single delete query within a transaction.
        $this->pdo->beginTransaction();
        try {
            // Create the correct number of '?' placeholders for the IN clause
            $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));
            
            $deleteSql = "DELETE FROM project_management WHERE id IN ($placeholders) AND user_id = ?";
            $deleteStmt = $this->pdo->prepare($deleteSql);
            
            // The parameters for execute() are the IDs followed by the user_id.
            $params = $idsToDelete;
            $params[] = $this->user_id;
            
            $deleteStmt->execute($params);

            $this->pdo->commit();
            
            return ['success' => true, 'message' => 'آیتم و تمام زیرمجموعه‌های آن با موفقیت حذف شدند'];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            // logError("Error deleting items: " . $e->getMessage()); // It's good practice to log the actual error
            return ['success' => false, 'message' => 'خطا در عملیات حذف از پایگاه داده'];
        }
    }

    // --- REWRITTEN MOVE FUNCTION ---
    public function move($data) {
        try {
            $id = $data['id'];
            $direction = $data['direction'];
            
            // Get the item we want to move
            $itemSql = "SELECT id, parent_id, sort_order FROM project_management WHERE id = ? AND user_id = ?";
            $itemStmt = $this->pdo->prepare($itemSql);
            $itemStmt->execute([$id, $this->user_id]);
            $item = $itemStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$item) {
                return ['success' => false, 'message' => 'آیتم برای جابجایی یافت نشد'];
            }
            
            // Find the item to swap with
            $targetSql = "SELECT id, sort_order FROM project_management WHERE ";
            $params = [];
            
            // Build query for siblings
            if ($item['parent_id'] === null) {
                $targetSql .= "parent_id IS NULL";
            } else {
                $targetSql .= "parent_id = ?";
                $params[] = $item['parent_id'];
            }
            $targetSql .= " AND user_id = ?";
            $params[] = $this->user_id;
            
            // Determine which item to find (the one above or below)
            if ($direction === 'up') {
                $targetSql .= " AND sort_order < ? ORDER BY sort_order DESC LIMIT 1";
                $params[] = $item['sort_order'];
            } else { // 'down'
                $targetSql .= " AND sort_order > ? ORDER BY sort_order ASC LIMIT 1";
                $params[] = $item['sort_order'];
            }
            
            $targetStmt = $this->pdo->prepare($targetSql);
            $targetStmt->execute($params);
            $targetItem = $targetStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$targetItem) {
                return ['success' => false, 'message' => 'امکان جابجایی بیشتر وجود ندارد'];
            }
            
            // Swap the sort_order values
            $this->pdo->beginTransaction();
            
            $updateItemSql = "UPDATE project_management SET sort_order = ? WHERE id = ?";
            $stmt1 = $this->pdo->prepare($updateItemSql);
            $stmt1->execute([$targetItem['sort_order'], $item['id']]);
            
            $updateTargetSql = "UPDATE project_management SET sort_order = ? WHERE id = ?";
            $stmt2 = $this->pdo->prepare($updateTargetSql);
            $stmt2->execute([$item['sort_order'], $targetItem['id']]);
            
            $this->pdo->commit();
            
            return ['success' => true, 'message' => 'آیتم با موفقیت جابجا شد'];

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            // logError("خطا در جابجایی آیتم پروژه: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطا در پایگاه داده هنگام جابجایی'];
        }
    }
public function get_comments($item_id) {
    try {
        // --- STEP 1: Get all comments from the PROJECT database (No change here) ---
        $sqlComments = "
            SELECT id, user_id, comment_text, created_at 
            FROM project_comments
            WHERE item_id = ?
            ORDER BY created_at ASC
        ";
        $stmtComments = $this->pdo->prepare($sqlComments);
        $stmtComments->execute([$item_id]);
        $comments = $stmtComments->fetchAll(PDO::FETCH_ASSOC);

        if (empty($comments)) {
            return ['success' => true, 'data' => []];
        }

        // --- STEP 2: Collect user IDs (No change here) ---
        $userIds = array_unique(array_column($comments, 'user_id'));

        // --- STEP 3: Get user information from the MAIN database (This is changed) ---
        $mainDb = getCommonDBConnection();
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        
        // THE CHANGE: Select first_name and last_name instead of username.
        $sqlUsers = "SELECT id, first_name, last_name FROM users WHERE id IN ($placeholders)";
        
        $stmtUsers = $mainDb->prepare($sqlUsers);
        $stmtUsers->execute($userIds);
        $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

        // --- STEP 4: Create the user map (This is changed) ---
        $userMap = [];
        foreach ($users as $user) {
            // THE CHANGE: Combine first_name and last_name into a full name.
            // trim() handles cases where one name might be missing.
            $fullName = trim($user['first_name'] . ' ' . $user['last_name']);
            
            // Only add to the map if the name is not empty.
            if (!empty($fullName)) {
                $userMap[$user['id']] = $fullName;
            }
        }

        // --- STEP 5: Combine the data (No change here, it works automatically) ---
        foreach ($comments as &$comment) {
            $userId = $comment['user_id'];
            // The key remains 'username' so the frontend doesn't need to change.
            // The value is now the full name from our map.
            $comment['username'] = $userMap[$userId] ?? "کاربر " . $userId;
        }

        return ['success' => true, 'data' => $comments];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'خطا در دریافت نظرات: ' . $e->getMessage()];
    }
}

    public function add_comment($data) {
        try {
            $item_id = $data['item_id'];
            $comment_text = $data['comment_text'];
            
            if (empty(trim($comment_text))) {
                return ['success' => false, 'message' => 'متن نظر نمی‌تواند خالی باشد.'];
            }

            $sql = "INSERT INTO project_comments (item_id, user_id, comment_text) VALUES (?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([$item_id, $this->user_id, $comment_text]);

            if ($result) {
                return ['success' => true, 'message' => 'نظر با موفقیت ثبت شد.'];
            }
            return ['success' => false, 'message' => 'خطا در ثبت نظر.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'خطا در پایگاه داده: ' . $e->getMessage()];
        }
    }
    // --- UPDATED LIST FUNCTION ---
public function list($project_id = null) {
        try {
            // If no specific project ID is given, return all items for the user as before.
            if (is_null($project_id)) {
                $sql = "SELECT *, 
          (SELECT COUNT(*) FROM project_comments WHERE item_id = pm.id) AS comment_count 
        FROM project_management pm 
        WHERE pm.user_id = ? 
        ORDER BY pm.parent_id, pm.sort_order ASC";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$this->user_id]);
            } else {
                // If a project ID IS provided, fetch only that project and all its descendants.
                // This is a recursive query.
                $sql = "
                    WITH RECURSIVE project_hierarchy AS (
        SELECT *, (SELECT COUNT(*) FROM project_comments WHERE item_id = pm.id) AS comment_count
        FROM project_management pm WHERE id = ? AND user_id = ?
        
        UNION ALL
        
        SELECT p.*, (SELECT COUNT(*) FROM project_comments WHERE item_id = p.id) AS comment_count
        FROM project_management p
        INNER JOIN project_hierarchy ph ON p.parent_id = ph.id
    )
    SELECT * FROM project_hierarchy ORDER BY parent_id, sort_order ASC;
";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$project_id, $this->user_id]);
            }

            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Recalculate days to ensure data is fresh
            foreach ($items as &$item) {
                $item['total_days'] = $this->calculateTotalDays($item['start_date'], $item['end_date']);
            }
            
            return ['success' => true, 'data' => $items];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'خطا در دریافت اطلاعات: ' . $e->getMessage()];
        }
    }
 public function duplicate($id) {
        $this->pdo->beginTransaction();
        try {
            // Step 1: Fetch the original top-level item to be copied.
            $stmt = $this->pdo->prepare("SELECT * FROM project_management WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $this->user_id]);
            $originalItem = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$originalItem) {
                throw new Exception('آیتم مورد نظر برای کپی یافت نشد.');
            }

            // Step 2: Create the new top-level item (e.g., "Project A (کپی)").
            $sqlInsert = "INSERT INTO project_management (title, type, parent_id, sort_order, description, start_date, end_date, days_to_add, total_days, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmtInsert = $this->pdo->prepare($sqlInsert);
            
            $stmtInsert->execute([
                $originalItem['title'] . ' (کپی)',
                $originalItem['type'],
                $originalItem['parent_id'], // It keeps its original parent, if it had one.
                time(), // Set a new sort order to place it at the end.
                $originalItem['description'],
                $originalItem['start_date'],
                $originalItem['end_date'],
                $originalItem['days_to_add'],
                $originalItem['total_days'],
                $this->user_id
            ]);
            $newItemId = $this->pdo->lastInsertId();

            // Step 3: Kick off the recursive copy for all children.
            $this->_recursive_copy($id, $newItemId);

            $this->pdo->commit();
            return ['success' => true, 'message' => 'آیتم و تمام زیرمجموعه‌های آن با موفقیت کپی شدند.'];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'خطا در عملیات کپی: ' . $e->getMessage()];
        }
    }

    /**
     * Private helper function to recursively copy all descendants of an item.
     *
     * @param int $old_parent_id The ID of the item in the original tree.
     * @param int $new_parent_id The ID of the corresponding newly created item.
     */
    private function _recursive_copy($old_parent_id, $new_parent_id) {
        // Find all direct children of the original item.
        $stmt = $this->pdo->prepare("SELECT * FROM project_management WHERE parent_id = ? AND user_id = ? ORDER BY sort_order ASC");
        $stmt->execute([$old_parent_id, $this->user_id]);
        $children = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Base case: If there are no children, stop the recursion for this branch.
        if (empty($children)) {
            return;
        }
        
        $sqlInsert = "INSERT INTO project_management (title, type, parent_id, sort_order, description, start_date, end_date, days_to_add, total_days, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmtInsert = $this->pdo->prepare($sqlInsert);

        foreach ($children as $child) {
            // Create a copy of the child, assigning it to the NEW parent.
            $stmtInsert->execute([
                $child['title'], // Sub-items don't get the "(کپی)" suffix.
                $child['type'],
                $new_parent_id, // This is the crucial step!
                $child['sort_order'], // Preserve the relative order of children.
                $child['description'],
                $child['start_date'],
                $child['end_date'],
                $child['days_to_add'],
                $child['total_days'],
                $this->user_id
            ]);
            $newChildId = $this->pdo->lastInsertId();

            // RECURSION: Now do the same process for any grandchildren.
            $this->_recursive_copy($child['id'], $newChildId);
        }
    }
    public function duplicateGroup($id) {
        // This function is complex, let's keep it as is unless it's a problem.
        // It needs to be updated to handle the new sort_order column if you use it frequently.
        $this->pdo->beginTransaction();
        try {
            $sqlGroup = "SELECT * FROM project_management WHERE id = ? AND user_id = ?";
            $stmtGroup = $this->pdo->prepare($sqlGroup);
            $stmtGroup->execute([$id, $this->user_id]);
            $originalGroup = $stmtGroup->fetch(PDO::FETCH_ASSOC);
            if (!$originalGroup) return ['success' => false, 'message' => 'آیتم مورد نظر یافت نشد'];
            $newGroupTitle = $originalGroup['title'] . ' (کپی)';
            // Add new sort order
            $newSortOrder = time();
            $sqlInsertGroup = "INSERT INTO project_management (title, type, parent_id, sort_order, description, start_date, end_date, days_to_add, total_days, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmtInsertGroup = $this->pdo->prepare($sqlInsertGroup);
            $stmtInsertGroup->execute([ $newGroupTitle, $originalGroup['type'], $originalGroup['parent_id'], $newSortOrder, $originalGroup['description'], $originalGroup['start_date'], $originalGroup['end_date'], $originalGroup['days_to_add'], $originalGroup['total_days'], $this->user_id ]);
            $newGroupId = $this->pdo->lastInsertId();
            $sqlTasks = "SELECT * FROM project_management WHERE parent_id = ? AND user_id = ? ORDER BY sort_order ASC";
            $stmtTasks = $this->pdo->prepare($sqlTasks);
            $stmtTasks->execute([$id, $this->user_id]);
            $tasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);
            $sqlInsertTask = "INSERT INTO project_management (title, type, parent_id, sort_order, description, start_date, end_date, days_to_add, total_days, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmtInsertTask = $this->pdo->prepare($sqlInsertTask);
            foreach ($tasks as $task) {
                $stmtInsertTask->execute([ $task['title'], $task['type'], $newGroupId, $task['sort_order'], $task['description'], $task['start_date'], $task['end_date'], $task['days_to_add'], $task['total_days'], $this->user_id ]);
            }
            $this->pdo->commit();
            return ['success' => true, 'message' => 'آیتم و زیرمجموعه‌های آن با موفقیت کپی شدند'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            // logError("خطا در کپی کردن آیتم: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطا در عملیات پایگاه داده هنگام کپی کردن'];
        }
    }
    
public function getStats($project_id = null) {
        try {
            $listResult = $this->list($project_id);
            if (!$listResult['success']) {
                return ['success' => false, 'message' => 'Could not fetch items for stats.'];
            }
            $items = $listResult['data'];
            
            // THIS IS THE CORRECTED ARRAY INITIALIZATION
            $stats = [
                'total_items' => count($items),
                'total_projects' => 0,
                'total_groups' => 0,
                'total_tasks' => 0,
                'total_subtasks' => 0,
                'total_days_sum' => 0,
                'earliest_start' => null,
                'latest_end' => null,
                'completed_items' => 0, // <-- Added
                'active_items' => 0      // <-- Added
            ];
            
            $earliest_start_ts = null;
            $latest_end_ts = null;
            
            foreach($items as $item) {
                if ($item['type'] == 'project') $stats['total_projects']++;
                if ($item['type'] == 'group') $stats['total_groups']++;
                if ($item['type'] == 'task') $stats['total_tasks']++;
                if ($item['type'] == 'subtask') $stats['total_subtasks']++;
                
                // These lines will now work without a warning
                if (isset($item['status']) && $item['status'] == 'completed') $stats['completed_items']++;
                if (isset($item['status']) && $item['status'] == 'active') $stats['active_items']++;
                
                $stats['total_days_sum'] += $item['total_days'];
                
                if (!empty($item['start_date'])) {
                    list($y, $m, $d) = explode('/', $item['start_date']);
                    $ts = jmktime(0,0,0, $m, $d, $y);
                    if (is_null($earliest_start_ts) || $ts < $earliest_start_ts) $earliest_start_ts = $ts;
                }
                if (!empty($item['end_date'])) {
                    list($y, $m, $d) = explode('/', $item['end_date']);
                    $ts = jmktime(0,0,0, $m, $d, $y);
                    if (is_null($latest_end_ts) || $ts > $latest_end_ts) $latest_end_ts = $ts;
                }
            }
            
            if (!is_null($earliest_start_ts)) $stats['earliest_start'] = jdate('Y/m/d', $earliest_start_ts);
            if (!is_null($latest_end_ts)) $stats['latest_end'] = jdate('Y/m/d', $latest_end_ts);
            
            return ['success' => true, 'data' => $stats];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'خطا در دریافت آمار'];
        }
    }
     public function bulkDelete($ids) {
        if (!is_array($ids) || empty($ids)) {
            return ['success' => false, 'message' => 'هیچ شناسه‌ای برای حذف ارائه نشده است'];
        }

        // Use the same recursive helper to find all children of all selected items
        $allIdsToDelete = [];
        foreach ($ids as $id) {
            $this->_collectIdsToDelete($id, $allIdsToDelete);
        }
        
        // Remove duplicates in case a parent and its child were both selected
        $uniqueIds = array_unique($allIdsToDelete);
        if (empty($uniqueIds)) {
            return ['success' => true, 'message' => 'آیتمی برای حذف یافت نشد'];
        }

        $this->pdo->beginTransaction();
        try {
            $placeholders = implode(',', array_fill(0, count($uniqueIds), '?'));
            $sql = "DELETE FROM project_management WHERE id IN ($placeholders) AND user_id = ?";
            
            $params = array_values($uniqueIds); // Ensure keys are numeric for execute
            $params[] = $this->user_id;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            $this->pdo->commit();
            return ['success' => true, 'message' => 'آیتم‌های منتخب و زیرمجموعه‌هایشان با موفقیت حذف شدند'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'خطا در حذف گروهی از پایگاه داده'];
        }
    }
    public function selective_copy($ids) {
        if (!is_array($ids) || empty($ids)) {
            return ['success' => false, 'message' => 'هیچ شناسه‌ای برای کپی ارائه نشده است'];
        }
        $this->pdo->beginTransaction();
        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "SELECT * FROM project_management WHERE id IN ($placeholders) AND user_id = ? ORDER BY parent_id, sort_order ASC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge($ids, [$this->user_id]));
            $itemsToCopy = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($itemsToCopy)) {
                return ['success' => false, 'message' => 'هیچ آیتمی برای کپی یافت نشد'];
            }
            $oldIdToNewIdMap = [];
            $sqlInsert = "INSERT INTO project_management (title, type, parent_id, sort_order, description, start_date, end_date, days_to_add, total_days, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmtInsert = $this->pdo->prepare($sqlInsert);
            foreach ($itemsToCopy as $item) {
                $oldId = $item['id'];
                $oldParentId = $item['parent_id'];
                $newParentId = isset($oldIdToNewIdMap[$oldParentId]) ? $oldIdToNewIdMap[$oldParentId] : $oldParentId;
                $stmtInsert->execute([
                    $item['title'] . ' (کپی)', $item['type'], $newParentId,
                    time() + $oldId, $item['description'], $item['start_date'],
                    $item['end_date'], $item['days_to_add'], $item['total_days'],
                    $this->user_id
                ]);
                $oldIdToNewIdMap[$oldId] = $this->pdo->lastInsertId();
            }
            $this->pdo->commit();
            return ['success' => true, 'message' => 'آیتم‌های منتخب با موفقیت کپی شدند'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'خطا در کپی کردن از پایگاه داده: ' . $e->getMessage()];
        }
    }

    public function selectiveCopy($ids) {
        if (!is_array($ids) || empty($ids)) {
            return ['success' => false, 'message' => 'هیچ شناسه‌ای برای کپی ارائه نشده است'];
        }

        $this->pdo->beginTransaction();
        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "SELECT * FROM project_management WHERE id IN ($placeholders) AND user_id = ? ORDER BY parent_id, sort_order ASC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge($ids, [$this->user_id]));
            $itemsToCopy = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($itemsToCopy)) {
                return ['success' => false, 'message' => 'هیچ آیتمی برای کپی یافت نشد'];
            }

            $oldIdToNewIdMap = [];
            $sqlInsert = "INSERT INTO project_management (title, type, parent_id, sort_order, description, start_date, end_date, days_to_add, total_days, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmtInsert = $this->pdo->prepare($sqlInsert);

            foreach ($itemsToCopy as $item) {
                $oldId = $item['id'];
                $oldParentId = $item['parent_id'];
                
                $newParentId = isset($oldIdToNewIdMap[$oldParentId]) ? $oldIdToNewIdMap[$oldParentId] : $oldParentId;

                $stmtInsert->execute([
                    $item['title'] . ' (کپی)',
                    $item['type'],
                    $newParentId,
                    time() + $oldId, 
                    $item['description'],
                    $item['start_date'],
                    $item['end_date'],
                    $item['days_to_add'],
                    $item['total_days'],
                    $this->user_id
                ]);
                
                $oldIdToNewIdMap[$oldId] = $this->pdo->lastInsertId();
            }

            $this->pdo->commit();
            return ['success' => true, 'message' => 'آیتم‌های منتخب با موفقیت کپی شدند'];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'خطا در کپی کردن از پایگاه داده: ' . $e->getMessage()];
        }
    }
     private function addWorkingDays($start_date_str, $days_to_add, $weekends, $holidays) {
        if (empty($start_date_str) || !is_numeric($days_to_add) || $days_to_add <= 0) return $start_date_str;
        
        list($y, $m, $d) = explode('/', $start_date_str);
        $timestamp = jmktime(0, 0, 0, $m, $d, $y);
        
        $added_days = 0;
        while ($added_days < $days_to_add) {
            $weekday = (int)jdate('w', $timestamp);
            $current_date_str = jdate('Y/m/d', $timestamp);
            
            // Only count the day if it's NOT a weekend and NOT a holiday
            if (!in_array($weekday, $weekends) && !in_array($current_date_str, $holidays)) {
                $added_days++;
            }
            
            // Move to the next day, unless we have already found our target date
            if ($added_days < $days_to_add) {
                $timestamp += 86400; // Add one day in seconds
            }
        }
        return jdate('Y/m/d', $timestamp);
    }

    private function countWorkingDays($start_date_str, $end_date_str, $weekends, $holidays) {
        if (empty($start_date_str) || empty($end_date_str)) return 0;

        list($sy, $sm, $sd) = explode('/', $start_date_str);
        list($ey, $em, $ed) = explode('/', $end_date_str);
        $start_timestamp = jmktime(0, 0, 0, $sm, $sd, $sy);
        $end_timestamp = jmktime(0, 0, 0, $em, $ed, $ey);

        if ($start_timestamp > $end_timestamp) return 0;
        
        $working_days = 0;
        $current_timestamp = $start_timestamp;

        while ($current_timestamp <= $end_timestamp) {
            $weekday = (int)jdate('w', $current_timestamp);
            $current_date_str = jdate('Y/m/d', $current_timestamp);

            if (!in_array($weekday, $weekends) && !in_array($current_date_str, $holidays)) {
                $working_days++;
            }
            $current_timestamp += 86400;
        }
        return $working_days;
    }
}

// --- Start of Executable Code ---
// This part remains mostly the same

secureSession();

if (!isLoggedIn()) {
    header('Location: /login.php?msg=login_required');
    exit();
}

if (!in_array($_SESSION['role'], ['admin', 'superuser', 'user', 'cat', 'car', 'coa', 'crs'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'عدم دسترسی']);
    exit;
}

$expected_project_key = 'ghom';
if (!isset($_SESSION['current_project_config_key']) || $_SESSION['current_project_config_key'] !== $expected_project_key) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'عدم دسترسی به پروژه']);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getProjectDBConnection('ghom');
    if (!$pdo instanceof PDO) {
        throw new Exception('Failed to get a valid ghom database connection object.');
    }
    
    $projectManager = new ProjectManager($pdo, $_SESSION['user_id']);
    
    $action = $_REQUEST['action'] ?? 'list';
    
    switch ($action) {
        case 'create':
        case 'update':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'متد نامعتبر']);
                break;
            }
            if ($action === 'update' && empty($_POST['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'شناسه آیتم برای بروزرسانی الزامی است']);
                break;
            }
            // These actions expect the full $_POST array
            $result = $projectManager->{$action}($_POST);
            echo json_encode($result);
            break;
            case 'delete':
                        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id'])) {
                            http_response_code(400);
                            echo json_encode(['success' => false, 'message' => 'درخواست نامعتبر برای حذف']);
                            break;
                        }
                        // This action only needs the ID
                        $result = $projectManager->delete($_POST['id']);
                        echo json_encode($result);
                        break;
            
        case 'move':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'متد نامعتبر']);
                break;
            }
            if (empty($_POST['id']) || empty($_POST['direction'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'شناسه آیتم و جهت جابجایی الزامی است']);
                break;
            }
            if (!in_array($_POST['direction'], ['up', 'down'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'جهت جابجایی نامعتبر']);
                break;
            }
            $result = $projectManager->move($_POST);
            echo json_encode($result);
            break;
            
          case 'list':
            $project_id = $_GET['project_id'] ?? null;
            $result = $projectManager->list($project_id);
            echo json_encode($result);
            break;
            
        case 'stats':
            $project_id = $_GET['project_id'] ?? null;
            $result = $projectManager->getStats($project_id);
            echo json_encode($result);
            break;
        
        case 'duplicate':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'درخواست نامعتبر']);
                break;
            }
            // Make sure this calls the new 'duplicate' function
            $result = $projectManager->duplicate($_POST['id']);
            echo json_encode($result);
            break;

        case 'bulk_delete':
        case 'selective_copy':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['ids'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'درخواست نامعتبر. شناسه‌ها الزامی است']);
                break;
            }
            $ids = json_decode($_POST['ids'], true); // Decode the JSON string into a PHP array
            if (json_last_error() !== JSON_ERROR_NONE) {
                 http_response_code(400);
                 echo json_encode(['success' => false, 'message' => 'فرمت شناسه‌ها نامعتبر است']);
                 break;
            }
            
            // The action name matches the function name (e.g., 'bulk_delete')
            $result = $projectManager->{$action}($ids); 
            echo json_encode($result);
            break;
 case 'reorder':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['ids_order'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'درخواست نامعتبر']);
                break;
            }
            $ids = json_decode($_POST['ids_order'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                 http_response_code(400);
                 echo json_encode(['success' => false, 'message' => 'فرمت ترتیب شناسه‌ها نامعتبر است']);
                 break;
            }
            $result = $projectManager->reorder($ids);
            echo json_encode($result);
            break;
             case 'get_comments':
            $item_id = $_GET['item_id'] ?? null;
            if (!$item_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'شناسه آیتم الزامی است']);
                break;
            }
            $result = $projectManager->get_comments($item_id);
            echo json_encode($result);
            break;

        case 'add_comment':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                break;
            }
            $result = $projectManager->add_comment($_POST);
            echo json_encode($result);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'عملیات نامعتبر']);
            break;
    }
    
} catch (Exception $e) {
    // logError("خطا در API پروژه: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
}
?>