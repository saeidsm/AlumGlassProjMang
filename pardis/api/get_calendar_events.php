<?php
// File: public_html/pardis/api/get_calendar_events.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../sercon/bootstrap.php';
secureSession();

if (!isLoggedIn()) {
    http_response_code(403);
    exit(json_encode([]));
}

$user_id = $_SESSION['user_id'];
$pdo = getProjectDBConnection('pardis');

$start_str = substr($_GET['start'] ?? '1970-01-01', 0, 10);
$end_str = substr($_GET['end'] ?? '2038-01-19', 0, 10);

// Improved query with better direction logic and unique parameter names
$sql = "
SELECT DISTINCT
    ce.event_id as id, 
    ce.title, 
    ce.start_date as start, 
    ce.end_date as end, 
    ce.color, 
    ce.related_link,
    e.element_type, 
    e.zone_name, 
    e.block, 
    e.contractor,
    t.status as task_status,
    t.created_by_user_id,
    t.user_id as notification_user_id,
    CASE 
        WHEN t.notification_id IS NULL THEN 'no_notification'
        WHEN t.created_by_user_id = :user_id_direction1 THEN 'outgoing'
        WHEN t.user_id = :user_id_direction2 AND (t.created_by_user_id IS NULL OR t.created_by_user_id != :user_id_direction3) THEN 'incoming'
        ELSE 'unknown'
    END as direction
FROM calendar_events ce
LEFT JOIN notifications t ON ce.related_link = t.link AND ce.user_id = t.user_id
LEFT JOIN elements e ON ce.element_id = e.element_id
WHERE ce.user_id = :user_id_filter 
AND ce.start_date BETWEEN :start_date AND :end_date
";

$params = [
    ':user_id_filter' => $user_id,
    ':user_id_direction1' => $user_id,
    ':user_id_direction2' => $user_id,
    ':user_id_direction3' => $user_id,
    ':start_date' => $start_str,
    ':end_date' => $end_str
];

// Separate WHERE conditions from HAVING conditions
$where_conditions = [];
$having_conditions = [];

if (!empty($_GET['block'])) {
    $where_conditions[] = "e.block = :block";
    $params[':block'] = $_GET['block'];
}

if (!empty($_GET['zone'])) {
    $where_conditions[] = "e.zone_name = :zone";
    $params[':zone'] = $_GET['zone'];
}

if (!empty($_GET['type'])) {
    $where_conditions[] = "e.element_type = :type";
    $params[':type'] = $_GET['type'];
}

if (!empty($_GET['contractor'])) {
    $where_conditions[] = "e.contractor = :contractor";
    $params[':contractor'] = $_GET['contractor'];
}

if (!empty($_GET['status'])) {
    $where_conditions[] = "t.status = :status";
    $params[':status'] = $_GET['status'];
}

// Add WHERE conditions
if (!empty($where_conditions)) {
    $sql .= " AND " . implode(" AND ", $where_conditions);
}

// Add GROUP BY to ensure uniqueness based on event_id
$sql .= " GROUP BY ce.event_id";

// Add HAVING conditions after GROUP BY
if (!empty($_GET['hide_my_actions']) && $_GET['hide_my_actions'] === '1') {
    // Hide events where current user is the creator (outgoing tasks)
    $sql .= " HAVING NOT (direction = 'outgoing')";
}

// Add ORDER BY for consistent results
$sql .= " ORDER BY ce.start_date ASC, ce.event_id ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $all_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Log the first few events to check direction values
    if (!empty($all_events)) {
        error_log("Sample events with directions: " . json_encode(array_slice($all_events, 0, 3)));
    }
    
    // Process events to ensure proper format and remove any remaining duplicates
    $unique_events = [];
    $seen_ids = [];
    
    foreach ($all_events as $event) {
        // Skip if we've already seen this event_id
        if (in_array($event['id'], $seen_ids)) {
            continue;
        }
        
        $seen_ids[] = $event['id'];
        
        // Ensure proper format for FullCalendar
        $processed_event = [
            'id' => $event['id'],
            'title' => $event['title'],
            'start' => $event['start'],
            'end' => $event['end'],
            'color' => $event['color'],
            'extendedProps' => [
                'related_link' => $event['related_link'],
                'element_type' => $event['element_type'],
                'zone_name' => $event['zone_name'],
                'block' => $event['block'],
                'contractor' => $event['contractor'],
                'task_status' => $event['task_status'],
                'direction' => $event['direction'],
                'created_by_user_id' => $event['created_by_user_id'],
                'notification_user_id' => $event['notification_user_id']
            ]
        ];
        
        $unique_events[] = $processed_event;
    }
    
    // Build filter options from unique events
    $filter_options = [
        'blocks' => [],
        'zones' => [],
        'types' => [],
        'contractors' => [],
        'statuses' => [],
        'directions' => []
    ];
    
    foreach ($unique_events as $event) {
        $props = $event['extendedProps'];
        
        if (!empty($props['block'])) {
            $filter_options['blocks'][] = $props['block'];
        }
        if (!empty($props['zone_name'])) {
            $filter_options['zones'][] = $props['zone_name'];
        }
        if (!empty($props['element_type'])) {
            $filter_options['types'][] = $props['element_type'];
        }
        if (!empty($props['contractor'])) {
            $filter_options['contractors'][] = $props['contractor'];
        }
        if (!empty($props['task_status'])) {
            $filter_options['statuses'][] = $props['task_status'];
        }
        if (!empty($props['direction']) && $props['direction'] !== 'unknown') {
            $filter_options['directions'][] = $props['direction'];
        }
    }
    
    // Remove duplicates and sort filter options
    foreach ($filter_options as $key => &$options) {
        if (!empty($options)) {
            $options = array_values(array_unique($options));
            sort($options);
        }
    }
    
    // Debug: Log direction filter options
    error_log("Available directions: " . json_encode($filter_options['directions']));
    
    echo json_encode([
        'events' => $unique_events,
        'filters' => $filter_options
    ]);

} catch (PDOException $e) {
    error_log("Database error in get_calendar_events.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error occurred',
        'events' => [],
        'filters' => [
            'blocks' => [],
            'zones' => [],
            'types' => [],
            'contractors' => [],
            'statuses' => [],
            'directions' => []
        ]
    ]);
} catch (Exception $e) {
    error_log("General error in get_calendar_events.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'An error occurred',
        'events' => [],
        'filters' => [
            'blocks' => [],
            'zones' => [],
            'types' => [],
            'contractors' => [],
            'statuses' => [],
            'directions' => []
        ]
    ]);
}
?>