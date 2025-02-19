<?php
session_start();
require_once '../../config/db.php';

$referer = $_GET['referer'] ?? '../../index.php';
$book_id = $_GET['id'] ?? null;

if (!$book_id) {
    header('Location: ../../index.php');
    exit;
}

// Fetch book details
try {
    $stmt = $pdo->prepare("SELECT * FROM book_info2 WHERE id = ?");
    $stmt->execute([$book_id]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$book) {
        $error = "書籍不存在";
    }
} catch(PDOException $e) {
    $error = "查詢失敗：" . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $stmt = $pdo->prepare("UPDATE book_info2 SET 
                               isbn = ?, 
                               dsedj_no = ?, 
                               anco = ?, 
                               title = ?, 
                               author = ?, 
                               publisher = ?, 
                               published_date = ?, 
                               available = ? 
                               WHERE id = ?");
        
        $available = isset($_POST['available']) ? 1 : 0;
        
        $stmt->execute([
            $_POST['isbn'],
            $_POST['dsedj_no'],
            $_POST['anco'],
            $_POST['title'],
            $_POST['author'],
            $_POST['publisher'],
            $_POST['published_date'],
            $available,
            $book_id
        ]);
        
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $target_dir = "../../uploads/books/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $image_url = $target_dir . basename($_FILES["image"]["name"]);
            move_uploaded_file($_FILES["image"]["tmp_name"], $image_url);
            
            $update_image = $pdo->prepare("UPDATE book_info2 SET image_url = ? WHERE id = ?");
            $update_image->execute([$image_url, $book_id]);
        }
        
        $success = "書籍資訊更新成功！";
        
        // Refresh book data
        $stmt = $pdo->prepare("SELECT * FROM book_info2 WHERE id = ?");
        $stmt->execute([$book_id]);
        $book = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $error = "更新失敗：" . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>編輯書籍 - 圖書借還系統</title>
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
        <h2>編輯書籍</h2>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (isset($book)): ?>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group mb-3">
                <label for="isbn">ISBN</label>
                <input type="text" class="form-control" id="isbn" name="isbn" value="<?php echo htmlspecialchars($book['isbn']); ?>" required>
            </div>

            <div class="form-group mb-3">
                <label for="dsedj_no">教青局書目編號</label>
                <input type="text" class="form-control" id="dsedj_no" name="dsedj_no" value="<?php echo htmlspecialchars($book['dsedj_no'] ?? ''); ?>">
            </div>

            <div class="form-group mb-3">
                <label for="anco">館藏編號</label>
                <input type="text" class="form-control" id="anco" name="anco" value="<?php echo htmlspecialchars($book['anco']); ?>" required>
            </div>

            <div class="form-group mb-3">
                <label for="title">書名</label>
                <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($book['title']); ?>" required>
            </div>

            <div class="form-group mb-3">
                <label for="author">作者</label>
                <input type="text" class="form-control" id="author" name="author" value="<?php echo htmlspecialchars($book['author']); ?>" required>
            </div>

            <div class="form-group mb-3">
                <label for="publisher">出版社</label>
                <input type="text" class="form-control" id="publisher" name="publisher" value="<?php echo htmlspecialchars($book['publisher']); ?>" required>
            </div>

            <div class="form-group mb-3">
                <label for="published_date">出版日期</label>
                <input type="date" class="form-control" id="published_date" name="published_date" value="<?php echo htmlspecialchars($book['published_date']); ?>" required>
            </div>

            <div class="form-group mb-3">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="available" name="available" <?php echo $book['available'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="available">可借閱</label>
                </div>
            </div>

            <?php if(!empty($book['image_url'])): ?>
            <div class="form-group mb-3">
                <label>目前圖片</label><br>
                <img src="<?php echo htmlspecialchars($book['image_url']); ?>" alt="Book Cover" style="max-width: 200px;" class="mb-2">
            </div>
            <?php endif; ?>

            <div class="form-group mb-3">
                <label for="image">更換圖片</label>
                <input type="file" class="form-control" id="image" name="image" accept="image/*">
            </div>

            <button type="submit" class="btn btn-primary">更新書籍</button>
            <a href="<?php echo htmlspecialchars($referer); ?>" class="btn btn-secondary">返回</a>
        </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
