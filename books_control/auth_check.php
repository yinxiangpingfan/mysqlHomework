<?php
// 认证检查文件
session_start();

// 检查是否已登录
function checkAdminAuth() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: login.php');
        exit;
    }
}

// 登出功能
function logout() {
    session_destroy();
    header('Location: login.php');
    exit;
}

// 处理登出请求
if (isset($_GET['logout'])) {
    logout();
}

// 获取管理员用户名
function getAdminUsername() {
    return isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : '';
}
?>