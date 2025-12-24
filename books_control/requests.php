<?php
require_once 'config.php';
require_once 'auth_check.php';
checkAdminAuth();
$pdo = getDBConnection();
$message = '';
$error = '';

if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add':
            try {
                $stmt = $pdo->prepare("INSERT INTO purchase_requests (buyer_id, book_id, isbn, title, course_code, max_price, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([intval($_POST['buyer_id']), !empty($_POST['book_id']) ? intval($_POST['book_id']) : null, sanitizeInput($_POST['isbn']), sanitizeInput($_POST['title']), sanitizeInput($_POST['course_code']), !empty($_POST['max_price']) ? floatval($_POST['max_price']) : null, sanitizeInput($_POST['description']), sanitizeInput($_POST['status'])]);
                $message = '求购需求发布成功！';
            } catch (PDOException $e) { $error = '发布失败：' . $e->getMessage(); }
            break;
        case 'update':
            try {
                $stmt = $pdo->prepare("UPDATE purchase_requests SET buyer_id=?, book_id=?, isbn=?, title=?, course_code=?, max_price=?, description=?, status=? WHERE request_id=?");
                $stmt->execute([intval($_POST['buyer_id']), !empty($_POST['book_id']) ? intval($_POST['book_id']) : null, sanitizeInput($_POST['isbn']), sanitizeInput($_POST['title']), sanitizeInput($_POST['course_code']), !empty($_POST['max_price']) ? floatval($_POST['max_price']) : null, sanitizeInput($_POST['description']), sanitizeInput($_POST['status']), intval($_POST['request_id'])]);
                $message = '求购需求更新成功！';
            } catch (PDOException $e) { $error = '更新失败：' . $e->getMessage(); }
            break;
        case 'delete':
            try {
                $stmt = $pdo->prepare("DELETE FROM purchase_requests WHERE request_id = ?");
                $stmt->execute([intval($_POST['request_id'])]);
                $message = '求购需求删除成功！';
            } catch (PDOException $e) { $error = '删除失败：' . $e->getMessage(); }
            break;
    }
}

$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status_filter = isset($_GET['status_filter']) ? sanitizeInput($_GET['status_filter']) : '';
$course_filter = isset($_GET['course_filter']) ? sanitizeInput($_GET['course_filter']) : '';
$price_max = isset($_GET['price_max']) ? floatval($_GET['price_max']) : 0;
$where_conditions = []; $params = [];
if ($search) { $where_conditions[] = "(pr.title LIKE ? OR pr.isbn LIKE ? OR pr.course_code LIKE ? OR u.username LIKE ?)"; $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]); }
if ($status_filter) { $where_conditions[] = "pr.status = ?"; $params[] = $status_filter; }
if ($course_filter) { $where_conditions[] = "pr.course_code = ?"; $params[] = $course_filter; }
if ($price_max > 0) { $where_conditions[] = "(pr.max_price IS NULL OR pr.max_price <= ?)"; $params[] = $price_max; }
$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$stmt = $pdo->prepare("SELECT pr.*, u.username as buyer_name, t.title as book_title, t.author, t.original_price FROM purchase_requests pr LEFT JOIN users u ON pr.buyer_id = u.user_id LEFT JOIN textbooks t ON pr.book_id = t.book_id $where_clause ORDER BY pr.created_at DESC");
$stmt->execute($params);
$requests = $stmt->fetchAll();

$users = $pdo->query("SELECT user_id, username, student_id FROM users ORDER BY username")->fetchAll();
$books = $pdo->query("SELECT book_id, title, author, isbn, course_code, original_price FROM textbooks ORDER BY title")->fetchAll();
$courses = $pdo->query("SELECT DISTINCT course_code FROM textbooks WHERE course_code IS NOT NULL UNION SELECT DISTINCT course_code FROM purchase_requests WHERE course_code IS NOT NULL ORDER BY course_code")->fetchAll();

$edit_request = null;
if (isset($_GET['edit'])) { $stmt = $pdo->prepare("SELECT * FROM purchase_requests WHERE request_id = ?"); $stmt->execute([intval($_GET['edit'])]); $edit_request = $stmt->fetch(); }

