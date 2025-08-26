<?php
// API管理页面

session_start();

// 检查是否已安装
if (!file_exists('installed.lock')) {
    header('Location: install.php');
    exit;
}

require_once 'config.php';

// 检查管理员是否已登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 加载API信息
$api_data = [];
$api_file = 'api.json';
if (file_exists($api_file)) {
    $api_data = json_decode(file_get_contents($api_file), true);
}

// 处理获取API最新信息请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fetch_api_info'])) {
    // 目标API URL
    $target_url = API_UPDATE_URL;

    // 尝试获取API信息
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $target_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 设置超时时间为10秒

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 检查请求是否成功
    if ($http_code === 200 && !empty($response)) {
        // 解析JSON响应
        $api_data = json_decode($response, true);

        if ($api_data !== null) {
            // 保存到本地api.json文件
            $file_path = 'api.json';
            if (file_put_contents($file_path, json_encode($api_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                // 记录日志
                $log_message = '[' . date('Y-m-d H:i:s') . '] 成功获取并更新API信息';
                file_put_contents('api.log', $log_message . PHP_EOL, FILE_APPEND);
                $success = 'API信息已成功更新!';
                // 重新加载API数据
                $api_data = $api_data;
                // 重新获取Java和Bedrock类型的API
                $java_apis = [];
                $bedrock_apis = [];
                if (isset($api_data['routes'])) {
                    foreach ($api_data['routes'] as $route) {
                        if ($route['type'] === 'java') {
                            $java_apis[] = $route;
                        } elseif ($route['type'] === 'bedrock') {
                            $bedrock_apis[] = $route;
                        }
                    }
                }
            } else {
                // 记录错误日志
                $log_message = '[' . date('Y-m-d H:i:s') . '] 保存API信息失败: 无法写入文件';
                file_put_contents('api.log', $log_message . PHP_EOL, FILE_APPEND);
                $error = '保存API信息失败: 无法写入文件';
            }
        } else {
            // 记录错误日志
            $log_message = '[' . date('Y-m-d H:i:s') . '] 解析API响应失败: JSON格式无效';
            file_put_contents('api.log', $log_message . PHP_EOL, FILE_APPEND);
            $error = '解析API响应失败: JSON格式无效';
        }
    } else {
        // 记录错误日志
        $error_msg = empty($response) ? '无响应' : 'HTTP错误码: ' . $http_code;
        $log_message = '[' . date('Y-m-d H:i:s') . '] 获取API信息失败: ' . $error_msg;
        file_put_contents('api.log', $log_message . PHP_EOL, FILE_APPEND);
        $error = '获取API信息失败: ' . $error_msg;
    }
}

// 处理API选择请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_api_selection'])) {
    $selected_java_api = $_POST['java_api'];
    $selected_bedrock_api = $_POST['bedrock_api'];

    // 保存选择到配置
    $config = [
        'selected_java_api' => $selected_java_api,
        'selected_bedrock_api' => $selected_bedrock_api
    ];

    file_put_contents('api_selection.json', json_encode($config, JSON_PRETTY_PRINT));
    $success = 'API选择已保存';
}

// 加载已选择的API
$selected_apis = [];
$selection_file = 'api_selection.json';
if (file_exists($selection_file)) {
    $selected_apis = json_decode(file_get_contents($selection_file), true);
}

// 获取Java和Bedrock类型的API
$java_apis = [];
$bedrock_apis = [];
if (isset($api_data['routes'])) {
    foreach ($api_data['routes'] as $route) {
        if ($route['type'] === 'java') {
            $java_apis[] = $route;
        } elseif ($route['type'] === 'bedrock') {
            $bedrock_apis[] = $route;
        }
    }
}

