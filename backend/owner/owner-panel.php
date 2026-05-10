<?php
// backend/owner/owner-panel.php
session_start();
header('Content-Type: text/html; charset=utf-8');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'owner') {
    http_response_code(403);
    echo 'غير مصرح بالدخول';
    exit;
}
// هذه الصفحة يمكن أن تعرض رسالة أو تحقق من الجلسة فقط، لأن كل شيء يتم عبر API.js
// يمكن تطويرها لاحقاً إذا أردت عرض بيانات مباشرة هنا

echo '<p>تم تسجيل الدخول كمالك أعلى. استخدم لوحة التحكم.</p>';
