<?php
// 数据库配置文件 - 支持Docker环境变量
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'books_admin');
define('DB_PASS', getenv('DB_PASS') ?: 'books123456');
define('DB_NAME', getenv('DB_NAME') ?: 'used_books_platform');
define('DB_CHARSET', 'utf8mb4');

// 创建数据库连接
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch(PDOException $e) {
        die("数据库连接失败: " . $e->getMessage());
    }
}

// 通用函数
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatPrice($price) {
    return number_format($price, 2) . '元';
}

function formatDate($date) {
    return date('Y-m-d H:i', strtotime($date));
}

function getStatusText($status) {
    $statusMap = [
        'available' => '可售',
        'reserved' => '预订中',
        'sold' => '已售',
        'pending' => '待确认',
        'confirmed' => '已确认',
        'completed' => '已完成',
        'cancelled' => '已取消',
        'active' => '求购中',
        'matched' => '已匹配',
        'closed' => '已关闭'
    ];
    return isset($statusMap[$status]) ? $statusMap[$status] : $status;
}
?>
