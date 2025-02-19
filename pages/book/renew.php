<?php
session_start();
require_once '../../config/db.php';

$book_id = $_GET['id'] ?? null;

if (!$book_id) {
    echo "書籍 ID 不存在。";
    exit;
}

try {
    // 查詢最後借閱者資訊
    $sql = "SELECT bi.*, s.name AS student_name, s.role AS student_role
            FROM book_info bi
            LEFT JOIN students s ON bi.school_card_number = s.school_card_number
            WHERE bi.isbn = (SELECT isbn FROM book_info2 WHERE id = ?)
            ORDER BY bi.borrow_date DESC
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$book_id]);
    $borrow_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$borrow_info) {
        echo "找不到借閱紀錄。";
        exit;
    }

    // 處理續借確認
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // 取得 role.days
        $role_sql = "SELECT days FROM role WHERE role = ?";
        $role_stmt = $pdo->prepare($role_sql);
        $role_stmt->execute([$borrow_info['student_role']]);
        $role_data = $role_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$role_data) {
            echo "找不到角色資訊。";
            exit;
        }

        $days = $role_data['days'];

        // 更新 return_date
        $new_return_date = date('Y-m-d', strtotime($borrow_info['return_date'] . ' + ' . $days . ' days'));
        $update_sql = "UPDATE book_info SET return_date = ? WHERE id = ?";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([$new_return_date, $borrow_info['id']]);

        echo "<script>alert('續借成功！新的應還日期為：" . $new_return_date . "'); window.location.href = 'search.php';</script>";
        exit;
    }

} catch(PDOException $e) {
    $error = "資料庫錯誤：" . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>續借確認 - 圖書借還系統</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>續借確認</h2>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (isset($borrow_info)): ?>
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">最後借閱者資訊</h5>
                    <p>姓名：<?php echo htmlspecialchars($borrow_info['student_name']); ?></p>
                    <p>借閱日期：<?php echo htmlspecialchars($borrow_info['borrow_date']); ?></p>
                    <p>應還日期：<?php echo htmlspecialchars($borrow_info['return_date']); ?></p>

                    <form method="POST">
                        <button type="submit" class="btn btn-primary">確認續借</button>
                        <a href="search.php" class="btn btn-secondary">取消</a>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>