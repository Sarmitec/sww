<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'swap_db');

// reCAPTCHA Keys
define('RECAPTCHA_SITE_KEY', '6LeBl40sAAAAAFA4TJRm23pkMpfXyqu7HS7KlJUA');
define('RECAPTCHA_SECRET_KEY', '');

// Email SMTP Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_FROM_EMAIL', '');
define('SMTP_FROM_NAME', 'SWAP System');

define('RATE_LIMIT_MAX_ATTEMPTS', 5);
define('RATE_LIMIT_LOCKOUT_SECONDS', 300);
define('REQUEST_COOLDOWN_SECONDS', 60);

function getDBConnection() {
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        error_log('Database connection error: ' . $conn->connect_error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'خطأ في الاتصال بقاعدة البيانات']);
        exit;
    }
    $conn->set_charset("utf8mb4");
    // Ensure strict mode for SQL security
    $conn->query("SET sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
    
    // Auto-migration: Ensure 'owner' role exists in ENUM and default owner user
    try {
        // Check role column type only if students table exists
        $tables = $conn->query("SHOW TABLES LIKE 'students'");
        if ($tables && $tables->num_rows > 0) {
            $result = $conn->query("SHOW COLUMNS FROM students LIKE 'role'");
            if ($result && $result->num_rows > 0) {
                $col = $result->fetch_assoc();
                $type = $col['Type'] ?? '';
                if (strpos($type, "'owner'") === false) {
                    $conn->query("ALTER TABLE students MODIFY COLUMN role ENUM('student','admin','owner') DEFAULT 'student'");
                }
                $result->free();
            }
            
            // Ensure default owner account exists
            $ownerUnivId = '1812007';
            $ownerName = 'TheOwnerAbdullahAlsarmini';
            $checkStmt = $conn->prepare("SELECT id, role FROM students WHERE university_id = ?");
            $checkStmt->bind_param("s", $ownerUnivId);
            $checkStmt->execute();
            $checkRes = $checkStmt->get_result();
            if ($checkRes->num_rows == 0) {
                // Create owner account
                $defaultEmail = 'owner@swap.edu';
                $defaultPassword = password_hash('Owner@1812007', PASSWORD_DEFAULT);
                $year = 5;
                $insertStmt = $conn->prepare("INSERT INTO students (name, university_id, year, email, password, role, is_verified) VALUES (?, ?, ?, ?, ?, 'owner', 1)");
                $insertStmt->bind_param("ssiss", $ownerName, $ownerUnivId, $year, $defaultEmail, $defaultPassword);
                $insertStmt->execute();
                $insertStmt->close();
            } else {
                $row = $checkRes->fetch_assoc();
                if ($row['role'] !== 'owner') {
                    $updStmt = $conn->prepare("UPDATE students SET role='owner' WHERE id = ?");
                    $updStmt->bind_param("i", $row['id']);
                    $updStmt->execute();
                    $updStmt->close();
                }
            }
            $checkStmt->close();
        }
    } catch (Exception $e) {
        // Ignore migration errors to avoid breaking the app
    }
    
    return $conn;
}

function initDatabase() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
    $conn->query($sql);
    
    $conn->select_db(DB_NAME);
    
    $studentsTable = "CREATE TABLE IF NOT EXISTS students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        university_id VARCHAR(50) UNIQUE NOT NULL,
        year INT NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        facebook VARCHAR(255),
        profile_image MEDIUMTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($studentsTable);
    
    $requestsTable = "CREATE TABLE IF NOT EXISTS swap_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        current_section VARCHAR(10) NOT NULL,
        desired_section VARCHAR(10) NOT NULL,
        status ENUM('pending', 'matched', 'completed') DEFAULT 'pending',
        match_type ENUM('binary', 'triple') DEFAULT NULL,
        triple_with JSON DEFAULT NULL,
        request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        year INT NOT NULL,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
    )";
    $conn->query($requestsTable);
    
    $result = $conn->query("SHOW COLUMNS FROM swap_requests LIKE 'year'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE swap_requests ADD COLUMN year INT NOT NULL AFTER request_date");
    }
    
    $result2 = $conn->query("SHOW COLUMNS FROM students LIKE 'role'");
    if ($result2->num_rows == 0) {
        // Column doesn't exist, create with full enum including owner
        $conn->query("ALTER TABLE students ADD COLUMN role ENUM('student', 'admin', 'owner') DEFAULT 'student' AFTER profile_image");
    } else {
        // Column exists, check if 'owner' is in enum and add if missing
        $colInfo = $result2->fetch_assoc();
        $type = $colInfo['Type'] ?? '';
        if (strpos($type, "'owner'") === false) {
            $conn->query("ALTER TABLE students MODIFY COLUMN role ENUM('student', 'admin', 'owner') DEFAULT 'student'");
        }
        $result2->free();
    }
    
    $result3 = $conn->query("SHOW COLUMNS FROM students LIKE 'verification_code'");
    if ($result3->num_rows == 0) {
        $conn->query("ALTER TABLE students ADD COLUMN verification_code VARCHAR(64) AFTER role");
        $conn->query("ALTER TABLE students ADD COLUMN is_verified BOOLEAN DEFAULT FALSE AFTER verification_code");
        $conn->query("ALTER TABLE students ADD COLUMN verification_expires TIMESTAMP NULL AFTER is_verified");
    }
    
    $conn->close();
    return true;
}

function initSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Secure session settings - make sure no output before this
        $cookieParams = session_get_cookie_params();
        $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        
        if (defined('FORCE_SECURE_COOKIES') && FORCE_SECURE_COOKIES) {
            $isSecure = true;
        }
        
        // Set session cookie parameters before starting
        session_set_cookie_params([
            'lifetime' => 86400,
            'path' => $cookieParams['path'] ?: '/',
            'domain' => $cookieParams['domain'] ?: '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        
        // Start session with error suppression
        @session_start();
    }
}

function requireAuth() {
    initSession();
    if (!isset($_SESSION['student_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'غير مصرح']);
        exit;
    }
    // Regenerate session ID periodically to prevent fixation
    // if (!isset($_SESSION['login_time']) || (time() - $_SESSION['login_time'] > 300)) {
    //     session_regenerate_id(true);
    //     $_SESSION['login_time'] = time();
    // }
    return $_SESSION['student_id'];
}

function requireOwnerAuth($conn) {
    initSession();
    if (!isset($_SESSION['student_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'غير مصرح']);
        exit;
    }
    
    // Special case for super owner (student_id = 0 means the super owner)
    if ($_SESSION['student_id'] == 0) {
        // Super owner is authorized - no DB check needed as they are the system owner
        return;
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
    if ($user['role'] !== 'owner') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية']);
        exit;
    }
    
    $stmt->close();
}

function sanitizeInput($input) {
    if (is_null($input)) {
        return null;
    }
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    if (!is_string($input)) {
        return $input;
    }
    return htmlspecialchars(trim($input), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function checkRateLimit($identifier, $maxAttempts = RATE_LIMIT_MAX_ATTEMPTS, $lockoutSeconds = RATE_LIMIT_LOCKOUT_SECONDS) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = 'rate_' . $identifier . '_' . $ip;
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['attempts' => 0, 'first_attempt' => time(), 'locked_until' => 0];
    }
    
    $rateData = &$_SESSION[$key];
    
    if ($rateData['locked_until'] > time()) {
        $remaining = $rateData['locked_until'] - time();
        return ['allowed' => false, 'remaining' => $remaining, 'message' => 'تجاوزت عدد المحاولات. جرب مجدداً بعد ' . ceil($remaining / 60) . ' دقائق'];
    }
    
    if ($rateData['attempts'] >= $maxAttempts) {
        $rateData['locked_until'] = time() + $lockoutSeconds;
        return ['allowed' => false, 'remaining' => $lockoutSeconds, 'message' => 'تجاوزت عدد المحاولات. جرب مجدداً بعد 5 دقائق'];
    }
    
    return ['allowed' => true];
}

function incrementRateLimit($identifier) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = 'rate_' . $identifier . '_' . $ip;
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['attempts' => 0, 'first_attempt' => time(), 'locked_until' => 0];
    }
    
    $_SESSION[$key]['attempts']++;
}

function resetRateLimit($identifier) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = 'rate_' . $identifier . '_' . $ip;
    unset($_SESSION[$key]);
}

function checkRequestCooldown($student_id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT request_date FROM swap_requests WHERE student_id = ? ORDER BY request_date DESC LIMIT 1");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    $conn->close();
    
    if ($result->num_rows > 0) {
        $lastRequest = $result->fetch_assoc();
        $timeSinceLastRequest = time() - strtotime($lastRequest['request_date']);
        if ($timeSinceLastRequest < REQUEST_COOLDOWN_SECONDS) {
            return ['allowed' => false, 'remaining' => REQUEST_COOLDOWN_SECONDS - $timeSinceLastRequest];
        }
    }
    return ['allowed' => true];
}

function logError($message, $context = []) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
        @chmod($logDir, 0755);
    }
    $logFile = $logDir . '/errors_' . date('Y-m-d') . '.log';
    $logMessage = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if (!empty($context)) {
        $logMessage .= ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
    $logMessage .= PHP_EOL;
    @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

function logActivity($student_id, $action, $details = []) {
    try {
        $conn = getDBConnection();
        
        $tableCheck = $conn->query("SHOW TABLES LIKE 'activity_log'");
        if ($tableCheck->num_rows == 0) {
            $conn->query("CREATE TABLE IF NOT EXISTS activity_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT,
                action VARCHAR(255),
                details JSON,
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        }
        
        $actionJson = json_encode($action, JSON_UNESCAPED_UNICODE);
        $detailsJson = json_encode($details, JSON_UNESCAPED_UNICODE);
        
        $stmt = $conn->prepare("INSERT INTO activity_log (student_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt->bind_param("isss", $student_id, $actionJson, $detailsJson, $ip);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        // Silently fail - don't break the main flow
    }
}
?>