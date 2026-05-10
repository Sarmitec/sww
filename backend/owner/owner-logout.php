<?php
// backend/owner/owner-logout.php
session_start();
if (isset($_SESSION['user'])) unset($_SESSION['user']);
header('Content-Type: application/json');
echo json_encode(['success'=>true]);
exit;
