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
        $stats = [];
        
        // Total students
        if ($year) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE year = ? AND role = 'student'");
            $stmt->bind_param("i", $year);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query("SELECT COUNT(*) as count FROM students WHERE role = 'student'");
        }
        $stats['totalStudents'] = $result->fetch_assoc()['count'];
        if (isset($stmt)) { $stmt->close(); }
        
        // Total requests
        if ($year) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM swap_requests WHERE year = ?");
            $stmt->bind_param("i", $year);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query("SELECT COUNT(*) as count FROM swap_requests");
        }
        $stats['totalRequests'] = $result->fetch_assoc()['count'];
        if (isset($stmt)) { $stmt->close(); }
        
        // Pending requests
        if ($year) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM swap_requests WHERE status = 'pending' AND year = ?");
            $stmt->bind_param("i", $year);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query("SELECT COUNT(*) as count FROM swap_requests WHERE status = 'pending'");
        }
        $stats['pendingRequests'] = $result->fetch_assoc()['count'];
        if (isset($stmt)) { $stmt->close(); }
        
        // Matched requests
        if ($year) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM swap_requests WHERE status = 'matched' AND year = ?");
            $stmt->bind_param("i", $year);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query("SELECT COUNT(*) as count FROM swap_requests WHERE status = 'matched'");
        }
        $stats['matchedRequests'] = $result->fetch_assoc()['count'];
        if (isset($stmt)) { $stmt->close(); }
        
        // By year
        $stats['byYear'] = [];
        for ($y = 1; $y <= 5; $y++) {
            if ($year) {
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE year = ? AND role = 'student'");
                $stmt->bind_param("i", $y);
                $stmt->execute();
                $studentsResult = $stmt->get_result();
                $stmt->close();
                
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM swap_requests WHERE year = ?");
                $stmt->bind_param("i", $y);
                $stmt->execute();
                $requestsResult = $stmt->get_result();
                $stmt->close();
            } else {
                $studentsResult = $conn->query("SELECT COUNT(*) as count FROM students WHERE year = $y AND role = 'student'");
                $requestsResult = $conn->query("SELECT COUNT(*) as count FROM swap_requests WHERE year = $y");
            }
            
            $stats['byYear'][$y] = [
                'students' => $studentsResult->fetch_assoc()['count'],
                'requests' => $requestsResult->fetch_assoc()['count']
            ];
        }
        
        $conn->close();
        
        echo json_encode(['success' => true, 'stats' => $stats]);
    } catch (Exception $e) {
        logError('Admin stats error', ['error' => $e->getMessage(), 'year' => $year]);
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