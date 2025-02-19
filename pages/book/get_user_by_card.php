<?php
require_once '../../config/db.php';

header('Content-Type: application/json');

$card_number = isset($_GET['card_number']) ? trim($_GET['card_number']) : '';

try {
    if (empty($card_number)) {
        echo json_encode(['error' => '卡號不能為空']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT s.*, 
               r.quota,
               r.days,
               s.book_note_return as borrowed_count
        FROM students s
        LEFT JOIN role r ON s.role = r.id 
        WHERE s.student_card_number = :card_number
        AND s.valid_or_not = 1
        AND s.can_borrow_or_not = 1
    ");
    
    $stmt->bindParam(':card_number', $card_number, PDO::PARAM_STR);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Check borrow limit
        $canBorrow = ($user['quota'] > $user['borrowed_count']);
        
        // Format display info
        $user['can_borrow'] = $canBorrow;
        $user['display_info'] = [
            'name' => $user['name'],
            'class_type' => $user['class_type'],
            'student_number' => $user['student_number'],
            'borrow_status' => sprintf(
                "已借%d本，配額%d本，%s", 
                $user['borrowed_count'],
                $user['quota'],
                $canBorrow ? "可以借書" : "已達借閱上限"
            )
        ];
        
        // Ensure id field is set to school_card_number for backward compatibility
        $user['id'] = $user['school_card_number'];
        
        echo json_encode([
            'status' => 'success',
            'data' => $user
        ]);
        
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => '找不到此卡號對應的使用者'
        ]);
    }
    
} catch(PDOException $e) {
    error_log("Database Error in get_user_by_card.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => '查詢過程中發生錯誤'
    ]);
}
?>
