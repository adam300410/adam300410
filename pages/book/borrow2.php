<?php
session_start();
require_once '../../config/db.php';

$book = null;
$users = [];

if (isset($_GET['id'])) {
    try {
        // Get book details
        $stmt = $pdo->prepare("SELECT * FROM book_info2 WHERE id = ? AND available = 1");
        $stmt->execute([$_GET['id']]);
        $book = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$book) {
            $error = "找不到可借閱的書籍";
        }
    } catch(PDOException $e) {
        $error = "查詢失敗：" . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_id']) && isset($_POST['user_id'])) {
    try {
        $pdo->beginTransaction();

        // Update book_info2
        $stmt = $pdo->prepare("UPDATE book_info2 SET available = 0, borrow_time = borrow_time + 1 WHERE id = ?");
        $stmt->execute([$_POST['book_id']]);

        // Get user info
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([$_POST['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Insert into book_info
        $stmt = $pdo->prepare("INSERT INTO book_info (isbn, cal_no, title, author, publisher, published_date, available, borrow_date, student_card_number, name, role, image_url) 
            SELECT isbn, cal_no, title, author, publisher, published_date, 0, CURDATE(), ?, ?, ?, image_url 
            FROM book_info2 WHERE id = ?");
        $stmt->execute([
            $user['student_card_number'],
            $user['name'],
            $user['role'] == 1 ? '老師' : '學生',
            $_POST['book_id']
        ]);

        $pdo->commit();
        header("Location: search.php");
        exit;
    } catch(PDOException $e) {
        $pdo->rollBack();
        $error = "借閱失敗：" . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>借閱書籍 - 圖書借還系統</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet">
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
        <h2>借閱書籍</h2>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($book): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($book['title']); ?></h5>
                    <p class="card-text">
                        <strong>ISBN:</strong> <?php echo htmlspecialchars($book['isbn']); ?><br>
                        <strong>館藏編號:</strong> <?php echo htmlspecialchars($book['cal_no']); ?><br>
                        <strong>作者:</strong> <?php echo htmlspecialchars($book['author']); ?><br>
                        <strong>出版社:</strong> <?php echo htmlspecialchars($book['publisher']); ?><br>
                        <strong>出版日期:</strong> <?php echo htmlspecialchars($book['published_date']); ?>
                    </p>
                </div>
            </div>

            <ul class="nav nav-tabs mb-3" id="searchTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" id="name-tab" data-bs-toggle="tab" data-bs-target="#name-search" type="button" role="tab">姓名/學生證搜尋</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="class-tab" data-bs-toggle="tab" data-bs-target="#class-search" type="button" role="tab">班級搜尋</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="card-tab" data-bs-toggle="tab" data-bs-target="#card-search" type="button" role="tab">讀卡機</button>
                </li>
            </ul>

            <div class="tab-content mb-3">
                <!-- 姓名/學生證搜尋 -->
                <div class="tab-pane fade show active" id="name-search" role="tabpanel">
                    <form method="POST" id="nameSearchForm">
                        <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                        <input type="hidden" name="user_id" id="selected_user_id">
                        <div class="mb-3">
                            <label for="search_user">輸入姓名或學生證號碼</label>
                            <select class="form-control" id="search_user" style="width: 100%"></select>
                        </div>
                        <button type="submit" class="btn btn-primary" disabled>確認借閱</button>
                    </form>
                </div>

                <!-- 班級搜尋 -->
                <div class="tab-pane fade" id="class-search" role="tabpanel">
                    <form method="POST" id="classSearchForm">
                        <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                        <input type="hidden" name="user_id" id="class_user_id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="class_type">班級</label>
                                <select class="form-control" id="class_type" required>
                                    <option value="">請選擇班級</option>
                                    <?php
                                    $stmt = $pdo->query("SELECT DISTINCT class_type FROM students WHERE valid_or_not = 1 ORDER BY class_type");
                                    while ($row = $stmt->fetch()) {
                                        echo "<option value='" . htmlspecialchars($row['class_type']) . "'>" . htmlspecialchars($row['class_type']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="student_number">學號</label>
                                <select class="form-control" id="student_number" required disabled>
                                    <option value="">請先選擇班級</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>學生資料</label>
                            <div id="student_info" class="alert alert-info d-none"></div>
                        </div>
                        <button type="submit" class="btn btn-primary" disabled>確認借閱</button>
                    </form>
                </div>

                <!-- 讀卡機 -->
                <div class="tab-pane fade" id="card-search" role="tabpanel">
                    <form method="POST" id="cardSearchForm">
                        <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                        <input type="hidden" name="user_id" id="card_user_id">
                        <div class="mb-3">
                            <label for="card_number">請刷卡</label>
                            <input type="text" class="form-control" id="card_number" placeholder="學生證號碼" required>
                        </div>
                        <div class="mb-3">
                            <label>學生資料</label>
                            <div id="card_student_info" class="alert alert-info d-none"></div>
                        </div>
                        <button type="submit" class="btn btn-primary" disabled>確認借閱</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">找不到書籍資料</div>
        <?php endif; ?>

        <a href="search.php" class="btn btn-secondary">返回查詢</a>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <script>
    $(document).ready(function() {
        // Initialize Select2 for name search
        $('#search_user').select2({
            ajax: {
                url: 'search_users.php',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        search: params.term
                    };
                },
                processResults: function(data) {
                    return {
                        results: data.map(function(item) {
                            return {
                                id: item.id,
                                text: item.name + ' (' + item.student_number + ')',
                                user: item
                            };
                        })
                    };
                },
                cache: true
            },
            minimumInputLength: 1,
            placeholder: '請輸入姓名或學號'
        }).on('select2:select', function(e) {
            $('#selected_user_id').val(e.params.data.id);
            $('#nameSearchForm button[type="submit"]').prop('disabled', false);
        });

        // Handle class type change
        $('#class_type').change(function() {
            const classType = $(this).val();
            const studentNumberSelect = $('#student_number');
            const studentInfo = $('#student_info');
            const submitBtn = $('#classSearchForm button[type="submit"]');
            
            if (classType) {
                $.getJSON('filter_users.php', { class_type: classType })
                    .done(function(data) {
                        studentNumberSelect.empty().append('<option value="">請選擇學號</option>');
                        data.forEach(function(user) {
                            studentNumberSelect.append(
                                $('<option></option>')
                                    .val(user.id)
                                    .text(user.student_number)
                                    .data('user', user)
                            );
                        });
                        studentNumberSelect.prop('disabled', false);
                        studentInfo.addClass('d-none');
                        submitBtn.prop('disabled', true);
                    })
                    .fail(function() {
                        alert('載入學生資料失敗');
                    });
            } else {
                studentNumberSelect.empty().append('<option value="">請先選擇班級</option>').prop('disabled', true);
                studentInfo.addClass('d-none');
                submitBtn.prop('disabled', true);
            }
        });

        // Handle student number change
        $('#student_number').change(function() {
            const selectedOption = $(this).find('option:selected');
            const user = selectedOption.data('user');
            const studentInfo = $('#student_info');
            const submitBtn = $('#classSearchForm button[type="submit"]');
            
            if (user) {
                studentInfo
                    .html('姓名：' + user.name + '<br>班別：' + user.class_type + '<br>學號：' + user.student_number)
                    .removeClass('d-none');
                $('#class_user_id').val(user.id);
                submitBtn.prop('disabled', false);
            } else {
                studentInfo.addClass('d-none');
                $('#class_user_id').val('');
                submitBtn.prop('disabled', true);
            }
        });

        // Handle card reader input
        let cardInputTimeout;
        $('#card_number').on('input', function() {
            clearTimeout(cardInputTimeout);
            const studentInfo = $('#card_student_info');
            const submitBtn = $('#cardSearchForm button[type="submit"]');
            
            cardInputTimeout = setTimeout(() => {
                const cardNumber = $(this).val();
                if (cardNumber) {
                    $.getJSON('get_user_by_card.php', { card_number: cardNumber })
                        .done(function(user) {
                            if (user) {
                                studentInfo
                                    .html('姓名：' + user.name + '<br>班別：' + user.class_type + '<br>學號：' + user.student_number)
                                    .removeClass('d-none');
                                $('#card_user_id').val(user.id);
                                submitBtn.prop('disabled', false);
                            } else {
                                studentInfo
                                    .html('找不到學生資料')
                                    .removeClass('d-none');
                                $('#card_user_id').val('');
                                submitBtn.prop('disabled', true);
                            }
                        })
                        .fail(function() {
                            studentInfo
                                .html('查詢失敗')
                                .removeClass('d-none');
                            $('#card_user_id').val('');
                            submitBtn.prop('disabled', true);
                        });
                } else {
                    studentInfo.addClass('d-none');
                    $('#card_user_id').val('');
                    submitBtn.prop('disabled', true);
                }
            }, 500);
        });

        // Modify all form submissions to go to confirmation page
        $('form').on('submit', function(e) {
            e.preventDefault();
            const bookId = $(this).find('input[name="book_id"]').val();
            const userId = $(this).find('input[name="user_id"]').val();
            
            if (bookId && userId) {
                window.location.href = 'borrow_confirm.php?book_id=' + bookId + '&user_id=' + userId;
            }
        });
    });
    </script>
</body>
</html>