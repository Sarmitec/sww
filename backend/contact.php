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

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    exit(0);
}

if ($method === 'GET') {
    $student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
    
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'معرف الطالب مطلوب']);
        exit;
    }
    
    $conn = getDBConnection();
    
    try {
        $stmt = $conn->prepare("SELECT id, name, university_id, phone, facebook, email, profile_image FROM students WHERE id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $student = $result->fetch_assoc();
            echo json_encode(['success' => true, 'student' => $student]);
        } else {
            echo json_encode(['success' => false, 'message' => 'الطالب غير موجود']);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        logError('Contact fetch error', ['student_id' => $student_id, 'exception' => $e->getMessage()]);
        echo json_encode(['success' => false, 'message' => 'حدث خطأ']);
    } finally {
        $conn->close();
    }
    exit;
}
?>