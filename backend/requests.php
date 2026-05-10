<?php
// Enable error logging but not display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    exit(0);
}

if ($method === 'GET') {
    $year = isset($_GET['year']) ? intval($_GET['year']) : null;
    $student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : null;
    
    // Require authentication
    $currentUser = requireAuth();
    
    $conn = getDBConnection();
    
    try {
        if ($student_id) {
            // Only allow viewing own requests OR admin
            $isAdmin = false;
            $stmt = $conn->prepare("SELECT role FROM students WHERE id = ?");
            $stmt->bind_param("i", $currentUser);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $isAdmin = ($user['role'] === 'admin');
            }
            $stmt->close();
            
            // Check ownership
            if (!$isAdmin && $student_id != $currentUser) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'غير مصرح']);
                $conn->close();
                exit;
            }
            
            $stmt = $conn->prepare("SELECT * FROM swap_requests WHERE student_id = ? AND status != 'completed' ORDER BY request_date DESC");
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $requests = [];
            while ($row = $result->fetch_assoc()) {
                $requests[] = $row;
            }
            $stmt->close();
            $conn->close();
            
            echo json_encode(['success' => true, 'requests' => $requests]);
            exit;
        }
        
        $pendingSql = "SELECT sr.id, sr.student_id, sr.current_section, sr.desired_section, sr.status, sr.request_date, sr.match_type, sr.triple_with,
                       s.name, s.university_id, s.year as student_year
                       FROM swap_requests sr
                       JOIN students s ON sr.student_id = s.id
                        WHERE sr.status = 'pending'";

        if ($year) {
            $pendingSql .= " AND s.year = " . intval($year);
        }
        
        $pendingSql .= " ORDER BY sr.request_date ASC";
        
        $result = $conn->query($pendingSql);
        $pendingRequests = [];
        
        while ($row = $result->fetch_assoc()) {
            if ($row['triple_with']) {
                $row['triple_with'] = json_decode($row['triple_with'], true);
            }
            $pendingRequests[] = $row;
        }
        
        $matchedSql = "SELECT sr.id, sr.student_id, sr.current_section, sr.desired_section, sr.status, sr.request_date, sr.match_type, sr.triple_with,
                       s.name, s.university_id, s.year as student_year
                       FROM swap_requests sr 
                       JOIN students s ON sr.student_id = s.id 
                       WHERE sr.status = 'matched'";
        
        if ($year) {
            $matchedSql .= " AND s.year = " . intval($year);
        }
        
        $matchedSql .= " ORDER BY sr.request_date ASC";
        
        $result = $conn->query($matchedSql);
        $matchedRequests = [];
        
        while ($row = $result->fetch_assoc()) {
            if ($row['triple_with']) {
                $row['triple_with'] = json_decode($row['triple_with'], true);
            }
            $matchedRequests[] = $row;
        }
        
        echo json_encode(['success' => true, 'pending' => $pendingRequests, 'matched' => $matchedRequests]);
    } catch (Exception $e) {
        logError('GET request exception', ['error' => $e->getMessage()]);
        echo json_encode(['success' => false, 'message' => 'حدث خطأ في جلب الطلبات']);
        if (isset($conn)) $conn->close();
        exit;
    }
    
    $conn->close();
    exit;
}

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    
    if (!$data) {
        logError('Invalid JSON in request submission', ['raw_input' => $rawInput]);
        echo json_encode(['success' => false, 'message' => 'بيانات غير صالحة']);
        exit;
    }
    
    $data = sanitizeInput($data);
    
    $student_id = intval($data['student_id'] ?? 0);
    $current_section = $data['current_section'] ?? '';
    $desired_section = $data['desired_section'] ?? '';
    
    // Validate section values (alphanumeric only)
    if (empty($student_id) || empty($current_section) || empty($desired_section)) {
        echo json_encode(['success' => false, 'message' => 'جميع الحقول مطلوبة']);
        exit;
    }
    
    if (!preg_match('/^[a-zA-Z0-9]+$/', $current_section) || !preg_match('/^[a-zA-Z0-9]+$/', $desired_section)) {
        echo json_encode(['success' => false, 'message' => 'أرقام الفئات يجب أن تحتوي على أحرف وأرقام فقط']);
        exit;
    }
    
    if ($current_section === $desired_section) {
        echo json_encode(['success' => false, 'message' => 'الفئة الحالية والفئة المطلوبة يجب أن تكونا مختلفتين']);
        exit;
    }
    
    $cooldownCheck = checkRequestCooldown($student_id);
    if (!$cooldownCheck['allowed']) {
        echo json_encode(['success' => false, 'message' => 'يجب الانتظار ' . $cooldownCheck['remaining'] . ' ثانية قبل تقديم طلب جديد']);
        exit;
    }
    
    $conn = getDBConnection();
    
    try {
        $checkStmt = $conn->prepare("SELECT id FROM swap_requests WHERE student_id = ? AND status != 'completed'");
        $checkStmt->bind_param("i", $student_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'لديك طلب تبديل نشط']);
            $checkStmt->close();
            $conn->close();
            exit;
        }
        $checkStmt->close();
        
        $getYearStmt = $conn->prepare("SELECT year FROM students WHERE id = ?");
        $getYearStmt->bind_param("i", $student_id);
        $getYearStmt->execute();
        $yearResult = $getYearStmt->get_result();
        $studentData = $yearResult->fetch_assoc();
        $studentYear = $studentData['year'];
        $getYearStmt->close();
        
        $stmt = $conn->prepare("INSERT INTO swap_requests (student_id, current_section, desired_section, year) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issi", $student_id, $current_section, $desired_section, $studentYear);
        
        if ($stmt->execute()) {
            $request_id = $conn->insert_id;
            
            $matchResult = findMatches($conn, $student_id, $request_id, $current_section, $desired_section, $studentYear);
            
            $response = ['success' => true, 'message' => 'تم تقديم طلب التبديل بنجاح'];
            
            if ($matchResult['type'] === 'binary') {
                $response['message'] = 'تم التطابق الثنائي!';
                $response['match_type'] = 'binary';
            } else if ($matchResult['type'] === 'triple') {
                $response['message'] = 'تم التطابق الثلاثي! 🎉';
                $response['match_type'] = 'triple';
            }
            
            echo json_encode($response);
        } else {
            logError('Failed to insert swap request', ['student_id' => $student_id, 'error' => $stmt->error]);
            echo json_encode(['success' => false, 'message' => 'حدث خطأ في تقديم الطلب']);
        }
        
        $stmt->close();
        $conn->close();
        exit;
    } catch (Exception $e) {
        logError('Request submission exception', ['error' => $e->getMessage(), 'data' => $data]);
        echo json_encode(['success' => false, 'message' => 'حدث خطأ']);
        if (isset($conn)) $conn->close();
        exit;
    }
}

if ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $data = sanitizeInput($data);
    
    $request_id = intval($data['request_id'] ?? 0);
    $student_id = intval($data['student_id'] ?? 0);
    
    $conn = getDBConnection();
    
    $getStmt = $conn->prepare("SELECT status, match_type, triple_with FROM swap_requests WHERE id = ? AND student_id = ?");
    $getStmt->bind_param("ii", $request_id, $student_id);
    $getStmt->execute();
    $result = $getStmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'الطلب غير موجود']);
        $getStmt->close();
        $conn->close();
        exit;
    }
    
    $request = $result->fetch_assoc();
    $getStmt->close();
    
    $getDetailsStmt = $conn->prepare("SELECT sr.current_section, sr.desired_section, s.year FROM swap_requests sr JOIN students s ON sr.student_id = s.id WHERE sr.id = ?");
    $getDetailsStmt->bind_param("i", $request_id);
    $getDetailsStmt->execute();
    $detailsResult = $getDetailsStmt->get_result();
    $requestDetails = $detailsResult->fetch_assoc();
    $current_section = $requestDetails['current_section'];
    $desired_section = $requestDetails['desired_section'];
    $studentYear = $requestDetails['year'];
    $getDetailsStmt->close();
    
    if ($request['match_type'] === 'triple' && $request['triple_with']) {
        $tripleWith = json_decode($request['triple_with'], true);
        
        $resetStmt = $conn->prepare("UPDATE swap_requests SET status = 'pending', match_type = NULL, triple_with = NULL WHERE student_id = ? AND status = 'matched'");
        $resetStmt->bind_param("i", $tripleWith[0]);
        $resetStmt->execute();
        $resetStmt->close();
        
        $resetStmt2 = $conn->prepare("UPDATE swap_requests SET status = 'pending', match_type = NULL, triple_with = NULL WHERE student_id = ? AND status = 'matched'");
        $resetStmt2->bind_param("i", $tripleWith[1]);
        $resetStmt2->execute();
        $resetStmt2->close();
        
        $findNewStmt = $conn->prepare("SELECT sr.id, sr.student_id, sr.current_section, sr.desired_section, s.year
                                       FROM swap_requests sr 
                                       JOIN students s ON sr.student_id = s.id 
                                       WHERE sr.status = 'pending' AND sr.student_id NOT IN (?, ?, ?) AND s.year = ?
                                       ORDER BY sr.request_date ASC LIMIT 1");
        $findNewStmt->bind_param("iiii", $student_id, $tripleWith[0], $tripleWith[1], $studentYear);
        $findNewStmt->execute();
        $newResult = $findNewStmt->get_result();
        
        if ($newResult->num_rows > 0) {
            $candidate = $newResult->fetch_assoc();
            tryFormNewTriple($conn, $tripleWith[0], $tripleWith[1], $candidate['student_id']);
        }
        $findNewStmt->close();
    } elseif ($request['match_type'] === 'binary') {
        $findPartnerStmt = $conn->prepare("SELECT sr.student_id FROM swap_requests sr 
                                         JOIN students s ON sr.student_id = s.id 
                                         WHERE sr.status = 'matched' AND sr.student_id != ? AND s.year = ?
                                         AND sr.current_section = ? AND sr.desired_section = ?");
        $findPartnerStmt->bind_param("iiss", $student_id, $studentYear, $desired_section, $current_section);
        $findPartnerStmt->execute();
        $partnerResult = $findPartnerStmt->get_result();
        
        if ($partnerResult->num_rows > 0) {
            $partner = $partnerResult->fetch_assoc();
            $resetPartnerStmt = $conn->prepare("UPDATE swap_requests SET status = 'pending', match_type = NULL WHERE student_id = ?");
            $resetPartnerStmt->bind_param("i", $partner['student_id']);
            $resetPartnerStmt->execute();
            $resetPartnerStmt->close();
        }
        $findPartnerStmt->close();
    }
    
    $deleteStmt = $conn->prepare("DELETE FROM swap_requests WHERE id = ?");
    $deleteStmt->bind_param("i", $request_id);
    
    if ($deleteStmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'تم إلغاء الطلب']);
    } else {
        echo json_encode(['success' => false, 'message' => 'حدث خطأ']);
    }
    
    $deleteStmt->close();
    $conn->close();
    exit;
}