function findMatches($pdo, $request) {
    $matches = [];
    if ($request['book_id']) { $stmt = $pdo->prepare("SELECT p.*, t.title, t.author, u.username as seller_name FROM products p LEFT JOIN textbooks t ON p.book_id = t.book_id LEFT JOIN users u ON p.seller_id = u.user_id WHERE p.book_id = ? AND p.status = 'available' AND (? IS NULL OR p.selling_price <= ?)"); $stmt->execute([$request['book_id'], $request['max_price'], $request['max_price']]); $matches = array_merge($matches, $stmt->fetchAll()); }
    if ($request['isbn']) { $stmt = $pdo->prepare("SELECT p.*, t.title, t.author, u.username as seller_name FROM products p LEFT JOIN textbooks t ON p.book_id = t.book_id LEFT JOIN users u ON p.seller_id = u.user_id WHERE t.isbn = ? AND p.status = 'available' AND (? IS NULL OR p.selling_price <= ?)"); $stmt->execute([$request['isbn'], $request['max_price'], $request['max_price']]); $matches = array_merge($matches, $stmt->fetchAll()); }
    if ($request['title']) { $stmt = $pdo->prepare("SELECT p.*, t.title, t.author, u.username as seller_name FROM products p LEFT JOIN textbooks t ON p.book_id = t.book_id LEFT JOIN users u ON p.seller_id = u.user_id WHERE t.title LIKE ? AND p.status = 'available' AND (? IS NULL OR p.selling_price <= ?)"); $stmt->execute(["%{$request['title']}%", $request['max_price'], $request['max_price']]); $matches = array_merge($matches, $stmt->fetchAll()); }
    $unique = []; $seen = [];
    foreach ($matches as $m) { if (!in_array($m['product_id'], $seen)) { $unique[] = $m; $seen[] = $m['product_id']; } }
    return $unique;
}

