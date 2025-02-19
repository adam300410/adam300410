<?php
session_start();
require_once '../../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['book_id'])) {
        $book_id = $_POST['book_id'];

        try {
            // Update book_info table
            $stmt = $pdo->prepare("
                UPDATE book_info 
                SET available = 1, 
                    return_date = NOW(),
                    return_date_time = NOW(),
                    borrow_day = FLOOR(TIMESTAMPDIFF(DAY, borrow_date, NOW()))
                WHERE id = ?");
            $stmt->execute([$book_id]);

            // Get student card number
            $stmt = $pdo->prepare("SELECT school_card_number FROM book_info WHERE id = ?");
            $stmt->execute([$book_id]);
            $book = $stmt->fetch(PDO::FETCH_ASSOC);
            $school_card_number = $book['school_card_number'];

            // Get student id
            $stmt = $pdo->prepare("SELECT id FROM students WHERE school_card_number = ?");
            $stmt->execute([$school_card_number]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            $student_id = $student['id'];

            header("Location: user_detail.php?id=" . $student_id);
            exit();
        } catch(PDOException $e) {
            echo "還書失敗: " . $e->getMessage();
        }
    } else {
        echo "未指定書籍";
    }
} else {
    echo "錯誤請求";
}
?>
