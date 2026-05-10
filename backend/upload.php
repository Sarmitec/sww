<?php
// Enable error logging but not display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    exit(0);
}

if ($method === 'POST') {
    $current_student_id = requireAuth();
    
    $student_id = intval($_POST['student_id'] ?? 0);
    
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'معرف الطالب مطلوب']);
        exit;
    }
    
    if ($student_id !== $current_student_id) {
        logError('Unauthorized image upload attempt', ['current_student_id' => $current_student_id, 'target_student_id' => $student_id]);
        echo json_encode(['success' => false, 'message' => 'غير مصرح برفع صورة لهذا الطالب']);
        exit;
    }
    
    if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'لم يتم اختيار صورة']);
        exit;
    }
    
    $file = $_FILES['profile_image'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'نوع الملف غير مسموح']);
        exit;
    }
    
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'حجم الصورة كبير جداً (الحد الأقصى 5MB)']);
        exit;
    }
    
    $imageData = file_get_contents($file['tmp_name']);
    $mimeType = $file['type'];
    $base64Image = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
    
    $conn = getDBConnection();
    
    try {
        $stmt = $conn->prepare("UPDATE students SET profile_image = ? WHERE id = ?");
        $stmt->bind_param("si", $base64Image, $student_id);
        
        if ($stmt->execute()) {
            logActivity($student_id, 'image_upload', ['size' => $file['size'], 'type' => $mimeType]);
            echo json_encode([
                'success' => true, 
                'message' => 'تم رفع الصورة بنجاح'
            ]);
        } else {
            logError('Image upload failed', ['student_id' => $student_id, 'error' => $stmt->error]);
            echo json_encode(['success' => false, 'message' => 'حدث خطأ في تحديث الصورة']);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        logError('Image upload exception', ['student_id' => $student_id, 'exception' => $e->getMessage()]);
        echo json_encode(['success' => false, 'message' => 'حدث خطأ في رفع الصورة']);
    } finally {
        $conn->close();
    }
    
    exit;
}
?>