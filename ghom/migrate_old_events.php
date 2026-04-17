<?php
// ===================================================================
// SCRIPT FOR MIGRATING PAST INSPECTIONS TO CALENDAR EVENTS
// Run this file once in your browser, then delete it.
// ===================================================================

require_once __DIR__ . '/../../sercon/bootstrap.php';
require_once __DIR__ . '/includes/notification_helper.php';

// --- Security Check ---
secureSession();
if (!in_array($_SESSION['role'], ['admin', 'superuser'])) {
    http_response_code(403);
    die('Access Denied. You must be an administrator to run this script.');
}

// --- Main Script Body ---
echo "<h1>Migration Script Started...</h1>";

try {
    $pdo = getProjectDBConnection('ghom');

    // Step 1: Clear any old/test data to ensure a clean run.
    $pdo->exec("TRUNCATE TABLE notifications");
    $pdo->exec("TRUNCATE TABLE calendar_events");
    echo "<p style='color: orange;'>Old notification and calendar data has been cleared.</p>";

    // Step 2: Select all significant, dated inspection records from the past.
    $sql = "
        SELECT 
            i.user_id as actor_id,
            i.element_id, 
            i.part_name, 
            e.plan_file, 
            i.overall_status, 
            i.contractor_status, 
            i.notes,
            i.contractor_notes,
            DATE(i.inspection_date) as inspection_date, 
            DATE(i.contractor_date) as contractor_date
        FROM inspections i
        JOIN elements e ON i.element_id = e.element_id
        WHERE 
            i.stage_id > 0 AND (
                (i.overall_status IN ('OK', 'Repair', 'Reject') AND i.inspection_date IS NOT NULL) OR
                (i.contractor_status IN ('Opening Approved', 'Pre-Inspection Complete') AND i.contractor_date IS NOT NULL)
            )
    ";

    $stmt = $pdo->query($sql);
    if (!$stmt) {
        throw new Exception("The main query failed to execute.");
    }
    $past_inspections = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p>Found " . count($past_inspections) . " past records to process.</p><hr>";

    $events_created_count = 0;

    // Step 3: Loop through each record and trigger the notification/event creation.
    foreach ($past_inspections as $inspection) {
        $event_data = null;

        // Determine the event type and date based on the record's status.
        if ($inspection['overall_status'] === 'Repair' && $inspection['inspection_date']) {
            $event_data = ['event_type' => 'REPAIR_REQUESTED', 'event_date' => $inspection['inspection_date'], 'notes' => $inspection['notes']];
        } elseif ($inspection['contractor_status'] === 'Opening Approved' && $inspection['contractor_date']) {
            $event_data = ['event_type' => 'REPAIR_DONE', 'event_date' => $inspection['contractor_date'], 'notes' => $inspection['contractor_notes']];
        } elseif ($inspection['overall_status'] === 'OK' && $inspection['inspection_date']) {
            $event_data = ['event_type' => 'INSPECTION_OK', 'event_date' => $inspection['inspection_date'], 'notes' => $inspection['notes']];
        } elseif ($inspection['overall_status'] === 'Reject' && $inspection['inspection_date']) {
            $event_data = ['event_type' => 'INSPECTION_REJECT', 'event_date' => $inspection['inspection_date'], 'notes' => $inspection['notes']];
        } elseif ($inspection['contractor_status'] === 'Pre-Inspection Complete' && $inspection['contractor_date']) {
            $event_data = ['event_type' => 'PRE_INSPECTION_REQUEST', 'event_date' => $inspection['contractor_date'], 'notes' => $inspection['contractor_notes']];
        }

        if ($event_data) {
            // Call the helper function.
            // Using the actual actor_id from the record.
            trigger_workflow_notifications_and_events(
                $pdo,
                $inspection['element_id'],
                $inspection['part_name'],
                $inspection['plan_file'],
                $event_data['event_type'],
                (int)$inspection['actor_id'],
                $event_data['event_date'],
                $event_data['notes']
            );
            $events_created_count++;
        }
    }

    echo "<h2 style='color: green;'>Migration Complete!</h2>";
    echo "<p>Successfully processed and created event sets for <strong>{$events_created_count}</strong> past activities.</p>";
    echo "<p>Please check your `notifications` and `calendar_events` tables now.</p>";
    echo "<p style='color: red; font-weight: bold;'>For security, please delete this file (migrate_old_events.php) from the server now.</p>";
} catch (Exception $e) {
    echo "<h2>An Error Occurred:</h2><pre style='background: #ffecec; border: 1px solid red; padding: 10px;'>" . $e->getMessage() . "</pre>";
}
