<?php
// Enable error logging but not display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    exit(0);
}

if ($method === 'GET') {
    $requested_student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
    $current_student_id = requireAuth();
    
    if (!$requested_student_id) {
        echo json_encode(['success' => false, 'message' => 'معرف الطالب مطلوب']);
        exit;
    }
    
    if ($requested_student_id !== $current_student_id) {
        echo json_encode(['success' => false, 'message' => 'غير مصرح']);
        exit;
    }
    
    $student_id = $requested_student_id;
    
    $conn = getDBConnection();
    
    try {
        $stmt = $conn->prepare("SELECT id, name, university_id, year, email, phone, facebook, profile_image FROM students WHERE id = ?");
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
        logError('Profile fetch error', ['student_id' => $student_id, 'exception' => $e->getMessage()]);
        echo json_encode(['success' => false, 'message' => 'حدث خطأ']);
    } finally {
        $conn->close();
    }
    exit;
}

if ($method === 'PUT') {
    $current_student_id = requireAuth();
    
    $data = json_decode(file_get_contents('php://input'), true);
    $data = sanitizeInput($data);
    
    $student_id = intval($data['student_id'] ?? 0);
    
    if ($student_id !== $current_student_id) {
        logError('Unauthorized profile update', ['current' => $current_student_id, 'target' => $student_id]);
        echo json_encode(['success' => false, 'message' => 'غير مصرح بتعديل بيانات هذا الطالب']);
        exit;
    }
    
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'معرف الطالب مطلوب']);
        exit;
    }
    
    $phone = $data['phone'] ?? '';
    $facebook = $data['facebook'] ?? '';
    $email = $data['email'] ?? '';
    
    $conn = getDBConnection();
    
    try {
        $checkStmt = $conn->prepare("SELECT email FROM students WHERE id = ?");
        $checkStmt->bind_param("i", $student_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $currentStudent = $checkResult->fetch_assoc();
        $checkStmt->close();
        
        $newEmail = $email ?: $currentStudent['email'];
        
        if ($email && $email !== $currentStudent['email']) {
            $emailCheck = $conn->prepare("SELECT id FROM students WHERE email = ? AND id != ?");
            $emailCheck->bind_param("si", $email, $student_id);
            $emailCheck->execute();
            $emailResult = $emailCheck->get_result();
            if ($emailResult->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'البريد الإلكتروني مسجل مسبقا']);
                $emailCheck->close();
                $conn->close();
                exit;
            }
            $emailCheck->close();
        }
        
        $stmt = $conn->prepare("UPDATE students SET phone = ?, facebook = ?, email = ? WHERE id = ?");
        $stmt->bind_param("sssi", $phone, $facebook, $newEmail, $student_id);
        
        if ($stmt->execute()) {
            logActivity($student_id, 'profile_update', ['email_changed' => ($email !== $currentStudent['email'])]);
            echo json_encode(['success' => true, 'message' => 'تم تحديث الملف الشخصي بنجاح']);
        } else {
            logError('Profile update failed', ['student_id' => $student_id, 'error' => $stmt->error]);
            echo json_encode(['success' => false, 'message' => 'حدث خطأ في التحديث']);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        logError('Profile update exception', ['student_id' => $student_id, 'exception' => $e->getMessage()]);
        echo json_encode(['success' => false, 'message' => 'حدث خطأ']);
    } finally {
        $conn->close();
    }
    exit;
}
?>