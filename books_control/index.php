<?php
require_once 'config.php';
require_once 'auth_check.php';

// 检查管理员登录状态
checkAdminAuth();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>校园二手教材循环交易平台</title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            background: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            position: relative;
            border: 1px solid #e0e0e0;
        }
        
        .admin-info {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #1a1a1a;
            color: white;
            padding: 10px 15px;
            border-radius: 4px;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .admin-info svg {
            width: 18px;
            height: 18px;
            fill: currentColor;
        }
        
        .logout-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8em;
            transition: background 0.3s;
            text-decoration: none;
        }
        
        .logout-btn:hover {
            background: #c82333;
        }
        
        .header h1 {
            color: #1a1a1a;
            font-size: 2.2em;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        
        .header h1 svg {
            width: 36px;
            height: 36px;
            fill: #1a1a1a;
        }
        
        .header .subtitle {
            color: #666;
            font-size: 1.1em;
            margin-bottom: 20px;
        }
        
        .features {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        
        .feature-tag {
            background: #1a1a1a;
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 0.85em;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .feature-tag svg {
            width: 14px;
            height: 14px;
            fill: currentColor;
        }
        
        .nav-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .nav-card {
            background: #ffffff;
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid #e0e0e0;
        }
        
        .nav-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
            border-color: #1a1a1a;
        }
        
        .nav-card h3 {
            color: #1a1a1a;
            margin-bottom: 12px;
            font-size: 1.3em;
        }
        
        .nav-card p {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.6;
            font-size: 0.95em;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 28px;
            background: #1a1a1a;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: all 0.3s ease;
            font-weight: 500;
            border: none;
            cursor: pointer;
        }
        
        .btn:hover {
            background: #333;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-warning {
            background: #fd7e14;
        }
        
        .btn-warning:hover {
            background: #e96b02;
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .icon {
            width: 48px;
            height: 48px;
            margin: 0 auto 15px;
            fill: #1a1a1a;
        }
        
        .footer {
            text-align: center;
            margin-top: 50px;
            padding: 25px;
            background: #1a1a1a;
            border-radius: 8px;
            color: white;
        }
        
        .footer p {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .footer svg {
            width: 16px;
            height: 16px;
            fill: currentColor;
        }
        
        .footer .copyright {
            margin-top: 10px;
            font-size: 0.85em;
            opacity: 0.7;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="admin-info">
                <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                <span>管理员: <?php echo htmlspecialchars(getAdminUsername()); ?></span>
                <a href="?logout=1" class="logout-btn" onclick="return confirm('确定要退出登录吗？')">退出</a>
            </div>
            <h1>
                <svg viewBox="0 0 24 24"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/></svg>
                校园二手教材循环交易平台
            </h1>
            <p class="subtitle">绿色校园 · 智能匹配 · 诚信交易</p>
            <div class="features">
                <span class="feature-tag">
                    <svg viewBox="0 0 24 24"><path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/></svg>
                    精准匹配
                </span>
                <span class="feature-tag">
                    <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                    预约验书
                </span>
                <span class="feature-tag">
                    <svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                    信用评价
                </span>
                <span class="feature-tag">
                    <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/></svg>
                    求购发布
                </span>
                <span class="feature-tag">
                    <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                    绿色环保
                </span>
            </div>
        </div>
        
        <div class="nav-grid">
            <div class="nav-card">
                <svg class="icon" viewBox="0 0 24 24"><path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/></svg>
                <h3>教材管理</h3>
                <p>管理教材信息库，包括ISBN、课程关联、版本信息等，支持精准匹配功能</p>
                <a href="textbooks.php" class="btn">进入管理</a>
            </div>
            
            <div class="nav-card">
                <svg class="icon" viewBox="0 0 24 24"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/></svg>
                <h3>商品管理</h3>
                <p>发布和管理二手教材商品，设置价格、描述新旧程度，跟踪交易状态</p>
                <a href="products.php" class="btn btn-success">商品中心</a>
            </div>
            
            <div class="nav-card">
                <svg class="icon" viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                <h3>用户管理</h3>
                <p>管理用户信息、学生认证、信用评分系统，建立可信交易环境</p>
                <a href="users.php" class="btn btn-warning">用户中心</a>
            </div>
            
            <div class="nav-card">
                <svg class="icon" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                <h3>交易记录</h3>
                <p>查看所有交易记录、预约状态、交易流程管理，确保交易透明</p>
                <a href="transactions.php" class="btn btn-danger">交易记录</a>
            </div>
            
            <div class="nav-card">
                <svg class="icon" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                <h3>求购需求</h3>
                <p>发布和管理求购信息，智能匹配系统帮助买卖双方快速对接</p>
                <a href="requests.php" class="btn">求购中心</a>
            </div>
            
            <div class="nav-card">
                <svg class="icon" viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                <h3>评价系统</h3>
                <p>查看和管理用户评价，包括教材新旧程度、描述准确性等多维度评分</p>
                <a href="reviews.php" class="btn btn-success">评价管理</a>
            </div>
        </div>
        
        <div class="footer">
            <p>
                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                响应可持续发展理念 · 减少资源浪费 · 共建绿色校园
            </p>
            <p class="copyright">校园二手教材循环交易平台 © 2025</p>
        </div>
    </div>
</body>
</html>
