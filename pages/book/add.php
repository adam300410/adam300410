<?php
session_start();
require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $stmt = $pdo->prepare("INSERT INTO book_info2 (isbn, dsedj_no, anco, title, author, publisher, published_date, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        $date = $_POST['published_date'] ?? date('Y-m-d');
        $image_url = '';
        
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $target_dir = "../../uploads/books/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $image_url = $target_dir . basename($_FILES["image"]["name"]);
            move_uploaded_file($_FILES["image"]["tmp_name"], $image_url);
        }
        
        $stmt->execute([
            $_POST['isbn'],
            $_POST['dsedj_no'],
            $_POST['anco'],
            $_POST['title'],
            $_POST['author'],
            $_POST['publisher'],
            $date,
            $image_url
        ]);
        
        $success = "書籍新增成功！";
    } catch(PDOException $e) {
        $error = "新增失敗：" . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新增書籍 - 圖書借還系統</title>
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
        <h2>新增書籍</h2>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group mb-3">
                <label for="isbn">ISBN</label>
                <input type="text" class="form-control" id="isbn" name="isbn" required>
            </div>

            <div class="form-group mb-3">
                <label for="dsedj_no">教青局書目編號</label>
                <input type="text" class="form-control" id="dsedj_no" name="dsedj_no">
            </div>

            <div class="form-group mb-3">
                <label for="anco">館藏編號</label>
                <input type="text" class="form-control" id="anco" name="anco" required>
            </div>

            <div class="form-group mb-3">
                <label for="title">書名</label>
                <input type="text" class="form-control" id="title" name="title" required>
            </div>

            <div class="form-group mb-3">
                <label for="author">作者</label>
                <input type="text" class="form-control" id="author" name="author" required>
            </div>

            <div class="form-group mb-3">
                <label for="publisher">出版社</label>
                <input type="text" class="form-control" id="publisher" name="publisher" required>
            </div>

            <div class="form-group mb-3">
                <label for="published_date">出版日期</label>
                <input type="date" class="form-control" id="published_date" name="published_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <div class="form-group mb-3">
                <label for="image">圖片</label>
                <input type="file" class="form-control" id="image" name="image" accept="image/*">
            </div>

            <button type="submit" class="btn btn-primary">新增書籍</button>
            <a href="../../index.php" class="btn btn-secondary">返回首頁</a>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
