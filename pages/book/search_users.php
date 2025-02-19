<?php
require_once '../../config/db.php';

header('Content-Type: application/json');

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    if (empty($search)) {
        echo json_encode([]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT s.*, 
               r.quota,
               r.days,
               s.book_note_return as borrowed_count,
               (r.quota - s.book_note_return) as remaining_quota
        FROM students s
        LEFT JOIN role r ON s.role = r.id
        WHERE (s.name LIKE :term 
              OR s.student_number LIKE :term 
              OR s.school_card_number LIKE :term)
        AND s.valid_or_not = 1 
        AND s.can_borrow_or_not = 1
        ORDER BY s.name ASC 
        LIMIT 10
    ");

    $searchTerm = "%{$search}%";
    $stmt->bindParam(':term', $searchTerm, PDO::PARAM_STR);
    $stmt->execute();

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        // Format the results for Select2
        $formattedResults = array_map(function($user) {
            // Check if user can borrow
            $canBorrow = ($user['quota'] > $user['borrowed_count']);
            
            $displayText = sprintf(
                "%s (%s)",
                $user['name'],
                $user['student_number']
            );

            return [
                'id' => $user['school_card_number'],
                'text' => $displayText,
                'student_number' => $user['student_number'],
                'name' => $user['name'],
                'class_type' => $user['class_type'],
                'quota' => $user['quota'],
                'borrowed_count' => $user['borrowed_count'],
                'can_borrow' => $canBorrow
            ];
    }, $results);

    echo json_encode($formattedResults);
    
} catch(PDOException $e) {
    error_log("Database Error in search_users.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => '查詢過程中發生錯誤']);
}
?>
