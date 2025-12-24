<?php
require_once 'config.php';
require_once 'auth_check.php';

// 检查管理员登录状态
checkAdminAuth();

$pdo = getDBConnection();
$message = '';
$error = '';

// 处理表单提交
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    // 检查商品是否可用
                    $product_stmt = $pdo->prepare("SELECT status, seller_id, selling_price FROM products WHERE product_id = ?");
                    $product_stmt->execute([intval($_POST['product_id'])]);
                    $product = $product_stmt->fetch();
                    
                    if (!$product) {
                        $error = '商品不存在！';
                    } elseif ($product['status'] !== 'available') {
                        $error = '商品不可用！';
                    } elseif ($product['seller_id'] == intval($_POST['buyer_id'])) {
                        $error = '不能购买自己的商品！';
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO transactions (product_id, buyer_id, seller_id, price, status, appointment_time, appointment_location) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $appointment_time = !empty($_POST['appointment_time']) ? $_POST['appointment_time'] : null;
                        $stmt->execute([
                            intval($_POST['product_id']),
                            intval($_POST['buyer_id']),
                            $product['seller_id'],
                            floatval($_POST['price']),
                            sanitizeInput($_POST['status']),
                            $appointment_time,
                            sanitizeInput($_POST['appointment_location'])
                        ]);
                        
                        // 如果交易确认，更新商品状态
                        if ($_POST['status'] === 'confirmed') {
                            $update_stmt = $pdo->prepare("UPDATE products SET status = 'reserved' WHERE product_id = ?");
                            $update_stmt->execute([intval($_POST['product_id'])]);
                        }
                        
                        $message = '交易记录创建成功！';
                    }
                } catch (PDOException $e) {
                    $error = '创建失败：' . $e->getMessage();
                }
                break;
                
            case 'update':
                try {
                    $stmt = $pdo->prepare("UPDATE transactions SET status=?, appointment_time=?, appointment_location=? WHERE transaction_id=?");
                    $appointment_time = !empty($_POST['appointment_time']) ? $_POST['appointment_time'] : null;
                    $stmt->execute([
                        sanitizeInput($_POST['status']),
                        $appointment_time,
                        sanitizeInput($_POST['appointment_location']),
                        intval($_POST['transaction_id'])
                    ]);
                    
                    // 根据交易状态更新商品状态
                    $transaction_stmt = $pdo->prepare("SELECT product_id FROM transactions WHERE transaction_id = ?");
                    $transaction_stmt->execute([intval($_POST['transaction_id'])]);
                    $transaction = $transaction_stmt->fetch();
                    
                    if ($transaction) {
                        $product_status = 'available';
                        switch ($_POST['status']) {
                            case 'confirmed':
                                $product_status = 'reserved';
                                break;
                            case 'completed':
                                $product_status = 'sold';
                                break;
                            case 'cancelled':
                                $product_status = 'available';
                                break;
                        }
                        
                        $update_stmt = $pdo->prepare("UPDATE products SET status = ? WHERE product_id = ?");
                        $update_stmt->execute([$product_status, $transaction['product_id']]);
                    }
                    
                    $message = '交易记录更新成功！';
                } catch (PDOException $e) {
                    $error = '更新失败：' . $e->getMessage();
                }
                break;
                
            case 'delete':
                try {
                    $transaction_id = intval($_POST['transaction_id']);
                    
                    // 开始事务
                    $pdo->beginTransaction();
                    
                    // 获取交易信息以便恢复商品状态
                    $transaction_stmt = $pdo->prepare("SELECT product_id, status FROM transactions WHERE transaction_id = ?");
                    $transaction_stmt->execute([$transaction_id]);
                    $transaction = $transaction_stmt->fetch();
                    
                    // 1. 先删除相关的评价记录
                    $stmt = $pdo->prepare("DELETE FROM reviews WHERE transaction_id = ?");
                    $stmt->execute([$transaction_id]);
                    
                    // 2. 删除交易记录
                    $stmt = $pdo->prepare("DELETE FROM transactions WHERE transaction_id = ?");
                    $stmt->execute([$transaction_id]);
                    
                    // 3. 如果删除的是已确认或已完成的交易，恢复商品为可用状态
                    if ($transaction && in_array($transaction['status'], ['confirmed', 'completed'])) {
                        $update_stmt = $pdo->prepare("UPDATE products SET status = 'available' WHERE product_id = ?");
                        $update_stmt->execute([$transaction['product_id']]);
                    }
                    
                    // 提交事务
                    $pdo->commit();
                    $message = '交易记录及相关评价删除成功！';
                } catch (PDOException $e) {
                    // 回滚事务
                    $pdo->rollBack();
                    $error = '删除失败：' . $e->getMessage();
                }
                break;
        }
    }
}

