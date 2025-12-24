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
                    $stmt = $pdo->prepare("INSERT INTO textbooks (isbn, title, author, publisher, edition, course_code, course_name, teacher_name, original_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        sanitizeInput($_POST['isbn']),
                        sanitizeInput($_POST['title']),
                        sanitizeInput($_POST['author']),
                        sanitizeInput($_POST['publisher']),
                        sanitizeInput($_POST['edition']),
                        sanitizeInput($_POST['course_code']),
                        sanitizeInput($_POST['course_name']),
                        sanitizeInput($_POST['teacher_name']),
                        floatval($_POST['original_price'])
                    ]);
                    $message = '教材添加成功！';
                } catch (PDOException $e) {
                    $error = '添加失败：' . $e->getMessage();
                }
                break;
                
            case 'update':
                try {
                    $stmt = $pdo->prepare("UPDATE textbooks SET isbn=?, title=?, author=?, publisher=?, edition=?, course_code=?, course_name=?, teacher_name=?, original_price=? WHERE book_id=?");
                    $stmt->execute([
                        sanitizeInput($_POST['isbn']),
                        sanitizeInput($_POST['title']),
                        sanitizeInput($_POST['author']),
                        sanitizeInput($_POST['publisher']),
                        sanitizeInput($_POST['edition']),
                        sanitizeInput($_POST['course_code']),
                        sanitizeInput($_POST['course_name']),
                        sanitizeInput($_POST['teacher_name']),
                        floatval($_POST['original_price']),
                        intval($_POST['book_id'])
                    ]);
                    $message = '教材更新成功！';
                } catch (PDOException $e) {
                    $error = '更新失败：' . $e->getMessage();
                }
                break;
                
            case 'delete':
                try {
                    $book_id = intval($_POST['book_id']);
                    
                    // 开始事务
                    $pdo->beginTransaction();
                    
                    // 1. 先删除相关的评价记录（通过交易记录关联）
                    $stmt = $pdo->prepare("DELETE r FROM reviews r INNER JOIN transactions t ON r.transaction_id = t.transaction_id INNER JOIN products p ON t.product_id = p.product_id WHERE p.book_id = ?");
                    $stmt->execute([$book_id]);
                    
                    // 2. 删除相关的交易记录
                    $stmt = $pdo->prepare("DELETE t FROM transactions t INNER JOIN products p ON t.product_id = p.product_id WHERE p.book_id = ?");
                    $stmt->execute([$book_id]);
                    
                    // 3. 删除相关的求购需求
                    $stmt = $pdo->prepare("DELETE FROM purchase_requests WHERE book_id = ?");
                    $stmt->execute([$book_id]);
                    
                    // 4. 删除相关的产品
                    $stmt = $pdo->prepare("DELETE FROM products WHERE book_id = ?");
                    $stmt->execute([$book_id]);
                    
                    // 5. 最后删除教材
                    $stmt = $pdo->prepare("DELETE FROM textbooks WHERE book_id = ?");
                    $stmt->execute([$book_id]);
                    
                    // 提交事务
                    $pdo->commit();
                    $message = '教材及所有相关记录删除成功！';
                } catch (PDOException $e) {
                    // 回滚事务
                    $pdo->rollBack();
                    $error = '删除失败：' . $e->getMessage();
                }
                break;
        }
    }
}

// 获取搜索条件
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$course_filter = isset($_GET['course_filter']) ? sanitizeInput($_GET['course_filter']) : '';