function findMatches($conn, $student_id, $request_id, $current_section, $desired_section, $year) {
    $binaryStmt = $conn->prepare("SELECT sr.id, sr.student_id, sr.current_section, sr.desired_section
                                 FROM swap_requests sr 
                                 JOIN students s ON sr.student_id = s.id 
                                 WHERE sr.status = 'pending' 
                                 AND sr.student_id != ?
                                 AND s.year = ?
                                 AND sr.current_section = ?
                                 AND sr.desired_section = ?
                                 ORDER BY sr.request_date ASC 
                                 LIMIT 1");
    $binaryStmt->bind_param("iiss", $student_id, $year, $desired_section, $current_section);
    $binaryStmt->execute();
    $binaryResult = $binaryStmt->get_result();
    
    if ($binaryResult->num_rows > 0) {
        $match = $binaryResult->fetch_assoc();
        
        $updateStmt = $conn->prepare("UPDATE swap_requests SET status = 'matched', match_type = 'binary' WHERE id = ?");
        $updateStmt->bind_param("i", $match['id']);
        $updateStmt->execute();
        $updateStmt->close();
        
        $updateStmt2 = $conn->prepare("UPDATE swap_requests SET status = 'matched', match_type = 'binary' WHERE id = ?");
        $updateStmt2->bind_param("i", $request_id);
        $updateStmt2->execute();
        $updateStmt2->close();
        
        $binaryStmt->close();
        return ['type' => 'binary', 'match_id' => $match['id']];
    }
    $binaryStmt->close();
    
    $firstStmt = $conn->prepare("SELECT sr.id, sr.student_id, sr.current_section, sr.desired_section
                                FROM swap_requests sr 
                                JOIN students s ON sr.student_id = s.id 
                                WHERE sr.status = 'pending' 
                                AND sr.student_id != ?
                                AND s.year = ?
                                AND sr.current_section = ?
                                ORDER BY sr.request_date ASC 
                                LIMIT 1");
    $firstStmt->bind_param("iis", $student_id, $year, $desired_section);
    $firstStmt->execute();
    $firstResult = $firstStmt->get_result();
    
    if ($firstResult->num_rows > 0) {
        $first = $firstResult->fetch_assoc();
        
        $thirdStmt = $conn->prepare("SELECT sr.id, sr.student_id, sr.current_section, sr.desired_section
                                    FROM swap_requests sr 
                                    JOIN students s ON sr.student_id = s.id 
                                    WHERE sr.status = 'pending' 
                                    AND sr.student_id != ?
                                    AND sr.student_id != ?
                                    AND s.year = ?
                                    AND sr.current_section = ?
                                    AND sr.desired_section = ?
                                    ORDER BY sr.request_date ASC 
                                    LIMIT 1");
        $thirdStmt->bind_param("iiiss", $student_id, $first['student_id'], $year, $first['desired_section'], $current_section);
        $thirdStmt->execute();
        $thirdResult = $thirdStmt->get_result();
        
        if ($thirdResult->num_rows > 0) {
            $third = $thirdResult->fetch_assoc();
            
            $tripleWith1 = json_encode([strval($first['student_id']), strval($third['student_id'])]);
            $tripleWith2 = json_encode([strval($student_id), strval($third['student_id'])]);
            $tripleWith3 = json_encode([strval($student_id), strval($first['student_id'])]);
            
            $updateStmt1 = $conn->prepare("UPDATE swap_requests SET status = 'matched', match_type = 'triple', triple_with = ? WHERE id = ?");
            $updateStmt1->bind_param("si", $tripleWith1, $request_id);
            $updateStmt1->execute();
            $updateStmt1->close();
            
            $updateStmt2 = $conn->prepare("UPDATE swap_requests SET status = 'matched', match_type = 'triple', triple_with = ? WHERE id = ?");
            $updateStmt2->bind_param("si", $tripleWith2, $first['id']);
            $updateStmt2->execute();
            $updateStmt2->close();
            
            $updateStmt3 = $conn->prepare("UPDATE swap_requests SET status = 'matched', match_type = 'triple', triple_with = ? WHERE id = ?");
            $updateStmt3->bind_param("si", $tripleWith3, $third['id']);
            $updateStmt3->execute();
            $updateStmt3->close();
            
            $thirdStmt->close();
            $firstStmt->close();
            return ['type' => 'triple', 'match_ids' => [$first['id'], $third['id']]];
        }
        $thirdStmt->close();
    }
    $firstStmt->close();
    
    return ['type' => 'none'];
}

function tryFormNewTriple($conn, $student1_id, $student2_id, $candidate_id) {
    $stmt1 = $conn->prepare("SELECT current_section, desired_section FROM swap_requests WHERE student_id = ? AND status = 'pending'");
    $stmt1->bind_param("i", $student1_id);
    $stmt1->execute();
    $result1 = $stmt1->get_result();
    $req1 = $result1->fetch_assoc();
    $stmt1->close();
    
    $stmt2 = $conn->prepare("SELECT current_section, desired_section FROM swap_requests WHERE student_id = ? AND status = 'pending'");
    $stmt2->bind_param("i", $student2_id);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $req2 = $result2->fetch_assoc();
    $stmt2->close();
    
    $stmt3 = $conn->prepare("SELECT current_section, desired_section FROM swap_requests WHERE student_id = ? AND status = 'pending'");
    $stmt3->bind_param("i", $candidate_id);
    $stmt3->execute();
    $result3 = $stmt3->get_result();
    $req3 = $result3->fetch_assoc();
    $stmt3->close();
    
    if ($req1 && $req2 && $req3) {
        if ($req1['desired_section'] === $req2['current_section'] && 
            $req2['desired_section'] === $req3['current_section'] && 
            $req3['desired_section'] === $req1['current_section']) {
            
            $tripleWith1 = json_encode([strval($student2_id), strval($candidate_id)]);
            $tripleWith2 = json_encode([strval($student1_id), strval($candidate_id)]);
            $tripleWith3 = json_encode([strval($student1_id), strval($student2_id)]);
            
            $updateStmt1 = $conn->prepare("UPDATE swap_requests SET status = 'matched', match_type = 'triple', triple_with = ? WHERE student_id = ?");
            $updateStmt1->bind_param("si", $tripleWith1, $student1_id);
            $updateStmt1->execute();
            $updateStmt1->close();
            
            $updateStmt2 = $conn->prepare("UPDATE swap_requests SET status = 'matched', match_type = 'triple', triple_with = ? WHERE student_id = ?");
            $updateStmt2->bind_param("si", $tripleWith2, $student2_id);
            $updateStmt2->execute();
            $updateStmt2->close();
            
            $updateStmt3 = $conn->prepare("UPDATE swap_requests SET status = 'matched', match_type = 'triple', triple_with = ? WHERE student_id = ?");
            $updateStmt3->bind_param("si", $tripleWith3, $candidate_id);
            $updateStmt3->execute();
            $updateStmt3->close();
        }
    }
}
?>
