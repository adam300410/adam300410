<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../../config/db.php';

// Get JSON data from POST request
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => '無效的數據格式']);
    exit;
}

$table = $data['table'];
$rows = $data['data'];
$isUpdate = isset($data['update']) && $data['update'] === true;

try {
    // Start transaction
    $pdo->beginTransaction();

    $successCount = 0;
    $errorCount = 0;
    $sqlStatements = [];

    foreach ($rows as $row) {
        $keys = array_keys($row);
        $values = array_values($row);
        
        // Process all values
        $validKeys = [];
        $validValues = [];
        foreach ($keys as $i => $key) {
            // Skip id field for new records
            if ($key === 'id' && !$isUpdate) {
                continue;
            }
            
            $validKeys[] = $key;
            $value = $values[$i];
            
            // Handle different field types
            if ($value === null || $value === '') {
                $validValues[] = null;
            } elseif ($key === 'enrollment_year') {
                $year = trim($value, '"');
                $validValues[] = empty($year) ? null : intval($year);
            } elseif (in_array($key, ['valid_or_not', 'role', 'late_return_book', 'SEN'])) {
                $validValues[] = is_numeric($value) ? intval($value) : 0;
            } else {
                $validValues[] = trim($value, '"');
            }
        }

        try {
            if ($isUpdate && isset($row['id'])) {
                // Build UPDATE query
                $sets = [];
                foreach ($validKeys as $i => $key) {
                    if ($key !== 'id') {
                        $sets[] = "`$key` = ?";
                    }
                }
                
                if (!empty($sets)) {
                    $sql = "UPDATE `$table` SET " . implode(', ', $sets) . " WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    
                    // Bind values excluding id
                    $paramIndex = 1;
                    foreach ($validKeys as $i => $key) {
                        if ($key !== 'id') {
                            $stmt->bindValue($paramIndex++, $validValues[$i]);
                        }
                    }
                    $stmt->bindValue(':id', $row['id'], PDO::PARAM_INT);
                    
                    // Create display SQL with actual values
                    $displaySql = "UPDATE `$table` SET ";
                    $setParts = [];
                    $paramIndex = 0;
                    foreach ($validKeys as $i => $key) {
                        if ($key !== 'id') {
                            $value = $validValues[$i];
                            if ($value === null) {
                                $setParts[] = "`$key` = NULL";
                            } elseif (is_numeric($value)) {
                                $setParts[] = "`$key` = $value";
                            } else {
                                $setParts[] = "`$key` = '" . addslashes($value) . "'";
                            }
                        }
                    }
                    $displaySql .= implode(', ', $setParts) . " WHERE id = " . $row['id'];
                    $sqlStatements[] = $displaySql;
                    
                    // Execute the prepared statement
                    $stmt->execute();
                }
            } else {
                // Build INSERT query
                // Prepare the SQL statement for execution
                $sql = "INSERT INTO `$table` (`" . implode('`, `', $validKeys) . "`) VALUES (" .
                       implode(", ", array_fill(0, count($validValues), '?')) . ")";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($validValues);

                // Create display SQL with actual values
                $displayValues = array_map(function($value) {
                    if ($value === null) {
                        return 'NULL';
                    } elseif (is_numeric($value)) {
                        return $value;
                    } else {
                        return "'" . addslashes($value) . "'";
                    }
                }, $validValues);
                
                $displaySql = "INSERT INTO `$table` (`" . implode('`, `', $validKeys) . "`) VALUES (" .
                             implode(", ", $displayValues) . ")";
                $sqlStatements[] = $displaySql;
            }
            $successCount++;
        } catch (PDOException $e) {
            $errorCount++;
            error_log("SQL Error: " . $e->getMessage());
            throw $e;
        }
    }

    // Commit transaction if no errors
    $pdo->commit();
    
    $message = $isUpdate ? "更新成功: $successCount 行" : "導入成功: $successCount 行";
    if ($errorCount > 0) {
        $message .= ", 失敗: $errorCount 行";
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'sql' => implode(";\n", $sqlStatements)
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => '操作失敗: ' . $e->getMessage(),
        'sql' => implode(";\n", $sqlStatements)
    ]);
}
