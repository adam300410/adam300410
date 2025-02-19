<?php
session_start();
require_once '../../config/db.php';

$user = null;
$current_borrows = [];
$borrow_history = [];

if (isset($_GET['id'])) {
    try {
        // Get user details
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Get current borrows
            $stmt = $pdo->prepare("SELECT * FROM book_info 
                WHERE school_card_number = ? AND available = 0 
                ORDER BY borrow_date DESC");
            $stmt->execute([$user['school_card_number']]);
            $current_borrows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get borrow history
            $stmt = $pdo->prepare("SELECT * FROM book_info 
                WHERE school_card_number = ? AND available = 1 
                ORDER BY borrow_date DESC");
            $stmt->execute([$user['school_card_number']]);
            $borrow_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $error = "找不到使用者";
        }
    } catch(PDOException $e) {
        $error = "查詢失敗：" . $e->getMessage();
    }
} else {
    $error = "未指定使用者";
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>借閱人詳細資料 - 圖書借還系統</title>
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
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
            <a href="user.php" class="btn btn-secondary">返回查詢</a>
        <?php elseif ($user): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="card-title">借閱人資料</h2>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>姓名：</strong> <?php echo htmlspecialchars($user['name']); ?></p>
<p><strong>學號：</strong> <?php echo htmlspecialchars($user['student_number']); ?></p>
<p><strong>學部：</strong> <?php echo htmlspecialchars($user['section']); ?></p>
                            <p><strong>班級：</strong> <?php echo htmlspecialchars($user['class']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>目前借閱數：</strong> <?php echo count($current_borrows); ?></p>
                            <p><strong>歷史借閱數：</strong> <?php echo count($borrow_history); ?></p>
                            <p><strong>總借閱次數：</strong> <?php echo count($current_borrows) + count($borrow_history); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($current_borrows)): ?>
                <h3>目前借閱中</h3>
                <table class="table table-striped mb-4">
                    <thead>
                        <tr>
                            <th>ISBN</th>
                            <th>館藏編號</th>
                            <th>書名</th>
                            <th>借出日期</th>
                            <th>已借閱天數</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($current_borrows as $book): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($book['isbn']); ?></td>
                                <td><?php echo htmlspecialchars($book['cal_no']); ?></td>
                                <td><?php echo htmlspecialchars($book['title']); ?></td>
                                <td><?php echo htmlspecialchars($book['borrow_date']); ?></td>
                                <td><?php echo floor((strtotime('now') - strtotime($book['borrow_date'])) / (60 * 60 * 24)); ?> 天</td>
                                <td>
                                    <form method="post" action="return.php">
                                        <input type="hidden" name="book_id" value="<?php echo htmlspecialchars($book['id']); ?>">
                                        <button type="submit" class="btn btn-primary">還書</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if (!empty($borrow_history)): ?>
                <h3>借閱歷史記錄</h3>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ISBN</th>
                            <th>館藏編號</th>
                            <th>書名</th>
                            <th>借出日期</th>
                            <th>歸還日期</th>
                            <th>借閱天數</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($borrow_history as $book): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($book['isbn']); ?></td>
                                <td><?php echo htmlspecialchars($book['cal_no']); ?></td>
                                <td><?php echo htmlspecialchars($book['title']); ?></td>
                                <td><?php echo htmlspecialchars($book['borrow_date']); ?></td>
                                <td><?php echo htmlspecialchars($book['return_date']); ?></td>
                                <td><?php echo $book['borrow_day']; ?> 天</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if (empty($current_borrows) && empty($borrow_history)): ?>
                <div class="alert alert-info">此使用者尚無借閱記錄</div>
            <?php endif; ?>

            <div class="mt-3">
                <a href="user.php" class="btn btn-secondary">返回查詢</a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
