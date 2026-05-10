<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $conn = getDBConnection();
    requireOwnerAuth($conn);
    
    $year = isset($_GET['year']) ? intval($_GET['year']) : null;
    
    try {
        $stats = [];
        
        // Total users by role
        if ($year) {
            $stmt = $conn->prepare("SELECT role, COUNT(*) as count FROM students WHERE year = ? GROUP BY role");
            $stmt->bind_param("i", $year);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query("SELECT role, COUNT(*) as count FROM students GROUP BY role");
        }
        $roleCounts = ['student' => 0, 'admin' => 0, 'owner' => 0];
        while ($row = $result->fetch_assoc()) {
            $roleCounts[$row['role']] = (int)$row['count'];
        }
        if (isset($stmt)) { $stmt->close(); }
        
        $stats['totalStudents'] = $roleCounts['student'];
        $stats['totalAdmins'] = $roleCounts['admin'];
        $stats['totalOwners'] = $roleCounts['owner'];
        $stats['totalUsers'] = array_sum($roleCounts);
        
        // Total requests
        if ($year) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM swap_requests WHERE year = ?");
            $stmt->bind_param("i", $year);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query("SELECT COUNT(*) as count FROM swap_requests");
        }
        $stats['totalRequests'] = $result->fetch_assoc()['count'];
        if (isset($stmt)) { $stmt->close(); }
        
        // Pending requests
        if ($year) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM swap_requests WHERE status = 'pending' AND year = ?");
            $stmt->bind_param("i", $year);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query("SELECT COUNT(*) as count FROM swap_requests WHERE status = 'pending'");
        }
        $stats['pendingRequests'] = $result->fetch_assoc()['count'];
        if (isset($stmt)) { $stmt->close(); }
        
        // Matched requests
        if ($year) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM swap_requests WHERE status = 'matched' AND year = ?");
            $stmt->bind_param("i", $year);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query("SELECT COUNT(*) as count FROM swap_requests WHERE status = 'matched'");
        }
        $stats['matchedRequests'] = $result->fetch_assoc()['count'];
        if (isset($stmt)) { $stmt->close(); }
        
        // Completed requests
        if ($year) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM swap_requests WHERE status = 'completed' AND year = ?");
            $stmt->bind_param("i", $year);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query("SELECT COUNT(*) as count FROM swap_requests WHERE status = 'completed'");
        }
        $stats['completedRequests'] = $result->fetch_assoc()['count'];
        if (isset($stmt)) { $stmt->close(); }
        
        // Breakdown by year (students, admins, owners, requests)
        $stats['byYear'] = [];
        for ($y = 1; $y <= 5; $y++) {
            if ($year && $year != $y) {
                // if specific year filter, skip others
                $stats['byYear'][$y] = ['students' => 0, 'admins' => 0, 'owners' => 0, 'requests' => 0];
                continue;
            }
            // Students count for this year by role
            $stmt = $conn->prepare("SELECT role, COUNT(*) as count FROM students WHERE year = ? GROUP BY role");
            $stmt->bind_param("i", $y);
            $stmt->execute();
            $res = $stmt->get_result();
            $yearRoleCounts = ['student' => 0, 'admin' => 0, 'owner' => 0];
            while ($r = $res->fetch_assoc()) {
                $yearRoleCounts[$r['role']] = (int)$r['count'];
            }
            $stmt->close();
            
            // Requests count
            if ($year) {
                $stmt2 = $conn->prepare("SELECT COUNT(*) as count FROM swap_requests WHERE year = ?");
                $stmt2->bind_param("i", $y);
                $stmt2->execute();
                $reqResult = $stmt2->get_result();
                $reqCount = $reqResult->fetch_assoc()['count'];
                $stmt2->close();
            } else {
                $reqResult = $conn->query("SELECT COUNT(*) as count FROM swap_requests WHERE year = $y");
                $reqCount = $reqResult->fetch_assoc()['count'];
            }
            
            $stats['byYear'][$y] = [
                'students' => $yearRoleCounts['student'],
                'admins' => $yearRoleCounts['admin'],
                'owners' => $yearRoleCounts['owner'],
                'requests' => $reqCount
            ];
        }
        
        $conn->close();
        
        echo json_encode(['success' => true, 'stats' => $stats]);
    } catch (Exception $e) {
        logError('Owner stats error', ['error' => $e->getMessage(), 'year' => $year]);
        echo json_encode(['success' => false, 'message' => 'حدث خطأ']);
    }
    exit;
}

?>