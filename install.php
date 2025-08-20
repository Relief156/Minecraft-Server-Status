<?php
// 安装设置页面

// 如果已经安装，则重定向到首页
if (file_exists('installed.lock')) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取表单数据
    $db_host = $_POST['db_host'];
    $db_user = $_POST['db_user'];
    $db_pass = $_POST['db_pass'];
    $db_name = $_POST['db_name'];
    $admin_user = $_POST['admin_user'];
    $admin_pass = $_POST['admin_pass'];
    $admin_pass_confirm = $_POST['admin_pass_confirm'];
    $site_title = $_POST['site_title'];

    // 验证表单数据
    if (empty($db_host) || empty($db_user) || empty($db_name) || empty($admin_user) || empty($admin_pass)) {
        $error = '所有必填字段都必须填写';
    } elseif ($admin_pass !== $admin_pass_confirm) {
        $error = '两次输入的密码不一致';
    } else {
        // 尝试连接数据库
        $conn = new mysqli($db_host, $db_user, $db_pass);
        
        if ($conn->connect_error) {
            $error = '数据库连接失败: ' . $conn->connect_error;
        } else {
            // 创建数据库
            if (!$conn->query("CREATE DATABASE IF NOT EXISTS $db_name")) {
                $error = '创建数据库失败: ' . $conn->error;
            } else {
                // 选择数据库
                $conn->select_db($db_name);
                
                // 创建服务器表
                $sql = "CREATE TABLE IF NOT EXISTS servers (
                    id INT(11) AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    address VARCHAR(255) NOT NULL,
                    server_type ENUM('java', 'bedrock') DEFAULT 'java',
                    sort_weight INT NOT NULL DEFAULT 1000,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )";
                
                if (!$conn->query($sql)) {
                    $error = '创建servers表失败: ' . $conn->error;
                } else {
                    // 创建管理员表
                    $sql = "CREATE TABLE IF NOT EXISTS admins (
                        id INT(11) AUTO_INCREMENT PRIMARY KEY,
                        username VARCHAR(50) NOT NULL UNIQUE,
                        password VARCHAR(255) NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )";
                    
                    if (!$conn->query($sql)) {
                        $error = '创建admins表失败: ' . $conn->error;
                    } else {
                        // 插入管理员账户
                        $hashed_password = password_hash($admin_pass, PASSWORD_DEFAULT);
                        $sql = "INSERT INTO admins (username, password) VALUES ('$admin_user', '$hashed_password')";
                        
                        if (!$conn->query($sql)) {
                            $error = '创建管理员账户失败: ' . $conn->error;
                        } else {
                            // 创建玩家历史数据表
                            $sql = "CREATE TABLE IF NOT EXISTS player_history (
                                id INT(11) AUTO_INCREMENT PRIMARY KEY,
                                server_id INT(11) NOT NULL,
                                players_online INT NOT NULL,
                                record_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE
                            )";
                            
                            if (!$conn->query($sql)) {
                                $error = '创建player_history表失败: ' . $conn->error;
                            } else {
                                // 添加索引以提高查询性能
                                $conn->query("CREATE INDEX idx_server_id ON player_history(server_id)");
                                $conn->query("CREATE INDEX idx_record_time ON player_history(record_time)");
                                
                                // 创建存储过程用于定期清理旧数据
                                // 临时设置SQL模式以避免分隔符问题
                                $conn->query("SET sql_mode='NO_BACKSLASH_ESCAPES'");
                                $procedure_sql = "CREATE PROCEDURE IF NOT EXISTS cleanup_old_player_history()
                                BEGIN
                                    DELETE FROM player_history WHERE record_time < DATE_SUB(NOW(), INTERVAL 30 DAY);
                                END";
                                $conn->query($procedure_sql);
                                
                                // 更新配置文件
                                $config_content = "<?php\n";
                                $config_content .= "// 配置文件\n";
                                $config_content .= "// 数据库配置\n";
                                $config_content .= "define('DB_HOST', '$db_host');\n";
                                $config_content .= "define('DB_USER', '$db_user');\n";
                                $config_content .= "define('DB_PASS', '$db_pass');\n";
                                $config_content .= "define('DB_NAME', '$db_name');\n\n";
                                $config_content .= "// API配置\n";
                                $config_content .= "define('API_URL', 'https://api.mcsrvstat.us/3/');\n";
                                $config_content .= "// mcsrvstat.us API不需要API密钥\n";
                                $config_content .= "// define('API_KEY', 'your_api_key_here');\n\n";
                                $config_content .= "// 网站配置\n";
                                $config_content .= "define('SITE_TITLE', '$site_title');\n";
                                $config_content .= "?>\n";
                                
                                if (!file_put_contents('config.php', $config_content)) {
                                    $error = '更新配置文件失败，请确保文件有写入权限';
                                } else {
                                    // 创建已安装标记文件
                                    file_put_contents('installed.lock', 'Installed on ' . date('Y-m-d H:i:s'));
                                    
                                    $success = '安装成功！即将跳转到监控页面...';
                                    header('Refresh: 3; URL=index.php');
                                }
                            }
                        }
                    }
                }
            }
            
            // 关闭连接
            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minecraft服务器状态监控 - 安装设置</title>
    <style>
        /* 全局样式重置和基础设置 */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            background-image: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .container {
            max-width: 800px;
            width: 100%;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            padding: 40px;
            animation: fadeIn 0.8s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        h1 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
            font-size: 2.2em;
        }
        
        .subtitle {
            text-align: center;
            color: #7f8c8d;
            margin-bottom: 40px;
            font-size: 1.1em;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .form-section {
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .form-section h2 {
            margin-bottom: 20px;
            color: #34495e;
            font-size: 1.5em;
        }
        
        .btn-submit {
            width: 100%;
            padding: 15px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        
        .btn-submit:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }
        
        .btn-submit:active {
            transform: translateY(0);
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            text-align: center;
            font-size: 1.1em;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Minecraft服务器状态监控</h1>
        <p class="subtitle">首次安装设置</p>
        
        <?php if ($error): ?>
            <div class="error-message">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message">
                <?php echo $success; ?>
            </div>
        <?php else: ?>
            <form method="POST">
                <!-- 数据库配置部分 -->
                <div class="form-section">
                    <h2>数据库配置</h2>
                    
                    <div class="form-group">
                        <label for="db_host">数据库主机 *</label>
                        <input type="text" id="db_host" name="db_host" value="localhost" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_user">数据库用户名 *</label>
                        <input type="text" id="db_user" name="db_user" value="root" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_pass">数据库密码</label>
                        <input type="password" id="db_pass" name="db_pass" value="">
                    </div>
                    
                    <div class="form-group">
                        <label for="db_name">数据库名称 *</label>
                        <input type="text" id="db_name" name="db_name" value="mc_server_status" required>
                    </div>
                </div>
                
                <!-- 管理员账户配置部分 -->
                <div class="form-section">
                    <h2>管理员账户配置</h2>
                    
                    <div class="form-group">
                        <label for="admin_user">管理员用户名 *</label>
                        <input type="text" id="admin_user" name="admin_user" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="admin_pass">管理员密码 *</label>
                            <input type="password" id="admin_pass" name="admin_pass" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="admin_pass_confirm">确认管理员密码 *</label>
                            <input type="password" id="admin_pass_confirm" name="admin_pass_confirm" required>
                        </div>
                    </div>
                </div>
                
                <!-- 网站配置部分 -->
                <div class="form-section">
                    <h2>网站配置</h2>
                    
                    <div class="form-group">
                        <label for="site_title">网站标题</label>
                        <input type="text" id="site_title" name="site_title" value="Minecraft服务器状态监控">
                    </div>
                </div>
                
                <button type="submit" class="btn-submit">完成安装</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>