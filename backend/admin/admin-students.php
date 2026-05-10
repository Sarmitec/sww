<?php
// Enable error logging but not display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, DELETE');
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
        if ($year) {
            $stmt = $conn->prepare("SELECT id, name, university_id, year, email, phone, created_at FROM students WHERE year = ? AND role = 'student' ORDER BY created_at DESC");
            $stmt->bind_param("i", $year);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
        } else {
            $result = $conn->query("SELECT id, name, university_id, year, email, phone, created_at FROM students WHERE role = 'student' ORDER BY created_at DESC");
        }
        
        $students = [];
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
        
        $conn->close();
        
        echo json_encode(['success' => true, 'students' => $students]);
    } catch (Exception $e) {
        logError('Admin get students error', ['error' => $e->getMessage(), 'year' => $year]);
        echo json_encode(['success' => false, 'message' => 'حدث خطأ']);
    }
    exit;
}

if ($method === 'DELETE') {
    $conn = getDBConnection();
    requireAdminAuth($conn);
    
    $data = json_decode(file_get_contents('php://input'), true);
    $student_id = intval($data['student_id'] ?? 0);
    
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'معرف الطالب مطلوب']);
        exit;
    }
    
    // Prevent deleting admin accounts
    $checkAdmin = $conn->prepare("SELECT role FROM students WHERE id = ?");
    $checkAdmin->bind_param("i", $student_id);
    $checkAdmin->execute();
    $adminResult = $checkAdmin->get_result();
    if ($adminResult->num_rows > 0) {
        $userRole = $adminResult->fetch_assoc()['role'];
        if ($userRole === 'admin') {
            echo json_encode(['success' => false, 'message' => 'لا يمكن حذف حساب المدير']);
            $checkAdmin->close();
            $conn->close();
            exit;
        }
    }
    $checkAdmin->close();
    
    // Delete student's requests first
    $deleteRequests = $conn->prepare("DELETE FROM swap_requests WHERE student_id = ?");
    $deleteRequests->bind_param("i", $student_id);
    $deleteRequests->execute();
    $deleteRequests->close();
    
    // Delete student
    $deleteStudent = $conn->prepare("DELETE FROM students WHERE id = ?");
    $deleteStudent->bind_param("i", $student_id);
    
    if ($deleteStudent->execute()) {
        logActivity($student_id, 'admin_delete_student', ['deleted_by' => $_SESSION['student_id']]);
        echo json_encode(['success' => true, 'message' => 'تم حذف الطالب']);
    } else {
        echo json_encode(['success' => false, 'message' => 'حدث خطأ']);
    }
    
    $deleteStudent->close();
    $conn->close();
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