<?php
// Set header to return JSON
header('Content-Type: application/json; charset=utf-8');

// Basic setup
require_once __DIR__ . '/../sercon/bootstrap.php';
secureSession();

// A simple response helper function
function send_json_response($success, $message, $data = null) {
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}

// Check login and permissions
if (!isLoggedIn()) {
    send_json_response(false, 'Authentication required.');
}
// You can add role checks here as well

try {
    $pdo = getProjectDBConnection('pardis');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    send_json_response(false, 'Database connection failed.');
}

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$project_id = $_SESSION['pardis_project_id'] ?? 1; // You should manage this in session

switch ($action) {
    case 'list':
        try {
            $stmt = $pdo->prepare("SELECT * FROM tasks WHERE project_id = ? ORDER BY wbs");
            $stmt->execute([$project_id]);
            $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // **NEW**: Add Persian dates directly to the data
            foreach ($tasks as &$task) {
                $task['start_date_fa'] = !empty($task['baseline_start_date']) ? jdate('Y/m/d', strtotime($task['baseline_start_date'])) : '';
                $task['finish_date_fa'] = !empty($task['baseline_finish_date']) ? jdate('Y/m/d', strtotime($task['baseline_finish_date'])) : '';
            }

            send_json_response(true, 'Tasks fetched successfully.', $tasks);
        } catch (Exception $e) {
            send_json_response(false, 'Error fetching tasks: ' . $e->getMessage());
        }
        break;

    case 'update_weights': // New action to handle weight updates
        try {
            $weights = json_decode(file_get_contents('php://input'), true);
            if (empty($weights)) {
                send_json_response(false, 'No data received.');
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "UPDATE tasks SET cost_weight = ?, time_weight = ?, hybrid_weight = ? 
                 WHERE task_id = ? AND project_id = ?"
            );
            foreach ($weights as $task_id => $data) {
                $stmt->execute([
                    (float)($data['cost_weight'] ?? 0),
                    (float)($data['time_weight'] ?? 0),
                    (float)($data['hybrid_weight'] ?? 0),
                    (int)$task_id,
                    (int)$project_id
                ]);
            }
            $pdo->commit();
            send_json_response(true, 'Weights updated successfully.');
        } catch (Exception $e) {
            $pdo->rollBack();
            send_json_response(false, 'Error updating weights: ' . $e->getMessage());
        }
        break;

    default:
        send_json_response(false, 'Invalid action specified.');
        break;
}