CREATE DATABASE IF NOT EXISTS student_info;
USE student_info;

-- Book information table
CREATE TABLE book_info2 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    isbn VARCHAR(20),
    anco VARCHAR(20),
    dsedj_no VARCHAR(50),
    cal_no VARCHAR(50),
    title VARCHAR(100),
    author VARCHAR(50),
    publisher VARCHAR(50),
    published_date YEAR,
    available BOOLEAN DEFAULT 1,
    image_url VARCHAR(255),
    borrow_time INT DEFAULT 0,
    remark TEXT
);

-- Book borrowing records table
CREATE TABLE book_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    anco VARCHAR(20),
    isbn VARCHAR(20),
    cal_no VARCHAR(50),
    title VARCHAR(100),
    author VARCHAR(50),
    publisher VARCHAR(50),
    published_date YEAR,
    available BOOLEAN DEFAULT 1,
    borrow_date DATE,
    return_date DATE,
    school_card_number VARCHAR(20),
    student_card_number VARCHAR(20),
    name VARCHAR(50),
    role VARCHAR(10),
    image_url VARCHAR(255),
    borrow_day INT,
    creation_day DATETIME 1970-01-01 00:00:00,
    return_date_time DATETIME
);

-- Users table (merged students and users)
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section VARCHAR(20),
    name VARCHAR(50),
    class VARCHAR(20),
    class_type VARCHAR(20),
    student_number VARCHAR(20),
    school_card_number VARCHAR(20),
    student_id_number VARCHAR(20),
    enrollment_year YEAR,
    student_group VARCHAR(20),
    valid_or_not BOOLEAN DEFAULT 1,
    password VARCHAR(255),
    name_en VARCHAR(50),
    name_cn VARCHAR(50),
    unitid VARCHAR(20),
    SEN VARCHAR(20),
    role TINYINT DEFAULT 0,
    late_return_book INT DEFAULT 0,
    image_url VARCHAR(255),
    can_borrow_or_not BOOLEAN DEFAULT 1,
    book_lend INT DEFAULT 0,
    book_note_return INT DEFAULT 0
);

-- Role table for managing borrowing privileges
CREATE TABLE role (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role TINYINT NOT NULL COMMENT '0:學生, 1:老師, 2:副校長, 3:校長',
    role_name VARCHAR(20) COMMENT '身份名稱',
    quota INT NOT NULL COMMENT '可同期借出書的數量',
    days INT NOT NULL COMMENT '可借出的天數'
);

-- Migration queries for existing data
INSERT INTO students (
    student_number,
    name,
    section,
    class,
    school_card_number,
    valid_or_not,
    role,
    late_return_book,
    image_url
)
SELECT
    u.學號,
    u.顯示姓名,
    u.學部,
    u.班級,
    u.學校卡號,
    u.有效或冇效,
    u.角色,
    u.遲還書次數,
    s.圖片
FROM students u
LEFT JOIN students s ON u.學號 = s.學號;

-- Update book_info2 data with anco for joining
UPDATE book_info2 SET
    anco = ANCO,
    cal_no = ANCO,
    title = 書名,
    author = 作者,
    publisher = 出版社,
    published_date = 日期,
    available = NOT 已借出,
    image_url = 圖片,
    borrow_time = 共借出次數;

-- Update book_info data with anco for joining
UPDATE book_info SET
    anco = ANCO,
    cal_no = ANCO,
    title = 書名,
    author = 作者,
    publisher = 出版社,
    published_date = 日期,
    available = NOT 已借出,
    borrow_date = 借出日期,
    return_date = 歸還日期,
    school_card_number = 借出人,
    name = (SELECT name FROM students WHERE school_card_number = 借出人),
    role = 老師學生,
    image_url = 圖片,
    borrow_day = 借出共幾天;

-- Drop old columns from book_info2
ALTER TABLE book_info2
    DROP COLUMN ANCO,
    DROP COLUMN 書名,
    DROP COLUMN 作者,
    DROP COLUMN 出版社,
    DROP COLUMN 日期,
    DROP COLUMN 已借出,
    DROP COLUMN 圖片,
    DROP COLUMN 共借出次數;

-- Drop old columns from book_info
ALTER TABLE book_info
    DROP COLUMN ANCO,
    DROP COLUMN 書名,
    DROP COLUMN 作者,
    DROP COLUMN 出版社,
    DROP COLUMN 日期,
    DROP COLUMN 已借出,
    DROP COLUMN 借出日期,
    DROP COLUMN 歸還日期,
    DROP COLUMN 借出人,
    DROP COLUMN 老師學生,
    DROP COLUMN 圖片,
    DROP COLUMN 借出共幾天;

-- Drop old tables
DROP TABLE IF EXISTS users;

-- Update existing records with names from students table
UPDATE book_info b
JOIN students s ON b.school_card_number = s.school_card_number
SET b.name = s.name
WHERE b.name IS NULL;

-- Insert default role configurations
INSERT INTO role (role, quota, days) VALUES
(0, 2, 14),   -- 學生：可借2本書，借期14天
(1, 5, 30),   -- 老師：可借5本書，借期30天
(2, 8, 45),   -- 副校長：可借8本書，借期45天
(3, 10, 60);  -- 校長：可借10本書，借期60天
