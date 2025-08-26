<?php
// 管理员页面

// 检查是否已安装
if (!file_exists('installed.lock')) {
    header('Location: install.php');
    exit;
}

require_once 'config.php';
require_once 'db.php';

session_start();

// 检查管理员是否已登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 连接数据库
$db = new Database();

// 处理删除服务器请求
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    if ($db->deleteServer($id)) {
        $success = '服务器已成功删除';
    } else {
        $error = '删除服务器失败';
    }
}

// 处理添加服务器请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_server'])) {
    $name = $_POST['name'];
    $address = $_POST['address'];
    $server_type = isset($_POST['server_type']) ? $_POST['server_type'] : 'java';
    $sort_weight = isset($_POST['sort_weight']) ? intval($_POST['sort_weight']) : 1000;
    $show_player_history = isset($_POST['show_player_history']) ? 1 : 0;
    $show_ip = isset($_POST['show_ip']) ? 1 : 0;
    $ip_description = isset($_POST['ip_description']) ? $_POST['ip_description'] : '';

    if (empty($name) || empty($address)) {
        $error = '请输入服务器名称和地址';
    } else {
        if ($db->addServer($name, $address, $server_type, $sort_weight)) {
            // 获取刚刚添加的服务器ID
            $server_id = $db->getConnection()->insert_id;
            // 设置显示历史在线人数的选项
            $db->setShowPlayerHistory($server_id, $show_player_history);
            // 设置显示IP的选项
            $db->setShowIp($server_id, $show_ip);
            // 设置IP替代描述
            $db->setIpDescription($server_id, $ip_description);
            $success = '服务器已成功添加';
        } else {
            $error = '添加服务器失败';
        }
    }
}

// 处理更新服务器请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_server'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $address = $_POST['address'];
    $server_type = isset($_POST['server_type']) ? $_POST['server_type'] : 'java';
    $sort_weight = isset($_POST['sort_weight']) ? intval($_POST['sort_weight']) : null;
    $show_player_history = isset($_POST['show_player_history']) ? 1 : 0;
    $show_ip = isset($_POST['show_ip']) ? 1 : 0;
    $ip_description = isset($_POST['ip_description']) ? $_POST['ip_description'] : '';

    if (empty($name) || empty($address)) {
        $error = '请输入服务器名称和地址';
    } else {
        if ($db->updateServer($id, $name, $address, $server_type, $sort_weight)) {
            // 设置显示历史在线人数的选项
            $db->setShowPlayerHistory($id, $show_player_history);
            // 设置显示IP的选项
            $db->setShowIp($id, $show_ip);
            // 设置IP替代描述
            $db->setIpDescription($id, $ip_description);
            $success = '服务器已成功更新';
        } else {
            $error = '更新服务器失败';
        }
    }
}

// 获取排序参数，默认按排序权重降序
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'sort_weight';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'DESC';

// 获取所有服务器（带排序）
$servers = $db->getAllServers($sort_by, $sort_order);

