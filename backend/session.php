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
    initSession();
    
    // Handle super owner (student_id = 0)
    if (isset($_SESSION['student_id']) && $_SESSION['student_id'] == 0) {
        $student = [
            'id' => 0,
            'name' => $_SESSION['student_name'] ?? 'TheOwnerAbdullahAlsarmini',
            'university_id' => $_SESSION['university_id'] ?? '1812007',
            'year' => null,
            'email' => null,
            'phone' => null,
            'facebook' => null,
            'profile_image' => null,
            'role' => 'owner'
        ];
        echo json_encode(['success' => true, 'student' => $student]);
        exit;
    }
    
    $student_id = requireAuth();
    
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT id, name, university_id, year, email, phone, facebook, profile_image, role FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $student = $result->fetch_assoc();
        
        if (!empty($student['profile_image']) && strlen($student['profile_image']) > 100) {
            // Already base64 encoded
        } else {
            $student['profile_image'] = null;
        }
        
        echo json_encode(['success' => true, 'student' => $student]);
    } else {
        echo json_encode(['success' => false, 'message' => 'الطالب غير موجود']);
    }
    
    $stmt->close();
    $conn->close();
    exit;
}
?>