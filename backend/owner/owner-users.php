<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$conn = getDBConnection();

switch ($method) {
case 'GET':
         requireOwnerAuth($conn);
         try {
             $year = isset($_GET['year']) && $_GET['year'] !== '' ? intval($_GET['year']) : null;
             error_log("Owner users fetch - received year param: " . var_export($year, true) . ", raw GET: " . var_export($_GET, true));

             $sql = "SELECT id, name, university_id, year, email, phone, facebook, profile_image, created_at, role FROM students";

             // تصفية فقط إذا كانت السنة رقم صحيح بين 1 و5
             $filterByYear = ($year !== null && $year >= 1 && $year <= 5);
             error_log("Filter by year: " . var_export($filterByYear, true) . ", year value: " . var_export($year, true));
             if ($filterByYear) {
                 $sql .= " WHERE year = ?";
             }

            // ترتيب الأقدم أولاً
            $sql .= " ORDER BY created_at ASC";

            if ($filterByYear) {
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $year);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $conn->query($sql);
            }
             
             $users = [];
             while ($row = $result->fetch_assoc()) {
                 // Remove sensitive data
                 unset($row['profile_image']); // Not needed in list
                 $users[] = $row;
             }
             
             if (isset($stmt)) { $stmt->close(); }
             $conn->close();
             echo json_encode(['success' => true, 'users' => $users]);
         } catch (Exception $e) {
             logError('Owner get users error', ['error' => $e->getMessage()]);
             echo json_encode(['success' => false, 'message' => 'حدث خطأ']);
         }
         break;

    case 'POST':
        requireOwnerAuth($conn);
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        if (!$data) {
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
        $role = $data['role'] ?? 'student';

        // Validate role
        $allowedRoles = ['student', 'admin', 'owner'];
        if (!in_array($role, $allowedRoles)) {
            echo json_encode(['success' => false, 'message' => 'دور غير صالح']);
            exit;
        }

        // Required fields
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

        // Check for existing student with same university_id or email
        $checkStmt = $conn->prepare("SELECT id FROM students WHERE university_id = ? OR email = ?");
        $checkStmt->bind_param("ss", $university_id, $email);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'الرقم الجامعي أو البريد الإلكتروني مسجل مسبقاً']);
            $checkStmt->close();
            $conn->close();
            exit;
        }
        $checkStmt->close();

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $is_verified = 1; // Owner creates verified accounts directly

        $insertStmt = $conn->prepare("INSERT INTO students (name, university_id, year, email, password, role, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insertStmt->bind_param("ssisssi", $name, $university_id, $year, $email, $hashedPassword, $role, $is_verified);

        if ($insertStmt->execute()) {
            $newId = $conn->insert_id;
            logActivity($_SESSION['student_id'], 'owner_create_user', ['new_user_id' => $newId, 'role' => $role, 'university_id' => $university_id]);
            echo json_encode(['success' => true, 'message' => 'تم إنشاء المستخدم بنجاح', 'user_id' => $newId]);
        } else {
            logError('Owner create user failed', ['error' => $insertStmt->error]);
            echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء إنشاء المستخدم']);
        }
        $insertStmt->close();
        $conn->close();
        break;

    case 'PUT':
        requireOwnerAuth($conn);
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        if (!$data) {
            echo json_encode(['success' => false, 'message' => 'بيانات غير صالحة']);
            exit;
        }
        $data = sanitizeInput($data);

        $student_id = intval($data['student_id'] ?? 0);
        $newRole = $data['role'] ?? '';

        if (!$student_id || empty($newRole)) {
            echo json_encode(['success' => false, 'message' => 'معرف المستخدم ودوره مطلوبان']);
            exit;
        }

        $allowedRoles = ['student', 'admin', 'owner'];
        if (!in_array($newRole, $allowedRoles)) {
            echo json_encode(['success' => false, 'message' => 'دور غير صالح']);
            exit;
        }

        // Prevent owner from demoting themselves to non-owner if they are the only owner?
        // We'll allow but add check to prevent accidental removal of last owner? Not strictly needed.
        // Additional safety: Check if the target user is self and trying to change to something else? That's fine.

        $updateStmt = $conn->prepare("UPDATE students SET role = ? WHERE id = ?");
        $updateStmt->bind_param("si", $newRole, $student_id);

        if ($updateStmt->execute()) {
            logActivity($_SESSION['student_id'], 'owner_update_role', ['target_user_id' => $student_id, 'new_role' => $newRole]);
            echo json_encode(['success' => true, 'message' => 'تم تحديث دور المستخدم بنجاح']);
        } else {
            logError('Owner update role failed', ['error' => $updateStmt->error]);
            echo json_encode(['success' => false, 'message' => 'حدث خطأ']);
        }
        $updateStmt->close();
        $conn->close();
        break;

    case 'DELETE':
        requireOwnerAuth($conn);
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        if (!$data) {
            echo json_encode(['success' => false, 'message' => 'بيانات غير صالحة']);
            exit;
        }
        $student_id = intval($data['student_id'] ?? 0);

        if (!$student_id) {
            echo json_encode(['success' => false, 'message' => 'معرف المستخدم مطلوب']);
            exit;
        }

        // Optional: Prevent owner from deleting themselves? We'll allow but log.
        if ($student_id == $_SESSION['student_id']) {
            echo json_encode(['success' => false, 'message' => 'لا يمكنك حذف حسابك الشخصي']);
            $conn->close();
            exit;
        }

        // Delete user (swap_requests will cascade)
        $deleteStmt = $conn->prepare("DELETE FROM students WHERE id = ?");
        $deleteStmt->bind_param("i", $student_id);
        if ($deleteStmt->execute()) {
            logActivity($_SESSION['student_id'], 'owner_delete_user', ['deleted_user_id' => $student_id]);
            echo json_encode(['success' => true, 'message' => 'تم حذف المستخدم بنجاح']);
        } else {
            logError('Owner delete user failed', ['error' => $deleteStmt->error]);
            echo json_encode(['success' => false, 'message' => 'حدث خطأ']);
        }
        $deleteStmt->close();
        $conn->close();
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
}

?>