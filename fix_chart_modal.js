// 图表模态框修复脚本

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
    
    // 获取所有图表按钮
    const chartButtons = document.querySelectorAll('.show-chart-btn');
    console.log('找到的图表按钮数量：', chartButtons.length);
    
    // 为每个图表按钮添加点击事件
    chartButtons.forEach(function(button, index) {
        const serverId = button.getAttribute('data-server-id');
        const serverName = button.getAttribute('data-server-name');
        console.log(`为按钮${index}添加事件，服务器ID: ${serverId}, 名称: ${serverName}`);
        
        button.addEventListener('click', function(e) {
            // 阻止默认行为和事件冒泡
            e.preventDefault();
            e.stopPropagation();
            
            console.log('点击了图表按钮，服务器ID:', serverId);
            showChartModal(serverId, serverName);
        });
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
    
    // 为服务器卡片添加点击事件，确保不会与按钮冲突
    const serverCards = document.querySelectorAll('.server-card');
    serverCards.forEach(function(card) {
        card.addEventListener('click', function(e) {
            // 仅当点击的不是图表按钮时才执行卡片点击操作
            if (!e.target.closest('.show-chart-btn')) {
                console.log('点击了服务器卡片:', card.getAttribute('data-server-id'));
                // 这里可以添加卡片点击的其他逻辑
            }
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
    
    // 设置模态框标题
    modalTitle.textContent = serverName + ' - 在线人数历史数据';
    
    // 获取服务器状态
    const serverCard = document.querySelector(`.server-card[data-server-id="${serverId}"]`);
    const isOnline = serverCard ? serverCard.querySelector('.server-header').classList.contains('online') : false;
    
    console.log('服务器在线状态:', isOnline);
    
    // 清空模态框内容
    modalBody.innerHTML = '';
    
    // 无论服务器是否在线，都显示图表（使用模拟数据）
    modalBody.innerHTML = `
        <div class="chart-wrapper">
            <canvas id="modalPlayerChart" width="400" height="300"></canvas>
        </div>
        <div class="chart-controls">
            <button class="chart-btn active" data-days="1">今日</button>
            <button class="chart-btn" data-days="7">本周</button>
            <button class="chart-btn" data-days="30">本月</button>
        </div>
    `;
    
    // 添加图表控制按钮的点击事件
    document.querySelectorAll('.chart-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            const days = parseInt(this.getAttribute('data-days'));
            
            // 切换活动按钮样式
            document.querySelectorAll('.chart-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            this.classList.add('active');
            
            // 更新图表
            updateModalChart(serverId, days);
        });
    });
    
    // 初始化图表
    initModalChart(serverId, 1);
    
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

// 全局存储玩家列表数据的变量
let modalPlayerLists = [];

// 初始化模态框中的图表函数
function initModalChart(serverId, days) {
    console.log('初始化图表，服务器ID:', serverId, '天数:', days);
    
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
    
    // 重新初始化玩家列表数据
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
                    callbacks: {
                        // 自定义标题显示时间
                        title: function(context) {
                            const index = context[0].dataIndex;
                            const label = context[0].label || '';
                            return label;
                        },
                        // 自定义标签显示在线人数和玩家列表
                        label: function(context) {
                            let label = '在线人数：' + context.raw;
                            const index = context.dataIndex;
                            
                            console.log('--- tooltip触发 ---');
                            console.log('上下文数据:', { index, rawValue: context.raw, chartDataLength: currentModalChart?.data?.datasets?.[0]?.data?.length });
                            console.log('全局modalPlayerLists引用类型:', typeof modalPlayerLists);
                            console.log('全局modalPlayerLists是否为数组:', Array.isArray(modalPlayerLists));
                            console.log('全局modalPlayerLists长度:', modalPlayerLists?.length || '未定义');
                            
                            // 检查modalPlayerLists是否已正确初始化
                            if (!modalPlayerLists || !Array.isArray(modalPlayerLists)) {
                                console.error('modalPlayerLists未正确初始化或不是数组');
                                return label + '\n[调试] 玩家列表数据结构错误';
                            }
                            
                            // 检查索引范围
                            if (index < 0 || index >= modalPlayerLists.length) {
                                console.error('索引越界:', index, '玩家列表长度:', modalPlayerLists.length);
                                return label + `\n[调试] 索引(${index})超出范围(0-${modalPlayerLists.length-1})`;
                            }
                            
                            // 尝试获取对应的玩家列表
                            const playerList = modalPlayerLists[index];
                            console.log('当前索引的玩家列表数据:', playerList, '类型:', typeof playerList);
                            
                            try {
                                // 无论数据类型如何，都尝试获取玩家列表
                                if (playerList) {
                                    // 处理可能的JSON字符串
                                    let parsedPlayers = playerList;
                                    if (typeof parsedPlayers === 'string') {
                                        console.log('尝试解析JSON字符串:', parsedPlayers);
                                        parsedPlayers = JSON.parse(parsedPlayers);
                                    }
                                    
                                    // 处理数组情况
                                    if (Array.isArray(parsedPlayers)) {
                                        if (parsedPlayers.length > 0) {
                                            label += '\n在线玩家：' + parsedPlayers.join(', ');
                                            console.log('成功显示玩家列表，共', parsedPlayers.length, '人');
                                        } else {
                                            label += '\n在线玩家：无';
                                            console.log('玩家列表为空数组');
                                        }
                                    } else {
                                        // 处理非数组但有值的情况
                                        label += '\n[调试] 玩家列表格式异常: ' + JSON.stringify(parsedPlayers);
                                        console.log('玩家列表不是数组，值为:', parsedPlayers);
                                    }
                                } else {
                                    label += '\n在线玩家：无数据';
                                    console.log('玩家列表为空值');
                                }
                            } catch (e) {
                                console.error('处理玩家列表时发生异常:', e);
                                label += '\n[调试] 处理玩家列表失败: ' + e.message;
                            }
                            
                            console.log('--- tooltip处理完成 ---');
                            return label;
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
        // 显示错误信息
        const modalBody = document.getElementById('modalBody');
        if (modalBody) {
            modalBody.innerHTML = '<p class="status-error">创建图表失败：' + e.message + '</p>';
        }
        return;
    }
    
    // 初始化玩家列表数据
    modalPlayerLists = [];
    
    // 加载数据
    loadModalChartData(serverId, days);
}

// 更新模态框中的图表函数
function updateModalChart(serverId, days) {
    console.log('更新图表，服务器ID:', serverId, '天数:', days);
    // 重新初始化玩家列表数据
    modalPlayerLists = [];
    loadModalChartData(serverId, days);
}

// 加载模态框图表数据函数
function loadModalChartData(serverId, days) {
    console.log('加载图表数据，服务器ID:', serverId, '天数:', days);
    
    try {
        // 通过API获取包含玩家列表的原始历史数据
        const data = getRawHistoricalData(serverId, days);
        
        // 更新图表数据
        if (currentModalChart && data) {
            currentModalChart.data.labels = data.labels;
            currentModalChart.data.datasets[0].data = data.values;
            // 保存玩家列表数据
            console.log('原始数据中的playerLists:', data.playerLists);
            modalPlayerLists = Array.isArray(data.playerLists) ? data.playerLists : [];
            console.log('保存的玩家列表数据(类型检查后):', modalPlayerLists);
            console.log('玩家列表数据长度:', modalPlayerLists.length);
            currentModalChart.update();
            console.log('图表数据更新成功，数据点数量:', data.values.length);
        }
    } catch (e) {
        console.error('加载图表数据失败:', e);
        // 尝试使用传统的聚合数据作为备选方案
        try {
            const data = getHistoricalData(serverId, days);
            if (currentModalChart && data) {
                currentModalChart.data.labels = data.labels;
                currentModalChart.data.datasets[0].data = data.values;
                // 清空玩家列表数据
                modalPlayerLists = [];
                currentModalChart.update();
                console.log('使用备选数据更新图表成功，数据点数量:', data.values.length);
            }
        } catch (fallbackError) {
            console.error('备选数据加载也失败:', fallbackError);
        }
    }
}

// 获取包含玩家列表的原始历史数据函数
function getRawHistoricalData(serverId, days) {
    console.log('获取原始历史数据，服务器ID:', serverId, '天数:', days);
    
    try {
        // 使用同步XMLHttpRequest
        const xhr = new XMLHttpRequest();
        const url = `api.php?action=get_raw_player_history&server_id=${serverId}&days=${days}`;
        
        // 添加缓存控制参数
        const timestamp = new Date().getTime();
        const fullUrl = url + '&_=' + timestamp;
        
        console.log('请求URL:', fullUrl);
        
        xhr.open('GET', fullUrl, false);
        xhr.send();
        
        console.log('API响应状态码:', xhr.status);
        
        if (xhr.status === 200) {
            console.log('API响应内容:', xhr.responseText);
            
            try {
                const response = JSON.parse(xhr.responseText);
                
                if (response.success) {
                    // 检查响应数据结构
                    if (response.data && response.data.labels && response.data.values && response.data.playerLists) {
                        console.log('原始历史数据格式正确，返回数据');
                        return {
                            labels: response.data.labels,
                            values: response.data.values,
                            playerLists: response.data.playerLists
                        };
                    } else if (response.labels && response.values && response.playerLists) {
                        // 兼容旧的返回格式
                        console.log('使用旧的数据格式');
                        return {
                            labels: response.labels,
                            values: response.values,
                            playerLists: response.playerLists
                        };
                    } else {
                        console.error('原始历史数据格式不正确:', response);
                        // 使用普通历史数据作为备选
                        return getHistoricalData(serverId, days);
                    }
                } else {
                    console.error('获取原始历史数据失败:', response.error);
                    // 使用普通历史数据作为备选
                    return getHistoricalData(serverId, days);
                }
            } catch (e) {
                console.error('解析原始历史数据失败:', e);
                // 使用普通历史数据作为备选
                return getHistoricalData(serverId, days);
            }
        } else {
            console.error('API请求失败，状态码:', xhr.status);
            // 使用普通历史数据作为备选
            return getHistoricalData(serverId, days);
        }
    } catch (e) {
        console.error('获取原始历史数据时发生异常:', e);
        // 使用普通历史数据作为备选
        return getHistoricalData(serverId, days);
    }
}

// 获取传统历史数据函数（聚合数据）
function getHistoricalData(serverId, days) {
    console.log('获取历史数据，服务器ID:', serverId, '天数:', days);
    
    try {
        // 使用同步XMLHttpRequest
        const xhr = new XMLHttpRequest();
        const url = `api.php?action=get_player_history&server_id=${serverId}&days=${days}`;
        
        // 添加缓存控制参数
        const timestamp = new Date().getTime();
        const fullUrl = url + '&_=' + timestamp;
        
        console.log('请求URL:', fullUrl);
        
        xhr.open('GET', fullUrl, false);
        xhr.send();
        
        console.log('API响应状态码:', xhr.status);
        
        if (xhr.status === 200) {
            console.log('API响应内容:', xhr.responseText);
            
            try {
                const response = JSON.parse(xhr.responseText);
                
                if (response.success) {
                    // 检查响应数据结构
                    if (response.data && response.data.labels && response.data.values) {
                        console.log('历史数据格式正确，返回数据');
                        return {
                            labels: response.data.labels,
                            values: response.data.values,
                            playerLists: [] // 空的玩家列表数组
                        };
                    } else if (response.labels && response.values) {
                        // 兼容旧的返回格式
                        console.log('使用旧的数据格式');
                        return {
                            labels: response.labels,
                            values: response.values,
                            playerLists: [] // 空的玩家列表数组
                        };
                    } else {
                        console.error('历史数据格式不正确:', response);
                        return generateMockData(days);
                    }
                } else {
                    console.error('获取历史数据失败:', response.error);
                    return generateMockData(days);
                }
            } catch (e) {
                console.error('解析历史数据失败:', e);
                return generateMockData(days);
            }
        } else {
            console.error('API请求失败，状态码:', xhr.status);
            return generateMockData(days);
        }
    } catch (e) {
        console.error('获取历史数据时发生异常:', e);
        return generateMockData(days);
    }
}

// 生成模拟数据函数（当API请求失败时使用）
function generateMockData(days) {
    console.log('生成模拟数据，天数:', days);
    
    const labels = [];
    const values = [];
    
    // 生成模拟数据
    const now = new Date();
    let step = 3600000; // 1小时
    let totalPoints = 24;
    
    if (days > 1 && days <= 7) {
        step = 7200000; // 2小时
        totalPoints = days * 12;
    } else if (days > 7) {
        step = 86400000; // 1天
        totalPoints = days;
    }
    
    for (let i = totalPoints - 1; i >= 0; i--) {
        const time = new Date(now.getTime() - (i * step));
        let label = '';
        
        if (days <= 1) {
            label = time.getHours() + ':00';
        } else if (days <= 7) {
            label = time.getMonth() + 1 + '/' + time.getDate() + ' ' + time.getHours() + ':00';
        } else {
            label = time.getMonth() + 1 + '/' + time.getDate();
        }
        
        labels.push(label);
        
        // 生成随机的玩家数量
        const randomPlayers = Math.floor(Math.random() * 20);
        values.push(randomPlayers);
    }
    
    console.log('模拟数据生成完成，数据点数量:', values.length);
    return { labels, values };
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
    }
    console.log('=============================');
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

// 添加辅助函数：检查元素是否在视图中
function isElementInViewport(el) {
    const rect = el.getBoundingClientRect();
    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
}

// 定时检查模态框显示状态（用于调试）
setInterval(function() {
    const chartModal = document.getElementById('chartModal');
    if (chartModal && chartModal.style.display === 'flex') {
        console.log('模态框当前处于显示状态');
        if (!isElementInViewport(chartModal)) {
            console.log('警告：模态框虽然设置为显示，但可能不在视图中');
        }
    }
}, 5000);