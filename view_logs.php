<?php
// 日志查看页面 - 显示API调用日志

// 检查是否已安装
if (!file_exists('installed.lock')) {
    header('Location: install.php');
    exit;
}

require_once 'config.php';

// 检查是否已登录
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 日志文件路径
$log_file = 'api_log.txt';

// 获取日志内容
$log_content = '';
if (file_exists($log_file)) {
    $log_content = file_get_contents($log_file);
}

// 清空日志功能
if (isset($_POST['clear_log'])) {
    if (file_exists($log_file)) {
        file_put_contents($log_file, '');
        header('Location: view_logs.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>查看日志 - <?= SITE_TITLE ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .header {
            background-color: #333;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .nav {
            background-color: #444;
            padding: 10px 20px;
        }
        .nav a {
            color: white;
            text-decoration: none;
            margin-right: 20px;
        }
        .nav a:hover {
            text-decoration: underline;
        }
        .content {
            padding: 20px;
        }
        .log-container {
            background-color: #f8f8f8;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            max-height: 500px;
            overflow-y: auto;
            font-family: monospace;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .no-log {
            color: #999;
            text-align: center;
            padding: 40px;
        }
        .action-bar {
            margin-bottom: 15px;
            text-align: right;
        }
        .btn {
            padding: 8px 15px;
            background-color: #f44336;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn:hover {
            background-color: #d32f2f;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>API调用日志</h1>
            <div class="logout"><a href="logout.php" style="color: white;">退出登录</a></div>
        </div>
        <div class="nav">
            <a href="admin.php">服务器管理</a>
            <a href="view_logs.php">查看日志</a>
            <a href="index.php">返回首页</a>
        </div>
        <div class="content">
            <div class="action-bar">
                <form method="post">
                    <button type="submit" name="clear_log" class="btn" onclick="return confirm('确定要清空所有日志吗？');">清空日志</button>
                </form>
            </div>
            <div class="log-container">
                <?php if (!empty($log_content)): ?>
                    <?= htmlspecialchars($log_content) ?>
                <?php else: ?>
                    <div class="no-log">暂无日志记录</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>