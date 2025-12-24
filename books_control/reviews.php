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
                $stmt = $pdo->prepare("INSERT INTO reviews (transaction_id, reviewer_id, reviewed_id, book_condition_score, description_accuracy_score, overall_score, comment) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([intval($_POST['transaction_id']), intval($_POST['reviewer_id']), intval($_POST['reviewed_id']), intval($_POST['condition_rating']), intval($_POST['description_accuracy']), floatval($_POST['overall_rating']), sanitizeInput($_POST['comment'])]);
                updateUserCreditScore($pdo, intval($_POST['reviewed_id']));
                $message = '评价提交成功！';
            } catch (PDOException $e) { $error = '提交失败：' . $e->getMessage(); }
            break;
        case 'update':
            try {
                $stmt = $pdo->prepare("UPDATE reviews SET book_condition_score=?, description_accuracy_score=?, overall_score=?, comment=? WHERE review_id=?");
                $stmt->execute([intval($_POST['condition_rating']), intval($_POST['description_accuracy']), floatval($_POST['overall_rating']), sanitizeInput($_POST['comment']), intval($_POST['review_id'])]);
                $r = $pdo->prepare("SELECT reviewed_id FROM reviews WHERE review_id = ?"); $r->execute([intval($_POST['review_id'])]); $rd = $r->fetch();
                if ($rd) updateUserCreditScore($pdo, $rd['reviewed_id']);
                $message = '评价更新成功！';
            } catch (PDOException $e) { $error = '更新失败：' . $e->getMessage(); }
            break;
        case 'delete':
            try {
                $r = $pdo->prepare("SELECT reviewed_id FROM reviews WHERE review_id = ?"); $r->execute([intval($_POST['review_id'])]); $rd = $r->fetch();
                $stmt = $pdo->prepare("DELETE FROM reviews WHERE review_id = ?"); $stmt->execute([intval($_POST['review_id'])]);
                if ($rd) updateUserCreditScore($pdo, $rd['reviewed_id']);
                $message = '评价删除成功！';
            } catch (PDOException $e) { $error = '删除失败：' . $e->getMessage(); }
            break;
    }
}

$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$rating_filter = isset($_GET['rating_filter']) ? intval($_GET['rating_filter']) : 0;
$user_filter = isset($_GET['user_filter']) ? intval($_GET['user_filter']) : 0;
$date_from = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';

$where_conditions = []; $params = [];
if ($search) { $where_conditions[] = "(r.comment LIKE ? OR t.title LIKE ? OR u1.username LIKE ? OR u2.username LIKE ?)"; $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]); }
if ($rating_filter > 0) { $where_conditions[] = "r.overall_score >= ?"; $params[] = $rating_filter; }
if ($user_filter > 0) { $where_conditions[] = "(r.reviewer_id = ? OR r.reviewed_id = ?)"; $params[] = $user_filter; $params[] = $user_filter; }
if ($date_from) { $where_conditions[] = "DATE(r.created_at) >= ?"; $params[] = $date_from; }
if ($date_to) { $where_conditions[] = "DATE(r.created_at) <= ?"; $params[] = $date_to; }
$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$stmt = $pdo->prepare("SELECT r.*, u1.username as reviewer_name, u1.student_id as reviewer_student_id, u2.username as reviewed_user_name, u2.student_id as reviewed_user_student_id, t.title as book_title, t.author as book_author, p.selling_price, p.condition_score, tr.status as transaction_status FROM reviews r LEFT JOIN users u1 ON r.reviewer_id = u1.user_id LEFT JOIN users u2 ON r.reviewed_id = u2.user_id LEFT JOIN transactions tr ON r.transaction_id = tr.transaction_id LEFT JOIN products p ON tr.product_id = p.product_id LEFT JOIN textbooks t ON p.book_id = t.book_id $where_clause ORDER BY r.created_at DESC");
$stmt->execute($params);
$reviews = $stmt->fetchAll();

$users = $pdo->query("SELECT user_id, username, student_id FROM users ORDER BY username")->fetchAll();
$available_transactions = $pdo->query("SELECT tr.transaction_id, tr.buyer_id, tr.seller_id, u1.username as buyer_name, u2.username as seller_name, t.title as book_title, p.product_id FROM transactions tr LEFT JOIN users u1 ON tr.buyer_id = u1.user_id LEFT JOIN users u2 ON tr.seller_id = u2.user_id LEFT JOIN products p ON tr.product_id = p.product_id LEFT JOIN textbooks t ON p.book_id = t.book_id WHERE tr.status = 'completed' AND tr.transaction_id NOT IN (SELECT transaction_id FROM reviews WHERE transaction_id IS NOT NULL) ORDER BY tr.created_at DESC")->fetchAll();

