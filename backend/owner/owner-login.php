<?php
// backend/owner/owner-login.php
session_start();
header('Content-Type: application/json');

// بيانات الدخول الثابتة للمالك الأعلى
$OWNER_USERNAME = 'TheOwnerAbdullahAlsarmini';
$OWNER_ID = '1812007';

$data = json_decode(file_get_contents('php://input'), true);

$username = isset($data['username']) ? trim($data['username']) : '';
$id = isset($data['id']) ? trim($data['id']) : '';
$password = isset($data['password']) ? $data['password'] : '';


// تحقق من بيانات الدخول الخاصة بالمالك
if (($id === $OWNER_ID && $password === 'Abdullahalsarmini2007')) {
    $_SESSION['user'] = [
        'name' => $OWNER_USERNAME,
        'university_id' => $OWNER_ID,
        'role' => 'owner',
        'owner_super' => true
    ];
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'بيانات الدخول غير صحيحة']);
exit;
