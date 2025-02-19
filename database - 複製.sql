CREATE DATABASE IF NOT EXISTS student_info;
USE student_info;

-- Book information table
CREATE TABLE book_info2 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    isbn VARCHAR(20),
    cal_no VARCHAR(50),
    title VARCHAR(100),
    author VARCHAR(50),
    publisher VARCHAR(50),
    published_date DATE,
    available BOOLEAN DEFAULT 1,
    image_url VARCHAR(255),
    borrow_time INT DEFAULT 0
);

-- Book borrowing records table
CREATE TABLE book_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    isbn VARCHAR(20),
    cal_no VARCHAR(50),
    title VARCHAR(100),
    author VARCHAR(50),
    publisher VARCHAR(50),
    published_date DATE,
    available BOOLEAN DEFAULT 1,
    borrow_date DATE,
    return_date DATE,
    student_card_number VARCHAR(20),
    role VARCHAR(10),
    image_url VARCHAR(255),
    borrow_day INT
);

-- Students table
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section VARCHAR(50),
    name VARCHAR(50),
    class VARCHAR(20),
    class_type VARCHAR(20),
    student_number VARCHAR(20),
    student_card_number VARCHAR(20),
    student_id_number VARCHAR(20),
    school_card_number VARCHAR(20),
    enrollment_year YEAR,
    student_group VARCHAR(20),
    valid_or_not BOOLEAN,
    password VARCHAR(255),
    name_en VARCHAR(50),
    name_cn VARCHAR(50),
    unitid VARCHAR(50),
    SEN VARCHAR(50),
    role TINYINT DEFAULT 0,
    late_return_book INT DEFAULT 0
);

-- Migration queries for existing data
-- No longer inserting data from old tables
-- Previous schema merged students and users - data has been migrated
-- This table has been renamed to students and schema has been updated

-- Update book_info2 data
UPDATE book_info2 SET
    cal_no = ANCO,
    title = 書名,
    author = 作者,
    publisher = 出版社,
    published_date = 日期,
    available = NOT 已借出,
    image_url = 圖片,
    borrow_time = 共借出次數;

-- Update book_info data
UPDATE book_info SET
    cal_no = ANCO,
    title = 書名,
    author = 作者,
    publisher = 出版社,
    published_date = 日期,
    available = NOT 已借出,
    borrow_date = 借出日期,
    return_date = 歸還日期,
    student_card_number = 借出人,
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
