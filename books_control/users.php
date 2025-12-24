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
                    // 检查用户名和邮箱是否已存在
                    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
                    $check_stmt->execute([sanitizeInput($_POST['username']), sanitizeInput($_POST['email'])]);
                    if ($check_stmt->fetchColumn() > 0) {
                        $error = '用户名或邮箱已存在！';
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, phone, student_id, credit_score) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            sanitizeInput($_POST['username']),
                            md5(sanitizeInput($_POST['password'])),
                            sanitizeInput($_POST['email']),
                            sanitizeInput($_POST['phone']),
                            sanitizeInput($_POST['student_id']),
                            floatval($_POST['credit_score'])
                        ]);
                        $message = '用户添加成功！';
                    }
                } catch (PDOException $e) {
                    $error = '添加失败：' . $e->getMessage();
                }
                break;
                
            case 'update':
                try {
                    if (!empty($_POST['password'])) {
                        $stmt = $pdo->prepare("UPDATE users SET username=?, password=?, email=?, phone=?, student_id=?, credit_score=? WHERE user_id=?");
                        $stmt->execute([
                            sanitizeInput($_POST['username']),
                            md5(sanitizeInput($_POST['password'])),
                            sanitizeInput($_POST['email']),
                            sanitizeInput($_POST['phone']),
                            sanitizeInput($_POST['student_id']),
                            floatval($_POST['credit_score']),
                            intval($_POST['user_id'])
                        ]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET username=?, email=?, phone=?, student_id=?, credit_score=? WHERE user_id=?");
                        $stmt->execute([
                            sanitizeInput($_POST['username']),
                            sanitizeInput($_POST['email']),
                            sanitizeInput($_POST['phone']),
                            sanitizeInput($_POST['student_id']),
                            floatval($_POST['credit_score']),
                            intval($_POST['user_id'])
                        ]);
                    }
                    $message = '用户更新成功！';
                } catch (PDOException $e) {
                    $error = '更新失败：' . $e->getMessage();
                }
                break;
                
            case 'delete':
                try {
                    $user_id = intval($_POST['user_id']);
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("DELETE FROM reviews WHERE reviewer_id = ? OR reviewed_id = ?");
                    $stmt->execute([$user_id, $user_id]);
                    $stmt = $pdo->prepare("DELETE FROM transactions WHERE buyer_id = ? OR seller_id = ?");
                    $stmt->execute([$user_id, $user_id]);
                    $stmt = $pdo->prepare("DELETE FROM purchase_requests WHERE buyer_id = ?");
                    $stmt->execute([$user_id]);
                    $stmt = $pdo->prepare("DELETE FROM products WHERE seller_id = ?");
                    $stmt->execute([$user_id]);
                    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $pdo->commit();
                    $message = '用户及所有相关记录删除成功！';
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = '删除失败：' . $e->getMessage();
                }
                break;
        }
    }
}

$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$credit_filter = isset($_GET['credit_filter']) ? sanitizeInput($_GET['credit_filter']) : '';

