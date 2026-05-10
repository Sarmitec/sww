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
    logError('Invalid JSON in login request', ['raw_input' => $rawInput]);
    echo json_encode(['success' => false, 'message' => 'بيانات غير صالحة']);
    exit;
}

$university_id = trim($data['university_id'] ?? '');
$password = $data['password'] ?? '';

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateKey = 'login_' . $university_id . '_' . $ip;
$rateFile = __DIR__ . '/rate_login_' . md5($rateKey) . '.lock';

// Owner credentials check first
$OWNER_ID = '1812007';
$OWNER_PASSWORD = 'Abdullahalsarmini2007';

if ($university_id === $OWNER_ID && $password === $OWNER_PASSWORD) {
    if (file_exists($rateFile)) {
        unlink($rateFile);
    }
    
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    } else {
        initSession();
    }
    
    $owner_data = [
        'id' => 0,
        'name' => 'TheOwnerAbdullahAlsarmini',
        'university_id' => $OWNER_ID,
        'year' => null,
        'email' => null,
        'phone' => null,
        'facebook' => null,
        'profile_image' => null,
        'role' => 'owner',
        'is_verified' => true
    ];
    
    $_SESSION['student_id'] = 0;
    $_SESSION['student_name'] = $owner_data['name'];
    $_SESSION['university_id'] = $owner_data['university_id'];
    $_SESSION['year'] = null;
    $_SESSION['token'] = bin2hex(random_bytes(32));
    $_SESSION['login_time'] = time();
    
    logActivity(0, 'owner_login', ['ip' => $ip]);
    
    echo json_encode([
        'success' => true,
        'message' => 'تم تسجيل الدخول بنجاح',
        'student' => $owner_data,
        'token' => $_SESSION['token']
    ]);
    exit;
}

if (empty($university_id) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'الرقم الجامعي وكلمة المرور مطلوبة']);
    exit;
}

$conn = getDBConnection();

try {
    $stmt = $conn->prepare("SELECT id, name, university_id, year, email, phone, facebook, profile_image, password, role, is_verified FROM students WHERE university_id = ?");
    $stmt->bind_param("s", $university_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Log failed attempt
        logError('Login attempt with non-existent university_id', ['university_id' => $university_id, 'ip' => $ip]);
        echo json_encode(['success' => false, 'message' => 'الرقم الجامعي غير مسجل']);
        $stmt->close();
        $conn->close();
        exit;
    }

    $student = $result->fetch_assoc();

    if (!password_verify($password, $student['password'])) {
        // Log failed password attempt
        logError('Failed login attempt - wrong password', ['university_id' => $university_id, 'ip' => $ip]);
        echo json_encode(['success' => false, 'message' => 'كلمة المرور غير صحيحة']);
        $stmt->close();
        $conn->close();
        exit;
    }

    // Successful login - clear rate limit file
    if (file_exists($rateFile)) {
        unlink($rateFile);
    }

    // Regenerate session ID to prevent fixation
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    } else {
        initSession();
    }
    
    $_SESSION['student_id'] = $student['id'];
    $_SESSION['student_name'] = $student['name'];
    $_SESSION['university_id'] = $student['university_id'];
    $_SESSION['year'] = $student['year'];
    $_SESSION['token'] = bin2hex(random_bytes(32));
    $_SESSION['login_time'] = time();

    $token = $_SESSION['token'];
    
    unset($student['password']);
    
    logActivity($student['id'], 'login', ['ip' => $ip]);

    echo json_encode([
        'success' => true, 
        'message' => 'تم تسجيل الدخول بنجاح',
        'student' => $student,
        'token' => $token
    ]);

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    logError('Login exception', ['error' => $e->getMessage(), 'university_id' => $university_id]);
    echo json_encode(['success' => false, 'message' => 'حدث خطأ في تسجيل الدخول']);
}
?>