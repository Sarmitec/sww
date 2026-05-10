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

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config.php';

initSession();

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    logError('Invalid JSON in registration request', ['raw_input' => $rawInput]);
    echo json_encode(['success' => false, 'message' => 'بيانات غير صالحة']);
    exit;
}

$data = sanitizeInput($data);

$name = $data['name'] ?? '';
$university_id = $data['university_id'] ?? '';
$year = intval($data['year'] ?? 0);
$email = $data['email'] ?? '';
$password = $data['password'] ?? '';
$confirmPassword = $data['confirm_password'] ?? '';

// Validate required fields
if (empty($name) || empty($university_id) || empty($year) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'جميع الحقول مطلوبة']);
    exit;
}

// Validate year
if ($year < 1 || $year > 5) {
    echo json_encode(['success' => false, 'message' => 'سنة دراسية غير صحيحة']);
    exit;
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'البريد الإلكتروني غير صالح']);
    exit;
}

// Validate university ID format (alphanumeric only)
if (!preg_match('/^[a-zA-Z0-9]+$/', $university_id)) {
    echo json_encode(['success' => false, 'message' => 'الرقم الجامعي يجب أن يحتوي على أحرف وأرقام فقط']);
    exit;
}

// Validate name (Arabic characters only)
if (!preg_match('/^[\p{Arabic}\s]+$/u', $name)) {
    echo json_encode(['success' => false, 'message' => 'الاسم يجب أن يحتوي على أحرف عربية فقط']);
    exit;
}

// Password validation
if ($password !== $confirmPassword) {
    echo json_encode(['success' => false, 'message' => 'كلمتا المرور غير متطابقتين']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل']);
    exit;
}

if (!preg_match('/[A-Z]/', $password)) {
    echo json_encode(['success' => false, 'message' => 'كلمة المرور يجب أن تحتوي على حرف كبير']);
    exit;
}

if (!preg_match('/[0-9]/', $password)) {
    echo json_encode(['success' => false, 'message' => 'كلمة المرور يجب أن تحتوي على رقم']);
    exit;
}

// No reCAPTCHA or email verification required

// Rate limiting
$rateCheck = checkRateLimit('register_' . $university_id);
if (!$rateCheck['allowed']) {
    echo json_encode(['success' => false, 'message' => $rateCheck['message']]);
    exit;
}

$conn = getDBConnection();

try {
    // Check for existing student
    $checkStmt = $conn->prepare("SELECT id FROM students WHERE university_id = ? OR email = ?");
    $checkStmt->bind_param("ss", $university_id, $email);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows > 0) {
        incrementRateLimit('register_' . $university_id);
        echo json_encode(['success' => false, 'message' => 'الرقم الجامعي أو البريد الإلكتروني مسجل مسبقاً']);
        $checkStmt->close();
        $conn->close();
        exit;
    }
    $checkStmt->close();

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$is_verified = 1;
     $stmt = $conn->prepare("INSERT INTO students (name, university_id, year, email, password, is_verified) VALUES (?, ?, ?, ?, ?, ?)");
     $stmt->bind_param("ssissi", $name, $university_id, $year, $email, $hashedPassword, $is_verified);

    if ($stmt->execute()) {
        $studentId = $conn->insert_id;

        resetRateLimit('register_' . $university_id);
        logActivity($studentId, 'register', ['university_id' => $university_id, 'email' => $email]);

        echo json_encode(['success' => true, 'message' => 'تم التسجيل بنجاح']);
    } else {
        logError('Registration failed', ['university_id' => $university_id, 'error' => $stmt->error]);
        echo json_encode(['success' => false, 'message' => 'حدث خطأ في التسجيل']);
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    logError('Registration exception', ['error' => $e->getMessage(), 'data' => $data]);
    echo json_encode(['success' => false, 'message' => 'حدث خطأ في التسجيل']);
}
?>