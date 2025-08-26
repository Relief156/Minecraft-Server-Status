<?php
// 默认启用错误报告
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 但对于AJAX请求，我们需要禁用错误显示，只在日志中记录
if (isset($_POST['test_database'])) {
    // 禁用错误显示，防止HTML错误信息破坏JSON响应
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}

// 安装设置页面

// 获取全局日志数组的辅助函数
function &get_global_logs() {
    static $logs = array();
    return $logs;
}

// 日志记录功能
function log_message($message, $type = 'info') {
    $logs = &get_global_logs();
    $timestamp = date('H:i:s');
    $logs[] = array(
        'time' => $timestamp,  // 统一使用time键名
        'message' => $message,
        'type' => $type
    );
    return $logs;  // 返回日志数组用于AJAX响应
}

// 获取所有日志
function get_logs() {
    $logs = &get_global_logs();
    return $logs;
}

// 清空日志
function clear_logs() {
    $logs = &get_global_logs();
    $logs = array();
    log_message('日志已清空', 'info');
}

// 数据库测试功能
if (isset($_POST['test_database'])) {
    // 确保在设置头之前没有任何输出
    ob_clean(); // 清除可能的输出缓冲区
    
    // 设置JSON内容类型
    header('Content-Type: application/json');
    
    // 确保正确处理中文字符
    header('Content-Type: application/json; charset=utf-8');
    
    // 初始化响应数组
    $response = ['success' => false, 'message' => '', 'logs' => []];
    
    // 获取表单数据
    $db_host = isset($_POST['db_host']) ? $_POST['db_host'] : '';
    $db_user = isset($_POST['db_user']) ? $_POST['db_user'] : '';
    $db_pass = isset($_POST['db_pass']) ? $_POST['db_pass'] : '';
    $db_name = isset($_POST['db_name']) ? $_POST['db_name'] : '';
    
    // 开始记录日志
    log_message('开始数据库测试...', 'info');
    
    // 尝试连接数据库
    // 设置连接超时（5秒）
    $conn = new mysqli();
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    
    log_message('尝试连接数据库: ' . $db_host . '，用户名: ' . $db_user, 'info');
    if (!$conn->real_connect($db_host, $db_user, $db_pass)) {
        $error = '数据库连接失败: ' . $conn->connect_error;
        $error_code = $conn->connect_errno;
        
        // 根据错误代码提供更详细的解决方案建议
        $suggestion = '';
        switch($error_code) {
            case 1045: // 访问被拒绝
                $suggestion = ' 请检查用户名和密码是否正确，以及该用户是否有足够的权限。';
                break;
            case 1049: // 未知数据库
                $suggestion = ' 请检查数据库名称是否正确。';
                break;
            case 2002: // 无法连接到服务器
                $suggestion = ' 请检查主机名和端口是否正确，以及MySQL服务器是否正在运行。';
                break;
        }
        
        $error .= $suggestion;
        log_message('数据库连接失败 (错误码: ' . $error_code . '): ' . $conn->connect_error . $suggestion, 'error');
        $response['success'] = false;
        $response['message'] = $error;
        
        // 关闭连接
        $conn->close();
        log_message('数据库连接已关闭', 'info');
    } else {
        log_message('数据库连接成功', 'success');
        
        // 创建数据库
        if (!$conn->query("CREATE DATABASE IF NOT EXISTS $db_name")) {
            $error_msg = '创建数据库失败: ' . $conn->error;
            $suggestion = ' 请查询数据库名称是否正确/用户是否有创建数据库的权限';
            log_message($error_msg . $suggestion, 'error');
            $response['success'] = false;
            $response['message'] = $error_msg . $suggestion;
            
            // 关闭连接
            $conn->close();
            log_message('数据库连接已关闭', 'info');
            
            // 直接跳转到返回响应的代码
            goto end_response;
        } else {
            log_message('数据库 ' . $db_name . ' 创建/检查成功', 'success');
            
            // 选择数据库
            if (!$conn->select_db($db_name)) {
                log_message('选择数据库失败: ' . $conn->error, 'error');
                $response['success'] = false;
                $response['message'] = '选择数据库失败: ' . $conn->error;
                
                // 关闭连接
                $conn->close();
                log_message('数据库连接已关闭', 'info');
                
                // 直接跳转到返回响应的代码
                goto end_response;
            }
            log_message('已选择数据库: ' . $db_name, 'info');
            
            // 检查并创建服务器表
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
                log_message('创建servers表失败: ' . $conn->error, 'error');
                $response['success'] = false;
                $response['message'] = '创建servers表失败: ' . $conn->error;
                
                // 关闭连接
                $conn->close();
                log_message('数据库连接已关闭', 'info');
                
                // 直接跳转到返回响应的代码
                goto end_response;
            } else {
                log_message('servers表创建/检查成功', 'success');
                
                // 检查并创建管理员表
                $sql = "CREATE TABLE IF NOT EXISTS admins (
                    id INT(11) AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(50) NOT NULL UNIQUE,
                    password VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
                
                if (!$conn->query($sql)) {
                    log_message('创建admins表失败: ' . $conn->error, 'error');
                    $response['success'] = false;
                    $response['message'] = '创建admins表失败: ' . $conn->error;
                    
                    // 关闭连接
                    $conn->close();
                    log_message('数据库连接已关闭', 'info');
                } else {
                    log_message('admins表创建/检查成功', 'success');
                    
                    // 检查并创建玩家历史数据表
                    $sql = "CREATE TABLE IF NOT EXISTS player_history (
                        id INT(11) AUTO_INCREMENT PRIMARY KEY,
                        server_id INT(11) NOT NULL,
                        players_online INT NOT NULL,
                        player_list_json TEXT DEFAULT NULL,
                        record_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE
                    )";
                    
                    if (!$conn->query($sql)) {
                        log_message('创建player_history表失败: ' . $conn->error, 'error');
                        $response['success'] = false;
                        $response['message'] = '创建player_history表失败: ' . $conn->error;
                        
                        // 关闭连接
                        $conn->close();
                        log_message('数据库连接已关闭', 'info');
                    } else {
                        log_message('player_history表创建/检查成功', 'success');
                        
                        // 添加索引以提高查询性能（兼容所有MySQL版本）
                        // 检查并创建idx_server_id索引
                        $result = $conn->query("SHOW INDEX FROM player_history WHERE Key_name = 'idx_server_id'");
                        if ($result->num_rows == 0) {
                            if ($conn->query("CREATE INDEX idx_server_id ON player_history(server_id)")) {
                                log_message('添加idx_server_id索引成功', 'success');
                            } else {
                                log_message('添加idx_server_id索引失败: ' . $conn->error, 'error');
                            }
                        } else {
                            log_message('idx_server_id索引已存在，跳过创建', 'info');
                        }
                        
                        // 检查并创建idx_record_time索引
                        $result = $conn->query("SHOW INDEX FROM player_history WHERE Key_name = 'idx_record_time'");
                        if ($result->num_rows == 0) {
                            if ($conn->query("CREATE INDEX idx_record_time ON player_history(record_time)")) {
                                log_message('添加idx_record_time索引成功', 'success');
                            } else {
                                log_message('添加idx_record_time索引失败: ' . $conn->error, 'error');
                            }
                        } else {
                            log_message('idx_record_time索引已存在，跳过创建', 'info');
                        }
                        
                        // 创建存储过程用于定期清理旧数据
                        // 使用兼容更多MySQL版本的方式创建存储过程
                        // 先检查存储过程是否存在，如果存在则删除
                        $check_procedure = $conn->query("SHOW PROCEDURE STATUS WHERE name = 'cleanup_old_player_history'");
                        if ($check_procedure && $check_procedure->num_rows > 0) {
                            $conn->query("DROP PROCEDURE IF EXISTS cleanup_old_player_history");
                            log_message('已删除旧的cleanup_old_player_history存储过程', 'info');
                        }
                        
                        // 创建新的存储过程
                        // 在PHP中不需要设置DELIMITER，直接创建存储过程
                        $create_procedure_sql = "CREATE PROCEDURE cleanup_old_player_history()
BEGIN
    DELETE FROM player_history WHERE record_time < DATE_SUB(NOW(), INTERVAL 30 DAY);
END";
                        
                        if ($conn->query($create_procedure_sql)) {
                            log_message('创建cleanup_old_player_history存储过程成功', 'success');
                        } else {
                            log_message('创建cleanup_old_player_history存储过程失败: ' . $conn->error, 'error');
                        }
                        
                        $response['success'] = true;
                        $response['message'] = '数据库测试成功！所有必要的表已准备就绪。';
                        log_message('数据库测试完成，所有必要的表已准备就绪', 'success');
                    }
                }
            }
        }
        
        // 关闭连接
        $conn->close();
        log_message('数据库连接已关闭', 'info');
    }
    
    end_response: // 响应结束标签
    
    // 记录测试结束日志
    log_message('测试结束', 'info');
    
    // 获取所有日志
    $response['logs'] = get_logs();
    
    // 返回JSON响应
    // 确保响应不包含任何UTF-8 BOM
    ob_end_clean(); // 清除所有输出缓冲区
    
    // 设置JSON内容类型（只需设置一次）
    header('Content-Type: application/json; charset=utf-8');
    
    // 确保中文正常显示
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// 如果已经安装，则重定向到首页
if (file_exists('installed.lock')) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';
$logs = array(); // 存储安装过程的日志

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 清空旧日志
    clear_logs();
    log_message('开始安装流程...', 'info');
    
    // 获取表单数据
    $db_host = $_POST['db_host'];
    $db_user = $_POST['db_user'];
    $db_pass = $_POST['db_pass'];
    $db_name = $_POST['db_name'];
    $admin_user = $_POST['admin_user'];
    $admin_pass = $_POST['admin_pass'];
    $admin_pass_confirm = $_POST['admin_pass_confirm'];
    $site_title = $_POST['site_title'];
    
    log_message('表单数据已获取', 'info');

    // 验证表单数据
    if (empty($db_host) || empty($db_user) || empty($db_name) || empty($admin_user) || empty($admin_pass)) {
        $error = '所有必填字段都必须填写';
        log_message('表单验证失败：所有必填字段都必须填写', 'error');
    } elseif ($admin_pass !== $admin_pass_confirm) {
        $error = '两次输入的密码不一致';
        log_message('表单验证失败：两次输入的密码不一致', 'error');
    } else {
        log_message('表单验证成功', 'success');
        
        // 尝试连接数据库
        // 设置连接超时（5秒）
        $conn = new mysqli();
        $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
        
        log_message('尝试连接数据库: ' . $db_host . '，用户名: ' . $db_user, 'info');
        if (!$conn->real_connect($db_host, $db_user, $db_pass)) {
            $error = '数据库连接失败: ' . $conn->connect_error;
            $error_code = $conn->connect_errno;
            
            // 根据错误代码提供更详细的解决方案建议
            $suggestion = '';
            switch($error_code) {
                case 1045: // 访问被拒绝
                    $suggestion = ' 请检查用户名和密码是否正确，以及该用户是否有足够的权限。';
                    break;
                case 1049: // 未知数据库
                    $suggestion = ' 请检查数据库名称是否正确。';
                    break;
                case 2002: // 无法连接到服务器
                    $suggestion = ' 请检查主机名和端口是否正确，以及MySQL服务器是否正在运行。';
                    break;
            }
            
            $error .= $suggestion;
            log_message('数据库连接失败 (错误码: ' . $error_code . '): ' . $conn->connect_error . $suggestion, 'error');
            
            // 关闭连接
            $conn->close();
            log_message('数据库连接已关闭', 'info');
        } else {
            log_message('数据库连接成功', 'success');
            
            // 创建数据库
                if (!$conn->query("CREATE DATABASE IF NOT EXISTS $db_name")) {
                    $error = '创建数据库失败: ' . $conn->error;
                    log_message('创建数据库失败: ' . $conn->error, 'error');
                    
                    // 关闭连接
                    $conn->close();
                    log_message('数据库连接已关闭', 'info');
                } else {
                log_message('数据库 ' . $db_name . ' 创建/检查成功', 'success');
                
                // 选择数据库
                $conn->select_db($db_name);
                log_message('已选择数据库: ' . $db_name, 'info');
                
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
                        log_message('创建servers表失败: ' . $conn->error, 'error');
                        
                        // 关闭连接
                        $conn->close();
                        log_message('数据库连接已关闭', 'info');
                    } else {
                    log_message('servers表创建成功', 'success');
                    
                    // 创建管理员表
                    $sql = "CREATE TABLE IF NOT EXISTS admins (
                        id INT(11) AUTO_INCREMENT PRIMARY KEY,
                        username VARCHAR(50) NOT NULL UNIQUE,
                        password VARCHAR(255) NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )";
                    
                    if (!$conn->query($sql)) {
                        $error = '创建admins表失败: ' . $conn->error;
                        log_message('创建admins表失败: ' . $conn->error, 'error');
                        
                        // 关闭连接
                        $conn->close();
                        log_message('数据库连接已关闭', 'info');
                    } else {
                        log_message('admins表创建成功', 'success');
                        
                        // 插入或更新管理员账户
                        $hashed_password = password_hash($admin_pass, PASSWORD_DEFAULT);
                        $sql = "INSERT INTO admins (username, password) VALUES ('$admin_user', '$hashed_password') ON DUPLICATE KEY UPDATE password='$hashed_password'";
                        
                        if (!$conn->query($sql)) {
                            $error = '创建/更新管理员账户失败: ' . $conn->error;
                            log_message('创建/更新管理员账户失败: ' . $conn->error, 'error');
                            
                            // 关闭连接
                            $conn->close();
                            log_message('数据库连接已关闭', 'info');
                        } else {
                            $success = '管理员账户已' . ($conn->affected_rows == 1 ? '创建' : '更新') . '成功！';
                            log_message('管理员账户 ' . $admin_user . ' 创建/更新成功', 'success');
                            
                            // 创建玩家历史数据表
                                $sql = "CREATE TABLE IF NOT EXISTS player_history (
                                    id INT(11) AUTO_INCREMENT PRIMARY KEY,
                                    server_id INT(11) NOT NULL,
                                    players_online INT NOT NULL,
                                    player_list_json TEXT DEFAULT NULL,
                                    record_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE
                                )";
                            
                            if (!$conn->query($sql)) {
                                $error = '创建player_history表失败: ' . $conn->error;
                                log_message('创建player_history表失败: ' . $conn->error, 'error');
                                
                                // 关闭连接
                                $conn->close();
                                log_message('数据库连接已关闭', 'info');
                            } else {
                                log_message('player_history表创建成功', 'success');
                                
                                // 添加索引以提高查询性能（兼容所有MySQL版本）
                                // 检查并创建idx_server_id索引
                                $result = $conn->query("SHOW INDEX FROM player_history WHERE Key_name = 'idx_server_id'");
                                if ($result->num_rows == 0) {
                                    if ($conn->query("CREATE INDEX idx_server_id ON player_history(server_id)")) {
                                        log_message('添加idx_server_id索引成功', 'success');
                                    } else {
                                        log_message('添加idx_server_id索引失败: ' . $conn->error, 'error');
                                    }
                                } else {
                                    log_message('idx_server_id索引已存在，跳过创建', 'info');
                                }
                                
                                // 检查并创建idx_record_time索引
                                $result = $conn->query("SHOW INDEX FROM player_history WHERE Key_name = 'idx_record_time'");
                                if ($result->num_rows == 0) {
                                    if ($conn->query("CREATE INDEX idx_record_time ON player_history(record_time)")) {
                                        log_message('添加idx_record_time索引成功', 'success');
                                    } else {
                                        log_message('添加idx_record_time索引失败: ' . $conn->error, 'error');
                                    }
                                } else {
                                    log_message('idx_record_time索引已存在，跳过创建', 'info');
                                }
                                
                                // 创建存储过程用于定期清理旧数据
                                // 使用兼容更多MySQL版本的方式创建存储过程
                                // 先检查存储过程是否存在，如果存在则删除
                                $check_procedure = $conn->query("SHOW PROCEDURE STATUS WHERE name = 'cleanup_old_player_history'");
                                if ($check_procedure && $check_procedure->num_rows > 0) {
                                    $conn->query("DROP PROCEDURE IF EXISTS cleanup_old_player_history");
                                    log_message('已删除旧的cleanup_old_player_history存储过程', 'info');
                                }
                                
                                // 创建新的存储过程
                                // 在PHP中不需要设置DELIMITER，直接创建存储过程
                                $create_procedure_sql = "CREATE PROCEDURE cleanup_old_player_history()
BEGIN
    DELETE FROM player_history WHERE record_time < DATE_SUB(NOW(), INTERVAL 30 DAY);
END";
                                
                                if ($conn->query($create_procedure_sql)) {
                                    log_message('创建cleanup_old_player_history存储过程成功', 'success');
                                } else {
                                    log_message('创建cleanup_old_player_history存储过程失败: ' . $conn->error, 'error');
                                }
                                
                                // 更新配置文件
                                $config_content = "<?php\n";
                                $config_content .= "// 配置文件\n";
                                $config_content .= "// 数据库配置\n";
                                $config_content .= "define('DB_HOST', '$db_host');\n";
                                $config_content .= "define('DB_USER', '$db_user');\n";
                                $config_content .= "define('DB_PASS', '$db_pass');\n";
                                $config_content .= "define('DB_NAME', '$db_name');\n\n";
                                $config_content .= "// API更新地址配置\n";
                                $config_content .= "define('API_UPDATE_URL', 'https://raw.githubusercontent.com/Relief156/Minecraft-Server-Status/refs/heads/main/api.json');\n\n";
                                $config_content .= "// 引入API加载器\n";
                                $config_content .= "require_once 'api_loader.php';\n\n";
                                $config_content .= "// 网站配置\n";
                                $config_content .= "define('SITE_TITLE', '$site_title');\n";
                                $config_content .= "?>\n";
                                
                                if (!file_put_contents('config.php', $config_content)) {
                                    $error = '更新配置文件失败，请确保文件有写入权限';
                                    log_message('更新配置文件失败，请确保文件有写入权限', 'error');
                                    
                                    // 关闭连接
                                    $conn->close();
                                    log_message('数据库连接已关闭', 'info');
                                } else {
                                    log_message('配置文件创建成功', 'success');
                                    
                                    // 创建api_loader.php文件
                                    $api_loader_content = <<<'EOD'
                                    <?php
                                    
                                    /**
                                     * 从api.json文件加载API URL
                                     * @return array 包含Java和Bedrock API URL列表的数组
                                     */
                                    function loadApiUrls() {
                                        $api_file = 'api.json';
                                        if (file_exists($api_file)) {
                                            $api_data = json_decode(file_get_contents($api_file), true);
                                            if ($api_data && isset($api_data['routes'])) {
                                                $java_apis = [];
                                                $bedrock_apis = [];
                                                foreach ($api_data['routes'] as $route) {
                                                    if ($route['type'] === 'java') {
                                                        $java_apis[] = $route['api_url'];
                                                    } elseif ($route['type'] === 'bedrock') {
                                                        $bedrock_apis[] = $route['api_url'];
                                                    }
                                                }
                                                return [
                                                    'java' => $java_apis,
                                                    'bedrock' => $bedrock_apis
                                                ];
                                            }
                                        }
                                        // 默认API（当api.json不存在或格式错误时使用）
                                        return [
                                            'java' => ['http://cow.mc6.cn:10709/raw/'],
                                            'bedrock' => ['https://api.mcsrvstat.us/bedrock/3/']
                                        ];
                                    }
                                    
                                    // 加载API URL
                                    $api_urls = loadApiUrls();
                                    // 定义API URL常量
                                    define('JAVA_API_URLS', $api_urls['java']);
                                    define('BEDROCK_API_URLS', $api_urls['bedrock']);
                                    
                                    ?>
                                    EOD;
                                    if (!file_put_contents('api_loader.php', $api_loader_content)) {
                                        $error = '创建api_loader.php文件失败，请确保文件有写入权限';
                                        log_message('创建api_loader.php文件失败，请确保文件有写入权限', 'error');
                                        
                                        // 关闭连接
                                        $conn->close();
                                        log_message('数据库连接已关闭', 'info');
                                    } else {
                                        log_message('api_loader.php文件创建成功', 'success');
                                        
                                        // 创建api.json文件
                                        $default_api_data = [
                                            'routes' => [
                                                [
                                                    'type' => 'java',
                                                    'api_url' => 'http://cow.mc6.cn:10709/raw/',
                                                    'name' => '默认Java API'
                                                ],
                                                [
                                                    'type' => 'bedrock',
                                                    'api_url' => 'https://api.mcsrvstat.us/bedrock/3/',
                                                    'name' => '默认Bedrock API'
                                                ]
                                            ]
                                        ];
                                        if (!file_put_contents('api.json', json_encode($default_api_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                                            $error = '创建api.json文件失败，请确保文件有写入权限';
                                            log_message('创建api.json文件失败，请确保文件有写入权限', 'error');
                                             
                                            // 关闭连接
                                            $conn->close();
                                            log_message('数据库连接已关闭', 'info');
                                        } else {
                                            log_message('api.json文件创建成功', 'success');

                                            // 创建已安装标记文件
                                            if (!file_put_contents('installed.lock', 'Installed on ' . date('Y-m-d H:i:s'))) {
                                                $error = '创建installed.lock文件失败，请确保文件有写入权限';
                                                log_message('创建installed.lock文件失败，请确保文件有写入权限', 'error');
                                                
                                                // 关闭连接
                                                $conn->close();
                                                log_message('数据库连接已关闭', 'info');
                                            } else {
                                                log_message('创建installed.lock文件，标记安装完成', 'success');

                                                $success = '安装成功！即将跳转到监控页面...';
                                                log_message('安装成功！3秒后重定向到首页', 'success');
                                                header('Refresh: 3; URL=index.php');
                                                exit;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // 关闭连接
        $conn->close();
        log_message('数据库连接已关闭', 'info');
    }
    
    // 获取所有日志
    $logs = log_message('安装流程结束', 'info');
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
            <!-- 日志框 -->
            <div class="log-container">
                <div class="log-header">
                    <h3>安装日志</h3>
                    <button type="button" id="clear-log-btn" class="btn-clear-log">清空日志</button>
                </div>
                <div id="log-box" class="log-box">
                    <?php if (!empty($logs)): ?>
                        <?php foreach ($logs as $log): ?>
                            <div class="log-entry log-<?php echo $log['type']; ?>">
                                <span class="log-time"><?php echo $log['time']; ?></span>
                                <span class="log-message"><?php echo $log['message']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="log-entry log-info">
                            <span class="log-time"><?php echo date('Y-m-d H:i:s'); ?></span>
                            <span class="log-message">系统就绪，等待安装流程开始...</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
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
                
                <div style="display: flex; gap: 10px;">
                    <button type="button" id="test-db-btn" class="btn-submit" style="flex: 1; background-color: #2ecc71;">数据库测试</button>
                    <button type="submit" class="btn-submit" style="flex: 1;">完成安装</button>
                </div>
                
                <script>
                    // 数据库测试按钮点击事件
                    document.getElementById('test-db-btn').addEventListener('click', function() {
                        // 收集表单数据
                        const formData = new FormData(document.querySelector('form'));
                        formData.append('test_database', 'true');
                        
                        // 禁用测试按钮
                        const testBtn = this;
                        testBtn.disabled = true;
                        testBtn.textContent = '测试中...';
                        
                        // 清空日志框
                        clearLog();
                        addLogEntry('info', '开始数据库连接测试...');
                        
                        // 发送AJAX请求
                        fetch('', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            // 启用测试按钮
                            testBtn.disabled = false;
                            testBtn.textContent = '数据库测试';
                            
                            // 显示测试结果日志
                if (data.logs && data.logs.length > 0) {
                    clearLog();
                    data.logs.forEach(log => {
                        addLogEntry(log.type, log.message, log.time);
                    });
                }
                            
                            // 显示结果
                            const resultDiv = document.createElement('div');
                            resultDiv.className = data.success ? 'success-message' : 'error-message';
                            resultDiv.textContent = data.message;
                            
                            // 添加到页面
                            const container = document.querySelector('.container');
                            const form = document.querySelector('form');
                            container.insertBefore(resultDiv, form);
                            
                            // 3秒后移除
                            setTimeout(() => {
                                resultDiv.remove();
                            }, 3000);
                        })
                        .catch(error => {
                            // 启用测试按钮
                            testBtn.disabled = false;
                            testBtn.textContent = '数据库测试';
                            
                            // 添加错误日志
                            addLogEntry('error', '请求失败: ' + error.message);
                            
                            console.error('Error:', error);
                            alert('测试过程中发生错误');
                        });
                    });
                    
                    // 清空日志按钮点击事件
                    document.getElementById('clear-log-btn').addEventListener('click', function() {
                        clearLog();
                        addLogEntry('info', '日志已清空');
                    });
                    
                    // 添加日志条目函数
                    function addLogEntry(type, message, time = null) {
                        const logBox = document.getElementById('log-box');
                        const logEntry = document.createElement('div');
                        logEntry.className = 'log-entry log-' + type;
                        
                        const logTime = document.createElement('span');
                        logTime.className = 'log-time';
                        logTime.textContent = time || new Date().toLocaleString();
                        
                        const logMessage = document.createElement('span');
                        logMessage.className = 'log-message';
                        logMessage.textContent = message;
                        
                        logEntry.appendChild(logTime);
                        logEntry.appendChild(logMessage);
                        logBox.appendChild(logEntry);
                        
                        // 自动滚动到底部
                        logBox.scrollTop = logBox.scrollHeight;
                    }
                    
                    // 清空日志函数
                    function clearLog() {
                        const logBox = document.getElementById('log-box');
                        logBox.innerHTML = '';
                    }
                    
                    // 添加日志CSS样式
                    const style = document.createElement('style');
                    style.textContent = `
                        .log-container {
                            margin-bottom: 20px;
                            border: 1px solid #e0e0e0;
                            border-radius: 8px;
                            overflow: hidden;
                        }
                        .log-header {
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            padding: 10px 15px;
                            background-color: #f8f9fa;
                            border-bottom: 1px solid #e0e0e0;
                        }
                        .log-header h3 {
                            margin: 0;
                            color: #2c3e50;
                            font-size: 16px;
                        }
                        .btn-clear-log {
                            padding: 5px 10px;
                            background-color: #6c757d;
                            color: white;
                            border: none;
                            border-radius: 4px;
                            cursor: pointer;
                            font-size: 14px;
                        }
                        .btn-clear-log:hover {
                            background-color: #5a6268;
                        }
                        .log-box {
                            height: 200px;
                            overflow-y: auto;
                            padding: 10px;
                            background-color: #fafafa;
                            font-family: 'Courier New', monospace;
                            font-size: 14px;
                        }
                        .log-entry {
                            margin-bottom: 5px;
                            padding: 5px;
                            border-radius: 4px;
                            display: flex;
                            gap: 10px;
                        }
                        .log-time {
                            color: #666;
                            min-width: 140px;
                        }
                        .log-info .log-message {
                            color: #212529;
                        }
                        .log-success .log-message {
                            color: #155724;
                            background-color: #d4edda;
                        }
                        .log-error .log-message {
                            color: #721c24;
                            background-color: #f8d7da;
                        }
                    `;
                    document.head.appendChild(style);
                    </script>
            </form>
        <?php endif; ?>
    </div>
</body>

</html>
