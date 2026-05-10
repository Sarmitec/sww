<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config.php';

$student_id = requireAuth();

logActivity($student_id, 'logout', []);

session_unset();
session_destroy();

echo json_encode(['success' => true, 'message' => 'تم تسجيل الخروج بنجاح']);
?>