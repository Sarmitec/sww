<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $conn = getDBConnection();
    requireOwnerAuth($conn);
    
    $year = isset($_GET['year']) ? intval($_GET['year']) : null;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
    $limit = max(1, min(500, $limit)); // between 1 and 500
    
    try {
        // Ensure activity_log table exists
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
                                   WHERE s.year = ? OR al.student_id IS NULL
                                   ORDER BY al.created_at DESC 
                                   LIMIT ?");
            $stmt->bind_param("ii", $year, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
        } else {
            $result = $conn->query("SELECT al.*, s.name, s.university_id 
                                   FROM activity_log al 
                                   LEFT JOIN students s ON al.student_id = s.id
                                   ORDER BY al.created_at DESC 
                                   LIMIT $limit");
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
        logError('Owner logs fetch error', ['error' => $e->getMessage(), 'year' => $year]);
        echo json_encode(['success' => false, 'message' => 'حدث خطأ']);
    }
    exit;
}

?>