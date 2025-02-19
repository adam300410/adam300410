<?php
session_start();
require_once '../../config/db.php';

$book_id = $_GET['id'] ?? null;
$history = [];
$book = null;

if ($book_id) {
    try {
        // Get book information
        $stmt = $pdo->prepare("SELECT * FROM book_info2 WHERE id = ?");
        $stmt->execute([$book_id]);
        $book = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($book) {
            // Get borrowing history including current borrowing
            $stmt = $pdo->prepare("
                SELECT 
                    bi.*,
                    COALESCE(s.name, bi.name) as student_name,
                    s.class_type,
                    s.section
                FROM book_info2 b2
                INNER JOIN book_info bi ON b2.anco = bi.anco 
                LEFT JOIN students s ON bi.school_card_number = s.school_card_number 
                WHERE b2.id = ?
                ORDER BY 
                    CASE 
                        WHEN bi.return_date_time = '1970-01-01 00:00:00' OR bi.return_date_time IS NULL THEN 1 
                        ELSE 0 
                    END DESC,
                    bi.creation_day DESC, 
                    bi.return_date_time DESC
            ");
            $stmt->execute([$book_id]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Filter out null records from LEFT JOIN
            $valid_history = array_filter($history, function($record) {
                return !empty($record['creation_day']);
            });
        }
    } catch(PDOException $e) {
        $error = "查詢失敗：" . $e->getMessage();
    }
}

// Handle return book action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['return_book']) && isset($_POST['book_info_id']) && $book) {
    try {
        $pdo->beginTransaction();
        
        // Update book_info2 availability
        $stmt = $pdo->prepare("UPDATE book_info2 SET available = 1 WHERE id = ?");
        $stmt->execute([$book_id]);
        
        // Process remarks
        $remark = [];
        if (!empty($_POST['is_damaged'])) $remark[] = "損壞";
        if (!empty($_POST['is_lost'])) $remark[] = "遺失";
        if (!empty($_POST['is_exchanged'])) $remark[] = "換書";
        
        $remarkText = !empty($remark) ? implode(",", $remark) : null;
        if (!empty($_POST['remark'])) {
            $remarkText = $remarkText ? $remarkText . "," . $_POST['remark'] : $_POST['remark'];
        }

        // Update book_info return date and status
        $stmt = $pdo->prepare("
            UPDATE book_info 
            SET 
                return_date = NOW(),
                return_date_time = NOW(),
                borrow_day = FLOOR(TIMESTAMPDIFF(DAY, borrow_date, NOW())),
                available = 1
            WHERE id = ? AND (return_date_time IS NULL OR return_date_time = '1970-01-01 00:00:00')
        ");
        $stmt->execute([
            $_POST['book_info_id']
        ]);

        // Update remark in book_info2 table by matching anco
        if ($remarkText) {
            $stmt = $pdo->prepare("
                UPDATE book_info2 
                SET remark = ? 
                WHERE anco = (
                    SELECT anco FROM book_info WHERE id = ?
                )
            ");
            $stmt->execute([
                $remarkText,
                $_POST['book_info_id']
            ]);
        }
        
        $pdo->commit();
        
        // Get the book info record that's being returned
        $stmt = $pdo->prepare("
            SELECT 
                bi.*,
                COALESCE(s.name, bi.name) as student_name,
                s.class_type
            FROM book_info bi
            LEFT JOIN students s ON bi.school_card_number = s.school_card_number 
            WHERE bi.id = ?
        ");
        $stmt->execute([$_POST['book_info_id']]);
        $bookInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($bookInfo) {
            // Build status messages
            $status_msgs = [];
            if (isset($_POST['is_damaged'])) $status_msgs[] = "書籍已標記為損壞";
            if (isset($_POST['is_lost'])) $status_msgs[] = "書籍已標記為遺失";
            if (isset($_POST['is_exchanged'])) $status_msgs[] = "書籍已標記為換書";
            
            $status_text = $status_msgs ? "\n狀態：" . implode('、', $status_msgs) : '';
            $remark_text = !empty($_POST['remark']) ? "\n備註：" . $_POST['remark'] : '';
            
            // Set success message in session and redirect
            $_SESSION['success'] = sprintf(
                "還書成功!\n書名：%s\n借閱人：%s\n班級：%s\n學號：%s%s%s",
                $book['title'],
                $bookInfo['student_name'] ?? $bookInfo['name'],
                $bookInfo['class_type'],
                $bookInfo['school_card_number'],
                $status_text,
                $remark_text
            );
        } else {
            $_SESSION['error'] = "找不到書籍借閱記錄";
        }
        
        // Redirect back to the history page
        header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $_GET['id']);
        exit;
    } catch(PDOException $e) {
        $pdo->rollBack();
        $error = "歸還失敗：" . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>借閱歷史 - 圖書借還系統</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="../../index.php">圖書借還系統</a>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (isset($error) && !empty($error)): ?>
            <div class="alert alert-danger"><?php echo nl2br(htmlspecialchars($error)); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success']) && !empty($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo nl2br(htmlspecialchars($_SESSION['success'])); ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error']) && !empty($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo nl2br(htmlspecialchars($_SESSION['error'])); ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if ($book) {
            ?>
            <h2>借閱歷史</h2>
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($book['title']); ?></h5>
                    <p class="card-text">
                        <strong>ISBN：</strong><?php echo htmlspecialchars($book['isbn']); ?><br>
                        <strong>館藏編號：</strong><?php echo htmlspecialchars($book['anco']); ?><br>
                        <strong>作者：</strong><?php echo htmlspecialchars($book['author']); ?><br>
                        <strong>出版社：</strong><?php echo htmlspecialchars($book['publisher']); ?><br>
                        <strong>目前狀態：</strong><?php echo $book['available'] ? '可借閱' : '已借出'; ?>
                    </p>
                    
                    <?php 
                    // Find current borrower from history
                    $current_record = null;
                    foreach ($valid_history as $record) {
                        if (!$record['return_date_time'] || $record['return_date_time'] == '1970-01-01 00:00:00') {
                            $current_record = $record;
                            break;
                        }
                    }
                    if (!$book['available'] && $current_record) {
                        ?>
                        <button type="button" class="btn btn-primary mt-3" 
                                onclick="showReturnModal(
                                    '<?php echo htmlspecialchars($current_record['id']); ?>', 
                                    '<?php echo htmlspecialchars($book['title']); ?>',
                                    '<?php echo htmlspecialchars($current_record['student_name'] ?? $current_record['name']); ?>',
                                    '<?php echo htmlspecialchars($current_record['class_type']); ?>',
                                    '<?php echo htmlspecialchars($current_record['school_card_number']); ?>'
                                )">
                            歸還書籍
                        </button>
                        <?php 
                    }
                    ?>
                </div>
            </div>

            <h3>過往借閱記錄</h3>
            <?php 
            if (!empty($valid_history)) {
                ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>借出時間</th>
                            <th>歸還時間</th>
                            <th>借閱者</th>
                            <th>班級</th>
                            <th>學部</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($valid_history as $record) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['creation_day']); ?></td>
                                <td><?php echo ($record['return_date_time'] && $record['return_date_time'] != '1970-01-01 00:00:00') ? htmlspecialchars($record['return_date_time']) : '未還書'; ?></td>
                                <td><?php echo htmlspecialchars($record['student_name'] ?? $record['name']); ?></td>
                                <td><?php echo htmlspecialchars($record['class_type']); ?></td>
                                <td><?php echo htmlspecialchars($record['section']); ?></td>
                                <td>
                                    <?php if (!$record['return_date_time'] || $record['return_date_time'] == '1970-01-01 00:00:00'): ?>
                                        <button type="button" class="btn btn-primary btn-sm"
                                                onclick="showReturnModal(
                                                    '<?php echo htmlspecialchars($record['id']); ?>', 
                                                    '<?php echo htmlspecialchars($book['title']); ?>',
                                                    '<?php echo htmlspecialchars($record['student_name'] ?? $record['name']); ?>',
                                                    '<?php echo htmlspecialchars($record['class_type']); ?>',
                                                    '<?php echo htmlspecialchars($record['school_card_number']); ?>'
                                                )">
                                            還書
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } else { ?>
            <div class="alert alert-info">此書還沒有過往借閱記錄</div>
            <?php } ?>
        <?php } else { ?>
            <div class="alert alert-danger">找不到指定的書籍</div>
        <?php } ?>

        <a href="search.php" class="btn btn-secondary">返回查詢</a>
    </div>

    <!-- Return Modal -->
    <div class="modal fade" id="returnModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">歸還書籍</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="book_info_id" id="modal_book_info_id">
                        <div class="mb-3" id="book_info"></div>
                        <div class="mb-3">
                            <label for="remark" class="form-label">備註</label>
                            <textarea class="form-control" name="remark" id="remark" rows="3" placeholder="請輸入備註"></textarea>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_damaged" value="1" id="is_damaged">
                                <label class="form-check-label" for="is_damaged">損壞</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_lost" value="1" id="is_lost">
                                <label class="form-check-label" for="is_lost">遺失</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_exchanged" value="1" id="is_exchanged">
                                <label class="form-check-label" for="is_exchanged">換書</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" name="return_book" class="btn btn-primary">確認歸還</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function showReturnModal(bookInfoId, title, studentName, classType, studentNumber) {
        document.getElementById('modal_book_info_id').value = bookInfoId;
        
        // Display book and student info
        document.getElementById('book_info').innerHTML = `
            書名：${title}<br>
            借閱人：${studentName}<br>
            班級：${classType}<br>
            學號：${studentNumber}
        `;
        
        // Reset form
        document.getElementById('remark').value = '';
        document.getElementById('is_damaged').checked = false;
        document.getElementById('is_lost').checked = false;
        document.getElementById('is_exchanged').checked = false;
        
        // Show modal
        new bootstrap.Modal(document.getElementById('returnModal')).show();
    }
    </script>
</body>
</html>
