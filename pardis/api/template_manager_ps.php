<?php
require_once __DIR__ . '/../../sercon/bootstrap.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}
require_once __DIR__ . '/../../includes/security.php';
requireCsrf();

$pdo = getProjectDBConnection('pardis');
$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    switch($action) {
        case 'save_template':
            $templateName = $_POST['template_name'] ?? '';
            $templateData = $_POST['template_data'] ?? '';
            
            if (empty($templateName) || empty($templateData)) {
                throw new Exception('نام و داده قالب الزامی است');
            }
            
            // Check if template exists
            $check = $pdo->prepare("SELECT id FROM ps_report_templates WHERE user_id = ? AND template_name = ?");
            $check->execute([$user_id, $templateName]);
            
            if ($check->fetch()) {
                // Update existing
                $stmt = $pdo->prepare("UPDATE ps_report_templates SET template_data = ?, updated_at = NOW() WHERE user_id = ? AND template_name = ?");
                $stmt->execute([$templateData, $user_id, $templateName]);
            } else {
                // Insert new
                $stmt = $pdo->prepare("INSERT INTO ps_report_templates (user_id, template_name, template_data) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $templateName, $templateData]);
            }
            
            echo json_encode(['success' => true, 'message' => 'قالب ذخیره شد']);
            break;
            
        case 'list_templates':
            $stmt = $pdo->prepare("SELECT id, template_name as name, DATE_FORMAT(updated_at, '%Y/%m/%d') as date, template_data as data FROM ps_report_templates WHERE user_id = ? ORDER BY updated_at DESC");
            $stmt->execute([$user_id]);
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Decode JSON data
            foreach($templates as &$t) {
                $t['data'] = json_decode($t['data'], true);
            }
            
            echo json_encode(['success' => true, 'templates' => $templates]);
            break;
            
        case 'delete_template':
            $templateId = $_POST['template_id'] ?? 0;
            $stmt = $pdo->prepare("DELETE FROM ps_report_templates WHERE id = ? AND user_id = ?");
            $stmt->execute([$templateId, $user_id]);
            
            echo json_encode(['success' => true, 'message' => 'قالب حذف شد']);
            break;
            
        default:
            throw new Exception('عملیات نامعتبر');
    }
    
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}