// 构建查询
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(title LIKE ? OR author LIKE ? OR isbn LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($course_filter) {
    $where_conditions[] = "course_code = ?";
    $params[] = $course_filter;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// 获取教材列表
$stmt = $pdo->prepare("SELECT * FROM textbooks $where_clause ORDER BY created_at DESC");
$stmt->execute($params);
$textbooks = $stmt->fetchAll();

// 获取课程列表用于筛选
$courses_stmt = $pdo->query("SELECT DISTINCT course_code, course_name FROM textbooks WHERE course_code IS NOT NULL ORDER BY course_code");
$courses = $courses_stmt->fetchAll();

// 获取编辑的教材信息
$edit_book = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM textbooks WHERE book_id = ?");
    $stmt->execute([intval($_GET['edit'])]);
    $edit_book = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>教材管理 - 校园二手教材平台</title>
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
        
        .form-group input, .form-group select {
            padding: 10px;
            border: 1px solid #d0d0d0;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus, .form-group select:focus {
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
        
        .course-tag {
            background: #1a1a1a;
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
                <svg viewBox="0 0 24 24"><path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/></svg>
                教材管理
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
        
        <!-- 添加/编辑教材表单 -->
        <div class="form-section">
            <h3><?php echo $edit_book ? '编辑教材' : '添加新教材'; ?></h3>
            <form method="POST">
                <input type="hidden" name="action" value="<?php echo $edit_book ? 'update' : 'add'; ?>">
                <?php if ($edit_book): ?>
                    <input type="hidden" name="book_id" value="<?php echo $edit_book['book_id']; ?>">
                <?php endif; ?>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="isbn">ISBN *</label>
                        <input type="text" id="isbn" name="isbn" required value="<?php echo $edit_book ? htmlspecialchars($edit_book['isbn']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="title">教材名称 *</label>
                        <input type="text" id="title" name="title" required value="<?php echo $edit_book ? htmlspecialchars($edit_book['title']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="author">作者</label>
                        <input type="text" id="author" name="author" value="<?php echo $edit_book ? htmlspecialchars($edit_book['author']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="publisher">出版社</label>
                        <input type="text" id="publisher" name="publisher" value="<?php echo $edit_book ? htmlspecialchars($edit_book['publisher']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="edition">版本</label>
                        <input type="text" id="edition" name="edition" value="<?php echo $edit_book ? htmlspecialchars($edit_book['edition']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="course_code">课程代码</label>
                        <input type="text" id="course_code" name="course_code" value="<?php echo $edit_book ? htmlspecialchars($edit_book['course_code']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="course_name">课程名称</label>
                        <input type="text" id="course_name" name="course_name" value="<?php echo $edit_book ? htmlspecialchars($edit_book['course_name']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="teacher_name">任课教师</label>
                        <input type="text" id="teacher_name" name="teacher_name" value="<?php echo $edit_book ? htmlspecialchars($edit_book['teacher_name']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="original_price">原价（元）</label>
                        <input type="number" step="0.01" id="original_price" name="original_price" value="<?php echo $edit_book ? $edit_book['original_price'] : ''; ?>">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-success">
                    <?php echo $edit_book ? '更新教材' : '添加教材'; ?>
                </button>
                
                <?php if ($edit_book): ?>
                    <a href="textbooks.php" class="btn btn-primary">取消编辑</a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- 搜索和筛选 -->
        <div class="search-section">
            <h3>
                <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                搜索教材
            </h3>
            <form method="GET" class="search-form">
                <div class="form-group">
                    <label for="search">关键词搜索</label>
                    <input type="text" id="search" name="search" placeholder="教材名称、作者或ISBN" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="form-group">
                    <label for="course_filter">课程筛选</label>
                    <select id="course_filter" name="course_filter">
                        <option value="">全部课程</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo htmlspecialchars($course['course_code']); ?>" 
                                    <?php echo $course_filter === $course['course_code'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">搜索</button>
                <a href="textbooks.php" class="btn btn-warning">重置</a>
            </form>
        </div>
        
        <!-- 教材列表 -->
        <div class="table-section">
            <h3>
                <svg viewBox="0 0 24 24"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-1 9H9V9h10v2zm-4 4H9v-2h6v2zm4-8H9V5h10v2z"/></svg>
                教材列表 (共 <?php echo count($textbooks); ?> 本)
            </h3>
            
            <?php if (empty($textbooks)): ?>
                <p style="text-align: center; color: #666; margin: 40px 0;">暂无教材信息</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ISBN</th>
                            <th>教材名称</th>
                            <th>作者</th>
                            <th>出版社</th>
                            <th>版本</th>
                            <th>课程信息</th>
                            <th>任课教师</th>
                            <th>原价</th>
                            <th>添加时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($textbooks as $book): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($book['isbn']); ?></td>
                                <td><strong><?php echo htmlspecialchars($book['title']); ?></strong></td>
                                <td><?php echo htmlspecialchars($book['author']); ?></td>
                                <td><?php echo htmlspecialchars($book['publisher']); ?></td>
                                <td><?php echo htmlspecialchars($book['edition']); ?></td>
                                <td>
                                    <?php if ($book['course_code']): ?>
                                        <span class="course-tag"><?php echo htmlspecialchars($book['course_code']); ?></span><br>
                                        <small><?php echo htmlspecialchars($book['course_name']); ?></small>
                                    <?php else: ?>
                                        <span style="color: #999;">未设置</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($book['teacher_name']); ?></td>
                                <td class="price"><?php echo $book['original_price'] ? formatPrice($book['original_price']) : '-'; ?></td>
                                <td><?php echo formatDate($book['created_at']); ?></td>
                                <td>
                                    <a href="?edit=<?php echo $book['book_id']; ?>" class="btn btn-warning btn-small">编辑</a>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('确定要删除这本教材吗？')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
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
