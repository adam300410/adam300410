<?php

namespace Library\Models;

use PDO;
use RuntimeException;

class Book
{
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * 根據 ID 獲取書籍資訊
     *
     * @param int $id 書籍 ID
     * @return array|null 書籍資訊，如果不存在則返回 null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT id, title, author, isbn, status, created_at, updated_at
            FROM books
            WHERE id = :id
        ');
        
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }
    
    /**
     * 新增書籍
     *
     * @param array $data 書籍資料
     * @return int 新增的書籍 ID
     * @throws RuntimeException 當新增失敗時
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO books (title, author, isbn, status, created_at, updated_at)
            VALUES (:title, :author, :isbn, :status, NOW(), NOW())
        ');
        
        $result = $stmt->execute([
            'title' => $data['title'],
            'author' => $data['author'],
            'isbn' => $data['isbn'],
            'status' => $data['status'] ?? 'available'
        ]);
        
        if (!$result) {
            throw new RuntimeException('Failed to create book');
        }
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * 更新書籍資訊
     *
     * @param int $id 書籍 ID
     * @param array $data 更新的資料
     * @return bool 是否更新成功
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];
        
        foreach ($data as $key => $value) {
            if (in_array($key, ['title', 'author', 'isbn', 'status'])) {
                $fields[] = "{$key} = :{$key}";
                $params[$key] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = 'UPDATE books SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }
}