$matches = [];
if (isset($_GET['match']) && is_numeric($_GET['match'])) { $stmt = $pdo->prepare("SELECT * FROM purchase_requests WHERE request_id = ?"); $stmt->execute([intval($_GET['match'])]); $mr = $stmt->fetch(); if ($mr) $matches = findMatches($pdo, $mr); }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>求购需求 - 校园二手教材平台</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Microsoft YaHei', Arial, sans-serif; background: #f5f5f5; min-height: 100vh; color: #333; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .header { background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border: 1px solid #e0e0e0; }
        .header h1 { color: #1a1a1a; margin-bottom: 10px; display: flex; align-items: center; gap: 10px; }
        .header h1 svg { width: 28px; height: 28px; fill: #1a1a1a; }
        .nav-link { color: #1a1a1a; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 5px; }
        .nav-link svg { width: 16px; height: 16px; fill: currentColor; }
        .nav-link:hover { text-decoration: underline; }
        .form-section, .search-section, .table-section, .match-section { background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border: 1px solid #e0e0e0; }
        .form-section h3, .search-section h3, .table-section h3, .match-section h3 { color: #1a1a1a; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
        .form-section h3 svg, .search-section h3 svg, .table-section h3 svg, .match-section h3 svg { width: 20px; height: 20px; fill: #1a1a1a; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 5px; font-weight: 500; color: #1a1a1a; }
        .form-group input, .form-group select, .form-group textarea { padding: 10px; border: 1px solid #d0d0d0; border-radius: 4px; font-size: 14px; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #1a1a1a; }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500; text-decoration: none; display: inline-block; text-align: center; margin: 5px; transition: all 0.3s; }
        .btn-primary { background: #1a1a1a; color: white; }
        .btn-primary:hover { background: #333; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-warning { background: #fd7e14; color: white; }
        .btn-warning:hover { background: #e96b02; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-info { background: #17a2b8; color: white; }
        .btn-info:hover { background: #138496; }
        .btn-small { padding: 5px 10px; font-size: 12px; }
        .search-form { display: flex; gap: 15px; align-items: end; flex-wrap: wrap; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e0e0e0; }
        th { background: #fafafa; font-weight: 500; color: #1a1a1a; }
        tr:hover { background: #fafafa; }
        .message { padding: 15px; border-radius: 4px; margin-bottom: 20px; font-weight: 500; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .price { color: #dc3545; font-weight: bold; }
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
        .status-active { background: #d4edda; color: #155724; }
        .status-matched { background: #d1ecf1; color: #0c5460; }
        .status-closed { background: #f8d7da; color: #721c24; }
        .course-tag { background: #6c757d; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px; }
        .match-item { background: #fafafa; padding: 15px; border-radius: 8px; margin-bottom: 10px; border-left: 4px solid #1a1a1a; }
        .match-item:hover { background: #f0f0f0; }
        .help-text { font-size: 12px; color: #666; margin-top: 5px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: #fff; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border: 1px solid #e0e0e0; }
        .stat-number { font-size: 2em; font-weight: bold; color: #1a1a1a; }
        .stat-label { color: #666; margin-top: 5px; }
        .table-section { overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><svg viewBox="0 0 24 24"><path d="M11 17h2v-6h-2v6zm1-15C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zM11 9h2V7h-2v2z"/></svg>求购需求</h1>
            <p><a href="index.php" class="nav-link"><svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>返回首页</a></p>
        </div>
        
        <?php if ($message): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>
        
        <div class="stats-grid">
            <?php $total = count($requests); $active = count(array_filter($requests, fn($r) => $r['status'] === 'active')); $matched = count(array_filter($requests, fn($r) => $r['status'] === 'matched')); $prices = array_filter(array_column($requests, 'max_price')); $avg = $prices ? array_sum($prices) / count($prices) : 0; ?>
            <div class="stat-card"><div class="stat-number"><?php echo $total; ?></div><div class="stat-label">总求购数</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo $active; ?></div><div class="stat-label">活跃求购</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo $matched; ?></div><div class="stat-label">已匹配</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo number_format($avg, 2); ?>元</div><div class="stat-label">平均预算</div></div>
        </div>
        
        <div class="form-section">
            <h3><?php echo $edit_request ? '编辑求购需求' : '发布新求购需求'; ?></h3>
            <form method="POST">
                <input type="hidden" name="action" value="<?php echo $edit_request ? 'update' : 'add'; ?>">
                <?php if ($edit_request): ?><input type="hidden" name="request_id" value="<?php echo $edit_request['request_id']; ?>"><?php endif; ?>
                <div class="form-grid">
                    <div class="form-group"><label for="buyer_id">求购者 *</label><select id="buyer_id" name="buyer_id" required><option value="">请选择</option><?php foreach ($users as $u): ?><option value="<?php echo $u['user_id']; ?>" <?php echo ($edit_request && $edit_request['buyer_id'] == $u['user_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['username'] . ' (' . $u['student_id'] . ')'); ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label for="book_id">指定教材</label><select id="book_id" name="book_id"><option value="">选择或手动填写</option><?php foreach ($books as $b): ?><option value="<?php echo $b['book_id']; ?>" <?php echo ($edit_request && $edit_request['book_id'] == $b['book_id']) ? 'selected' : ''; ?> data-title="<?php echo htmlspecialchars($b['title']); ?>" data-isbn="<?php echo htmlspecialchars($b['isbn']); ?>" data-course="<?php echo htmlspecialchars($b['course_code']); ?>"><?php echo htmlspecialchars($b['title'] . ' - ' . $b['author']); ?></option><?php endforeach; ?></select><div class="help-text">选择教材自动填充信息</div></div>
                    <div class="form-group"><label for="isbn">ISBN</label><input type="text" id="isbn" name="isbn" value="<?php echo $edit_request ? htmlspecialchars($edit_request['isbn']) : ''; ?>"></div>
                    <div class="form-group"><label for="title">教材名称</label><input type="text" id="title" name="title" value="<?php echo $edit_request ? htmlspecialchars($edit_request['title']) : ''; ?>"></div>
                    <div class="form-group"><label for="course_code">课程代码</label><input type="text" id="course_code" name="course_code" value="<?php echo $edit_request ? htmlspecialchars($edit_request['course_code']) : ''; ?>"></div>
                    <div class="form-group"><label for="max_price">最高预算（元）</label><input type="number" step="0.01" id="max_price" name="max_price" value="<?php echo $edit_request ? $edit_request['max_price'] : ''; ?>"><div class="help-text">留空为面议</div></div>
                    <div class="form-group"><label for="status">状态 *</label><select id="status" name="status" required><option value="active" <?php echo ($edit_request && $edit_request['status'] == 'active') ? 'selected' : ''; ?>>求购中</option><option value="matched" <?php echo ($edit_request && $edit_request['status'] == 'matched') ? 'selected' : ''; ?>>已匹配</option><option value="closed" <?php echo ($edit_request && $edit_request['status'] == 'closed') ? 'selected' : ''; ?>>已关闭</option></select></div>
                </div>
                <div class="form-group"><label for="description">详细描述</label><textarea id="description" name="description"><?php echo $edit_request ? htmlspecialchars($edit_request['description']) : ''; ?></textarea></div>
                <button type="submit" class="btn btn-success"><?php echo $edit_request ? '更新需求' : '发布需求'; ?></button>
                <?php if ($edit_request): ?><a href="requests.php" class="btn btn-primary">取消编辑</a><?php endif; ?>
            </form>
        </div>
        
        <div class="search-section">
            <h3><svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>搜索求购需求</h3>
            <form method="GET" class="search-form">
                <div class="form-group"><label for="search">关键词</label><input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>"></div>
                <div class="form-group"><label for="status_filter">状态</label><select id="status_filter" name="status_filter"><option value="">全部</option><option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>求购中</option><option value="matched" <?php echo $status_filter === 'matched' ? 'selected' : ''; ?>>已匹配</option><option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>已关闭</option></select></div>
                <div class="form-group"><label for="course_filter">课程</label><select id="course_filter" name="course_filter"><option value="">全部</option><?php foreach ($courses as $c): ?><option value="<?php echo htmlspecialchars($c['course_code']); ?>" <?php echo $course_filter === $c['course_code'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['course_code']); ?></option><?php endforeach; ?></select></div>
                <button type="submit" class="btn btn-primary">搜索</button>
                <a href="requests.php" class="btn btn-warning">重置</a>
            </form>
        </div>
        
        <?php if (!empty($matches)): ?>
        <div class="match-section">
            <h3><svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>智能匹配结果 (<?php echo count($matches); ?> 个)</h3>
            <?php foreach ($matches as $m): ?>
                <div class="match-item">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div><strong><?php echo htmlspecialchars($m['title']); ?></strong> <span>作者：<?php echo htmlspecialchars($m['author']); ?></span> <span>卖家：<?php echo htmlspecialchars($m['seller_name']); ?></span></div>
                        <div><span class="price"><?php echo number_format($m['selling_price'], 2); ?>元</span> <span style="font-size: 12px; color: #666;">新旧：<?php echo $m['condition_score']; ?>分</span></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <div class="table-section">
            <h3><svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>求购需求列表 (共 <?php echo count($requests); ?> 条)</h3>
            <?php if (empty($requests)): ?>
                <p style="text-align: center; color: #666; margin: 40px 0;">暂无求购需求</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>ID</th><th>求购者</th><th>教材信息</th><th>课程</th><th>预算</th><th>状态</th><th>描述</th><th>时间</th><th>操作</th></tr></thead>
                    <tbody>
                        <?php foreach ($requests as $r): ?>
                            <tr>
                                <td><strong>#<?php echo $r['request_id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($r['buyer_name']); ?></td>
                                <td><?php if ($r['book_title']): ?><strong><?php echo htmlspecialchars($r['book_title']); ?></strong><br><small>作者：<?php echo htmlspecialchars($r['author']); ?></small><?php elseif ($r['title']): ?><strong><?php echo htmlspecialchars($r['title']); ?></strong><?php else: ?><span style="color: #999;">未指定</span><?php endif; ?><?php if ($r['isbn']): ?><br><small>ISBN：<?php echo htmlspecialchars($r['isbn']); ?></small><?php endif; ?></td>
                                <td><?php if ($r['course_code']): ?><span class="course-tag"><?php echo htmlspecialchars($r['course_code']); ?></span><?php else: ?><span style="color: #999;">-</span><?php endif; ?></td>
                                <td><?php if ($r['max_price']): ?><span class="price">≤ <?php echo formatPrice($r['max_price']); ?></span><?php else: ?><span style="color: #666;">面议</span><?php endif; ?></td>
                                <td><span class="status-badge status-<?php echo $r['status']; ?>"><?php echo getStatusText($r['status']); ?></span></td>
                                <td><?php if ($r['description']): ?><div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars(mb_substr($r['description'], 0, 50)); ?><?php if (mb_strlen($r['description']) > 50): ?>...<?php endif; ?></div><?php else: ?><span style="color: #999;">无</span><?php endif; ?></td>
                                <td><?php echo formatDate($r['created_at']); ?></td>
                                <td>
                                    <a href="?match=<?php echo $r['request_id']; ?>" class="btn btn-info btn-small">匹配</a>
                                    <a href="?edit=<?php echo $r['request_id']; ?>" class="btn btn-warning btn-small">编辑</a>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('确定删除？')"><input type="hidden" name="action" value="delete"><input type="hidden" name="request_id" value="<?php echo $r['request_id']; ?>"><button type="submit" class="btn btn-danger btn-small">删除</button></form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <script>document.getElementById('book_id')?.addEventListener('change', function() { const o = this.options[this.selectedIndex]; if (o.value) { document.getElementById('title').value = o.getAttribute('data-title') || ''; document.getElementById('isbn').value = o.getAttribute('data-isbn') || ''; document.getElementById('course_code').value = o.getAttribute('data-course') || ''; } });</script>
</body>
</html>
