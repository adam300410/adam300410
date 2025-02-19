<?php 
date_default_timezone_set('Asia/Shanghai');
session_start();
require_once '../../config/db.php';

$book = null;
$user = null;
$error = null;
$success = null;

if (isset($_GET['book_id']) && isset($_GET['user_id'])) {
    try {
        // Get book details
        $stmt = $pdo->prepare("SELECT * FROM book_info2 WHERE id = ? AND available = 1");
        $stmt->execute([$_GET['book_id']]);
        $book = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$book) {
            $error = "找不到可借閱的書籍";
        }

        // Get user details with role and borrowed books info
        $stmt = $pdo->prepare("
            SELECT s.*, r.quota, r.days, r.role_name,
            s.book_note_return as current_borrowed,
            (r.quota - s.book_note_return) as remaining_quota,
            r.id as role_id
            FROM students s 
            LEFT JOIN role r ON s.role = r.role
            WHERE s.school_card_number = ?");
        $stmt->execute([$_GET['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Debug log for diagnosis
        error_log(sprintf(
            "[DEBUG] Initial Load - Card: %s, Current: %d, Quota: %d, Role: %d, CanBorrow: %d",
            $_GET['user_id'],
            $user['current_borrowed'] ?? -1,
            $user['quota'] ?? -1,
            $user['role_id'] ?? -1,
            $user['can_borrow_or_not'] ?? -1
        ));

        if (!$user) {
            $error = "找不到使用者資料";
        } else {
            // Check user status and borrow limits
            $error_messages = [];
            
            if (!isset($user['valid_or_not']) || $user['valid_or_not'] != 1) {
                $error_messages[] = "此帳戶已被停權，請聯繫圖書館管理員";
            }

            if ($user['quota'] === null) {
                $error_messages[] = "此帳戶的身份沒有設定借閱額度，請聯繫圖書館管理員";
            } elseif ($user['current_borrowed'] >= $user['quota']) {
                $error_messages[] = sprintf(
                    "此帳戶已借滿 %d 本書，不能再借 (目前已借: %d)",
                    $user['quota'],
                    $user['current_borrowed']
                );
            }

            if (!empty($error_messages)) {
                $error = implode("<br>", $error_messages);
            }
        }
    } catch (PDOException $e) {
        $error = "查詢失敗：" . $e->getMessage();
        error_log("[ERROR] Query failed: " . $e->getMessage());
    }
}

// Calculate return date based on user's role days
if ($user && isset($user['days'])) {
    $borrow_date = new DateTime();
    $return_date = clone $borrow_date;
    $days = intval($user['days']);
    $return_date->modify("+{$days} days");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_id']) && isset($_POST['user_id'])) {
    try {
        $pdo->beginTransaction();

        // Double check book availability
        $stmt = $pdo->prepare("SELECT available FROM book_info2 WHERE id = ? FOR UPDATE");
        $stmt->execute([$_POST['book_id']]);
        $book_status = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$book_status || $book_status['available'] != 1) {
            throw new Exception("此書已被借出");
        }

        // Double check user's borrow limit using role.id join
        $stmt = $pdo->prepare("
            SELECT s.book_note_return, r.quota, s.can_borrow_or_not, s.valid_or_not,
                   s.role, r.id as role_id
            FROM students s
            LEFT JOIN role r ON s.role = r.role
            WHERE s.school_card_number = ? 
            FOR UPDATE");
        $stmt->execute([$_POST['user_id']]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);

        // Debug log for transaction check
        error_log(sprintf(
            "[DEBUG] Borrow Check - Card: %s, Current: %d, Quota: %d, Role: %d, Valid: %d, CanBorrow: %d",
            $_POST['user_id'],
            $current['book_note_return'],
            $current['quota'],
            $current['role_id'],
            $current['valid_or_not'],
            $current['can_borrow_or_not']
        ));

        if (!$current) {
            throw new Exception("找不到使用者資料");
        }
        
        if ($current['valid_or_not'] != 1) {
            throw new Exception("此帳戶已被停權");
        }
        
        // If current borrowed count is less than quota, allow borrowing even if can_borrow_or_not is 0
        if ($current['book_note_return'] >= $current['quota']) {
            throw new Exception(sprintf(
                "已超過借閱上限 (目前已借: %d 本, 上限: %d 本)",
                $current['book_note_return'],
                $current['quota']
            ));
        }

        // Update book status with safety check
        $stmt = $pdo->prepare("
            UPDATE book_info2 
            SET available = 0, 
                borrow_time = borrow_time + 1 
            WHERE id = ? AND available = 1");
        $result = $stmt->execute([$_POST['book_id']]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("更新書籍狀態失敗");
        }

        // Update student's borrowed count with safety check
        $stmt = $pdo->prepare("
            UPDATE students s 
            SET book_note_return = book_note_return + 1,
                can_borrow_or_not = CASE 
                    WHEN book_note_return + 1 >= (
                        SELECT quota 
                        FROM role r 
                        WHERE r.role = s.role
                    ) THEN 0 
                    ELSE 1 
                END
            WHERE school_card_number = ?
            AND book_note_return < (
                SELECT quota 
                FROM role r 
                WHERE r.role = s.role
            )");
        $result = $stmt->execute([$_POST['user_id']]);

        if ($stmt->rowCount() === 0) {
            throw new Exception("更新借閱數量失敗，可能已達借閱上限");
        }

        // Insert borrow record
        $stmt = $pdo->prepare("
            INSERT INTO book_info (
                isbn, anco, cal_no, title, author, publisher, published_date, 
                available, borrow_date, return_date,
                student_card_number, school_card_number, name, role, image_url,
                creation_day
            ) SELECT 
                isbn, anco, cal_no, title, author, publisher, published_date,
                0, CURDATE(), DATE_ADD(CURDATE(), INTERVAL :days DAY),
                :student_card_number, :school_card_number, :name, :role, image_url,
                NOW()
            FROM book_info2 
            WHERE id = :book_id");

        $stmt->execute([
            ':days' => $days,
            ':student_card_number' => $user['student_card_number'],
            ':school_card_number' => $user['school_card_number'],
            ':name' => $user['name'],
            ':role' => $user['role'],
            ':book_id' => $_POST['book_id']
        ]);

        $pdo->commit();

        $success = sprintf(
            "%s-%s-%s 已成功借出 %s書籍 及 到時還書日期為：%s",
            $user['name'],
            $user['class_type'],
            $user['student_number'],
            $book['title'],
            $return_date->format('Y-m-d')
        );

    } catch(Exception $e) {
        $pdo->rollBack();
        $error = "借閱失敗：" . $e->getMessage();
        error_log("[ERROR] Borrow failed: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>確認借閱 - 圖書借還系統</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="../../index.php">圖書借還系統</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>確認借閱</h2>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
            <div class="mb-3">
                <a href="search.php" class="btn btn-primary">繼續借書</a>
                <a href="../../index.php" class="btn btn-secondary">返回首頁</a>
            </div>
        <?php elseif (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php elseif ($book && $user): ?>
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">書籍資料</h5>
                        </div>
                        <div class="card-body">
                            <p class="card-text">
                                <strong>書名：</strong><?php echo htmlspecialchars($book['title']); ?><br>
                                <strong>ISBN：</strong><?php echo htmlspecialchars($book['isbn']); ?><br>
                                <strong>館藏編號：</strong><?php echo htmlspecialchars($book['anco']); ?><br>
                                <strong>作者：</strong><?php echo htmlspecialchars($book['author']); ?><br>
                                <strong>出版社：</strong><?php echo htmlspecialchars($book['publisher']); ?><br>
                                <strong>出版日期：</strong><?php echo htmlspecialchars($book['published_date']); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">借閱者資料</h5>
                        </div>
                        <div class="card-body">
                            <p class="card-text">
                                <strong>姓名：</strong><?php echo htmlspecialchars($user['name']); ?><br>
                                <strong>班級：</strong><?php echo htmlspecialchars($user['class_type']); ?><br>
                                <strong>學號：</strong><?php echo htmlspecialchars($user['student_number']); ?><br>
                                <strong>身份：</strong><?php echo htmlspecialchars($user['role_name']); ?><br>
                                <strong>已借冊數：</strong><?php echo $user['current_borrowed']; ?> / <?php echo $user['quota']; ?><br>
                                <strong>借閱日期：</strong><?php echo (new DateTime())->format('Y-m-d'); ?><br>
                                <strong>預計還書日期：</strong><?php echo $return_date->format('Y-m-d'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <form method="POST" class="mb-3">
                <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                <input type="hidden" name="user_id" value="<?php echo $user['school_card_number']; ?>">
                <button type="submit" class="btn btn-primary" onclick="return confirmBorrow()">確認借閱</button>
                <a href="javascript:history.back()" class="btn btn-secondary">返回修改</a>
            </form>
        <?php else: ?>
            <div class="alert alert-warning">參數錯誤，請重新操作</div>
        <?php endif; ?>
        
        <?php if (!isset($success)): ?>
            <a href="search.php" class="btn btn-link">返回查詢</a>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function confirmBorrow() {
        <?php if (isset($user) && isset($book)): ?>
            return confirm('確定要借出此書嗎？\n\n' + 
                '借閱人：<?php echo addslashes($user['name']); ?>\n' +
                '班級：<?php echo addslashes($user['class_type']); ?>\n' +
                '學號：<?php echo addslashes($user['student_number']); ?>\n' +
                '已借冊數：<?php echo $user['current_borrowed']; ?> / <?php echo $user['quota']; ?>\n' +
                '書名：<?php echo addslashes($book['title']); ?>\n' +
                '預計還書日期：<?php echo $return_date->format('Y-m-d'); ?>');
        <?php else: ?>
            return false;
        <?php endif; ?>
    }
    </script>
</body>
</html>
