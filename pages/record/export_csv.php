<?php
// 關閉任何可能的輸出緩衝
while (ob_get_level()) {
    ob_end_clean();
}

// 開啟錯誤報告
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    session_start();
    require_once '../../config/db.php';

    // 記錄程式碼修改紀錄
    $user_id = $_SESSION['user_id'] ?? 0;
    $sql_log = "INSERT INTO code_changes (user_id, file_path, action_type, change_date) VALUES (?, ?, ?, NOW())";
    $stmt_log = $pdo->prepare($sql_log);
    $stmt_log->execute([$user_id, 'pages/record/export_csv.php', 'access']);

    // 設定 response headers
    header("Content-Type: text/csv; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"借閱紀錄_" . date('Ymd_His') . ".csv\"");
    header("Pragma: public");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Cache-Control: private", false);

    // 開啟輸出流
    $fp = fopen("php://output", "w");
    if ($fp === false) {
        throw new Exception('無法創建輸出流');
    }

    // 寫入 UTF-8 BOM
    fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // 寫入 CSV 標題
    $headers = [
        'ISBN',
        '館藏編號',
        '書名',
        '學號',
        '借閱人姓名',
        '班級',
        '身分',
        '借出日期',
        '歸還日期',
        '借閱天數',
        '狀態'
    ];
    if (fputcsv($fp, $headers) === false) {
        throw new Exception('無法寫入標題');
    }

    // 建立 SQL 查詢
    $params = [];
    $conditions = [];
    
    $search_type = filter_input(INPUT_GET, 'type') ?: 'all';
    $search_query = filter_input(INPUT_GET, 'search') ?: '';
    $start_date = filter_input(INPUT_GET, 'start_date') ?: '';
    $end_date = filter_input(INPUT_GET, 'end_date') ?: '';

    if ($search_query !== '') {
        $search = "%{$search_query}%";
        switch ($search_type) {
            case 'book':
                $conditions[] = "(isbn LIKE ? OR cal_no LIKE ? OR title LIKE ?)";
                $params = array_merge($params, [$search, $search, $search]);
                break;
            case 'user':
                $conditions[] = "(school_card_number LIKE ? OR name LIKE ?)";
                $params = array_merge($params, [$search, $search]);
                break;
            default:
                $conditions[] = "(isbn LIKE ? OR cal_no LIKE ? OR title LIKE ? OR school_card_number LIKE ? OR name LIKE ?)";
                $params = array_merge($params, [$search, $search, $search, $search, $search]);
        }
    }

    if ($start_date !== '') {
        $conditions[] = "borrow_date >= ?";
        $params[] = $start_date;
    }

    if ($end_date !== '') {
        $conditions[] = "borrow_date <= ?";
        $params[] = $end_date;
    }

    $sql = "SELECT b.*, s.class FROM book_info b LEFT JOIN students s ON b.school_card_number = s.school_card_number WHERE b.available = 0";
    if (!empty($conditions)) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }
    $sql .= " ORDER BY borrow_date DESC";

    // 記錄 SQL 查詢
    $stmt_log = $pdo->prepare("INSERT INTO code_changes (user_id, file_path, action_type, sql_query, change_date) VALUES (?, ?, ?, ?, NOW())");
    $stmt_log->execute([$user_id, 'pages/record/export_csv.php', 'query', $sql]);

    $stmt = $pdo->prepare($sql);
    if (!$stmt) {
        throw new Exception('SQL 準備失敗');
    }

    if (!$stmt->execute($params)) {
        throw new Exception('SQL 執行失敗');
    }

    // 寫入數據
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (fputcsv($fp, [
            $row['isbn'],
            $row['cal_no'],
            $row['title'],
            $row['school_card_number'],
            $row['name'],
            isset($row['class']) ? $row['class'] : '-',
            $row['role'],
            $row['borrow_date'],
            isset($row['return_date']) ? $row['return_date'] : '-',
            isset($row['borrow_day']) ? $row['borrow_day'] : '-',
            !$row['available'] ? '借出中' : '已歸還'
        ]) === false) {
            throw new Exception('無法寫入數據行');
        }
    }

    if (is_resource($fp)) {
        fclose($fp);
    }

    // 記錄成功匯出
    $stmt_log = $pdo->prepare("INSERT INTO code_changes (user_id, file_path, action_type, change_date) VALUES (?, ?, ?, NOW())");
    $stmt_log->execute([$user_id, 'pages/record/export_csv.php', 'export_success']);
    
    exit;

} catch (Exception $e) {
    // 記錄錯誤
    if (isset($pdo) && isset($user_id)) {
        $stmt_log = $pdo->prepare("INSERT INTO code_changes (user_id, file_path, action_type, error_message, change_date) VALUES (?, ?, ?, ?, NOW())");
        $stmt_log->execute([$user_id, 'pages/record/export_csv.php', 'error', $e->getMessage()]);
    }

    // 清除之前的輸出
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // 返回錯誤信息
    header('Content-Type: text/plain; charset=UTF-8');
    echo '匯出失敗：' . $e->getMessage();
    exit;
}