// 获取搜索和筛选条件
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status_filter = isset($_GET['status_filter']) ? sanitizeInput($_GET['status_filter']) : '';
$date_from = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';

// 构建查询
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(t.title LIKE ? OR ub.username LIKE ? OR us.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $where_conditions[] = "tr.status = ?";
    $params[] = $status_filter;
}

if ($date_from) {
    $where_conditions[] = "DATE(tr.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "DATE(tr.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// 获取交易列表（关联商品、教材和用户信息）
$sql = "SELECT tr.*, p.selling_price as original_price, p.condition_score, 
               t.title, t.author, t.isbn, t.course_code,
               ub.username as buyer_name, us.username as seller_name
        FROM transactions tr
        LEFT JOIN products p ON tr.product_id = p.product_id
        LEFT JOIN textbooks t ON p.book_id = t.book_id
        LEFT JOIN users ub ON tr.buyer_id = ub.user_id
        LEFT JOIN users us ON tr.seller_id = us.user_id
        $where_clause
        ORDER BY tr.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// 获取可用商品列表
$products_stmt = $pdo->query("SELECT p.product_id, p.selling_price, p.condition_score, p.status, t.title, t.author, u.username as seller_name 
                              FROM products p 
                              LEFT JOIN textbooks t ON p.book_id = t.book_id 
                              LEFT JOIN users u ON p.seller_id = u.user_id 
                              WHERE p.status = 'available' 
                              ORDER BY t.title");
$products = $products_stmt->fetchAll();

// 如果没有可用商品，获取所有商品用于调试
if (empty($products)) {
    $all_products_stmt = $pdo->query("SELECT p.product_id, p.selling_price, p.condition_score, p.status, t.title, t.author, u.username as seller_name 
                                      FROM products p 
                                      LEFT JOIN textbooks t ON p.book_id = t.book_id 
                                      LEFT JOIN users u ON p.seller_id = u.user_id 
                                      ORDER BY t.title");
    $all_products = $all_products_stmt->fetchAll();
}

// 获取用户列表
$users_stmt = $pdo->query("SELECT user_id, username, student_id FROM users ORDER BY username");
$users = $users_stmt->fetchAll();

// 获取编辑的交易信息
$edit_transaction = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE transaction_id = ?");
    $stmt->execute([intval($_GET['edit'])]);
    $edit_transaction = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>交易记录 - 校园二手教材平台</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Microsoft YaHei', Arial, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            color: #333;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: #ffffff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid #e0e0e0;
        }
        
        .header h1 {
            color: #1a1a1a;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .header h1 svg {
            width: 28px;
            height: 28px;
            fill: #1a1a1a;
        }
        
        .nav-link {
            color: #1a1a1a;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .nav-link svg {
            width: 16px;
            height: 16px;
            fill: currentColor;
        }
        
        .nav-link:hover {
            text-decoration: underline;
        }
        
        .form-section {
            background: #ffffff;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid #e0e0e0;
        }
        
        .form-section h3 {
            color: #1a1a1a;
            margin-bottom: 20px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 5px;
            font-weight: 500;
            color: #1a1a1a;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            padding: 10px;
            border: 1px solid #d0d0d0;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #1a1a1a;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s;
            margin: 5px;
        }
        
        .btn-primary {
            background: #1a1a1a;
            color: white;
        }
        
        .btn-primary:hover {
            background: #333;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-warning {
            background: #fd7e14;
            color: white;
        }
        
        .btn-warning:hover {
            background: #e96b02;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-small {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .search-section {
            background: #ffffff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid #e0e0e0;
        }
        
        .search-section h3 {
            color: #1a1a1a;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .search-section h3 svg {
            width: 20px;
            height: 20px;
            fill: #1a1a1a;
        }
        
        .search-form {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .table-section {
            background: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid #e0e0e0;
            overflow-x: auto;
        }
        
        .table-section h3 {
            color: #1a1a1a;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .table-section h3 svg {
            width: 20px;
            height: 20px;
            fill: #1a1a1a;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        th {
            background: #fafafa;
            font-weight: 500;
            color: #1a1a1a;
        }
        
        tr:hover {
            background: #fafafa;
        }
        
        .message {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .price {
            color: #dc3545;
            font-weight: bold;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-confirmed {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .condition-score {
            display: inline-block;
            padding: 2px 8px;
            background: #1a1a1a;
            color: white;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .course-tag {
            background: #6c757d;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: #ffffff;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid #e0e0e0;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #1a1a1a;
        }
        
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                交易记录
            </h1>
            <p>
                <a href="index.php" class="nav-link">
                    <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                    返回首页
                </a>
            </p>
        </div>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- 交易统计 -->
        <div class="stats-grid">
            <?php
            $total_transactions = count($transactions);
            $completed_transactions = count(array_filter($transactions, function($t) { return $t['status'] === 'completed'; }));
            $pending_transactions = count(array_filter($transactions, function($t) { return $t['status'] === 'pending'; }));
            $total_amount = array_sum(array_map(function($t) { return $t['status'] === 'completed' ? $t['price'] : 0; }, $transactions));
            ?>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_transactions; ?></div>
                <div class="stat-label">总交易数</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $completed_transactions; ?></div>
                <div class="stat-label">已完成交易</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $pending_transactions; ?></div>
                <div class="stat-label">待处理交易</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($total_amount, 2); ?>元</div>
                <div class="stat-label">总交易金额</div>
            </div>
        </div>
        
        <!-- 创建/编辑交易表单 -->
        <div class="form-section">
            <h3><?php echo $edit_transaction ? '编辑交易' : '创建新交易'; ?></h3>
            <form method="POST">
                <input type="hidden" name="action" value="<?php echo $edit_transaction ? 'update' : 'add'; ?>">
                <?php if ($edit_transaction): ?>
                    <input type="hidden" name="transaction_id" value="<?php echo $edit_transaction['transaction_id']; ?>">
                <?php endif; ?>
                
                <div class="form-grid">
                    <?php if (!$edit_transaction): ?>
                    <div class="form-group">
                        <label for="product_id">商品 *</label>
                        <select id="product_id" name="product_id" required>
                            <option value="">请选择商品</option>
                            <?php if (empty($products)): ?>
                                <?php if (isset($all_products) && !empty($all_products)): ?>
                                    <option value="" disabled>--- 当前没有可用商品，以下是所有商品状态 ---</option>
                                    <?php foreach ($all_products as $product): ?>
                                        <option value="" disabled>
                                            <?php echo htmlspecialchars($product['title'] . ' - ' . $product['author'] . ' (状态: ' . $product['status'] . ' - ' . $product['seller_name'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled>暂无商品数据，请先添加商品</option>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?php echo $product['product_id']; ?>" data-price="<?php echo $product['selling_price']; ?>">
                                        <?php echo htmlspecialchars($product['title'] . ' - ' . $product['author'] . ' (' . $product['selling_price'] . '元 - ' . $product['seller_name'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <?php if (empty($products)): ?>
                            <small style="color: #dc3545; display: block; margin-top: 5px;">
                                提示：只有状态为"可用"的商品才能创建交易。请到 <a href="products.php" style="color: #1a1a1a;">商品管理</a> 页面检查商品状态。
                            </small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="buyer_id">买家 *</label>
                        <select id="buyer_id" name="buyer_id" required>
                            <option value="">请选择买家</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['user_id']; ?>">
                                    <?php echo htmlspecialchars($user['username'] . ' (' . $user['student_id'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="price">成交价格（元）*</label>
                        <input type="number" step="0.01" id="price" name="price" required>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="status">交易状态 *</label>
                        <select id="status" name="status" required>
                            <option value="pending" <?php echo ($edit_transaction && $edit_transaction['status'] == 'pending') ? 'selected' : ''; ?>>待确认</option>
                            <option value="confirmed" <?php echo ($edit_transaction && $edit_transaction['status'] == 'confirmed') ? 'selected' : ''; ?>>已确认</option>
                            <option value="completed" <?php echo ($edit_transaction && $edit_transaction['status'] == 'completed') ? 'selected' : ''; ?>>已完成</option>
                            <option value="cancelled" <?php echo ($edit_transaction && $edit_transaction['status'] == 'cancelled') ? 'selected' : ''; ?>>已取消</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="appointment_time">预约时间</label>
                        <input type="datetime-local" id="appointment_time" name="appointment_time" 
                               value="<?php echo $edit_transaction && $edit_transaction['appointment_time'] ? date('Y-m-d\TH:i', strtotime($edit_transaction['appointment_time'])) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="appointment_location">预约地点</label>
                        <input type="text" id="appointment_location" name="appointment_location" 
                               placeholder="如：图书馆一楼大厅" 
                               value="<?php echo $edit_transaction ? htmlspecialchars($edit_transaction['appointment_location']) : ''; ?>">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-success">
                    <?php echo $edit_transaction ? '更新交易' : '创建交易'; ?>
                </button>
                
                <?php if ($edit_transaction): ?>
                    <a href="transactions.php" class="btn btn-primary">取消编辑</a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- 搜索和筛选 -->
        <div class="search-section">
            <h3>
                <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                搜索交易
            </h3>
            <form method="GET" class="search-form">
                <div class="form-group">
                    <label for="search">关键词搜索</label>
                    <input type="text" id="search" name="search" placeholder="教材名称或用户名" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="form-group">
                    <label for="status_filter">状态筛选</label>
                    <select id="status_filter" name="status_filter">
                        <option value="">全部状态</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>待确认</option>
                        <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>已确认</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>已完成</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>已取消</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="date_from">开始日期</label>
                    <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                
                <div class="form-group">
                    <label for="date_to">结束日期</label>
                    <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                
                <button type="submit" class="btn btn-primary">搜索</button>
                <a href="transactions.php" class="btn btn-warning">重置</a>
            </form>
        </div>
        
        <!-- 交易列表 -->
        <div class="table-section">
            <h3>
                <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                交易列表 (共 <?php echo count($transactions); ?> 笔)
            </h3>
            
            <?php if (empty($transactions)): ?>
                <p style="text-align: center; color: #666; margin: 40px 0;">暂无交易记录</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>交易ID</th>
                            <th>教材信息</th>
                            <th>买家</th>
                            <th>卖家</th>
                            <th>成交价格</th>
                            <th>状态</th>
                            <th>预约信息</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td><strong>#<?php echo $transaction['transaction_id']; ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($transaction['title']); ?></strong><br>
                                    <small>作者：<?php echo htmlspecialchars($transaction['author']); ?></small><br>
                                    <small>ISBN：<?php echo htmlspecialchars($transaction['isbn']); ?></small>
                                    <?php if ($transaction['course_code']): ?>
                                        <br><span class="course-tag"><?php echo htmlspecialchars($transaction['course_code']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($transaction['condition_score']): ?>
                                        <br><span class="condition-score"><?php echo $transaction['condition_score']; ?>分</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($transaction['buyer_name']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['seller_name']); ?></td>
                                <td>
                                    <span class="price"><?php echo formatPrice($transaction['price']); ?></span>
                                    <?php if ($transaction['original_price'] && $transaction['original_price'] != $transaction['price']): ?>
                                        <br><small style="text-decoration: line-through; color: #999;">原价：<?php echo formatPrice($transaction['original_price']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $transaction['status']; ?>">
                                        <?php echo getStatusText($transaction['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($transaction['appointment_time']): ?>
                                        <strong>时间：</strong><?php echo formatDate($transaction['appointment_time']); ?><br>
                                    <?php endif; ?>
                                    <?php if ($transaction['appointment_location']): ?>
                                        <strong>地点：</strong><?php echo htmlspecialchars($transaction['appointment_location']); ?>
                                    <?php else: ?>
                                        <span style="color: #999;">未设置</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatDate($transaction['created_at']); ?></td>
                                <td>
                                    <a href="?edit=<?php echo $transaction['transaction_id']; ?>" class="btn btn-warning btn-small">编辑</a>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('确定要删除这笔交易记录吗？')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="transaction_id" value="<?php echo $transaction['transaction_id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-small">删除</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // 自动填充价格
        document.getElementById('product_id')?.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const price = selectedOption.getAttribute('data-price');
            if (price) {
                document.getElementById('price').value = price;
            }
        });
    </script>
</body>
</html>
