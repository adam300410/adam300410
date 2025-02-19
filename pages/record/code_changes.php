<?php
session_start();
require_once '../../config/db.php';

try {
    // 檢查資料表是否存在
    $tables = $pdo->query("SHOW TABLES LIKE 'code_changes'")->rowCount();
    if ($tables == 0) {
        throw new Exception('需要先建立 code_changes 資料表！請執行 database.sql 中的建表語句。');
    }

    // 檢查使用者權限
    if (!isset($_SESSION['user_id'])) {
        header('Location: /');
        exit;
    }

    // 取得搜尋參數
    $search_user = isset($_GET['user_id']) ? $_GET['user_id'] : '';
    $search_file = isset($_GET['file']) ? $_GET['file'] : '';
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

    // 準備查詢條件
    $params = [];
    $conditions = [];

    if ($search_user !== '') {
        $conditions[] = "cc.user_id = ?";
        $params[] = $search_user;
    }

    if ($search_file !== '') {
        $conditions[] = "cc.file_path LIKE ?";
        $params[] = "%$search_file%";
    }

    if ($start_date !== '') {
        $conditions[] = "cc.change_date >= ?";
        $params[] = $start_date;
    }

    if ($end_date !== '') {
        $conditions[] = "cc.change_date <= ?";
        $params[] = $end_date . ' 23:59:59';
    }

    // 建立 SQL 查詢
    $sql = "SELECT cc.*, s.name as user_name 
            FROM code_changes cc 
            LEFT JOIN students s ON cc.user_id = s.school_card_number";

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    $sql .= " ORDER BY cc.change_date DESC LIMIT 1000";

    // 執行查詢
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $changes = $stmt->fetchAll();

    // 取得所有使用者清單
    $stmt = $pdo->query("SELECT school_card_number as id, name FROM students ORDER BY name");
    $users = $stmt->fetchAll();

} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>程式碼修改記錄 - 圖書借還系統</title>
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
        <h2>程式碼修改記錄</h2>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php else: ?>
            <form method="GET" class="mb-4">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="user_id">使用者</label>
                        <select class="form-control" id="user_id" name="user_id">
                            <option value="">全部</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= htmlspecialchars($user['id']) ?>" <?= $search_user == $user['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="file">檔案路徑</label>
                        <input type="text" class="form-control" id="file" name="file" value="<?= htmlspecialchars($search_file) ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="start_date">開始日期</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?= $start_date ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="end_date">結束日期</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?= $end_date ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">搜尋</button>
            </form>

            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>時間</th>
                            <th>使用者</th>
                            <th>檔案路徑</th>
                            <th>動作類型</th>
                            <th>SQL查詢/錯誤訊息</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($changes as $change): ?>
                            <tr>
                                <td><?= $change['change_date'] ?></td>
                                <td><?= htmlspecialchars($change['user_name']) ?></td>
                                <td><?= htmlspecialchars($change['file_path']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $change['action_type'] == 'error' ? 'danger' : 'primary' ?>">
                                        <?= $change['action_type'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($change['sql_query']): ?>
                                        <pre class="mb-0"><code><?= htmlspecialchars($change['sql_query']) ?></code></pre>
                                    <?php endif; ?>
                                    <?php if ($change['error_message']): ?>
                                        <pre class="mb-0 text-danger"><code><?= htmlspecialchars($change['error_message']) ?></code></pre>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <a href="../../index.php" class="btn btn-secondary">返回首頁</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>