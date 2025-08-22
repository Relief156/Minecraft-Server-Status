<?php
// 首页 - 显示服务器状态

// 检查是否已安装
if (!file_exists('installed.lock')) {
    header('Location: install.php');
    exit;
}

// 引入配置文件
require_once 'config.php';
require_once 'db.php';
require_once 'api.php';

// 引入Chart.js用于图表展示
// Chart.js是一个轻量级的JavaScript图表库，用于绘制服务器在线人数历史图表
$chartjs_script = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';

// 连接数据库
$db = new Database();

// 获取所有服务器（使用默认排序，即按权重降序）
$servers = $db->getAllServers();

// 创建API实例
$minecraft_api = new MinecraftAPI();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_TITLE ?></title>
    <?= $chartjs_script ?>
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
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
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
            font-size: 2.5em;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .login-link {
            text-align: right;
            margin-bottom: 20px;
        }
        
        .login-link a {
            color: #2196F3;
            text-decoration: none;
            font-weight: 500;
            padding: 8px 15px;
            border-radius: 4px;
            transition: all 0.3s ease;
            background-color: rgba(255, 255, 255, 0.9);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .login-link a:hover {
            background-color: #2196F3;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(33, 150, 243, 0.3);
        }
        
        /* 服务器网格布局 */
        .server-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        /* 服务器卡片样式 */
        .server-card {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            overflow: visible;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            border: 1px solid #e0e0e0;
            z-index: 1;
        }
        
        .server-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        /* 服务器头部样式 - 根据状态变化 */
        .server-header {
            padding: 15px;
            color: white;
            display: flex;
            align-items: center;
            min-height: 80px;
            transition: background-color 0.3s ease;
        }
        
        .server-header.online {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
        }
        
        .server-header.offline {
            background: linear-gradient(135deg, #f44336 0%, #da190b 100%);
        }
        
        /* 基岩版服务器特殊样式 */
        .server-header.bedrock {
            background: linear-gradient(135deg, #2196F3 0%, #0b7dda 100%);
        }
        
        .server-icon {
            width: 64px;
            height: 64px;
            border-radius: 10px;
            margin-right: 15px;
            background-color: #fff;
            padding: 3px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease;
        }
        
        .server-card:hover .server-icon {
            transform: scale(1.05);
        }
        
        .server-name {
            font-size: 18px;
            font-weight: bold;
            flex: 1;
        }
        
        .server-status {
            margin-left: auto;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            background-color: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
        }
        
        /* 服务器主体内容 */
        .server-body {
            padding: 15px;
        }
        
        .server-info {
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }
        
        .server-card:hover .server-info {
            transform: translateX(5px);
        }
        
        .server-info label {
            font-weight: bold;
            color: #666;
            display: block;
            margin-bottom: 5px;
            font-size: 0.9em;
        }
        
        .server-info p {
            margin: 0;
            color: #333;
            font-size: 1.05em;
        }
        
        /* 服务器类型标签 */
        .server-type-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
            margin-top: 5px;
        }
        
        .server-type-badge.java {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .server-type-badge.bedrock {
            background-color: #e3f2fd;
            color: #1565c0;
        }
        
        /* 改进的MOTD显示 */
        .server-motd {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: #ecf0f1;
            padding: 15px;
            border-radius: 8px;
            margin-top: auto;
            margin-bottom: 0;
            font-style: normal;
            line-height: 1.6;
            white-space: normal;
            overflow-wrap: break-word;
            text-align: center;
            box-shadow: inset 0 2px 5px rgba(0, 0, 0, 0.3);
            border: 1px solid #1a252f;
            height: 80px; /* 设置固定高度 */
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        /* 服务器卡片样式优化 */
        .server-card {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            overflow: visible;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            border: 1px solid #e0e0e0;
            z-index: 1;
            display: flex;
            flex-direction: column;
            min-height: 300px; /* 设置最小高度 */
        }
        
        .server-body {
            padding: 15px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .player-list {
            display: none; /* 隐藏玩家列表区域 */
        }
        
        .player-list h4 {
            color: #fff;
            font-size: 0.9em;
            margin-bottom: 8px;
            font-weight: normal;
        }
        
        .player-names {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 5px;
        }
        
        .player-tag {
            background-color: rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            transition: background-color 0.3s ease;
        }
        
        .player-tag:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }
        
        .no-players {
            color: rgba(255, 255, 255, 0.7);
            font-style: italic;
            font-size: 0.9em;
        }
        
        /* 复制IP按钮 */
        .copy-ip-btn {
            background-color: #2196F3;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
            margin-top: 5px;
            transition: all 0.3s ease;
        }
        
        .copy-ip-btn:hover {
            background-color: #1976D2;
            transform: translateY(-1px);
        }
        
        .copy-ip-btn.copied {
            background-color: #4CAF50;
        }
        
        /* 无服务器提示 */
        .no-servers {
            text-align: center;
            padding: 60px 40px;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-top: 40px;
        }
        
        .no-servers h2 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .no-servers p {
            color: #7f8c8d;
            font-size: 1.1em;
        }
        
        /* 响应式设计 */
        @media (max-width: 768px) {
            .server-grid {
                grid-template-columns: 1fr;
            }
            
            h1 {
                font-size: 2em;
            }
            
            .container {
                padding: 10px;
            }
        }
        
        /* 加载动画 */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 style="cursor: pointer;"><a href="login.php" style="color: inherit; text-decoration: none;"><?= SITE_TITLE ?></a></h1>

        <?php if ($servers->num_rows > 0): ?>
            
            <div class="server-grid">
                <?php while ($server = $servers->fetch_assoc()): ?>
                    <?php
                    // 调试：打印服务器数据中的server_type字段值
                    $server_type_value = isset($server['server_type']) ? $server['server_type'] : '未设置';
                    // 确保即使字段存在但为空，也使用java作为默认值
                    $server_type = !empty($server['server_type']) ? $server['server_type'] : 'java';
                    // 调试：记录使用的服务器类型
                    error_log('服务器名称: ' . $server['name'] . ', 数据库中的server_type值: ' . $server_type_value . ', 使用的类型: ' . $server_type);
                    // 获取服务器状态，传递服务器类型
                    $status = $minecraft_api->getServerStatus($server['address'], $server_type);
                    
                    // 保存在线人数历史数据
                    // 只在服务器在线时保存数据
                    if (isset($status['online']) && $status['online']) {
                        // 获取玩家列表（如果存在）
                        $player_list = isset($status['player_list']) ? $status['player_list'] : null;
                        $db->savePlayerHistory($server['id'], $status['players_online'], $player_list);
                    }
                    ?>
                    <div class="server-card" data-server-id="<?= $server['id'] ?>">
                        <div class="server-header<?= isset($status['online']) && $status['online'] ? ' online' : ' offline' ?><?= $server_type === 'bedrock' ? ' bedrock' : '' ?>">
                            <?php if (isset($status['server_icon']) && !empty($status['server_icon'])): ?>
                                <img src="<?= $status['server_icon'] ?>" alt="Server Icon" class="server-icon" loading="lazy">
                            <?php else: ?>
                                <div class="server-icon"></div>
                            <?php endif; ?>
                            <div class="server-name"><?= $server['name'] ?></div>
                            <div class="server-status">
                                <?= isset($status['online']) && $status['online'] ? '在线' : '离线' ?>
                            </div>
                        
                        </div>
                        <div class="server-body">
                            <div class="server-info">
                                <label>地址</label>
                                <p><?= $server['address'] ?></p>
                            </div>
                            <div class="server-info">
                                <label>类型</label>
                                <span class="server-type-badge <?= $server_type ?>"><?= $server_type === 'java' ? 'Java' : '基岩' ?></span>
                            </div>
                            
                            <?php if (isset($status['online']) && $status['online']): ?>
                                <div class="server-info">
                                <label>在线人数</label>
                                <p><?= $status['players_online'] ?> / <?= $status['players_max'] ?></p>
                            </div>
                                <div class="server-info">
                                    <label>版本</label>
                                    <p><?= $status['version'] ?></p>
                                </div>
                                <div class="server-motd">
                                    <?= isset($status['motd_html']) ? $status['motd_html'] : $status['motd'] ?>
                                </div>
                                
                                <!-- 玩家列表 -->
                                <div class="player-list">
                                    <h4>在线玩家</h4>
                                    <div class="player-names" id="players-<?= $server['id'] ?>">
                                        <?php if (isset($status['player_list']) && !empty($status['player_list'])): ?>
                                            <?php foreach ($status['player_list'] as $player): ?>
                                                <span class="player-tag"><?= htmlspecialchars($player) ?></span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="no-players">暂无在线玩家</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="server-info">
                                    <label>连接信息</label>
                                    <p>
                                        <?php if (isset($status['ip_address'])): ?>
                                            IP: <?= $status['ip_address'] ?><br>
                                        <?php endif; ?>
                                        <?php if (isset($status['hostname']) && $status['hostname'] !== $status['server_address']): ?>
                                            主机名: <?= $status['hostname'] ?><br>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="server-motd">
                                    <?= isset($status['motd_html']) ? $status['motd_html'] : $status['motd'] ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        
                        <!-- 历史在线人数图表区域 - 默认隐藏 -->
                        <div class="chart-container" data-server-id="<?= $server['id'] ?>" style="display: none;">
                            <?php if (isset($status['online']) && $status['online']): ?>
                                <?php if ($db->getShowPlayerHistory($server['id'])): ?>
                                    <h4>在线人数历史数据</h4>
                                    <div class="chart-wrapper">
                                        <canvas id="playerChart<?= $server['id'] ?>" width="400" height="200"></canvas>
                                    </div>
                                    <div class="chart-controls">
                                        <button class="chart-btn" data-server-id="<?= $server['id'] ?>" data-days="1">今日</button>
                                        <button class="chart-btn" data-server-id="<?= $server['id'] ?>" data-days="7">本周</button>
                                        <button class="chart-btn" data-server-id="<?= $server['id'] ?>" data-days="30">本月</button>
                                        <button class="chart-btn" data-server-id="<?= $server['id'] ?>" data-days="0">所有记录</button>
                                    </div>
                            <?php else: ?>
                                <div class="no-history-message">
                                    <p>服务器离线，无法查看历史数据</p>
                                </div>
                            <?php endif; ?>
                            <?php else: ?>
                                <div class="no-history-message">
                                    <p>服务器离线，无法查看历史数据</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="no-servers">
                <h2>暂无服务器数据</h2>
                <p>请联系管理员添加Minecraft服务器</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- 图表模态框 -->
    <div id="chartModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">服务器在线人数历史数据</h3>
                <button id="closeModal" class="close-btn">×</button>
            </div>
            <div id="modalBody" class="modal-body">
                <!-- 图表内容将通过JavaScript动态填充 -->
            </div>
        </div>
    </div>

    <!-- 修复后的图表渲染JavaScript -->
    <script>
        // 等待DOM加载完成
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM已加载，初始化图表功能');
            
            // 获取模态框元素
            const chartModal = document.getElementById('chartModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalBody = document.getElementById('modalBody');
            const closeModalBtn = document.getElementById('closeModal');
            
            // 检查模态框元素是否存在
            console.log('模态框元素存在状态：', {
                chartModal: !!chartModal,
                modalTitle: !!modalTitle,
                modalBody: !!modalBody,
                closeModalBtn: !!closeModalBtn
            });
            

            
            // 关闭模态框按钮点击事件
            if (closeModalBtn) {
                closeModalBtn.addEventListener('click', function() {
                    hideChartModal();
                });
            }
            
            // 点击模态框外部区域关闭模态框
            if (chartModal) {
                chartModal.addEventListener('click', function(e) {
                    if (e.target === chartModal) {
                        hideChartModal();
                    }
                });
            }
            
            // 为服务器卡片添加点击事件，点击卡片时显示图表
            const serverCards = document.querySelectorAll('.server-card');
            serverCards.forEach(function(card) {
                const serverId = card.getAttribute('data-server-id');
                const serverName = card.querySelector('.server-name').textContent;
                
                card.addEventListener('click', function(e) {
                    console.log('点击了服务器卡片，显示图表，服务器ID:', serverId, '名称:', serverName);
                    showChartModal(serverId, serverName);
                });
            });
        });
        
        // 显示图表模态框函数
        function showChartModal(serverId, serverName) {
            console.log('显示图表模态框，服务器ID:', serverId, '名称:', serverName);
            
            // 获取模态框元素
            const chartModal = document.getElementById('chartModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalBody = document.getElementById('modalBody');
            
            if (!chartModal || !modalTitle || !modalBody) {
                console.error('模态框元素不存在！');
                return;
            }
            
            // 获取服务器卡片
            const serverCard = document.querySelector(`.server-card[data-server-id="${serverId}"]`);
            const isOnline = serverCard ? serverCard.querySelector('.server-header').classList.contains('online') : false;
            
            // 清空并设置模态框标题，添加服务器图标
            modalTitle.textContent = '';
            
            // 创建标题图标元素
            const titleIcon = document.createElement('div');
            titleIcon.className = 'modal-title-icon';
            
            // 获取服务器图标并添加到标题中
            if (serverCard) {
                const serverIcon = serverCard.querySelector('.server-icon');
                if (serverIcon && serverIcon.tagName === 'IMG') {
                    const iconImg = document.createElement('img');
                    iconImg.src = serverIcon.src;
                    iconImg.alt = 'Server Icon';
                    iconImg.style.width = '100%';
                    iconImg.style.height = '100%';
                    iconImg.style.objectFit = 'cover';
                    titleIcon.appendChild(iconImg);
                }
            }
            
            // 创建标题文本节点
            const titleText = document.createTextNode(serverName + ' - 在线人数历史数据');
            
            // 添加图标和文本到标题
            modalTitle.appendChild(titleIcon);
            modalTitle.appendChild(titleText);
            
            console.log('服务器在线状态:', isOnline);
            
            // 清空模态框内容
            modalBody.innerHTML = '';

            // 无论服务器是否在线，都显示图表和日期选择器
            modalBody.innerHTML = `
                <div class="chart-controls">
                    <div class="date-selector">
                        <label for="selectedDate">选择日期：</label>
                        <input type="date" id="selectedDate" class="date-input">
                    </div>
                </div>
                <div class="chart-wrapper">
                    <canvas id="modalPlayerChart" width="400" height="300"></canvas>
                </div>
            `;

            // 设置日期输入框的最大日期为今天，并默认选择今天
            const today = new Date().toISOString().split('T')[0];
            const dateInput = document.getElementById('selectedDate');
            dateInput.max = today;
            dateInput.value = today;

            // 定义日期选择处理函数
            function handleDateSelection() {
                const selectedDate = document.getElementById('selectedDate').value;
                if (selectedDate) {
                    console.log('应用日期筛选:', selectedDate, '服务器ID:', serverId);
                    loadModalChartDataForDate(serverId, selectedDate);
                }
            }

            // 添加日期输入框变化事件，实现自动查询
            if (dateInput) {
                dateInput.addEventListener('change', handleDateSelection);
            }

            // 初始化图表
            initModalChart(serverId, 0);

            // 自动加载今天的数据
            handleDateSelection();
            
            // 显示模态框 - 使用强制显示的方式
            chartModal.style.display = 'flex';
            chartModal.style.opacity = '1';
            chartModal.style.zIndex = '2147483647'; // 设置最高层级
            console.log('模态框显示状态:', chartModal.style.display);
        }
        
        // 隐藏图表模态框函数
        function hideChartModal() {
            const chartModal = document.getElementById('chartModal');
            if (chartModal) {
                chartModal.style.display = 'none';
                console.log('模态框已隐藏');
            }
        }
        
        // 当前模态框中的图表实例
        let currentModalChart = null;
        
        // 存储玩家列表数据的全局变量
        let modalPlayerLists = [];
        
        // 初始化模态框中的图表函数
        function initModalChart(serverId, days) {
            console.log('初始化图表，服务器ID:', serverId, '天数:', days);
            
            // 检查Chart.js是否可用
            if (typeof Chart === 'undefined') {
                console.error('Chart.js未定义！尝试显示静态数据...');
                
                // 直接生成并显示模拟数据
                const mockData = generateMockData(days);
                const modalBody = document.getElementById('modalBody');
                
                if (modalBody) {
                    // 创建静态数据展示
                    let dataHtml = '<div class="static-chart-data">';
                    dataHtml += '<h4>Chart.js未加载，显示静态数据</h4>';
                    dataHtml += '<table class="data-table">';
                    dataHtml += '<tr><th>时间</th><th>在线人数</th></tr>';
                    
                    for (let i = 0; i < Math.min(10, mockData.labels.length); i++) {
                        dataHtml += `<tr><td>${mockData.labels[i]}</td><td>${mockData.values[i]}</td></tr>`;
                    }
                    
                    if (mockData.labels.length > 10) {
                        dataHtml += `<tr><td colspan="2">... 还有 ${mockData.labels.length - 10} 条数据</td></tr>`;
                    }
                    
                    dataHtml += '</table>';
                    dataHtml += '<p class="status-warning">请检查网络连接或Chart.js CDN的可访问性</p>';
                    dataHtml += '</div>';
                    
                    modalBody.innerHTML = dataHtml;
                }
                
                // 尝试重新加载Chart.js
                try {
                    const script = document.createElement('script');
                    script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
                    script.onload = function() {
                            console.log('Chart.js重新加载成功！');
                            // 重新初始化图表，使用所有记录数据
                            setTimeout(() => initModalChart(serverId, 0), 500);
                        };
                    document.head.appendChild(script);
                } catch (e) {
                    console.error('尝试重新加载Chart.js时出错:', e);
                }
                
                return;
            }
            
            // 销毁之前的图表实例
            if (currentModalChart) {
                currentModalChart.destroy();
                console.log('已销毁之前的图表实例');
            }
            
            const ctx = document.getElementById('modalPlayerChart');
            if (!ctx) {
                console.error('图表画布元素不存在！');
                return;
            }
            
            // 重置玩家列表数据
            modalPlayerLists = [];
            
            // 设置图表配置
            const config = {
                type: 'line',
                data: {
                    labels: ['加载中...'],
                    datasets: [{
                        label: '在线人数',
                        data: [0],
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: 'rgba(255, 255, 255, 0.2)',
                            borderWidth: 1,
                            padding: 10,
                            displayColors: false,
                            callbacks: {
                                title: function(context) {
                                    return `时间: ${context[0].label}`;
                                },
                                label: function(context) {
                                    // 详细调试信息
                                    console.log('Tooltip上下文:', context);
                                    console.log('modalPlayerLists类型:', typeof modalPlayerLists, '长度:', modalPlayerLists.length);
                                    console.log('当前索引:', context.dataIndex);
                                    
                                    // 显示在线人数
                                    const value = context.parsed.y || 0;
                                    return `在线人数：${value}`;
                                },
                                // 使用afterLabel回调来显示在线玩家信息，确保它在新行显示
                                afterLabel: function(context) {
                                    try {
                                        // 检查索引是否有效
                                        if (context.dataIndex !== undefined && 
                                            Array.isArray(modalPlayerLists) && 
                                            context.dataIndex >= 0 && 
                                            context.dataIndex < modalPlayerLists.length) {
                                            
                                            const playerList = modalPlayerLists[context.dataIndex];
                                            console.log('当前玩家列表数据:', playerList);
                                            
                                            // 处理玩家列表数据
                                            if (playerList) {
                                                // 尝试解析JSON字符串
                                                let parsedPlayers = playerList;
                                                if (typeof playerList === 'string') {
                                                    try {
                                                        parsedPlayers = JSON.parse(playerList);
                                                    } catch (e) {
                                                        console.log('玩家列表不是JSON字符串，直接使用:', playerList);
                                                    }
                                                }
                                                
                                                // 格式化玩家列表显示
                                                if (Array.isArray(parsedPlayers) && parsedPlayers.length > 0) {
                                                    // 确保所有元素都是字符串
                                                    const playerNames = parsedPlayers.map(p => 
                                                        typeof p === 'string' ? p : 
                                                        typeof p === 'object' ? JSON.stringify(p) : 
                                                        String(p)
                                                    );
                                                    // 每个玩家单独一行显示
                                                    return `在线玩家：\n${playerNames.join('\n')}`;
                                                } else if (Array.isArray(parsedPlayers) && parsedPlayers.length === 0) {
                                                    return '在线玩家：无';
                                                } else if (parsedPlayers) {
                                                    // 非数组格式，尝试显示原始数据
                                                    return `玩家数据：${String(parsedPlayers).substring(0, 100)}`;
                                                }
                                            } else {
                                                console.log('当前索引没有对应的玩家列表数据');
                                            }
                                        } else {
                                            console.log('索引无效或玩家列表为空/非数组');
                                        }
                                    } catch (e) {
                                        console.error('处理玩家列表时出错:', e);
                                        return `错误: ${e.message.substring(0, 50)}`;
                                    }
                                    return '';
                                }
                            }
                        },
                        title: {
                            display: false
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
                                stepSize: 1
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: '时间'
                            }
                        }
                    }
                }
            };
            
            // 创建图表实例
            try {
                currentModalChart = new Chart(ctx, config);
                console.log('图表实例创建成功');
            } catch (e) {
                console.error('创建图表失败:', e);
                // 显示错误信息和静态数据
                const modalBody = document.getElementById('modalBody');
                if (modalBody) {
                    modalBody.innerHTML = '<p class="status-error">创建图表失败：' + e.message + '</p>';
                    
                    // 添加静态数据表格
                    const mockData = generateMockData(days);
                    let dataHtml = '<div class="static-chart-data">';
                    dataHtml += '<table class="data-table">';
                    dataHtml += '<tr><th>时间</th><th>在线人数</th></tr>';
                    
                    for (let i = 0; i < Math.min(10, mockData.labels.length); i++) {
                        dataHtml += `<tr><td>${mockData.labels[i]}</td><td>${mockData.values[i]}</td></tr>`;
                    }
                    
                    dataHtml += '</table>';
                    dataHtml += '</div>';
                    
                    modalBody.innerHTML += dataHtml;
                }
                return;
            }
            
            // 加载数据
            loadModalChartData(serverId, days);
        }
        
        // 更新模态框中的图表函数
        function updateModalChart(serverId, days) {
            console.log('更新图表，服务器ID:', serverId, '天数:', days);
            loadModalChartData(serverId, days);
        }
        
        // 加载模态框图表数据函数 - 按天数
        function loadModalChartData(serverId, days) {
            console.log('加载图表数据，服务器ID:', serverId, '天数:', days);
        }
        
        // 加载模态框图表数据函数 - 按日期
        function loadModalChartDataForDate(serverId, selectedDate) {
            console.log('加载日期图表数据，服务器ID:', serverId, '日期:', selectedDate);
            
            try {
                // 通过API获取指定日期的数据
                const data = getHistoricalDataForDate(serverId, selectedDate);
                
                // 更新图表数据
                if (currentModalChart && data) {
                    currentModalChart.data.labels = data.labels;
                    currentModalChart.data.datasets[0].data = data.values;
                    currentModalChart.update();
                    console.log('图表数据按日期更新成功，数据点数量:', data.values.length);
                }
            } catch (e) {
                console.error('加载按日期图表数据失败:', e);
            }
        }
        
        // 获取指定日期的历史数据函数
        function getHistoricalDataForDate(serverId, selectedDate) {
            console.log('获取指定日期的历史数据，服务器ID:', serverId, '日期:', selectedDate);
            
            try {
                // 使用同步XMLHttpRequest
                const xhr = new XMLHttpRequest();
                
                // 构建请求URL，调用get_player_data.php脚本，并添加日期参数
                const view_mode = 'date';
                const url = `get_player_data.php?server_id=${serverId}&view_mode=${view_mode}&date=${selectedDate}`;
                
                // 添加缓存控制参数
                const timestamp = new Date().getTime();
                const fullUrl = url + '&_=' + timestamp;
                
                console.log('请求URL:', fullUrl);
                
                xhr.open('GET', fullUrl, false);
                xhr.send();
                
                console.log('数据请求响应状态码:', xhr.status);
                
                if (xhr.status === 200) {
                    console.log('数据请求响应内容:', xhr.responseText);
                    
                    try {
                        const response = JSON.parse(xhr.responseText);
                        
                        if (response.success && response.data && response.data.labels && response.data.values) {
                                console.log('历史数据格式正确，返回数据');
                                // 检查数据是否为空
                                if (response.data.labels.length > 0) {
                                    // 保存玩家列表数据
                                    if (response.data.playerLists) {
                                        modalPlayerLists = Array.isArray(response.data.playerLists) ? response.data.playerLists : [];
                                        console.log('玩家列表数据已保存，数量:', modalPlayerLists.length);
                                    } else {
                                        modalPlayerLists = [];
                                        console.log('没有找到玩家列表数据');
                                    }
                                    
                                    return {
                                        labels: response.data.labels,
                                        values: response.data.values
                                    };
                                } else {
                                    console.log('返回了空数据，使用0值数据');
                                    modalPlayerLists = [];
                                    return generateEmptyData(); // 返回近两小时的0值数据
                                }
                            } else {
                            console.error('获取历史数据失败:', response.error || '未知错误');
                            // 显示错误提示给用户
                            const modalBody = document.getElementById('modalBody');
                            if (modalBody) {
                                modalBody.innerHTML = '<p class="status-error">获取数据失败：' + (response.error || '未知错误') + '</p>';
                            }
                            return generateEmptyData(); // 返回近两小时的0值数据
                        }
                    } catch (e) {
                        console.error('解析历史数据失败:', e);
                        // 显示解析错误提示给用户
                        const modalBody = document.getElementById('modalBody');
                        if (modalBody) {
                            modalBody.innerHTML = '<p class="status-error">数据解析失败：' + e.message + '</p>';
                        }
                        return generateEmptyData(); // 返回近两小时的0值数据
                    }
                } else {
                    console.error('数据请求失败，状态码:', xhr.status);
                    // 显示请求错误提示给用户
                    const modalBody = document.getElementById('modalBody');
                    if (modalBody) {
                        modalBody.innerHTML = '<p class="status-error">数据请求失败，状态码：' + xhr.status + '</p>';
                    }
                    return generateEmptyData(); // 返回近两小时的0值数据
                }
            } catch (e) {
                console.error('获取历史数据时发生异常:', e);
                // 显示异常提示给用户
                const modalBody = document.getElementById('modalBody');
                if (modalBody) {
                    modalBody.innerHTML = '<p class="status-error">获取数据时发生异常：' + e.message + '</p>';
                }
                return generateEmptyData(); // 返回近两小时的0值数据
            }
            
            try {
                // 通过API获取历史数据
                const data = getHistoricalData(serverId, days);
                
                // 更新图表数据
                if (currentModalChart && data) {
                    currentModalChart.data.labels = data.labels;
                    currentModalChart.data.datasets[0].data = data.values;
                    currentModalChart.update();
                    console.log('图表数据更新成功，数据点数量:', data.values.length);
                }
            } catch (e) {
                console.error('加载图表数据失败:', e);
            }
        }
        
        // 获取历史数据函数 - 使用原始数据绘图，不进行聚合
        function getHistoricalData(serverId, days) {
            console.log('获取历史数据，服务器ID:', serverId, '天数:', days);
            
            try {
                // 使用同步XMLHttpRequest
                const xhr = new XMLHttpRequest();
                
                // 构建请求URL，调用get_player_data.php脚本
                // 所有情况下都使用原始数据（view_mode=raw）
                const view_mode = 'raw'; // 直接使用原始数据
                
                // 构建URL
                const url = `get_player_data.php?server_id=${serverId}&view_mode=${view_mode}`;
                
                // 添加缓存控制参数
                const timestamp = new Date().getTime();
                const fullUrl = url + '&_=' + timestamp;
                
                console.log('请求URL:', fullUrl);
                
                xhr.open('GET', fullUrl, false);
                xhr.send();
                
                console.log('数据请求响应状态码:', xhr.status);
                
                if (xhr.status === 200) {
                    console.log('数据请求响应内容:', xhr.responseText);
                    
                    try {
                        const response = JSON.parse(xhr.responseText);
                        
                        if (response.success && response.data && response.data.labels && response.data.values) {
                            console.log('历史数据格式正确，返回数据');
                            // 检查数据是否为空
                            if (response.data.labels.length > 0) {
                                // 保存玩家列表数据
                                if (response.data.playerLists) {
                                    modalPlayerLists = Array.isArray(response.data.playerLists) ? response.data.playerLists : [];
                                    console.log('玩家列表数据已保存，数量:', modalPlayerLists.length);
                                } else {
                                    modalPlayerLists = [];
                                    console.log('没有找到玩家列表数据');
                                }
                                
                                return {
                                    labels: response.data.labels,
                                    values: response.data.values
                                };
                            } else {
                                console.log('返回了空数据，使用0值数据');
                                modalPlayerLists = [];
                                return generateEmptyData(); // 返回近两小时的0值数据
                            }
                        } else {
                            console.error('获取历史数据失败:', response.error || '未知错误');
                            // 显示错误提示给用户
                            const modalBody = document.getElementById('modalBody');
                            if (modalBody) {
                                modalBody.innerHTML = '<p class="status-error">获取数据失败：' + (response.error || '未知错误') + '</p>';
                            }
                            return generateEmptyData(); // 返回近两小时的0值数据
                        }
                    } catch (e) {
                        console.error('解析历史数据失败:', e);
                        // 显示解析错误提示给用户
                        const modalBody = document.getElementById('modalBody');
                        if (modalBody) {
                            modalBody.innerHTML = '<p class="status-error">数据解析失败：' + e.message + '</p>';
                        }
                        return generateEmptyData(); // 返回近两小时的0值数据
                    }
                } else {
                    console.error('数据请求失败，状态码:', xhr.status);
                    // 显示请求错误提示给用户
                    const modalBody = document.getElementById('modalBody');
                    if (modalBody) {
                        modalBody.innerHTML = '<p class="status-error">数据请求失败，状态码：' + xhr.status + '</p>';
                    }
                    return generateEmptyData(); // 返回近两小时的0值数据
                }
            } catch (e) {
                console.error('获取历史数据时发生异常:', e);
                // 显示异常提示给用户
                const modalBody = document.getElementById('modalBody');
                if (modalBody) {
                    modalBody.innerHTML = '<p class="status-error">获取数据时发生异常：' + e.message + '</p>';
                }
                return generateEmptyData(); // 返回近两小时的0值数据
            }
        }
        
        // 生成近两小时的0值数据（当没有数据时使用）
        function generateEmptyData() {
            console.log('生成近两小时的0值数据');
            
            const labels = [];
            const values = [];
            
            // 生成近两小时的数据点，每30分钟一个点
            const now = new Date();
            const step = 1800000; // 30分钟
            const totalPoints = 4; // 2小时 = 4个30分钟间隔
            
            for (let i = totalPoints - 1; i >= 0; i--) {
                const time = new Date(now.getTime() - (i * step));
                // 格式化为小时:分钟
                const hours = time.getHours().toString().padStart(2, '0');
                const minutes = time.getMinutes().toString().padStart(2, '0');
                const label = hours + ':' + minutes;
                
                labels.push(label);
                values.push(0); // 所有值都是0
            }
            
            console.log('0值数据生成完成，数据点数量:', values.length);
            return { labels, values };
        }
        
        // 生成模拟数据函数（当API请求失败时使用）- 保留以兼容旧代码
        function generateMockData(days) {
            console.log('生成模拟数据（已兼容为0值数据），天数:', days);
            // 直接调用generateEmptyData，确保所有情况下都返回近两小时的0值数据
            return generateEmptyData();
        }
        
        // 调试函数：打印页面上所有模态框相关元素
        function debugModalElements() {
            console.log('===== 模态框元素调试信息 =====');
            console.log('chartModal:', document.getElementById('chartModal'));
            console.log('modalTitle:', document.getElementById('modalTitle'));
            console.log('modalBody:', document.getElementById('modalBody'));
            console.log('closeModal:', document.getElementById('closeModal'));
            console.log('show-chart-btn数量:', document.querySelectorAll('.show-chart-btn').length);
            console.log('Chart.js是否加载:', typeof Chart !== 'undefined');
            if (typeof Chart !== 'undefined') {
                console.log('Chart.js版本:', Chart.version);
                console.log('Chart.js对象结构:', Object.keys(Chart).slice(0, 10)); // 显示前10个属性
            } else {
                console.error('Chart.js未加载！请检查CDN链接是否可访问。');
                // 尝试重新加载Chart.js
                try {
                    console.log('尝试重新加载Chart.js...');
                    const script = document.createElement('script');
                    script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
                    script.onload = function() {
                        console.log('Chart.js重新加载成功！');
                    };
                    script.onerror = function() {
                        console.error('Chart.js重新加载失败！');
                    };
                    document.head.appendChild(script);
                } catch (e) {
                    console.error('尝试重新加载Chart.js时出错:', e);
                }
            }
            console.log('=============================');
        }
        
        /* 静态图表数据样式 */
        const staticChartStyles = `
            .static-chart-data {
                padding: 15px;
                background-color: rgba(0, 0, 0, 0.05);
                border-radius: 8px;
                margin-top: 15px;
            }
            
            .static-chart-data h4 {
                margin-top: 0;
                margin-bottom: 15px;
                color: #6c757d;
                font-size: 1.1rem;
                text-align: center;
            }
            
            .data-table {
                width: 100%;
                border-collapse: collapse;
                background-color: white;
                border-radius: 4px;
                overflow: hidden;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }
            
            .data-table th,
            .data-table td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #dee2e6;
            }
            
            .data-table th {
                background-color: #f8f9fa;
                font-weight: 600;
                color: #495057;
            }
            
            .data-table tr:last-child td {
                border-bottom: none;
            }
            
            .data-table tr:hover {
                background-color: #f8f9fa;
            }
            
            .status-warning {
                margin-top: 15px;
                padding: 10px;
                background-color: #fff3cd;
                border: 1px solid #ffeaa7;
                border-radius: 4px;
                color: #856404;
                font-size: 0.9rem;
                text-align: center;
            }`;
            
        // 将样式添加到页面
        try {
            const styleElement = document.createElement('style');
            styleElement.textContent = staticChartStyles;
            document.head.appendChild(styleElement);
        } catch (e) {
            console.error('添加静态图表样式失败:', e);
        }
        
        // 页面加载完成后执行调试
        window.addEventListener('load', function() {
            console.log('页面完全加载完成');
            debugModalElements();
        });
        
        // 添加键盘事件监听，按ESC键关闭模态框
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideChartModal();
            }
        });
    </script>

    <style>
        /* 图表按钮容器样式 */
        .chart-button-container {
            padding: 15px;
            display: flex;
            justify-content: center;
        }
        
        /* 图表按钮样式 */
        .show-chart-btn {
            background: linear-gradient(135deg, #2196F3 0%, #0b7dda 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .show-chart-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(33, 150, 243, 0.3);
        }
        
        /* 图表容器样式 */
        .chart-container {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            margin-top: 0;
            margin-bottom: 15px;
            padding: 15px;
            background-color: #fff;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
            border: 1px solid #e0e0e0;
            border-top: none;
            z-index: 100;
            transform-origin: top center;
            transform: scaleY(0.95);
            opacity: 0;
            transition: transform 0.3s ease, opacity 0.3s ease;
        }
        
        .chart-container[style*='display: block'] {
            opacity: 1;
            transform: scaleY(1);
        }
        
        /* 模态框样式 */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }
        
        .modal-content {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow: hidden;
            animation: slideUp 0.3s ease;
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #333;
            display: flex;
            align-items: center;
        }
        
        .modal-title-icon {
            display: inline-flex;
            align-items: center;
            margin-right: 8px;
            width: 64px;
            height: 64px;
            border-radius: 10px;
            background-color: #fff;
            padding: 3px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 50%;
            transition: all 0.2s ease;
        }
        
        .close-btn:hover {
            background-color: #f0f0f0;
            color: #333;
        }
        
        .modal-body {
            padding: 20px;
            max-height: calc(80vh - 120px);
            overflow-y: auto;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* 确保图表容器在模态框中有合适的高度 */
        .modal-body .chart-wrapper {
            height: 300px;
            margin: 20px 0;
        }
        
        .modal-body .chart-controls {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 15px;
        }
        
        .chart-container h4 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #333;
            font-size: 16px;
        }
        
        .chart-wrapper {
            position: relative;
            height: 200px;
            margin-bottom: 10px;
        }
        
        .chart-controls {
            display: flex;
            gap: 5px;
            justify-content: center;
        }
        
        .chart-btn {
            padding: 5px 10px;
            border: 1px solid #ddd;
            background-color: #f9f9f9;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .chart-btn:hover {
            background-color: #e9e9e9;
        }
        
        .chart-btn.active {
            background-color: #4CAF50;
            color: white;
            border-color: #4CAF50;
        }
        
        /* 日期选择器样式 */
        .date-selector {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        
        .date-selector label {
            font-weight: bold;
            color: #333;
            font-size: 14px;
            white-space: nowrap;
        }
        
        .date-input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            background-color: white;
            transition: border-color 0.2s ease;
            min-width: 150px;
        }
        
        .date-input:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.1);
        }
        
        .date-btn {
            padding: 8px 16px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background-color 0.2s ease, transform 0.2s ease;
        }
        
        .date-btn:hover {
            background-color: #45a049;
            transform: translateY(-1px);
        }
        
        .date-btn:active {
            transform: translateY(0);
        }
        
        /* 服务器卡片悬停效果 */
        .server-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
        }
        
        .server-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        /* 图表容器动画 */
        .chart-container {
            transition: opacity 0.3s ease;
        }
        
        /* 未开启人数记录的提示样式 */
        .no-history-message {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
            background-color: #f9f9f9;
            border-radius: 5px;
            margin-top: 15px;
        }
    </style>
</body>
</html>