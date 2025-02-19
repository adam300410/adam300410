<?php
session_start();
require_once '../../config/db.php';

$user_records = [];
$classes = [];
$student_numbers = [];

// Get unique classes for filters
try {
    $stmt = $pdo->query("SELECT DISTINCT class FROM students ORDER BY class");
    $classes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($_GET['class'])) {
        $stmt = $pdo->prepare("SELECT student_number, name FROM students WHERE class = ? ORDER BY student_number");
        $stmt->execute([$_GET['class']]);
        $student_numbers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    $error = "讀取資料失敗：" . $e->getMessage();
}

// Search functionality
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    try {
        $conditions = [];
        $params = [];
        $having = [];
        $sql = "SELECT u.*, 
                COUNT(DISTINCT CASE WHEN b.available = 0 THEN b.id END) as current_borrows,
                COUNT(DISTINCT b.id) as total_borrows
                FROM students u
                LEFT JOIN book_info b ON u.school_card_number = b.school_card_number
                WHERE 1=1";

        $current_tab = $_GET['tab'] ?? 'name';

        // Name/Student Number Search
        if (!empty($_GET['search']) && $current_tab == 'name') {
            $search = "%" . $_GET['search'] . "%";
            $conditions[] = "(u.name LIKE ? OR u.student_number LIKE ?)";
            $params = array_merge($params, [$search, $search]);
        }

        // Class Search
        if ($current_tab == 'class') {
            if (!empty($_GET['class'])) {
                $conditions[] = "u.class = ?";
                $params[] = $_GET['class'];
            }
            if (!empty($_GET['student_number'])) {
                $conditions[] = "u.student_number = ?";
                $params[] = $_GET['student_number'];
            }
        }

        // Card Reader Search
        if (!empty($_GET['card_number']) && $current_tab == 'card') {
            $conditions[] = "u.school_card_number = ?";
            $params[] = $_GET['card_number'];
        }

        // Borrow Count Search
        if ($current_tab == 'borrow' && !empty($_GET['borrow_type']) && !empty($_GET['operator']) && isset($_GET['value'])) {
            $value = intval($_GET['value']);
            $operator = $_GET['operator'];
            $validOperators = ['=', '<=', '>='];
            
            if (in_array($operator, $validOperators)) {
                if ($_GET['borrow_type'] == 'current') {
                    $having[] = "current_borrows " . $operator . " " . $value;
                } else {
                    $having[] = "total_borrows " . $operator . " " . $value;
                }
            }
        }

        if (!empty($conditions)) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }

        $sql .= " GROUP BY u.id";

        if (!empty($having)) {
            $sql .= " HAVING " . implode(" AND ", $having);
        }

        $sql .= " ORDER BY u.section, u.class, u.student_number";

        if (!empty($conditions) || !empty($having)) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $user_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch(PDOException $e) {
        $error = "查詢失敗：" . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>查詢借閱人 - 圖書借還系統</title>
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
        <h2>查詢借閱人</h2>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <ul class="nav nav-tabs mb-3" id="searchTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link <?php echo (!isset($_GET['tab']) || $_GET['tab'] == 'name') ? 'active' : ''; ?>" 
                        id="name-tab" data-bs-toggle="tab" data-bs-target="#name-search" 
                        type="button" role="tab">姓名/學號搜尋</button>
            </li>
            <li class="nav-item">
                <button class="nav-link <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'class') ? 'active' : ''; ?>" 
                        id="class-tab" data-bs-toggle="tab" data-bs-target="#class-search" 
                        type="button" role="tab">班級搜尋</button>
            </li>
            <li class="nav-item">
                <button class="nav-link <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'card') ? 'active' : ''; ?>" 
                        id="card-tab" data-bs-toggle="tab" data-bs-target="#card-search" 
                        type="button" role="tab">讀卡機</button>
            </li>
            <li class="nav-item">
                <button class="nav-link <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'borrow') ? 'active' : ''; ?>" 
                        id="borrow-tab" data-bs-toggle="tab" data-bs-target="#borrow-search" 
                        type="button" role="tab">借閱次數搜尋</button>
            </li>
        </ul>

        <div class="tab-content mb-4">
            <!-- 姓名/學號搜尋 -->
            <div class="tab-pane fade <?php echo (!isset($_GET['tab']) || $_GET['tab'] == 'name') ? 'show active' : ''; ?>" 
                 id="name-search" role="tabpanel">
                <form method="GET" class="mb-3" id="nameSearchForm">
                    <input type="hidden" name="tab" value="name">
                    <div class="row">
                        <div class="col">
                            <input type="text" class="form-control" name="search" 
                                   value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
                                   placeholder="輸入姓名或學號" id="autoSearchInput">
                        </div>
                    </div>
                </form>
            </div>

            <!-- 班級搜尋 -->
            <div class="tab-pane fade <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'class') ? 'show active' : ''; ?>" 
                 id="class-search" role="tabpanel">
                <form method="GET" class="mb-3" id="classSearchForm">
                    <input type="hidden" name="tab" value="class">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <select class="form-control" name="class" id="class">
                                <option value="">選擇班級</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo htmlspecialchars($class); ?>" 
                                            <?php echo ($_GET['class'] ?? '') == $class ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <select class="form-control" name="student_number" id="student_number" <?php echo empty($_GET['class']) ? 'disabled' : ''; ?>>
                                <option value="">選擇學號</option>
                                <?php foreach ($student_numbers as $student): ?>
                                    <option value="<?php echo htmlspecialchars($student['student_number']); ?>" 
                                            <?php echo ($_GET['student_number'] ?? '') == $student['student_number'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($student['student_number'] . ' - ' . $student['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </form>
            </div>

            <!-- 讀卡機 -->
            <div class="tab-pane fade <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'card') ? 'show active' : ''; ?>" 
                 id="card-search" role="tabpanel">
                <form method="GET" class="mb-3">
                    <input type="hidden" name="tab" value="card">
                    <div class="row">
                        <div class="col">
                            <input type="text" class="form-control" name="card_number" 
                                   value="<?php echo htmlspecialchars($_GET['card_number'] ?? ''); ?>" 
                                   placeholder="請刷卡" autofocus>
                        </div>
                    </div>
                </form>
            </div>

            <!-- 借閱次數搜尋 -->
            <div class="tab-pane fade <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'borrow') ? 'show active' : ''; ?>" 
                 id="borrow-search" role="tabpanel">
                <form method="GET" class="mb-3" id="borrowSearchForm">
                    <input type="hidden" name="tab" value="borrow">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <select class="form-control" name="borrow_type" id="borrow_type">
                                <option value="current" <?php echo ($_GET['borrow_type'] ?? '') == 'current' ? 'selected' : ''; ?>>
                                    目前借閱數
                                </option>
                                <option value="total" <?php echo ($_GET['borrow_type'] ?? '') == 'total' ? 'selected' : ''; ?>>
                                    總借閱次數
                                </option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <select class="form-control" name="operator" id="operator">
                                <option value="=" <?php echo ($_GET['operator'] ?? '') == '=' ? 'selected' : ''; ?>>
                                    等於
                                </option>
                                <option value="<=" <?php echo ($_GET['operator'] ?? '') == '<=' ? 'selected' : ''; ?>>
                                    小於等於
                                </option>
                                <option value=">=" <?php echo ($_GET['operator'] ?? '') == '>=' ? 'selected' : ''; ?>>
                                    大於等於
                                </option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <input type="number" class="form-control" name="value"
                                    value="<?php echo htmlspecialchars($_GET['value'] ?? ''); ?>"
                                    placeholder="輸入數值" min="0" id="borrowValue">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <button type="submit" class="btn btn-primary">搜尋</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!empty($user_records)): ?>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>學部</th>
                        <th>班級</th>
                        <th>學號</th>
                        <th>姓名</th>
                        <th>目前借閱數</th>
                        <th>總借閱次數</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($user_records as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['section']); ?></td>
                            <td><?php echo htmlspecialchars($user['class']); ?></td>
                            <td><?php echo htmlspecialchars($user['student_number']); ?></td>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo $user['current_borrows']; ?></td>
                            <td><?php echo $user['total_borrows']; ?></td>
                            <td>
                                <a href="user_detail.php?id=<?php echo $user['id']; ?>" 
                                   class="btn btn-sm btn-primary">詳細資料</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif ($_SERVER['REQUEST_METHOD'] == 'GET' && 
                    (!empty($_GET['search']) || !empty($_GET['class']) || 
                     !empty($_GET['student_number']) || !empty($_GET['card_number']) ||
                     (!empty($_GET['borrow_type']) && isset($_GET['value'])))): ?>
            <div class="alert alert-info">沒有找到符合條件的借閱人</div>
        <?php endif; ?>

        <a href="../../index.php" class="btn btn-secondary">返回首頁</a>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <script>
    $(document).ready(function() {
        // Initialize select2 for better dropdown experience
        $('#class, #student_number').select2();

        // Auto-submit for name/student
