<?php

namespace Tests\Unit\Models;

use Library\Models\Book;
use PDO;
use PHPUnit\Framework\TestCase;

class BookTest extends TestCase
{
    private PDO $db;
    private Book $book;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // 建立資料庫連線
        $this->db = new PDO(
            sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                getenv('DB_HOST'),
                getenv('DB_NAME')
            ),
            getenv('DB_USER'),
            getenv('DB_PASS'),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        
        $this->book = new Book($this->db);
        
        // 清理資料
        $this->db->exec('TRUNCATE TABLE books');
    }
    
    public function testCreateBook(): void
    {
        $data = [
            'title' => '測試書籍',
            'author' => '測試作者',
            'isbn' => '9789571234567',
            'status' => 'available'
        ];
        
        $id = $this->book->create($data);
        
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
        
        // 驗證資料是否正確寫入
        $result = $this->book->find($id);
        $this->assertNotNull($result);
        $this->assertEquals($data['title'], $result['title']);
        $this->assertEquals($data['author'], $result['author']);
        $this->assertEquals($data['isbn'], $result['isbn']);
        $this->assertEquals($data['status'], $result['status']);
    }
    
    public function testUpdateBook(): void
    {
        // 先建立一筆測試資料
        $data = [
            'title' => '原始標題',
            'author' => '原始作者',
            'isbn' => '9789571234567',
            'status' => 'available'
        ];
        
        $id = $this->book->create($data);
        
        // 更新資料
        $updateData = [
            'title' => '更新後標題',
            'author' => '更新後作者'
        ];
        
        $result = $this->book->update($id, $updateData);
        
        $this->assertTrue($result);
        
        // 驗證更新是否成功
        $updated = $this->book->find($id);
        $this->assertEquals($updateData['title'], $updated['title']);
        $this->assertEquals($updateData['author'], $updated['author']);
        $this->assertEquals($data['isbn'], $updated['isbn']); // 未更新的欄位應保持原值
    }
    
    public function testFindNonExistentBook(): void
    {
        $result = $this->book->find(999);
        $this->assertNull($result);
    }
}