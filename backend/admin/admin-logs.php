<?php
// Enable error logging but not display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    exit(0);
}

if ($method === 'GET') {
    $conn = getDBConnection();
    requireAdminAuth($conn);
    
    $year = isset($_GET['year']) ? intval($_GET['year']) : null;
    
    try {
        // Create activity_log table if not exists
        $conn->query("CREATE TABLE IF NOT EXISTS activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT,
            action VARCHAR(255),
            details JSON,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        if ($year) {
            $stmt = $conn->prepare("SELECT al.*, s.name, s.university_id 
                                   FROM activity_log al 
                                   LEFT JOIN students s ON al.student_id = s.id
                                   WHERE s.year = ?
                                   ORDER BY al.created_at DESC 
                                   LIMIT 100");
            $stmt->bind_param("i", $year);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
        } else {
            $result = $conn->query("SELECT al.*, s.name, s.university_id 
                                   FROM activity_log al 
                                   LEFT JOIN students s ON al.student_id = s.id
                                   ORDER BY al.created_at DESC 
                                   LIMIT 100");
        }
        
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $detailsJson = $row['details'];
            if ($detailsJson) {
                $decoded = json_decode($detailsJson, true);
                $row['details'] = (is_array($decoded) ? $decoded : ['raw' => $detailsJson]);
            } else {
                $row['details'] = [];
            }
            $logs[] = $row;
        }
        
        $conn->close();
        
        echo json_encode(['success' => true, 'logs' => $logs]);
    } catch (Exception $e) {
        logError('Admin logs fetch error', ['error' => $e->getMessage(), 'year' => $year]);
        echo json_encode(['success' => false, 'message' => 'حدث خطأ']);
    }
    exit;
}

function requireAdminAuth($conn) {
    initSession();
    if (!isset($_SESSION['student_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'غير مصرح']);
        exit;
    }
    
    $stmt = $conn->prepare("SELECT role FROM students WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['student_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'غير موجود']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية']);
        exit;
    }
    
    $stmt->close();
}
?>