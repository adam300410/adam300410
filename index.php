<?php
session_start();
require_once 'config/db.php';
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>圖書借還系統</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">圖書借還系統</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="bookDropdown" role="button" data-bs-toggle="dropdown">
                            書籍管理
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="pages/book/add.php">新增書籍</a></li>
                            <li><a class="dropdown-item" href="pages/book/search.php">查詢書籍</a></li>
                            <li><a class="dropdown-item" href="pages/book/borrow.php">借閱書籍</a></li>
                            <li><a class="dropdown-item" href="pages/book/return.php">還書</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="recordDropdown" role="button" data-bs-toggle="dropdown">
                            借閱紀錄
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="pages/record/search.php">查詢借閱紀錄</a></li>
                            <li><a class="dropdown-item" href="pages/record/user.php">查詢借閱人</a></li>
                            <li><a class="dropdown-item" href="pages/record/books.php">查詢借閱書籍</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="reportDropdown" role="button" data-bs-toggle="dropdown">
                            報表
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="pages/report/monthly.php">每月報表</a></li>
                            <li><a class="dropdown-item" href="pages/report/custom.php">自定時間段報表</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <h2>歡迎使用圖書借還系統</h2>
                <p>請從上方選單選擇所需功能，或使用以下快速操作：</p>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">書籍管理</h5>
                        <div class="d-grid gap-2">
                            <a href="pages/book/add.php" class="btn btn-primary">新增書籍</a>
                            <a href="pages/book/search.php" class="btn btn-outline-primary">查詢書籍</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">借還書作業</h5>
                        <div class="d-grid gap-2">
                            <a href="pages/book/borrow.php" class="btn btn-success">借書</a>
                            <a href="pages/book/return.php" class="btn btn-outline-success">還書</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">借閱紀錄</h5>
                        <div class="d-grid gap-2">
                            <a href="pages/record/search.php" class="btn btn-info">查詢借閱紀錄</a>
                            <a href="pages/record/user.php" class="btn btn-outline-info">查詢借閱人</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php
        try {
            // Get total books count
            $stmt = $pdo->query("SELECT COUNT(*) FROM book_info2");
            $total_books = $stmt->fetchColumn();

            // Get borrowed books count
            $stmt = $pdo->query("SELECT COUNT(*) FROM book_info2 WHERE 已借出 = 1");
            $borrowed_books = $stmt->fetchColumn();

            // Get total students count
                        $stmt = $pdo->query("SELECT COUNT(*) FROM students WHERE valid_or_not = 1");
            $total_users = $stmt->fetchColumn();
        ?>
        <div class="row mt-4">
            <div class="col-md-4 mb-4">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?php echo $total_books; ?></h3>
                        <p class="card-text">總藏書量</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?php echo $borrowed_books; ?></h3>
                        <p class="card-text">目前借出</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?php echo $total_users; ?></h3>
                        <p class="card-text">有效使用者</p>
                    </div>
                </div>
            </div>
        </div>
        <?php
        } catch(PDOException $e) {
            echo '<div class="alert alert-danger">無法載入統計資料</div>';
        }
        ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