$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(username LIKE ? OR email LIKE ? OR student_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($credit_filter) {
    switch ($credit_filter) {
        case 'excellent': $where_conditions[] = "credit_score >= 4.5"; break;
        case 'good': $where_conditions[] = "credit_score >= 3.5 AND credit_score < 4.5"; break;
        case 'average': $where_conditions[] = "credit_score >= 2.5 AND credit_score < 3.5"; break;
        case 'poor': $where_conditions[] = "credit_score < 2.5"; break;
    }
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
$stmt = $pdo->prepare("SELECT * FROM users $where_clause ORDER BY created_at DESC");
$stmt->execute($params);
$users = $stmt->fetchAll();

$edit_user = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([intval($_GET['edit'])]);
    $edit_user = $stmt->fetch();
}

function getUserStats($pdo, $user_id) {
    $stats = [];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE seller_id = ?");
    $stmt->execute([$user_id]);
    $stats['products_count'] = $stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE seller_id = ? AND status = 'sold'");
    $stmt->execute([$user_id]);
    $stats['sold_count'] = $stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE buyer_id = ? AND status = 'completed'");
    $stmt->execute([$user_id]);
    $stats['bought_count'] = $stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE reviewed_id = ?");
    $stmt->execute([$user_id]);
    $stats['reviews_count'] = $stmt->fetchColumn();
    return $stats;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户管理 - 校园二手教材平台</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Microsoft YaHei', Arial, sans-serif; background: #f5f5f5; min-height: 100vh; color: #333; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .header { background: #ffffff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border: 1px solid #e0e0e0; }
        .header h1 { color: #1a1a1a; margin-bottom: 10px; display: flex; align-items: center; gap: 10px; }
        .header h1 svg { width: 28px; height: 28px; fill: #1a1a1a; }
        .nav-link { color: #1a1a1a; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 5px; }
        .nav-link svg { width: 16px; height: 16px; fill: currentColor; }
        .nav-link:hover { text-decoration: underline; }
        .form-section { background: #ffffff; padding: 25px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border: 1px solid #e0e0e0; }
        .form-section h3 { color: #1a1a1a; margin-bottom: 20px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 5px; font-weight: 500; color: #1a1a1a; }
        .form-group input, .form-group select { padding: 10px; border: 1px solid #d0d0d0; border-radius: 4px; font-size: 14px; transition: border-color 0.3s; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #1a1a1a; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500; text-decoration: none; display: inline-block; text-align: center; transition: all 0.3s; margin: 5px; }
        .btn-primary { background: #1a1a1a; color: white; }
        .btn-primary:hover { background: #333; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-warning { background: #fd7e14; color: white; }
        .btn-warning:hover { background: #e96b02; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-small { padding: 5px 10px; font-size: 12px; }
        .search-section { background: #ffffff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border: 1px solid #e0e0e0; }
        .search-section h3 { color: #1a1a1a; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
        .search-section h3 svg { width: 20px; height: 20px; fill: #1a1a1a; }
        .search-form { display: flex; gap: 15px; align-items: end; flex-wrap: wrap; }
        .table-section { background: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border: 1px solid #e0e0e0; overflow-x: auto; }
        .table-section h3 { color: #1a1a1a; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
        .table-section h3 svg { width: 20px; height: 20px; fill: #1a1a1a; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e0e0e0; }
        th { background: #fafafa; font-weight: 500; color: #1a1a1a; }
        tr:hover { background: #fafafa; }
        .message { padding: 15px; border-radius: 4px; margin-bottom: 20px; font-weight: 500; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .credit-score { display: inline-block; padding: 4px 8px; border-radius: 4px; font-weight: 500; font-size: 12px; }
        .credit-excellent { background: #d4edda; color: #155724; }
        .credit-good { background: #d1ecf1; color: #0c5460; }
        .credit-average { background: #fff3cd; color: #856404; }
        .credit-poor { background: #f8d7da; color: #721c24; }
        .user-stats { display: flex; gap: 10px; flex-wrap: wrap; }
        .stat-item { background: #fafafa; padding: 4px 8px; border-radius: 4px; font-size: 12px; color: #666; display: flex; align-items: center; gap: 4px; }
        .stat-item svg { width: 12px; height: 12px; fill: #666; }
        .password-note { font-size: 12px; color: #666; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                用户管理
            </h1>
            <p><a href="index.php" class="nav-link"><svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>返回首页</a></p>
        </div>
        
        <?php if ($message): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>
        
        <div class="form-section">
            <h3><?php echo $edit_user ? '编辑用户' : '添加新用户'; ?></h3>
            <form method="POST">
                <input type="hidden" name="action" value="<?php echo $edit_user ? 'update' : 'add'; ?>">
                <?php if ($edit_user): ?><input type="hidden" name="user_id" value="<?php echo $edit_user['user_id']; ?>"><?php endif; ?>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="username">用户名 *</label>
                        <input type="text" id="username" name="username" required value="<?php echo $edit_user ? htmlspecialchars($edit_user['username']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="password"><?php echo $edit_user ? '新密码（留空不修改）' : '密码 *'; ?></label>
                        <input type="password" id="password" name="password" <?php echo $edit_user ? '' : 'required'; ?>>
                        <?php if ($edit_user): ?><div class="password-note">留空表示不修改密码</div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="email">邮箱 *</label>
                        <input type="email" id="email" name="email" required value="<?php echo $edit_user ? htmlspecialchars($edit_user['email']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="phone">手机号</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo $edit_user ? htmlspecialchars($edit_user['phone']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="student_id">学号</label>
                        <input type="text" id="student_id" name="student_id" value="<?php echo $edit_user ? htmlspecialchars($edit_user['student_id']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="credit_score">信用评分 (1.0-5.0)</label>
                        <input type="number" step="0.1" min="1.0" max="5.0" id="credit_score" name="credit_score" value="<?php echo $edit_user ? $edit_user['credit_score'] : '5.0'; ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-success"><?php echo $edit_user ? '更新用户' : '添加用户'; ?></button>
                <?php if ($edit_user): ?><a href="users.php" class="btn btn-primary">取消编辑</a><?php endif; ?>
            </form>
        </div>
        
        <div class="search-section">
            <h3><svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>搜索用户</h3>
            <form method="GET" class="search-form">
                <div class="form-group">
                    <label for="search">关键词搜索</label>
                    <input type="text" id="search" name="search" placeholder="用户名、邮箱或学号" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="form-group">
                    <label for="credit_filter">信用等级筛选</label>
                    <select id="credit_filter" name="credit_filter">
                        <option value="">全部等级</option>
                        <option value="excellent" <?php echo $credit_filter === 'excellent' ? 'selected' : ''; ?>>优秀 (4.5-5.0)</option>
                        <option value="good" <?php echo $credit_filter === 'good' ? 'selected' : ''; ?>>良好 (3.5-4.5)</option>
                        <option value="average" <?php echo $credit_filter === 'average' ? 'selected' : ''; ?>>一般 (2.5-3.5)</option>
                        <option value="poor" <?php echo $credit_filter === 'poor' ? 'selected' : ''; ?>>较差 (<2.5)</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">搜索</button>
                <a href="users.php" class="btn btn-warning">重置</a>
            </form>
        </div>
        
        <div class="table-section">
            <h3><svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>用户列表 (共 <?php echo count($users); ?> 人)</h3>
            <?php if (empty($users)): ?>
                <p style="text-align: center; color: #666; margin: 40px 0;">暂无用户信息</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr><th>用户信息</th><th>联系方式</th><th>学号</th><th>信用评分</th><th>活动统计</th><th>注册时间</th><th>操作</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <?php $stats = getUserStats($pdo, $user['user_id']); ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($user['username']); ?></strong><br><small><?php echo htmlspecialchars($user['email']); ?></small></td>
                                <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                <td><?php echo htmlspecialchars($user['student_id']); ?></td>
                                <td>
                                    <?php 
                                    $score = $user['credit_score'];
                                    $class = 'credit-poor';
                                    if ($score >= 4.5) $class = 'credit-excellent';
                                    elseif ($score >= 3.5) $class = 'credit-good';
                                    elseif ($score >= 2.5) $class = 'credit-average';
                                    ?>
                                    <span class="credit-score <?php echo $class; ?>"><?php echo number_format($score, 1); ?></span>
                                </td>
                                <td>
                                    <div class="user-stats">
                                        <span class="stat-item"><svg viewBox="0 0 24 24"><path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/></svg>发布 <?php echo $stats['products_count']; ?></span>
                                        <span class="stat-item"><svg viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>已售 <?php echo $stats['sold_count']; ?></span>
                                        <span class="stat-item"><svg viewBox="0 0 24 24"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/></svg>购买 <?php echo $stats['bought_count']; ?></span>
                                        <span class="stat-item"><svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>评价 <?php echo $stats['reviews_count']; ?></span>
                                    </div>
                                </td>
                                <td><?php echo formatDate($user['created_at']); ?></td>
                                <td>
                                    <a href="?edit=<?php echo $user['user_id']; ?>" class="btn btn-warning btn-small">编辑</a>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('确定要删除这个用户吗？')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
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