// 加载默认选择
$selected_java_api = isset($selected_apis['selected_java_api']) ? $selected_apis['selected_java_api'] : (isset($java_apis[0]['api_url']) ? $java_apis[0]['api_url'] : '');
$selected_bedrock_api = isset($selected_apis['selected_bedrock_api']) ? $selected_apis['selected_bedrock_api'] : (isset($bedrock_apis[0]['api_url']) ? $bedrock_apis[0]['api_url'] : '');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_TITLE ?> - API管理</title>
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --accent-color: #e74c3c;
            --text-color: #333;
            --light-text: #666;
            --background-color: #f8f9fa;
            --card-bg: #ffffff;
            --border-color: #e0e0e0;
            --success-color: #27ae60;
            --success-bg: #d5f5e3;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 10px 15px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            line-height: 1.6;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: var(--card-bg);
            padding: 30px;
            border-radius: 10px;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-weight: bold;
        }

        .success-alert {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error-alert {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        h1 {
            color: var(--primary-color);
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 600;
        }

        h2 {
            color: var(--text-color);
            font-size: 22px;
            margin: 25px 0 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--border-color);
            font-weight: 500;
        }

        h3 {
            color: var(--light-text);
            font-size: 18px;
            margin: 20px 0 10px;
            font-weight: 500;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .back-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .back-btn:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);
        }

        .success {
            color: var(--success-color);
            margin-bottom: 20px;
            padding: 12px 15px;
            background-color: var(--success-bg);
            border-radius: 6px;
            border-left: 4px solid var(--success-color);
            font-weight: 500;
        }

        .api-list {
            margin-bottom: 30px;
        }

        .api-item {
            padding: 20px;
            margin-bottom: 15px;
            background-color: var(--card-bg);
            border-radius: 8px;
            border-left: 4px solid var(--secondary-color);
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .api-item:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-3px);
        }

        .api-name {
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 18px;
            color: var(--text-color);
        }

        .api-url {
            color: var(--primary-color);
            word-break: break-all;
            margin-bottom: 8px;
            display: block;
            font-size: 14px;
        }

        .api-website {
            margin-top: 10px;
        }

        .api-website a {
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .api-website a:hover {
            text-decoration: underline;
            opacity: 0.9;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
        }

        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            box-sizing: border-box;
            background-color: var(--card-bg);
            font-size: 14px;
            transition: var(--transition);
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
        }

        .form-group select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            font-size: 16px;
        }

        .btn.save-btn {
            background-color: var(--primary-color);
            color: white;
            margin-right: 10px;
        }

        .btn.fetch-btn {
            background-color: var(--secondary-color);
            color: white;
        }

        .btn:hover {
            background-color: #27ae60;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(46, 204, 113, 0.3);
        }

        .api-selection {
            background-color: var(--card-bg);
            padding: 20px;
            border-radius: 8px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }

            h1 {
                font-size: 24px;
            }

            h2 {
                font-size: 20px;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .back-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>API管理</h1>

        <!-- 操作结果提示 -->
        <?php if (isset($success)): ?>
            <div class="alert success-alert"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert error-alert"><?php echo $error; ?></div>
        <?php endif; ?>
            <a href="admin.php" class="back-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
                返回管理页面
            </a>
        </div>

        <?php if (isset($success)): ?>
            <div class="success"><?= $success ?></div>
        <?php endif; ?>

        <div class="api-selection">
            <h2>选择API</h2>
            <form method="post" action="api_management.php">
                <div class="form-group">
                    <label for="java_api">Java版服务器API:</label>
                    <select id="java_api" name="java_api">
                        <?php foreach ($java_apis as $api): ?>
                            <option value="<?= $api['api_url'] ?>" <?= ($api['api_url'] === $selected_java_api) ? 'selected' : '' ?>><?= $api['name'] ?> (<?= $api['api_url'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="bedrock_api">基岩版服务器API:</label>
                    <select id="bedrock_api" name="bedrock_api">
                        <?php foreach ($bedrock_apis as $api): ?>
                            <option value="<?= $api['api_url'] ?>" <?= ($api['api_url'] === $selected_bedrock_api) ? 'selected' : '' ?>><?= $api['name'] ?> (<?= $api['api_url'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" name="save_api_selection" class="btn save-btn">保存选择</button>
            <button type="submit" name="fetch_api_info" class="btn fetch-btn">获取API最新信息</button>
            </form>
        </div>

        <div class="api-list">
            <h2>API信息</h2>
            <?php if (empty($api_data['routes'])): ?>
                <p>没有可用的API信息，请先获取API最新信息。</p>
            <?php else: ?>
                <h3>Java版API</h3>
                <?php if (empty($java_apis)): ?>
                    <p>没有可用的Java版API。</p>
                <?php else: ?>
                    <?php foreach ($java_apis as $api): ?>
                        <div class="api-item">
                            <div class="api-name"><?= $api['name'] ?></div>
                            <div class="api-url"><?= $api['api_url'] ?></div>
                            <div class="api-website">官方网站: <a href="<?= $api['website'] ?>" target="_blank"><?= $api['website'] ?></a></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <h3>基岩版API</h3>
                <?php if (empty($bedrock_apis)): ?>
                    <p>没有可用的基岩版API。</p>
                <?php else: ?>
                    <?php foreach ($bedrock_apis as $api): ?>
                        <div class="api-item">
                            <div class="api-name"><?= $api['name'] ?></div>
                            <div class="api-url"><?= $api['api_url'] ?></div>
                            <div class="api-website">官方网站: <a href="<?= $api['website'] ?>" target="_blank"><?= $api['website'] ?></a></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>