$edit_review = null;
if (isset($_GET['edit'])) { $stmt = $pdo->prepare("SELECT r.*, tr.buyer_id, tr.seller_id FROM reviews r LEFT JOIN transactions tr ON r.transaction_id = tr.transaction_id WHERE r.review_id = ?"); $stmt->execute([intval($_GET['edit'])]); $edit_review = $stmt->fetch(); }

function updateUserCreditScore($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT AVG(overall_score) as avg, COUNT(*) as cnt FROM reviews WHERE reviewed_id = ?"); $stmt->execute([$user_id]); $r = $stmt->fetch();
    $base = $r['avg'] ?: 5.0; $bonus = min($r['cnt'] * 0.1, 2.0); $score = min(10.0, max(1.0, $base + $bonus));
    $pdo->prepare("UPDATE users SET credit_score = ? WHERE user_id = ?")->execute([$score, $user_id]);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>评价管理 - 校园二手教材平台</title>
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
        .form-section, .search-section, .table-section { background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border: 1px solid #e0e0e0; }
        .form-section h3, .search-section h3, .table-section h3 { color: #1a1a1a; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
        .form-section h3 svg, .search-section h3 svg, .table-section h3 svg { width: 20px; height: 20px; fill: #1a1a1a; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 5px; font-weight: 500; color: #1a1a1a; }
        .form-group input, .form-group select, .form-group textarea { padding: 10px; border: 1px solid #d0d0d0; border-radius: 4px; font-size: 14px; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #1a1a1a; }
        .form-group textarea { resize: vertical; min-height: 100px; }
        .rating-group { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; padding: 20px; background: #fafafa; border-radius: 8px; }
        .rating-item { text-align: center; }
        .rating-item label { display: block; margin-bottom: 10px; font-weight: 500; color: #1a1a1a; }
        .star-rating { display: flex; justify-content: center; gap: 5px; margin-bottom: 10px; }
        .star { font-size: 24px; color: #ddd; cursor: pointer; transition: color 0.2s; }
        .star.active, .star:hover { color: #1a1a1a; }
        .rating-value { font-size: 14px; color: #666; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500; text-decoration: none; display: inline-block; text-align: center; margin: 5px; transition: all 0.3s; }
        .btn-primary { background: #1a1a1a; color: white; }
        .btn-primary:hover { background: #333; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-warning { background: #fd7e14; color: white; }
        .btn-warning:hover { background: #e96b02; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-small { padding: 5px 10px; font-size: 12px; }
        .search-form { display: flex; gap: 15px; align-items: end; flex-wrap: wrap; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e0e0e0; }
        th { background: #fafafa; font-weight: 500; color: #1a1a1a; }
        tr:hover { background: #fafafa; }
        .message { padding: 15px; border-radius: 4px; margin-bottom: 20px; font-weight: 500; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .rating-display { display: flex; align-items: center; gap: 5px; }
        .rating-stars { color: #1a1a1a; }
        .rating-number { font-weight: bold; color: #1a1a1a; }
        .review-comment { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .review-comment:hover { white-space: normal; overflow: visible; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: #fff; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border: 1px solid #e0e0e0; }
        .stat-number { font-size: 2em; font-weight: bold; color: #1a1a1a; }
        .stat-label { color: #666; margin-top: 5px; }
        .help-text { font-size: 12px; color: #666; margin-top: 5px; }
        .transaction-info { background: #fafafa; padding: 10px; border-radius: 5px; margin-bottom: 15px; border-left: 4px solid #1a1a1a; }
        .table-section { overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>评价管理</h1>
            <p><a href="index.php" class="nav-link"><svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>返回首页</a></p>
        </div>
        
        <?php if ($message): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>
        
        <div class="stats-grid">
            <?php $total = count($reviews); $avg = $reviews ? array_sum(array_column($reviews, 'overall_score')) / count($reviews) : 0; $high = count(array_filter($reviews, fn($r) => $r['overall_score'] >= 4)); $recent = count(array_filter($reviews, fn($r) => strtotime($r['created_at']) > strtotime('-7 days'))); ?>
            <div class="stat-card"><div class="stat-number"><?php echo $total; ?></div><div class="stat-label">总评价数</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo number_format($avg, 1); ?></div><div class="stat-label">平均评分</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo $high; ?></div><div class="stat-label">好评数(≥4)</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo $recent; ?></div><div class="stat-label">近7天评价</div></div>
        </div>
        
        <div class="form-section">
            <h3><?php echo $edit_review ? '编辑评价' : '添加新评价'; ?></h3>
            <?php if (!$edit_review && empty($available_transactions)): ?>
                <div class="help-text" style="padding: 20px; text-align: center;">暂无可评价的交易。只有已完成的交易才能进行评价。</div>
            <?php else: ?>
                <form method="POST" id="reviewForm">
                    <input type="hidden" name="action" value="<?php echo $edit_review ? 'update' : 'add'; ?>">
                    <?php if ($edit_review): ?>
                        <input type="hidden" name="review_id" value="<?php echo $edit_review['review_id']; ?>">
                        <input type="hidden" name="transaction_id" value="<?php echo $edit_review['transaction_id']; ?>">
                        <input type="hidden" name="reviewer_id" value="<?php echo $edit_review['reviewer_id']; ?>">
                        <input type="hidden" name="reviewed_id" value="<?php echo $edit_review['reviewed_id']; ?>">
                    <?php else: ?>
                        <div class="form-group">
                            <label for="transaction_id">选择交易 *</label>
                            <select id="transaction_id" name="transaction_id" required onchange="updateTransactionInfo()">
                                <option value="">请选择</option>
                                <?php foreach ($available_transactions as $t): ?>
                                    <option value="<?php echo $t['transaction_id']; ?>" data-buyer-id="<?php echo $t['buyer_id']; ?>" data-seller-id="<?php echo $t['seller_id']; ?>" data-buyer-name="<?php echo htmlspecialchars($t['buyer_name']); ?>" data-seller-name="<?php echo htmlspecialchars($t['seller_name']); ?>" data-book-title="<?php echo htmlspecialchars($t['book_title']); ?>">交易#<?php echo $t['transaction_id']; ?> - <?php echo htmlspecialchars($t['book_title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="transaction-info" class="transaction-info" style="display: none;"><h4>交易信息</h4><p><strong>教材：</strong><span id="book-title"></span></p><p><strong>买家：</strong><span id="buyer-name"></span></p><p><strong>卖家：</strong><span id="seller-name"></span></p></div>
                        <div class="form-grid"><div class="form-group"><label for="reviewer_role">评价者身份 *</label><select id="reviewer_role" name="reviewer_role" required><option value="">请选择</option><option value="buyer">作为买家评价</option><option value="seller">作为卖家评价</option></select></div></div>
                        <input type="hidden" id="actual_reviewer_id" name="reviewer_id">
                        <input type="hidden" id="reviewed_id" name="reviewed_id">
                    <?php endif; ?>
                    
                    <div class="rating-group">
                        <div class="rating-item"><label>教材新旧程度</label><div class="star-rating" data-rating="condition_rating"><?php for($i=1;$i<=5;$i++): ?><span class="star" data-value="<?php echo $i; ?>">★</span><?php endfor; ?></div><div class="rating-value">1-5分</div><input type="hidden" name="condition_rating" value="<?php echo $edit_review ? $edit_review['book_condition_score'] : '5'; ?>"></div>
                        <div class="rating-item"><label>描述准确性</label><div class="star-rating" data-rating="description_accuracy"><?php for($i=1;$i<=5;$i++): ?><span class="star" data-value="<?php echo $i; ?>">★</span><?php endfor; ?></div><div class="rating-value">1-5分</div><input type="hidden" name="description_accuracy" value="<?php echo $edit_review ? $edit_review['description_accuracy_score'] : '5'; ?>"></div>
                        <div class="rating-item"><label>综合评分</label><div class="star-rating" data-rating="overall_rating"><?php for($i=1;$i<=5;$i++): ?><span class="star" data-value="<?php echo $i; ?>">★</span><?php endfor; ?></div><div class="rating-value">1-5分</div><input type="hidden" name="overall_rating" value="<?php echo $edit_review ? $edit_review['overall_score'] : '5'; ?>"></div>
                    </div>
                    <div class="form-group"><label for="comment">评价内容</label><textarea id="comment" name="comment"><?php echo $edit_review ? htmlspecialchars($edit_review['comment']) : ''; ?></textarea></div>
                    <button type="submit" class="btn btn-success"><?php echo $edit_review ? '更新评价' : '提交评价'; ?></button>
                    <?php if ($edit_review): ?><a href="reviews.php" class="btn btn-primary">取消编辑</a><?php endif; ?>
                </form>
            <?php endif; ?>
        </div>
        
        <div class="search-section">
            <h3><svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>搜索评价</h3>
            <form method="GET" class="search-form">
                <div class="form-group"><label for="search">关键词</label><input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>"></div>
                <div class="form-group"><label for="rating_filter">最低评分</label><select id="rating_filter" name="rating_filter"><option value="0">全部</option><?php for($i=1;$i<=5;$i++): ?><option value="<?php echo $i; ?>" <?php echo $rating_filter === $i ? 'selected' : ''; ?>><?php echo $i; ?>星及以上</option><?php endfor; ?></select></div>
                <div class="form-group"><label for="user_filter">用户</label><select id="user_filter" name="user_filter"><option value="0">全部</option><?php foreach ($users as $u): ?><option value="<?php echo $u['user_id']; ?>" <?php echo $user_filter === $u['user_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['username']); ?></option><?php endforeach; ?></select></div>
                <button type="submit" class="btn btn-primary">搜索</button>
                <a href="reviews.php" class="btn btn-warning">重置</a>
            </form>
        </div>
        
        <div class="table-section">
            <h3><svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>评价列表 (共 <?php echo count($reviews); ?> 条)</h3>
            <?php if (empty($reviews)): ?>
                <p style="text-align: center; color: #666; margin: 40px 0;">暂无评价记录</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>ID</th><th>交易信息</th><th>评价者</th><th>被评价者</th><th>评分</th><th>综合</th><th>内容</th><th>时间</th><th>操作</th></tr></thead>
                    <tbody>
                        <?php foreach ($reviews as $r): ?>
                            <tr>
                                <td><strong>#<?php echo $r['review_id']; ?></strong></td>
                                <td><div><strong>交易#<?php echo $r['transaction_id']; ?></strong></div><div><?php echo htmlspecialchars($r['book_title']); ?></div><div><small>¥<?php echo number_format($r['selling_price'], 2); ?></small></div></td>
                                <td><div><strong><?php echo htmlspecialchars($r['reviewer_name']); ?></strong></div><div><small><?php echo htmlspecialchars($r['reviewer_student_id']); ?></small></div></td>
                                <td><div><strong><?php echo htmlspecialchars($r['reviewed_user_name']); ?></strong></div><div><small><?php echo htmlspecialchars($r['reviewed_user_student_id']); ?></small></div></td>
                                <td><div><small>新旧：<?php echo $r['book_condition_score']; ?>分</small></div><div><small>描述：<?php echo $r['description_accuracy_score']; ?>分</small></div></td>
                                <td><div class="rating-display"><span class="rating-stars"><?php for($i=1;$i<=5;$i++) echo $i <= $r['overall_score'] ? '★' : '☆'; ?></span><span class="rating-number"><?php echo number_format($r['overall_score'], 1); ?></span></div></td>
                                <td><div class="review-comment" title="<?php echo htmlspecialchars($r['comment']); ?>"><?php echo htmlspecialchars($r['comment']); ?></div></td>
                                <td><?php echo formatDate($r['created_at']); ?></td>
                                <td>
                                    <a href="?edit=<?php echo $r['review_id']; ?>" class="btn btn-warning btn-small">编辑</a>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('确定删除？')"><input type="hidden" name="action" value="delete"><input type="hidden" name="review_id" value="<?php echo $r['review_id']; ?>"><button type="submit" class="btn btn-danger btn-small">删除</button></form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <script>
        document.querySelectorAll('.star-rating').forEach(function(rg) {
            const stars = rg.querySelectorAll('.star');
            const name = rg.getAttribute('data-rating');
            const input = document.querySelector(`input[name="${name}"]`);
            function update(v) { stars.forEach((s, i) => s.classList.toggle('active', i < v)); }
            update(input.value);
            stars.forEach(s => { s.onclick = () => { input.value = s.getAttribute('data-value'); update(input.value); }; s.onmouseover = () => update(s.getAttribute('data-value')); });
            rg.onmouseleave = () => update(input.value);
        });
        function updateTransactionInfo() {
            const s = document.getElementById('transaction_id'), o = s.options[s.selectedIndex], d = document.getElementById('transaction-info');
            if (o.value) { document.getElementById('book-title').textContent = o.getAttribute('data-book-title'); document.getElementById('buyer-name').textContent = o.getAttribute('data-buyer-name'); document.getElementById('seller-name').textContent = o.getAttribute('data-seller-name'); d.style.display = 'block'; } else { d.style.display = 'none'; }
        }
        document.getElementById('reviewer_role')?.addEventListener('change', function() {
            const t = document.getElementById('transaction_id'), o = t.options[t.selectedIndex];
            if (o.value && this.value) { document.getElementById('actual_reviewer_id').value = o.getAttribute(this.value === 'buyer' ? 'data-buyer-id' : 'data-seller-id'); document.getElementById('reviewed_id').value = o.getAttribute(this.value === 'buyer' ? 'data-seller-id' : 'data-buyer-id'); }
        });
    </script>
</body>
</html>
