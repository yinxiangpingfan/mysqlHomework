<?php
session_start();
require_once 'config.php';

$error = '';

// 处理登录
if ($_POST && isset($_POST['username']) && isset($_POST['password'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? AND password = MD5(?)");
        $stmt->execute([$username, $password]);
        $admin = $stmt->fetch();
        
        if ($admin) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_id'] = $admin['admin_id'];
            header('Location: index.php');
            exit;
        } else {
            $error = '用户名或密码错误！';
        }
    } catch (PDOException $e) {
        $error = '登录失败：' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员登录 - 校园二手教材循环交易平台</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Microsoft YaHei', Arial, sans-serif; background: #f5f5f5; min-height: 100vh; display: flex; align-items: center; justify-content: center; color: #333; }
        .login-container { background: #ffffff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); max-width: 500px; width: 90%; text-align: center; border: 1px solid #e0e0e0; }
        .logo { margin-bottom: 30px; }
        .logo-icon { width: 48px; height: 48px; fill: #1a1a1a; margin-bottom: 15px; }
        .logo h1 { color: #1a1a1a; font-size: 1.8em; margin-bottom: 10px; }
        .logo .subtitle { color: #666; font-size: 1em; margin-bottom: 20px; }
        .features { background: #fafafa; padding: 20px; border-radius: 6px; margin-bottom: 30px; text-align: left; border: 1px solid #e8e8e8; }
        .features h3 { color: #1a1a1a; margin-bottom: 15px; text-align: center; font-size: 1.2em; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .features h3 svg { width: 20px; height: 20px; fill: #1a1a1a; }
        .feature-list { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 0.9em; }
        .feature-item { display: flex; align-items: center; color: #555; }
        .feature-item svg { width: 14px; height: 14px; fill: #28a745; margin-right: 8px; flex-shrink: 0; }
        .login-form { margin-top: 20px; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; margin-bottom: 5px; color: #1a1a1a; font-weight: 500; }
        .form-group input { width: 100%; padding: 12px 15px; border: 1px solid #d0d0d0; border-radius: 4px; font-size: 16px; transition: border-color 0.3s; }
        .form-group input:focus { outline: none; border-color: #1a1a1a; }
        .login-btn { width: 100%; padding: 14px; background: #1a1a1a; color: white; border: none; border-radius: 4px; font-size: 16px; font-weight: 500; cursor: pointer; transition: background 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .login-btn svg { width: 18px; height: 18px; fill: currentColor; }
        .login-btn:hover { background: #333; }
        .error { background: #dc3545; color: white; padding: 12px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; }
        .admin-note { margin-top: 25px; padding: 15px; background: #fafafa; border-left: 3px solid #1a1a1a; border-radius: 4px; font-size: 0.85em; color: #555; text-align: left; }
        .admin-note strong { color: #1a1a1a; display: flex; align-items: center; gap: 6px; margin-bottom: 5px; }
        .admin-note strong svg { width: 16px; height: 16px; fill: #1a1a1a; }
        @media (max-width: 600px) { .feature-list { grid-template-columns: 1fr; } .login-container { padding: 30px 20px; } }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <svg class="logo-icon" viewBox="0 0 24 24"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/></svg>
            <h1>校园二手教材循环交易平台</h1>
            <p class="subtitle">管理员登录系统</p>
        </div>
        
        <div class="features">
            <h3><svg viewBox="0 0 24 24"><path d="M19 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/></svg>平台功能模块</h3>
            <div class="feature-list">
                <div class="feature-item"><svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>用户管理系统</div>
                <div class="feature-item"><svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>教材信息管理</div>
                <div class="feature-item"><svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>商品发布管理</div>
                <div class="feature-item"><svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>求购需求管理</div>
                <div class="feature-item"><svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>交易记录管理</div>
                <div class="feature-item"><svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>评价系统管理</div>
                <div class="feature-item"><svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>信用评分系统</div>
                <div class="feature-item"><svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>数据统计分析</div>
            </div>
        </div>
        
        <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        
        <form method="POST" class="login-form">
            <div class="form-group">
                <label for="username">管理员账号</label>
                <input type="text" id="username" name="username" required placeholder="请输入管理员账号">
            </div>
            <div class="form-group">
                <label for="password">管理员密码</label>
                <input type="password" id="password" name="password" required placeholder="请输入管理员密码">
            </div>
            <button type="submit" class="login-btn">
                <svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                登录管理系统
            </button>
        </form>
        
        <div class="admin-note">
            <strong><svg viewBox="0 0 24 24"><path d="M9 21c0 .55.45 1 1 1h4c.55 0 1-.45 1-1v-1H9v1zm3-19C8.14 2 5 5.14 5 9c0 2.38 1.19 4.47 3 5.74V17c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-2.26c1.81-1.27 3-3.36 3-5.74 0-3.86-3.14-7-7-7zm2.85 11.1l-.85.6V16h-4v-2.3l-.85-.6C7.8 12.16 7 10.63 7 9c0-2.76 2.24-5 5-5s5 2.24 5 5c0 1.63-.8 3.16-2.15 4.1z"/></svg>管理员提示</strong>
            默认账号：admin，密码：admin123。登录后可以管理平台的所有功能模块。
        </div>
    </div>
</body>
</html>
