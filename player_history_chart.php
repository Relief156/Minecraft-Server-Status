<?php
// 连接数据库并获取所有player_history数据，直接渲染在线人数记录图

// 检查是否已安装
if (!file_exists('installed.lock')) {
    die('系统尚未安装，请先完成安装设置');
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
            die("数据库连接失败: " . $this->conn->connect_error);
        }
    }
    
    // 获取数据库连接
    public function getConnection() {
        return $this->conn;
    }
    
    // 获取所有服务器ID和名称
    public function getAllServers() {
        $sql = "SELECT id, name FROM servers ORDER BY name ASC";
        $result = $this->conn->query($sql);
        $servers = array();
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $servers[$row['id']] = $row['name'];
            }
        }
        
        return $servers;
    }
    
    // 获取服务器的所有历史数据
    public function getPlayerHistory($server_id) {
        $sql = "SELECT DATE_FORMAT(record_time, '%Y-%m-%d %H:%i:%s') as time_label, 
               players_online 
               FROM player_history 
               WHERE server_id = ? 
               ORDER BY record_time ASC";
        
        $stmt = $this->conn->prepare($sql);
        
        if ($stmt === false) {
            error_log("获取玩家历史数据prepare语句失败: " . $this->conn->error);
            return false;
        }
        
        $stmt->bind_param("i", $server_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        return $result;
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
            error_log("获取聚合玩家历史数据prepare语句失败: " . $this->conn->error);
            return false;
        }
        
        $stmt->bind_param("i", $server_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        return $result;
    }
}

// 创建数据库连接
$db = new Database();

// 获取所有服务器
$servers = $db->getAllServers();

// 获取请求的服务器ID
$server_id = isset($_GET['server_id']) ? intval($_GET['server_id']) : (count($servers) > 0 ? array_key_first($servers) : 0);

// 获取聚合方式（raw原始数据或aggregated聚合数据）
$view_mode = isset($_GET['view_mode']) ? $_GET['view_mode'] : 'aggregated';

// 获取时间间隔
$interval = isset($_GET['interval']) ? $_GET['interval'] : 'hour';

// 获取数据
if ($view_mode === 'raw') {
    $result = $db->getPlayerHistory($server_id);
} else {
    $result = $db->getAggregatedPlayerHistory($server_id, $interval);
}

// 处理数据
$labels = array();
$values = array();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $labels[] = $row['time_label'];
        if ($view_mode === 'raw') {
            $values[] = $row['players_online'];
        } else {
            $values[] = round($row['avg_players']);
        }
    }
} else {
    // 如果没有数据，显示友好提示
    $labels = array('暂无数据');
    $values = array(0);
}

// 转换数据为JSON格式
$chart_labels = json_encode($labels);
$chart_values = json_encode($values);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>在线人数历史记录</title>
    <!-- 引入Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }
        .chart-container {
            position: relative;
            height: 500px;
            width: 100%;
            margin-bottom: 20px;
        }
        .controls {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .control-group {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        select, button {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: white;
            cursor: pointer;
            font-size: 14px;
        }
        button:hover {
            background-color: #f0f0f0;
        }
        .data-info {
            text-align: center;
            color: #666;
            font-size: 14px;
            margin-top: 10px;
        }
        @media (max-width: 768px) {
            .chart-container {
                height: 300px;
            }
            .controls {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>在线人数历史记录图表</h1>
        
        <div class="controls">
            <div class="control-group">
                <label for="server-select">选择服务器:</label>
                <select id="server-select">
                    <?php foreach ($servers as $id => $name): ?>
                        <option value="<?php echo $id; ?>" <?php echo ($id == $server_id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="control-group">
                <label for="view-mode-select">查看模式:</label>
                <select id="view-mode-select">
                    <option value="aggregated" <?php echo ($view_mode === 'aggregated') ? 'selected' : ''; ?>>聚合数据</option>
                    <option value="raw" <?php echo ($view_mode === 'raw') ? 'selected' : ''; ?>>原始数据</option>
                </select>
            </div>
            
            <div class="control-group">
                <label for="interval-select">时间间隔:</label>
                <select id="interval-select" <?php echo ($view_mode !== 'aggregated') ? 'disabled' : ''; ?>>
                    <option value="hour" <?php echo ($interval === 'hour') ? 'selected' : ''; ?>>按小时</option>
                    <option value="day" <?php echo ($interval === 'day') ? 'selected' : ''; ?>>按天</option>
                </select>
            </div>
            
            <button id="refresh-btn">刷新图表</button>
        </div>
        
        <div class="chart-container">
            <canvas id="playerHistoryChart"></canvas>
        </div>
        
        <div class="data-info">
            显示数据点数量: <?php echo count($labels); ?>
            <?php if (count($labels) > 0 && $labels[0] !== '暂无数据'): ?>
                | 时间范围: <?php echo $labels[0]; ?> 至 <?php echo end($labels); ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // 初始化图表
        const ctx = document.getElementById('playerHistoryChart').getContext('2d');
        const chartData = {
            labels: <?php echo $chart_labels; ?>,
            datasets: [{
                label: '在线人数',
                data: <?php echo $chart_values; ?>,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.3,
                fill: true,
                pointRadius: 3,
                pointHoverRadius: 5
            }]
        };
        
        const chartConfig = {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += context.parsed.y + ' 人';
                                return label;
                            }
                        }
                    },
                    title: {
                        display: true,
                        text: '玩家在线人数历史记录',
                        font: {
                            size: 16
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: '在线人数'
                        },
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: '时间'
                        },
                        ticks: {
                            autoSkip: true,
                            maxRotation: 45,
                            minRotation: 45,
                            maxTicksLimit: 20
                        }
                    }
                },
                interaction: {
                    mode: 'nearest',
                    intersect: false,
                    axis: 'x'
                },
                animation: {
                    duration: 1000,
                    easing: 'easeOutQuart'
                }
            }
        };
        
        // 创建图表实例
        const playerHistoryChart = new Chart(ctx, chartConfig);
        
        // 添加事件监听
        document.getElementById('refresh-btn').addEventListener('click', function() {
            refreshChart();
        });
        
        document.getElementById('server-select').addEventListener('change', function() {
            refreshChart();
        });
        
        document.getElementById('view-mode-select').addEventListener('change', function() {
            // 根据查看模式启用或禁用时间间隔选择
            const intervalSelect = document.getElementById('interval-select');
            intervalSelect.disabled = this.value !== 'aggregated';
            refreshChart();
        });
        
        document.getElementById('interval-select').addEventListener('change', function() {
            refreshChart();
        });
        
        // 刷新图表函数
        function refreshChart() {
            const serverId = document.getElementById('server-select').value;
            const viewMode = document.getElementById('view-mode-select').value;
            const interval = document.getElementById('interval-select').value;
            
            // 构建查询参数
            const params = new URLSearchParams();
            params.append('server_id', serverId);
            params.append('view_mode', viewMode);
            params.append('interval', interval);
            
            // 重新加载页面
            window.location.href = 'player_history_chart.php?' + params.toString();
        }
        
        // 添加键盘快捷键
        document.addEventListener('keydown', function(e) {
            // F5键刷新图表
            if (e.key === 'F5') {
                refreshChart();
            }
        });
    </script>
</body>
</html>