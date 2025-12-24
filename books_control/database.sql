-- 校园二手教材循环交易平台数据库
-- 创建数据库
CREATE DATABASE IF NOT EXISTS used_books_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE used_books_platform;

-- 管理员表
CREATE TABLE admins (
    admin_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 插入默认管理员（密码: admin123）
INSERT INTO admins (username, password) VALUES
('admin', MD5('admin123'));

-- 用户表
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    student_id VARCHAR(20),
    credit_score DECIMAL(3,1) DEFAULT 5.0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 教材信息表
CREATE TABLE textbooks (
    book_id INT PRIMARY KEY AUTO_INCREMENT,
    isbn VARCHAR(20) NOT NULL,
    title VARCHAR(200) NOT NULL,
    author VARCHAR(100),
    publisher VARCHAR(100),
    edition VARCHAR(20),
    course_code VARCHAR(20),
    course_name VARCHAR(100),
    teacher_name VARCHAR(50),
    original_price DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 商品表（用户发布的二手教材）
CREATE TABLE products (
    product_id INT PRIMARY KEY AUTO_INCREMENT,
    seller_id INT NOT NULL,
    book_id INT NOT NULL,
    condition_score TINYINT DEFAULT 5 COMMENT '教材新旧程度1-10分',
    selling_price DECIMAL(10,2) NOT NULL,
    description TEXT,
    status ENUM('available', 'reserved', 'sold') DEFAULT 'available',
    images VARCHAR(500) COMMENT '图片路径，多个用逗号分隔',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(user_id),
    FOREIGN KEY (book_id) REFERENCES textbooks(book_id)
);

-- 求购需求表
CREATE TABLE purchase_requests (
    request_id INT PRIMARY KEY AUTO_INCREMENT,
    buyer_id INT NOT NULL,
    book_id INT,
    isbn VARCHAR(20),
    title VARCHAR(200),
    course_code VARCHAR(20),
    max_price DECIMAL(10,2),
    description TEXT,
    status ENUM('active', 'matched', 'closed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(user_id),
    FOREIGN KEY (book_id) REFERENCES textbooks(book_id)
);

-- 交易记录表
CREATE TABLE transactions (
    transaction_id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    buyer_id INT NOT NULL,
    seller_id INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    appointment_time DATETIME,
    appointment_location VARCHAR(200),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id),
    FOREIGN KEY (buyer_id) REFERENCES users(user_id),
    FOREIGN KEY (seller_id) REFERENCES users(user_id)
);

-- 评价表
CREATE TABLE reviews (
    review_id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    reviewed_id INT NOT NULL,
    book_condition_score TINYINT COMMENT '教材实际新旧程度评分1-10',
    description_accuracy_score TINYINT COMMENT '卖家描述准确性评分1-10',
    overall_score TINYINT COMMENT '总体评分1-10',
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id),
    FOREIGN KEY (reviewer_id) REFERENCES users(user_id),
    FOREIGN KEY (reviewed_id) REFERENCES users(user_id)
);

-- 插入示例数据
-- 用户数据
INSERT INTO users (username, password, email, phone, student_id) VALUES
('张三', MD5('123456'), 'zhangsan@student.edu.cn', '13800138001', '2021001'),
('李四', MD5('123456'), 'lisi@student.edu.cn', '13800138002', '2021002'),
('王五', MD5('123456'), 'wangwu@student.edu.cn', '13800138003', '2021003');

-- 教材数据
INSERT INTO textbooks (isbn, title, author, publisher, edition, course_code, course_name, teacher_name, original_price) VALUES
('9787302123456', '高等数学（上册）', '同济大学数学系', '高等教育出版社', '第7版', 'MATH101', '高等数学A', '张教授', 45.00),
('9787111234567', 'C程序设计', '谭浩强', '清华大学出版社', '第5版', 'CS101', 'C语言程序设计', '李教授', 39.80),
('9787040345678', '大学英语综合教程1', '李荫华', '上海外语教育出版社', '第3版', 'ENG101', '大学英语', '王教授', 42.90);

-- 商品数据
INSERT INTO products (seller_id, book_id, condition_score, selling_price, description, status) VALUES
(1, 1, 8, 35.00, '九成新，无笔记，保存完好', 'available'),
(2, 2, 7, 28.00, '八成新，有少量笔记，不影响阅读', 'available'),
(1, 3, 9, 38.00, '几乎全新，仅翻阅过几次', 'available');

-- 求购需求数据
INSERT INTO purchase_requests (buyer_id, book_id, max_price, description) VALUES
(3, 1, 30.00, '急需高等数学教材，价格可商量'),
(3, 2, 25.00, '寻找C程序设计教材，八成新以上');

-- 交易记录数据
INSERT INTO transactions (product_id, buyer_id, seller_id, price, status, appointment_time, appointment_location) VALUES
(1, 3, 1, 35.00, 'completed', '2025-12-20 14:00:00', '图书馆一楼大厅'),
(2, 1, 2, 28.00, 'completed', '2025-12-21 10:30:00', '食堂门口'),
(3, 2, 1, 38.00, 'pending', '2025-12-25 15:00:00', '教学楼A座门口');

-- 评价数据（针对已完成的交易）
INSERT INTO reviews (transaction_id, reviewer_id, reviewed_id, book_condition_score, description_accuracy_score, overall_score, comment) VALUES
(1, 3, 1, 8, 9, 9, '卖家很热情，书保存得很好，和描述一致'),
(1, 1, 3, 10, 10, 10, '买家很准时，交易愉快'),
(2, 1, 2, 7, 8, 8, '书有少量笔记，总体满意'),
(2, 2, 1, 9, 9, 9, '买家态度很好，推荐');
