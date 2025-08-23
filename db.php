<?php
// 数据库连接文件

// 检查是否已安装
if (!file_exists('installed.lock')) {
    die('系统尚未安装，请先完成安装设置');
}

require_once 'config.php';

class Database {
    private $conn;

    // 构造函数 - 建立数据库连接
    public function __construct() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        // 检查连接
        if ($this->conn->connect_error) {
            die("数据库连接失败: " . $this->conn->connect_error);
        }
    }
    
    // 获取数据库连接
    public function getConnection() {
        return $this->conn;
    }

    // 获取所有服务器信息
    // $sort_by: 排序字段，默认为sort_weight
    // $sort_order: 排序顺序，默认为DESC(降序)
    public function getAllServers($sort_by = 'sort_weight', $sort_order = 'DESC') {
        // 验证排序字段和排序顺序的有效性
        $valid_sort_fields = ['id', 'name', 'address', 'server_type', 'created_at', 'updated_at', 'sort_weight'];
        $valid_sort_orders = ['ASC', 'DESC'];
        
        // 如果排序字段不在有效列表中，使用默认值
        if (!in_array($sort_by, $valid_sort_fields)) {
            $sort_by = 'sort_weight';
        }
        
        // 如果排序顺序不在有效列表中，使用默认值
        if (!in_array(strtoupper($sort_order), $valid_sort_orders)) {
            $sort_order = 'DESC';
        } else {
            $sort_order = strtoupper($sort_order);
        }
        
        // 构建SQL查询语句
        $sql = "SELECT * FROM servers ORDER BY $sort_by $sort_order";
        $result = $this->conn->query($sql);
        return $result;
    }

    // 获取单个服务器信息
    public function getServerById($id) {
        $sql = "SELECT * FROM servers WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    // 添加服务器
    public function addServer($name, $address, $server_type = 'java', $sort_weight = 1000) {
        // 先检查表结构是否包含server_type字段
        $checkColumn = $this->conn->query("SHOW COLUMNS FROM servers LIKE 'server_type'");
        // 检查是否包含sort_weight字段
        $checkSortWeight = $this->conn->query("SHOW COLUMNS FROM servers LIKE 'sort_weight'");
        
        if ($checkColumn && $checkColumn->num_rows > 0) {
            // 表包含server_type字段
            if ($checkSortWeight && $checkSortWeight->num_rows > 0) {
                // 表也包含sort_weight字段
                $sql = "INSERT INTO servers (name, address, server_type, sort_weight, created_at) VALUES (?, ?, ?, ?, NOW())";
                $stmt = $this->conn->prepare($sql);
                if ($stmt === false) {
                    die("添加服务器prepare语句失败: " . $this->conn->error);
                }
                $stmt->bind_param("sssi", $name, $address, $server_type, $sort_weight);
            } else {
                // 表不包含sort_weight字段
                $sql = "INSERT INTO servers (name, address, server_type, created_at) VALUES (?, ?, ?, NOW())";
                $stmt = $this->conn->prepare($sql);
                if ($stmt === false) {
                    die("添加服务器prepare语句失败: " . $this->conn->error);
                }
                $stmt->bind_param("sss", $name, $address, $server_type);
            }
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        } else {
            // 表不包含server_type字段，使用兼容旧结构的语句
            $sql = "INSERT INTO servers (name, address, created_at) VALUES (?, ?, NOW())";
            $stmt = $this->conn->prepare($sql);
            if ($stmt === false) {
                die("添加服务器prepare语句失败: " . $this->conn->error);
            }
            $stmt->bind_param("ss", $name, $address);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        }
    }

    // 更新服务器
    public function updateServer($id, $name, $address, $server_type = 'java', $sort_weight = null) {
        // 先检查表结构是否包含server_type字段
        $checkColumn = $this->conn->query("SHOW COLUMNS FROM servers LIKE 'server_type'");
        // 检查是否包含sort_weight字段
        $checkSortWeight = $this->conn->query("SHOW COLUMNS FROM servers LIKE 'sort_weight'");
        
        if ($checkColumn && $checkColumn->num_rows > 0) {
            // 表包含server_type字段
            if ($checkSortWeight && $checkSortWeight->num_rows > 0) {
                // 表也包含sort_weight字段
                if ($sort_weight !== null) {
                    // 如果提供了排序权重，更新它
                    $sql = "UPDATE servers SET name = ?, address = ?, server_type = ?, sort_weight = ?, updated_at = NOW() WHERE id = ?";
                    $stmt = $this->conn->prepare($sql);
                    if ($stmt === false) {
                        die("更新服务器prepare语句失败: " . $this->conn->error);
                    }
                    $stmt->bind_param("sssii", $name, $address, $server_type, $sort_weight, $id);
                } else {
                    // 如果没有提供排序权重，不更新它
                    $sql = "UPDATE servers SET name = ?, address = ?, server_type = ?, updated_at = NOW() WHERE id = ?";
                    $stmt = $this->conn->prepare($sql);
                    if ($stmt === false) {
                        die("更新服务器prepare语句失败: " . $this->conn->error);
                    }
                    $stmt->bind_param("sssi", $name, $address, $server_type, $id);
                }
            } else {
                // 表不包含sort_weight字段
                $sql = "UPDATE servers SET name = ?, address = ?, server_type = ?, updated_at = NOW() WHERE id = ?";
                $stmt = $this->conn->prepare($sql);
                if ($stmt === false) {
                    die("更新服务器prepare语句失败: " . $this->conn->error);
                }
                $stmt->bind_param("sssi", $name, $address, $server_type, $id);
            }
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        } else {
            // 表不包含server_type字段，使用兼容旧结构的语句
            $sql = "UPDATE servers SET name = ?, address = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            if ($stmt === false) {
                die("更新服务器prepare语句失败: " . $this->conn->error);
            }
            $stmt->bind_param("ssi", $name, $address, $id);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        }
    }

    // 删除服务器
    public function deleteServer($id) {
        $sql = "DELETE FROM servers WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    // 析构函数 - 关闭数据库连接
    public function __destruct() {
        $this->conn->close();
    }
    
    // 检查并更新表结构，添加show_player_history、show_ip和ip_description字段
    public function checkAndUpdateServerTable() {
        // 检查servers表是否包含show_player_history字段
        $checkColumn = $this->conn->query("SHOW COLUMNS FROM servers LIKE 'show_player_history'");
        
        if (!$checkColumn || $checkColumn->num_rows === 0) {
            // 如果不包含，添加show_player_history字段，默认为1（显示）
            $alterTable = $this->conn->query("ALTER TABLE servers ADD COLUMN show_player_history TINYINT(1) DEFAULT 1 AFTER sort_weight");
            
            if (!$alterTable) {
                error_log("添加show_player_history字段失败: " . $this->conn->error);
                return false;
            }
        }
        
        // 检查是否包含show_ip字段
        $checkShowIp = $this->conn->query("SHOW COLUMNS FROM servers LIKE 'show_ip'");
        
        if (!$checkShowIp || $checkShowIp->num_rows === 0) {
            // 如果不包含，添加show_ip字段，默认为1（显示IP）
            $alterTable = $this->conn->query("ALTER TABLE servers ADD COLUMN show_ip TINYINT(1) DEFAULT 1 AFTER show_player_history");
            
            if (!$alterTable) {
                error_log("添加show_ip字段失败: " . $this->conn->error);
                return false;
            }
        }
        
        // 检查是否包含ip_description字段
        $checkIpDesc = $this->conn->query("SHOW COLUMNS FROM servers LIKE 'ip_description'");
        
        if (!$checkIpDesc || $checkIpDesc->num_rows === 0) {
            // 如果不包含，添加ip_description字段，默认为空字符串
            $alterTable = $this->conn->query("ALTER TABLE servers ADD COLUMN ip_description TEXT DEFAULT '' AFTER show_ip");
            
            if (!$alterTable) {
                error_log("添加ip_description字段失败: " . $this->conn->error);
                return false;
            }
        }
        
        return true;
    }
    
    // 设置服务器是否显示历史在线人数
    public function setShowPlayerHistory($server_id, $show_history) {
        // 确保字段存在
        $this->checkAndUpdateServerTable();
        
        $sql = "UPDATE servers SET show_player_history = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        
        if ($stmt === false) {
            error_log("设置显示历史人数prepare语句失败: " . $this->conn->error);
            return false;
        }
        
        $show_history = $show_history ? 1 : 0;
        $stmt->bind_param("ii", $show_history, $server_id);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
        // 设置服务器是否显示IP地址
    public function setShowIp($server_id, $show_ip) {
        // 确保字段存在
        $this->checkAndUpdateServerTable();
        
        $sql = "UPDATE servers SET show_ip = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        
        if ($stmt === false) {
            error_log("设置显示IPprepare语句失败: " . $this->conn->error);
            return false;
        }
        
        $show_ip = $show_ip ? 1 : 0;
        $stmt->bind_param("ii", $show_ip, $server_id);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    // 获取服务器是否显示IP地址的设置
    public function getShowIp($server_id) {
        // 确保字段存在
        $this->checkAndUpdateServerTable();
        
        $sql = "SELECT show_ip FROM servers WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        
        if ($stmt === false) {
            error_log("获取显示IP设置prepare语句失败: " . $this->conn->error);
            return true; // 默认显示
        }
        
        $stmt->bind_param("i", $server_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row ? ($row['show_ip'] == 1) : true; // 默认显示
    }
    
    // 设置IP地址的替代描述文本
    public function setIpDescription($server_id, $description) {
        // 确保字段存在
        $this->checkAndUpdateServerTable();
        
        $sql = "UPDATE servers SET ip_description = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        
        if ($stmt === false) {
            error_log("设置IP描述prepare语句失败: " . $this->conn->error);
            return false;
        }
        
        $stmt->bind_param("si", $description, $server_id);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    // 获取IP地址的替代描述文本
    public function getIpDescription($server_id) {
        // 确保字段存在
        $this->checkAndUpdateServerTable();
        
        $sql = "SELECT ip_description FROM servers WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        
        if ($stmt === false) {
            error_log("获取IP描述prepare语句失败: " . $this->conn->error);
            return ''; // 默认空字符串
        }
        
        $stmt->bind_param("i", $server_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row ? $row['ip_description'] : ''; // 默认空字符串
    }
    
    // 获取服务器是否显示历史在线人数的设置
    public function getShowPlayerHistory($server_id) {
        // 确保字段存在
        $this->checkAndUpdateServerTable();
        
        $sql = "SELECT show_player_history FROM servers WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        
        if ($stmt === false) {
            error_log("获取显示历史人数设置prepare语句失败: " . $this->conn->error);
            return true; // 默认显示
        }
        
        $stmt->bind_param("i", $server_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row ? ($row['show_player_history'] == 1) : true; // 默认显示
    }
    
    // 保存服务器在线人数历史数据
    // 优化逻辑：如果当前人数与上一条记录相同，则更新记录时间；否则插入新记录
    // 如果提供了玩家列表，当人数相同时还会比较玩家列表，如果列表不同也会插入新记录
    public function savePlayerHistory($server_id, $players_online, $player_list = null) {
        // 准备玩家列表JSON
        $player_list_json = null;
        if ($players_online > 0 && is_array($player_list) && count($player_list) > 0) {
            $player_list_json = json_encode($player_list);
        }
        
        // 首先检查上一条记录
        $sql_last = "SELECT id, players_online, player_list_json FROM player_history WHERE server_id = ? ORDER BY record_time DESC LIMIT 1";
        $stmt_last = $this->conn->prepare($sql_last);
        
        if ($stmt_last === false) {
            error_log("查询最后一条玩家历史数据prepare语句失败: " . $this->conn->error);
            return false;
        }
        
        $stmt_last->bind_param("i", $server_id);
        $stmt_last->execute();
        $result_last = $stmt_last->get_result();
        $stmt_last->close();
        
        if ($result_last && $result_last->num_rows > 0) {
            // 有上一条记录，检查人数是否相同
            $row_last = $result_last->fetch_assoc();
            $last_id = $row_last['id'];
            $last_players_online = $row_last['players_online'];
            $last_player_list_json = $row_last['player_list_json'];
            
            if ($last_players_online == $players_online) {
                // 人数相同，检查玩家列表是否相同
                if ($players_online > 0 && $player_list !== null) {
                    // 获取排序后的当前玩家列表
                    sort($player_list);
                    $current_player_list_sorted = $player_list;
                    
                    // 获取排序后的历史玩家列表
                    $last_player_list_sorted = [];
                    if (!empty($last_player_list_json)) {
                        $last_player_list = json_decode($last_player_list_json, true);
                        if (is_array($last_player_list)) {
                            sort($last_player_list);
                            $last_player_list_sorted = $last_player_list;
                        }
                    }
                    
                    // 比较玩家列表（确保顺序不影响比较结果）
                    if (count($current_player_list_sorted) == count($last_player_list_sorted) && 
                        $current_player_list_sorted === $last_player_list_sorted) {
                        // 玩家列表相同，更新记录时间
                        $sql_update = "UPDATE player_history SET record_time = NOW() WHERE id = ?";
                        $stmt_update_players = $this->conn->prepare($sql_update);
                        
                        if ($stmt_update_players === false) {
                            error_log("更新玩家历史数据时间prepare语句失败: " . $this->conn->error);
                            return false;
                        }
                        
                        $stmt_update_players->bind_param("i", $last_id);
                        $result_update = $stmt_update_players->execute();
                        $stmt_update_players->close();
                        return $result_update;
                    } else {
                        // 玩家列表不同，插入新记录
                        $sql_insert = "INSERT INTO player_history (server_id, players_online, player_list_json) VALUES (?, ?, ?)";
                        $stmt_insert = $this->conn->prepare($sql_insert);
                        
                        if ($stmt_insert === false) {
                            error_log("保存玩家历史数据prepare语句失败: " . $this->conn->error);
                            return false;
                        }
                        
                        $stmt_insert->bind_param("iis", $server_id, $players_online, $player_list_json);
                        $result_insert = $stmt_insert->execute();
                        $stmt_insert->close();
                        return $result_insert;
                    }
                } else {
                    // 没有玩家在线，或没有提供玩家列表，直接更新记录时间
                    $sql_update = "UPDATE player_history SET record_time = NOW() WHERE id = ?";
                    $stmt_update_inner = $this->conn->prepare($sql_update);
                    
                    if ($stmt_update_inner === false) {
                        error_log("更新玩家历史数据时间prepare语句失败: " . $this->conn->error);
                        return false;
                    }
                    
                    $stmt_update_inner->bind_param("i", $last_id);
                    $result_update = $stmt_update_inner->execute();
                    $stmt_update_inner->close();
                    return $result_update;
                }
            } else {
                // 人数不同，插入新记录
                $sql_insert = "INSERT INTO player_history (server_id, players_online, player_list_json) VALUES (?, ?, ?)";
                $stmt_insert = $this->conn->prepare($sql_insert);
                
                if ($stmt_insert === false) {
                    error_log("保存玩家历史数据prepare语句失败: " . $this->conn->error);
                    return false;
                }
                
                $stmt_insert->bind_param("iis", $server_id, $players_online, $player_list_json);
                $result_insert = $stmt_insert->execute();
                $stmt_insert->close();
                return $result_insert;
            }
        } else {
            // 没有上一条记录，直接插入新记录
            $sql_insert = "INSERT INTO player_history (server_id, players_online, player_list_json) VALUES (?, ?, ?)";
            $stmt_insert = $this->conn->prepare($sql_insert);
            
            if ($stmt_insert === false) {
                error_log("保存玩家历史数据prepare语句失败: " . $this->conn->error);
                return false;
            }
            
            $stmt_insert->bind_param("iis", $server_id, $players_online, $player_list_json);
            $result_insert = $stmt_insert->execute();
            $stmt_insert->close();
            return $result_insert;
        }
        
        return false;
    }
    
    // 获取服务器的玩家历史数据（聚合数据）
    // $days: 要获取的天数范围，默认为1天，设置为0表示查询所有记录
    public function getPlayerHistory($server_id, $days = 1) {
        // 根据天数选择合适的时间间隔分组
        if ($days <= 0) {
            // 查询所有记录，按天分组
            $time_format = '%Y-%m-%d';
        } elseif ($days <= 1) {
            // 1天内，按小时分组
            $time_format = '%Y-%m-%d %H:00:00';
        } elseif ($days <= 7) {
            // 1-7天，按2小时分组
            $time_format = '%Y-%m-%d %H:00:00';
        } else {
            // 超过7天，按天分组
            $time_format = '%Y-%m-%d';
        }
        
        // 设置SQL查询语句基础部分
        $sql = "SELECT DATE_FORMAT(record_time, '$time_format') as time_label, 
               AVG(players_online) as avg_players 
               FROM player_history 
               WHERE server_id = ? ";
                
        // 计算时间范围（如果不是查询所有记录）
        if ($days > 0) {
            $time_ago = date('Y-m-d H:i:s', strtotime("-$days days"));
            $sql .= "AND record_time >= ? ";
        }
        
        // 完成SQL查询语句
        $sql .= "GROUP BY time_label 
               ORDER BY time_label ASC";
        
        // 准备预处理语句
        $stmt = $this->conn->prepare($sql);
        
        if ($stmt === false) {
            error_log("获取玩家历史数据prepare语句失败: " . $this->conn->error);
            return false;
        }
        
        // 绑定参数
        $stmt->bind_param("is", $server_id, $time_ago);
        
        // 执行查询
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        return $result;
    }
    
    // 获取服务器的原始玩家历史数据（包含玩家列表）
    // $days: 要获取的天数范围，默认为1天，设置为0表示查询所有记录
    public function getRawPlayerHistory($server_id, $days = 1) {
        // 设置SQL查询语句基础部分
        $sql = "SELECT record_time, players_online, player_list_json 
               FROM player_history 
               WHERE server_id = ? ";
                
        // 计算时间范围（如果不是查询所有记录）
        if ($days > 0) {
            $time_ago = date('Y-m-d H:i:s', strtotime("-$days days"));
            $sql .= "AND record_time >= ? ";
        }
        
        // 完成SQL查询语句
        $sql .= "ORDER BY record_time ASC";
        
        // 准备预处理语句
        $stmt = $this->conn->prepare($sql);
        
        if ($stmt === false) {
            error_log("获取原始玩家历史数据prepare语句失败: " . $this->conn->error);
            return false;
        }
        
        // 绑定参数
        if ($days > 0) {
            $stmt->bind_param("is", $server_id, $time_ago);
        } else {
            $stmt->bind_param("i", $server_id);
        }
        
        // 执行查询
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        return $result;
    }
}
?>