// 获取要编辑的服务器
$edit_server = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_server = $db->getServerById($_GET['id']);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_TITLE ?> - 管理员后台</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h1 {
            text-align: center;
            margin-bottom: 20px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .logout-btn {
            background-color: #f44336;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
        }
        .logout-btn:hover {
            background-color: #d32f2f;
        }
        .success {
            color: green;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #e8f5e9;
            border-radius: 4px;
        }
        .error {
            color: red;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #ffebee;
            border-radius: 4px;
        }
        .form-container {
            margin-bottom: 30px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 4px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        /* 美化服务器类型选择下拉菜单 */
        .form-group select {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 16px;
            background-color: white;
            background-image: url('data:image/svg+xml;charset=utf-8,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="%234CAF50"%3E%3Cpath fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/%3E%3C/svg%3E');
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 20px;
            appearance: none;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        
        .form-group select:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }
        
        /* 美化选项样式 */
        .form-group select option {
            padding: 10px;
            background-color: white;
            color: #333;
        }
        
        .form-group select option:hover {
            background-color: #f9f9f9;
        }
        .btn {
            padding: 10px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn:hover {
            background-color: #45a049;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
        .action-btn {
            padding: 5px 10px;
            margin-right: 5px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
        }
        .edit-btn {
            background-color: #2196F3;
            color: white;
        }
        .edit-btn:hover {
            background-color: #0b7dda;
        }
        .delete-btn {
            background-color: #f44336;
            color: white;
        }
        .delete-btn:hover {
            background-color: #d32f2f;
        }
        
        /* 滑动开关样式 */
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
        }
        
        input:checked + .slider {
            background-color: #4CAF50;
        }
        
        input:focus + .slider {
            box-shadow: 0 0 1px #4CAF50;
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        /* 圆角开关 */
        .slider.round {
            border-radius: 34px;
        }
        
        .slider.round:before {
            border-radius: 50%;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>服务器管理</h1>
            <div style="display: flex; gap: 10px;">
                <a href="index.php" class="logout-btn">回到主页</a>
                  <a href="view_logs.php" class="logout-btn">查看日志</a>
                  <a href="api_management.php" class="logout-btn">API管理</a>
                  <!-- 获取API最新信息功能已整合到API管理页面 -->
                <a href="logout.php" class="logout-btn">退出登录</a>
            </div>
        </div>

        <?php if (isset($success)): ?>
            <div class="success"><?= $success ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <div class="form-container">
            <h2><?= $edit_server ? '编辑服务器' : '添加服务器' ?></h2>
            <form method="post" action="admin.php">
                <?php if ($edit_server): ?>
                    <input type="hidden" name="id" value="<?= $edit_server['id'] ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label for="name">服务器名称</label>
                    <input type="text" id="name" name="name" value="<?= $edit_server ? $edit_server['name'] : '' ?>" required>
                </div>
                <div class="form-group">
                    <label for="address">服务器地址 (域名或IP:端口)</label>
                    <input type="text" id="address" name="address" value="<?= $edit_server ? $edit_server['address'] : '' ?>" required>
                </div>
                <div class="form-group">
                    <label for="server_type">服务器类型</label>
                    <select id="server_type" name="server_type" required>
                        <option value="java" <?= $edit_server && $edit_server['server_type'] === 'java' ? 'selected' : '' ?>>Java版</option>
                        <option value="bedrock" <?= $edit_server && $edit_server['server_type'] === 'bedrock' ? 'selected' : '' ?>>基岩版</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="sort_weight">排序权重（数字越大，越靠前）</label>
                    <input type="number" id="sort_weight" name="sort_weight" min="0" max="9999" value="<?= $edit_server ? $edit_server['sort_weight'] : 1000 ?>" required>
                    <small style="color: #666;">默认值：1000</small>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 10px;">
                        显示历史在线人数图表
                        <label class="switch">
                            <input type="checkbox" id="show_player_history" name="show_player_history" value="1"<?= $edit_server && isset($edit_server['show_player_history']) && $edit_server['show_player_history'] ? ' checked' : (empty($edit_server) ? ' checked' : '') ?>>
                            <span class="slider round"></span>
                        </label>
                    </label>
                    <small style="color: #666;">开启表示在服务器状态页面可以查看历史在线人数图表，关闭则默认不显示但仍记录数据</small>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 10px;">
                        显示服务器IP地址
                        <label class="switch">
                            <input type="checkbox" id="show_ip" name="show_ip" value="1"<?= $edit_server && isset($edit_server['show_ip']) && $edit_server['show_ip'] ? ' checked' : (empty($edit_server) ? ' checked' : '') ?>>
                            <span class="slider round"></span>
                        </label>
                    </label>
                    <small style="color: #666;">开启表示在服务器状态页面显示IP地址，关闭则隐藏IP地址</small>
                </div>
                <div class="form-group">
                    <label for="ip_description">IP替代描述文本 (不显示IP时使用)</label>
                    <input type="text" id="ip_description" name="ip_description" value="<?= $edit_server ? htmlspecialchars($edit_server['ip_description']) : '' ?>">
                    <small style="color: #666;">当关闭显示IP时，将显示此文本代替IP地址，例如："加群xxxxx以获取ip"</small>
                </div>
                <button type="submit" class="btn" name="<?= $edit_server ? 'update_server' : 'add_server' ?>">
                    <?= $edit_server ? '更新服务器' : '添加服务器' ?>
                </button>
                <?php if ($edit_server): ?>
                    <a href="admin.php" class="btn" style="background-color: #ccc; margin-left: 10px;">取消编辑</a>
                <?php endif; ?>
            </form>
        </div>

        <h2>服务器列表</h2>
        <table>
            <thead>
                <tr>
                    <th><a href="admin.php?sort_by=id&sort_order=<?= ($sort_by === 'id' && $sort_order === 'ASC') ? 'DESC' : 'ASC' ?>">ID</a></th>
                    <th><a href="admin.php?sort_by=name&sort_order=<?= ($sort_by === 'name' && $sort_order === 'ASC') ? 'DESC' : 'ASC' ?>">名称</a></th>
                    <th>地址</th>
                    <th><a href="admin.php?sort_by=server_type&sort_order=<?= ($sort_by === 'server_type' && $sort_order === 'ASC') ? 'DESC' : 'ASC' ?>">类型</a></th>
                    <th><a href="admin.php?sort_by=sort_weight&sort_order=<?= ($sort_by === 'sort_weight' && $sort_order === 'ASC') ? 'DESC' : 'ASC' ?>">排序权重</a></th>
                    <th><a href="admin.php?sort_by=created_at&sort_order=<?= ($sort_by === 'created_at' && $sort_order === 'ASC') ? 'DESC' : 'ASC' ?>">创建时间</a></th>
                    <th><a href="admin.php?sort_by=updated_at&sort_order=<?= ($sort_by === 'updated_at' && $sort_order === 'ASC') ? 'DESC' : 'ASC' ?>">更新时间</a></th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($servers->num_rows > 0): ?>
                    <?php while ($server = $servers->fetch_assoc()): ?>
                        <tr>
                            <td><?= $server['id'] ?></td>
                            <td><?= $server['name'] ?></td>
                            <td><?= $server['address'] ?></td>
                            <td><?= $server['server_type'] === 'java' ? 'Java版' : '基岩版' ?></td>
                            <td><?= $server['sort_weight'] ?></td>
                            <td><?= $server['created_at'] ?></td>
                            <td><?= $server['updated_at'] ?></td>
                            <td>
                                <a href="admin.php?action=edit&id=<?= $server['id'] ?>", class="action-btn edit-btn">编辑</a>
                                <a href="admin.php?action=delete&id=<?= $server['id'] ?>" onclick="return confirm('确定要删除这个服务器吗？')" class="action-btn delete-btn">删除</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">暂无服务器，请添加服务器</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>