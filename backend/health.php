<?php
// Simple health check endpoint
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

try {
    // Test database connection
    require_once 'config.php';
    $conn = getDBConnection();
    $conn->close();
    
    $dbStatus = 'connected';
} catch (Exception $e) {
    $dbStatus = 'error: ' . $e->getMessage();
}

echo json_encode([
    'success' => true,
    'message' => 'Backend is accessible',
    'timestamp' => date('Y-m-d H:i:s'),
    'database' => $dbStatus,
    'php_version' => PHP_VERSION
], JSON_UNESCAPED_UNICODE);
