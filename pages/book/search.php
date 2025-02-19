<?php
session_start();
require_once '../../config/db.php';

$search_type = $_GET['type'] ?? 'all';
$search_query = $_GET['search'] ?? '';

try {
    $params = [];
    $conditions = [];

    if (!empty($search_query)) {
        $search = "%$search_query%";
        switch ($search_type) {
            case 'isbn':
                $conditions[] = "isbn LIKE ?";
                $params[] = $search;
                break;
            case 'anco':
                $conditions[] = "anco LIKE ?";
                $params[] = $search;
                break;
            case 'title':
                $conditions[] = "title LIKE ?";
                $params[] = $search;
                break;
            case 'author':
                $conditions[] = "author LIKE ?";
                $params[] = $search;
                break;
            default:
                $conditions[] = "(isbn LIKE ? OR anco LIKE ? OR title LIKE ? OR author LIKE ?)";
                $params = array_merge($params, [$search, $search, $search, $search]);
        }
    }

    $sql = "SELECT * FROM book_info2 WHERE 1=1";
    if (!empty($conditions)) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }
    $sql .= " ORDER BY id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "查詢失敗：" . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>查詢書籍 - 圖書借還系統</title>
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
        <h2>查詢書籍</h2>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="GET" class="mb-4">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="type">搜尋類型</label>
                    <select class="form-control" id="type" name="type">
                        <option value="all" <?php echo $search_type == 'all' ? 'selected' : ''; ?>>全部</option>
                        <option value="isbn" <?php echo $search_type == 'isbn' ? 'selected' : ''; ?>>ISBN</option>
                        <option value="anco" <?php echo $search_type == 'anco' ? 'selected' : ''; ?>>館藏編號</option>
                        <option value="title" <?php echo $search_type == 'title' ? 'selected' : ''; ?>>書名</option>
                        <option value="author" <?php echo $search_type == 'author' ? 'selected' : ''; ?>>作者</option>
                    </select>
                </div>
                <div class="col-md-8 mb-3">
                    <label for="search">搜尋關鍵字</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="輸入關鍵字">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">搜尋</button>
            <a href="add.php" class="btn btn-success">新增書籍</a>
        </form>

        <?php if (!empty($books)): ?>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ISBN</th>
                        <th>館藏編號</th>
                        <th>書名</th>
                        <th>作者</th>
                        <th>出版社</th>
                        <th>狀態</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($books as $book): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($book['isbn']); ?></td>
                            <td><?php echo htmlspecialchars($book['anco']); ?></td>
                            <td><?php echo htmlspecialchars($book['title']); ?></td>
                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                            <td><?php echo htmlspecialchars($book['publisher']); ?></td>
                            <td><?php echo $book['available'] ? '可借閱' : '借出中'; ?></td>
                            <td>
                                <a href="history.php?id=<?php echo $book['id']; ?>" class="btn btn-info btn-sm">查看</a>
                                <a href="edit.php?id=<?php echo $book['id']; ?>" class="btn btn-primary btn-sm">編輯</a>
                                <?php if ($book['available']): ?>
                                    <a href="borrow.php?id=<?php echo $book['id']; ?>" class="btn btn-success btn-sm">借書</a>
                                <?php endif; ?>
                                <?php if (!$book['available']): ?>
                                    <a href="return.php?id=<?php echo $book['id']; ?>" class="btn btn-warning btn-sm">還書</a>
                                    <a href="renew.php?id=<?php echo $book['id']; ?>" class="btn btn-info btn-sm">續借</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif ($_SERVER['REQUEST_METHOD'] == 'GET' && !empty($search_query)): ?>
            <div class="alert alert-info">沒有找到符合條件的書籍</div>
        <?php endif; ?>

        <a href="../../index.php" class="btn btn-secondary">返回首頁</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
