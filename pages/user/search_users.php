<?php
require_once '../../config/db.php';

header('Content-Type: application/json');

// 獲取搜尋參數
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_type = isset($_GET['type']) ? trim($_GET['type']) : 'all';

if (empty($search_term)) {
    echo json_encode([
        'status' => 'error',
        'message' => '搜尋條件不能為空'
    ]);
    exit;
}

try {
    $where_clause = "";
    $params = [];
    
    // 根據搜尋類型構建查詢條件
    switch($filter_type) {
        case 'name':
            $where_clause = "WHERE s.name LIKE :search";
            $params[':search'] = "%{$search_term}%";
            break;
        case 'student_number':
            $where_clause = "WHERE s.student_number LIKE :search";
            $params[':search'] = "%{$search_term}%";
            break;
        case 'class_type':
            $where_clause = "WHERE s.class_type LIKE :search";
            $params[':search'] = "%{$search_term}%";
            break;
        default:
            // 預設搜尋所有欄位
            $where_clause = "WHERE (s.name LIKE :search OR s.student_number LIKE :search OR s.class_type LIKE :search)";
            $params[':search'] = "%{$search_term}%";
    }
    
    // 基本篩選條件
    $where_clause .= " AND s.valid_or_not = 1 AND s.can_borrow_or_not = 1";
    
    $query = "
        SELECT 
            s.id,
            s.student_number,
            s.name,
            s.class_type,
            s.book_note_return as current_borrowed,
            r.quota,
            r.days,
            (r.quota - s.book_note_return) as remaining_quota
        FROM students s 
        LEFT JOIN role r ON s.role = r.id
        {$where_clause}
        ORDER BY s.student_number
        LIMIT 50
    ";
    
    $stmt = $pdo->prepare($query);
    
    foreach($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response = [
        'status' => 'success',
        'count' => count($results),
        'data' => $results
    ];

} catch(PDOException $e) {
    error_log("Database Error in search_users.php: " . $e->getMessage());
    $response = [
        'status' => 'error',
        'message' => '搜尋過程中發生錯誤，請稍後再試'
    ];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>