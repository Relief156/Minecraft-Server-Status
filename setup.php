<?php
// 数据库初始化脚本
require_once 'config.php';

// 创建数据库连接
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

// 检查连接
if ($conn->connect_error) {
    die("数据库连接失败: " . $conn->connect_error);
}

// 创建数据库
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if (!$conn->query($sql)) {
    die("创建数据库失败: " . $conn->error);
}

// 选择数据库
$conn->select_db(DB_NAME);

// 创建服务器表
$sql = "CREATE TABLE IF NOT EXISTS servers (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    address VARCHAR(255) NOT NULL,
    server_type ENUM('java', 'bedrock') DEFAULT 'java',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "表 servers 创建成功
";
} else {
    die("创建表失败: " . $conn->error);
}

// 创建管理员表
$sql = "CREATE TABLE IF NOT EXISTS admins (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "表 admins 创建成功
";
} else {
    die("创建表失败: " . $conn->error);
}

// 插入默认管理员账户（请在首次运行后修改密码）
$default_username = 'admin';
$default_password = password_hash('admin123', PASSWORD_DEFAULT);

$sql = "INSERT IGNORE INTO admins (username, password) VALUES ('$default_username', '$default_password')";
if ($conn->query($sql) === TRUE) {
    echo "默认管理员账户已创建\n";
    echo "用户名: $default_username\n";
    echo "密码: admin123 (请在首次登录后修改)\n";
} else {
    echo "插入管理员账户失败: " . $conn->error . "\n";
}

// 数据库结构更新 - 确保server_type字段定义正确
// 检查server_type字段是否已存在
$check_server_type = $conn->query("SHOW COLUMNS FROM servers LIKE 'server_type'");
if ($check_server_type && $check_server_type->num_rows > 0) {
    // 字段存在，确保类型正确
    $sql = "ALTER TABLE servers MODIFY COLUMN server_type ENUM('java', 'bedrock') NOT NULL DEFAULT 'java' AFTER address";
    if ($conn->query($sql) === TRUE) {
        echo "服务器类型字段定义已更新\n";
    } else {
        echo "更新服务器类型字段失败: " . $conn->error . "\n";
    }
}

// 数据库结构更新 - 添加sort_weight字段
// 检查sort_weight字段是否已存在
$check_sort_weight = $conn->query("SHOW COLUMNS FROM servers LIKE 'sort_weight'");
if (!$check_sort_weight || $check_sort_weight->num_rows == 0) {
    // 字段不存在，添加它
    $sql = "ALTER TABLE servers ADD COLUMN sort_weight INT NOT NULL DEFAULT 1000 AFTER server_type";
    if ($conn->query($sql) === TRUE) {
        echo "排序权重字段已添加到服务器表\n";
        // 确保已有记录有默认排序权重
        $conn->query("UPDATE servers SET sort_weight = 1000 WHERE sort_weight IS NULL");
    } else {
        echo "添加排序权重字段失败: " . $conn->error . "\n";
    }
}

// 数据库结构更新 - 添加玩家历史数据表
// 检查player_history表是否已存在
$check_player_history = $conn->query("SHOW TABLES LIKE 'player_history'");
if (!$check_player_history || $check_player_history->num_rows == 0) {
    // 表不存在，创建它
    $sql = "CREATE TABLE IF NOT EXISTS player_history (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        server_id INT(11) NOT NULL,
        players_online INT NOT NULL,
        record_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($sql) === TRUE) {
        echo "玩家历史数据表已创建\n";
        
        // 添加索引以提高查询性能
        $conn->query("CREATE INDEX idx_server_id ON player_history(server_id)");
        $conn->query("CREATE INDEX idx_record_time ON player_history(record_time)");
        echo "已添加索引以提高查询性能\n";
        
        // 创建存储过程用于定期清理旧数据
        // 先检查存储过程是否存在，如果存在则删除
        $check_procedure = $conn->query("SHOW PROCEDURE STATUS WHERE name = 'cleanup_old_player_history'");
        if ($check_procedure && $check_procedure->num_rows > 0) {
            $conn->query("DROP PROCEDURE IF EXISTS cleanup_old_player_history");
            echo "已删除旧的存储过程\n";
        }
        
        // 创建新的存储过程
        // 在PHP中不需要设置DELIMITER，直接创建存储过程
        $create_procedure_sql = "CREATE PROCEDURE cleanup_old_player_history()
BEGIN
    DELETE FROM player_history WHERE record_time < DATE_SUB(NOW(), INTERVAL 30 DAY);
END";
        
        // 执行创建存储过程的SQL语句
        $conn->query($create_procedure_sql);
        
        // 检查是否有错误
        if (!$conn->errno) {
            echo "已创建存储过程用于定期清理30天前的历史数据\n";
        } else {
            echo "创建存储过程失败: " . $conn->error . "\n";
        }
}

// 关闭连接
$conn->close();

echo "数据库初始化完成！\n";
?>