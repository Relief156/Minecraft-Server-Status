<?php
// 提供原始和聚合的player_history数据，供前端图表使用

// 检查是否已安装
if (!file_exists('installed.lock')) {
    die(json_encode(array('success' => false, 'error' => '系统尚未安装，请先完成安装设置')));
}

require_once 'config.php';

// 数据库连接类
class Database {
    private $conn;

    // 构造函数 - 建立数据库连接
    public function __construct() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        // 检查连接
        if ($this->conn->connect_error) {
            die(json_encode(array('success' => false, 'error' => '数据库连接失败: ' . $this->conn->connect_error)));
        }
    }

    // 析构函数 - 关闭数据库连接
    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
    
    // 获取服务器的所有历史数据 - 只保留在线人数变化的时间点
    public function getPlayerHistory($server_id) {
        $sql = "SELECT DATE_FORMAT(record_time, '%Y-%m-%d %H:%i:%s') as time_label, 
               players_online 
               FROM player_history 
               WHERE server_id = ? 
               ORDER BY record_time ASC";
        
        $stmt = $this->conn->prepare($sql);
        
        if ($stmt === false) {
            return array('success' => false, 'error' => '准备SQL语句失败: ' . $this->conn->error);
        }
        
        $stmt->bind_param("i", $server_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        $data = array();
        $labels = array();
        $values = array();
        
        if ($result && $result->num_rows > 0) {
            $prev_players = null; // 上一条记录的在线人数
            $first_record = true; // 是否是第一条记录
            $last_time_label = ''; // 最后一条记录的时间标签
            $last_players = ''; // 最后一条记录的在线人数
            $temp_rows = array(); // 临时存储所有记录
            
            // 先将所有记录存储到临时数组
            while ($row = $result->fetch_assoc()) {
                $temp_rows[] = $row;
                $last_time_label = $row['time_label'];
                $last_players = $row['players_online'];
            }
            
            // 遍历临时数组，只保留在线人数变化的记录
            foreach ($temp_rows as $index => $row) {
                $current_players = $row['players_online'];
                
                // 保留第一条记录
                if ($first_record) {
                    $labels[] = $row['time_label'];
                    $values[] = $current_players;
                    $prev_players = $current_players;
                    $first_record = false;
                    continue;
                }
                
                // 如果在线人数发生变化，保留当前记录
                if ($current_players != $prev_players) {
                    $labels[] = $row['time_label'];
                    $values[] = $current_players;
                    $prev_players = $current_players;
                }
                // 确保保留最后一条记录（即使和前一条相同）
                else if ($index == count($temp_rows) - 1) {
                    $labels[] = $row['time_label'];
                    $values[] = $current_players;
                }
            }
        }
        
        $data['labels'] = $labels;
        $data['values'] = $values;
        
        return array('success' => true, 'data' => $data);
    }
    
    // 获取服务器的聚合历史数据（按指定时间间隔分组）
    public function getAggregatedPlayerHistory($server_id, $interval = 'hour') {
        // 根据间隔选择分组格式
        switch($interval) {
            case 'day':
                $time_format = '%Y-%m-%d';
                break;
            case 'hour':
            default:
                $time_format = '%Y-%m-%d %H:00:00';
                break;
        }
        
        $sql = "SELECT DATE_FORMAT(record_time, '$time_format') as time_label, 
               AVG(players_online) as avg_players 
               FROM player_history 
               WHERE server_id = ? 
               GROUP BY time_label 
               ORDER BY time_label ASC";
        
        $stmt = $this->conn->prepare($sql);
        
        if ($stmt === false) {
            return array('success' => false, 'error' => '准备SQL语句失败: ' . $this->conn->error);
        }
        
        $stmt->bind_param("i", $server_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        $data = array();
        $labels = array();
        $values = array();
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $labels[] = $row['time_label'];
                $values[] = round($row['avg_players']);
            }
        }
        
        $data['labels'] = $labels;
        $data['values'] = $values;
        
        return array('success' => true, 'data' => $data);
    }
    
    // 获取服务器指定日期的历史数据
    public function getPlayerHistoryByDate($server_id, $date) {
        // 确保日期格式正确（YYYY-MM-DD）
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return array('success' => false, 'error' => '无效的日期格式，请使用YYYY-MM-DD格式');
        }
        
        // 构建SQL查询，筛选指定日期的数据，包含玩家列表
        $sql = "SELECT DATE_FORMAT(record_time, '%H:%i:%s') as time_label, 
               players_online, 
               player_list_json
               FROM player_history 
               WHERE server_id = ? 
               AND DATE(record_time) = ? 
               ORDER BY record_time ASC";
        
        $stmt = $this->conn->prepare($sql);
        
        if ($stmt === false) {
            return array('success' => false, 'error' => '准备SQL语句失败: ' . $this->conn->error);
        }
        
        $stmt->bind_param("is", $server_id, $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        $data = array();
        $labels = array();
        $values = array();
        $playerLists = array();
        
        if ($result && $result->num_rows > 0) {
            $prev_players = null; // 上一条记录的在线人数
            $first_record = true; // 是否是第一条记录
            $last_time_label = ''; // 最后一条记录的时间标签
            $last_players = ''; // 最后一条记录的在线人数
            $last_player_list = null; // 最后一条记录的玩家列表
            $temp_rows = array(); // 临时存储所有记录
            
            // 先将所有记录存储到临时数组
            while ($row = $result->fetch_assoc()) {
                $temp_rows[] = $row;
                $last_time_label = $row['time_label'];
                $last_players = $row['players_online'];
                $last_player_list = $row['player_list_json'];
            }
            
            // 遍历临时数组，只保留在线人数变化的记录
            foreach ($temp_rows as $index => $row) {
                $current_players = $row['players_online'];
                $current_player_list = $row['player_list_json'];
                
                // 保留第一条记录
                if ($first_record) {
                    $labels[] = $row['time_label'];
                    $values[] = $current_players;
                    $playerLists[] = $current_player_list;
                    $prev_players = $current_players;
                    $first_record = false;
                    continue;
                }
                
                // 如果在线人数发生变化，保留当前记录
                if ($current_players != $prev_players) {
                    $labels[] = $row['time_label'];
                    $values[] = $current_players;
                    $playerLists[] = $current_player_list;
                    $prev_players = $current_players;
                }
                // 确保保留最后一条记录（即使和前一条相同）
                else if ($index == count($temp_rows) - 1) {
                    $labels[] = $row['time_label'];
                    $values[] = $current_players;
                    $playerLists[] = $current_player_list;
                }
            }
        }
        
        $data['labels'] = $labels;
        $data['values'] = $values;
        $data['playerLists'] = $playerLists; // 添加玩家列表数据
        
        return array('success' => true, 'data' => $data);
    }
    
    // 获取服务器的所有历史数据（包含玩家列表）
    public function getPlayerHistoryWithPlayerLists($server_id) {
        // 构建SQL查询，包含玩家列表
        $sql = "SELECT DATE_FORMAT(record_time, '%Y-%m-%d %H:%i:%s') as time_label, 
               players_online, 
               player_list_json
               FROM player_history 
               WHERE server_id = ? 
               ORDER BY record_time ASC";
        
        $stmt = $this->conn->prepare($sql);
        
        if ($stmt === false) {
            return array('success' => false, 'error' => '准备SQL语句失败: ' . $this->conn->error);
        }
        
        $stmt->bind_param("i", $server_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        $data = array();
        $labels = array();
        $values = array();
        $playerLists = array();
        
        if ($result && $result->num_rows > 0) {
            $prev_players = null; // 上一条记录的在线人数
            $first_record = true; // 是否是第一条记录
            $last_time_label = ''; // 最后一条记录的时间标签
            $last_players = ''; // 最后一条记录的在线人数
            $last_player_list = null; // 最后一条记录的玩家列表
            $temp_rows = array(); // 临时存储所有记录
            
            // 先将所有记录存储到临时数组
            while ($row = $result->fetch_assoc()) {
                $temp_rows[] = $row;
                $last_time_label = $row['time_label'];
                $last_players = $row['players_online'];
                $last_player_list = $row['player_list_json'];
            }
            
            // 遍历临时数组，只保留在线人数变化的记录
            foreach ($temp_rows as $index => $row) {
                $current_players = $row['players_online'];
                $current_player_list = $row['player_list_json'];
                
                // 保留第一条记录
                if ($first_record) {
                    $labels[] = $row['time_label'];
                    $values[] = $current_players;
                    $playerLists[] = $current_player_list;
                    $prev_players = $current_players;
                    $first_record = false;
                    continue;
                }
                
                // 如果在线人数发生变化，保留当前记录
                if ($current_players != $prev_players) {
                    $labels[] = $row['time_label'];
                    $values[] = $current_players;
                    $playerLists[] = $current_player_list;
                    $prev_players = $current_players;
                }
                // 确保保留最后一条记录（即使和前一条相同）
                else if ($index == count($temp_rows) - 1) {
                    $labels[] = $row['time_label'];
                    $values[] = $current_players;
                    $playerLists[] = $current_player_list;
                }
            }
        }
        
        $data['labels'] = $labels;
        $data['values'] = $values;
        $data['playerLists'] = $playerLists; // 添加玩家列表数据
        
        return array('success' => true, 'data' => $data);
    }
}

// 获取请求参数
$server_id = isset($_GET['server_id']) ? intval($_GET['server_id']) : 0;
$view_mode = isset($_GET['view_mode']) ? $_GET['view_mode'] : 'raw'; // 默认为raw
$date = isset($_GET['date']) ? $_GET['date'] : ''; // 日期参数

// 验证参数
if ($server_id <= 0) {
    die(json_encode(array('success' => false, 'error' => '无效的服务器ID')));
}

// 创建数据库连接
$db = new Database();

// 根据视图模式获取数据
if ($view_mode === 'date' && !empty($date)) {
    // 获取指定日期的数据（包含玩家列表）
    $result = $db->getPlayerHistoryByDate($server_id, $date);
} else {
    // 默认获取所有原始数据（包含玩家列表）
    $result = $db->getPlayerHistoryWithPlayerLists($server_id);
}

// 输出JSON响应
die(json_encode($result));
?>