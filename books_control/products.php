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
                    $stmt = $pdo->prepare("INSERT INTO products (seller_id, book_id, condition_score, selling_price, description, status) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        intval($_POST['seller_id']),
                        intval($_POST['book_id']),
                        intval($_POST['condition_score']),
                        floatval($_POST['selling_price']),
                        sanitizeInput($_POST['description']),
                        sanitizeInput($_POST['status'])
                    ]);
                    $message = '商品发布成功！';
                } catch (PDOException $e) {
                    $error = '发布失败：' . $e->getMessage();
                }
                break;
                
            case 'update':
                try {
                    $stmt = $pdo->prepare("UPDATE products SET seller_id=?, book_id=?, condition_score=?, selling_price=?, description=?, status=? WHERE product_id=?");
                    $stmt->execute([
                        intval($_POST['seller_id']),
                        intval($_POST['book_id']),
                        intval($_POST['condition_score']),
                        floatval($_POST['selling_price']),
                        sanitizeInput($_POST['description']),
                        sanitizeInput($_POST['status']),
                        intval($_POST['product_id'])
                    ]);
                    $message = '商品更新成功！';
                } catch (PDOException $e) {
                    $error = '更新失败：' . $e->getMessage();
                }
                break;
                
            case 'delete':
                try {
                    $product_id = intval($_POST['product_id']);
                    
                    // 开始事务
                    $pdo->beginTransaction();
                    
                    // 1. 先删除相关的评价记录
                    $stmt = $pdo->prepare("DELETE r FROM reviews r INNER JOIN transactions t ON r.transaction_id = t.transaction_id WHERE t.product_id = ?");
                    $stmt->execute([$product_id]);
                    
                    // 2. 再删除相关的交易记录
                    $stmt = $pdo->prepare("DELETE FROM transactions WHERE product_id = ?");
                    $stmt->execute([$product_id]);
                    
                    // 3. 最后删除商品
                    $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = ?");
                    $stmt->execute([$product_id]);
                    
                    // 提交事务
                    $pdo->commit();
                    $message = '商品及相关记录删除成功！';
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
$price_min = isset($_GET['price_min']) ? floatval($_GET['price_min']) : 0;
$price_max = isset($_GET['price_max']) ? floatval($_GET['price_max']) : 0;

// 构建查询
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(t.title LIKE ? OR t.author LIKE ? OR t.isbn LIKE ? OR t.course_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
}

if ($price_min > 0) {
    $where_conditions[] = "p.selling_price >= ?";
    $params[] = $price_min;
}

if ($price_max > 0) {
    $where_conditions[] = "p.selling_price <= ?";
    $params[] = $price_max;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// 获取商品列表（关联教材和用户信息）
$sql = "SELECT p.*, t.title, t.author, t.isbn, t.course_code, t.course_name, t.original_price, u.username 
        FROM products p 
        LEFT JOIN textbooks t ON p.book_id = t.book_id 
        LEFT JOIN users u ON p.seller_id = u.user_id 
        $where_clause 
        ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// 获取用户列表
$users_stmt = $pdo->query("SELECT user_id, username, student_id FROM users ORDER BY username");
$users = $users_stmt->fetchAll();

// 获取教材列表
$books_stmt = $pdo->query("SELECT book_id, title, author, isbn, course_code FROM textbooks ORDER BY title");
$books = $books_stmt->fetchAll();

// 获取编辑的商品信息
$edit_product = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = ?");
    $stmt->execute([intval($_GET['edit'])]);
    $edit_product = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>商品管理 - 校园二手教材平台</title>
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
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
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
        
        .status-available {
            background: #d4edda;
            color: #155724;
        }
        
        .status-reserved {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-sold {
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <svg viewBox="0 0 24 24"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/></svg>
                商品管理
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
        
        <!-- 发布/编辑商品表单 -->
        <div class="form-section">
            <h3><?php echo $edit_product ? '编辑商品' : '发布新商品'; ?></h3>
            <form method="POST">
                <input type="hidden" name="action" value="<?php echo $edit_product ? 'update' : 'add'; ?>">
                <?php if ($edit_product): ?>
                    <input type="hidden" name="product_id" value="<?php echo $edit_product['product_id']; ?>">
                <?php endif; ?>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="seller_id">卖家 *</label>
                        <select id="seller_id" name="seller_id" required>
                            <option value="">请选择卖家</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['user_id']; ?>" 
                                        <?php echo ($edit_product && $edit_product['seller_id'] == $user['user_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['username'] . ' (' . $user['student_id'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="book_id">教材 *</label>
                        <select id="book_id" name="book_id" required>
                            <option value="">请选择教材</option>
                            <?php foreach ($books as $book): ?>
                                <option value="<?php echo $book['book_id']; ?>" 
                                        <?php echo ($edit_product && $edit_product['book_id'] == $book['book_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($book['title'] . ' - ' . $book['author'] . ' (' . $book['isbn'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="condition_score">新旧程度 (1-10分) *</label>
                        <select id="condition_score" name="condition_score" required>
                            <option value="">请选择</option>
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                <option value="<?php echo $i; ?>" 
                                        <?php echo ($edit_product && $edit_product['condition_score'] == $i) ? 'selected' : ''; ?>>
                                    <?php echo $i; ?>分 - <?php 
                                        $conditions = ['很差', '较差', '一般', '良好', '较好', '好', '很好', '优秀', '极好', '全新'];
                                        echo $conditions[$i-1];
                                    ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="selling_price">售价（元）*</label>
                        <input type="number" step="0.01" id="selling_price" name="selling_price" required 
                               value="<?php echo $edit_product ? $edit_product['selling_price'] : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="status">状态 *</label>
                        <select id="status" name="status" required>
                            <option value="available" <?php echo ($edit_product && $edit_product['status'] == 'available') ? 'selected' : ''; ?>>可售</option>
                            <option value="reserved" <?php echo ($edit_product && $edit_product['status'] == 'reserved') ? 'selected' : ''; ?>>预订中</option>
                            <option value="sold" <?php echo ($edit_product && $edit_product['status'] == 'sold') ? 'selected' : ''; ?>>已售</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">商品描述</label>
                    <textarea id="description" name="description" placeholder="请详细描述教材的使用情况、有无笔记、缺页等..."><?php echo $edit_product ? htmlspecialchars($edit_product['description']) : ''; ?></textarea>
                </div>
                
                <button type="submit" class="btn btn-success">
                    <?php echo $edit_product ? '更新商品' : '发布商品'; ?>
                </button>
                
                <?php if ($edit_product): ?>
                    <a href="products.php" class="btn btn-primary">取消编辑</a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- 搜索和筛选 -->
        <div class="search-section">
            <h3>
                <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                搜索商品
            </h3>
            <form method="GET" class="search-form">
                <div class="form-group">
                    <label for="search">关键词搜索</label>
                    <input type="text" id="search" name="search" placeholder="教材名称、作者、ISBN或课程" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="form-group">
                    <label for="status_filter">状态筛选</label>
                    <select id="status_filter" name="status_filter">
                        <option value="">全部状态</option>
                        <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>>可售</option>
                        <option value="reserved" <?php echo $status_filter === 'reserved' ? 'selected' : ''; ?>>预订中</option>
                        <option value="sold" <?php echo $status_filter === 'sold' ? 'selected' : ''; ?>>已售</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="price_min">最低价格</label>
                    <input type="number" step="0.01" id="price_min" name="price_min" value="<?php echo $price_min > 0 ? $price_min : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="price_max">最高价格</label>
                    <input type="number" step="0.01" id="price_max" name="price_max" value="<?php echo $price_max > 0 ? $price_max : ''; ?>">
                </div>
                
                <button type="submit" class="btn btn-primary">搜索</button>
                <a href="products.php" class="btn btn-warning">重置</a>
            </form>
        </div>
        
        <!-- 商品列表 -->
        <div class="table-section">
            <h3>
                <svg viewBox="0 0 24 24"><path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/></svg>
                商品列表 (共 <?php echo count($products); ?> 件)
            </h3>
            
            <?php if (empty($products)): ?>
                <p style="text-align: center; color: #666; margin: 40px 0;">暂无商品信息</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>教材信息</th>
                            <th>卖家</th>
                            <th>新旧程度</th>
                            <th>原价/售价</th>
                            <th>状态</th>
                            <th>描述</th>
                            <th>发布时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($product['title']); ?></strong><br>
                                    <small>作者：<?php echo htmlspecialchars($product['author']); ?></small><br>
                                    <small>ISBN：<?php echo htmlspecialchars($product['isbn']); ?></small>
                                    <?php if ($product['course_code']): ?>
                                        <br><span class="course-tag"><?php echo htmlspecialchars($product['course_code']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($product['username']); ?></td>
                                <td>
                                    <span class="condition-score"><?php echo $product['condition_score']; ?>分</span>
                                </td>
                                <td>
                                    <?php if ($product['original_price']): ?>
                                        <small style="text-decoration: line-through; color: #999;"><?php echo formatPrice($product['original_price']); ?></small><br>
                                    <?php endif; ?>
                                    <span class="price"><?php echo formatPrice($product['selling_price']); ?></span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $product['status']; ?>">
                                        <?php echo getStatusText($product['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($product['description']): ?>
                                        <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                            <?php echo htmlspecialchars(mb_substr($product['description'], 0, 50)); ?>
                                            <?php if (mb_strlen($product['description']) > 50): ?>..<?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #999;">无描述</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatDate($product['created_at']); ?></td>
                                <td>
                                    <a href="?edit=<?php echo $product['product_id']; ?>" class="btn btn-warning btn-small">编辑</a>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('确定要删除这个商品吗？')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
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
</body>
</html>
