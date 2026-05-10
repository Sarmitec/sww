<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, DELETE, OPTIONS');
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
    
    try {
        $sql = "SELECT sr.id, sr.student_id, sr.current_section, sr.desired_section, 
                       sr.status, sr.request_date, sr.match_type, sr.year,
                       s.name, s.university_id
                FROM swap_requests sr 
                JOIN students s ON sr.student_id = s.id";
        
        if ($year) {
            $sql .= " WHERE sr.year = ?";
        }
        
        $sql .= " ORDER BY sr.request_date DESC";
        
        if ($year) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $year);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($sql);
        }
        
        $requests = [];
        while ($row = $result->fetch_assoc()) {
            $requests[] = $row;
        }
        
        $conn->close();
        
        echo json_encode(['success' => true, 'requests' => $requests]);
    } catch (Exception $e) {
        logError('Owner get requests error', ['error' => $e->getMessage(), 'year' => $year]);
        echo json_encode(['success' => false, 'message' => 'حدث خطأ']);
    }
    exit;
}

if ($method === 'PUT') {
    $conn = getDBConnection();
    requireOwnerAuth($conn);
    
    $data = json_decode(file_get_contents('php://input'), true);
    $request_id = intval($data['request_id'] ?? 0);
    $status = sanitizeInput($data['status'] ?? '');
    
    if (!$request_id || !in_array($status, ['pending', 'matched', 'completed'])) {
        echo json_encode(['success' => false, 'message' => 'بيانات غير صحيحة']);
        exit;
    }
    
    $stmt = $conn->prepare("UPDATE swap_requests SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $request_id);
    
    if ($stmt->execute()) {
        logActivity($_SESSION['student_id'], 'owner_update_request', [
            'request_id' => $request_id,
            'new_status' => $status
        ]);
        echo json_encode(['success' => true, 'message' => 'تم تحديث الحالة']);
    } else {
        echo json_encode(['success' => false, 'message' => 'حدث خطأ']);
    }
    
    $stmt->close();
    $conn->close();
    exit;
}

if ($method === 'DELETE') {
    $conn = getDBConnection();
    requireOwnerAuth($conn);
    
    $data = json_decode(file_get_contents('php://input'), true);
    $request_id = intval($data['request_id'] ?? 0);
    
    if (!$request_id) {
        echo json_encode(['success' => false, 'message' => 'معرف الطلب مطلوب']);
        exit;
    }
    
    $stmt = $conn->prepare("DELETE FROM swap_requests WHERE id = ?");
    $stmt->bind_param("i", $request_id);
    
    if ($stmt->execute()) {
        logActivity($_SESSION['student_id'], 'owner_delete_request', [
            'request_id' => $request_id
        ]);
        echo json_encode(['success' => true, 'message' => 'تم حذف الطلب']);
    } else {
        echo json_encode(['success' => false, 'message' => 'حدث خطأ']);
    }
    
    $stmt->close();
    $conn->close();
    exit;
}

?>