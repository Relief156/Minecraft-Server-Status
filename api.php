<?php
// API处理文件

// 确保PHP错误不会干扰JSON响应
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 启用输出缓冲以控制响应
ob_start();

// 检查是否已安装
if (!file_exists('installed.lock')) {
    header('Content-Type: application/json');
    echo json_encode(['error' => '系统尚未安装，请先完成安装设置']);
    exit;
}

require_once 'config.php';

class MinecraftAPI {
    private $api_url;
    private $bedrock_api_url;
    private $log_file = 'api_log.txt';
    private $icon_cache_dir = 'icon_cache';
    private $icon_cache_ttl = 86400; // 缓存过期时间，单位秒（这里设置为24小时）
    private $status_cache_ttl = 300; // 服务器状态缓存时间，单位秒（5分钟）
    private $status_cache = array(); // 内存缓存
    private $request_interval = 5; // 最小请求间隔，单位秒（防止请求过快）
    private $last_request_times = array(); // 记录每个服务器的最后请求时间

    // 构造函数
    public function __construct() {
        $this->api_url = API_URL;
        $this->bedrock_api_url = 'https://api.mcsrvstat.us/bedrock/3/';
    }

    // 日志记录方法
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] $message\n";
        file_put_contents($this->log_file, $log_entry, FILE_APPEND);
    }

    // 获取服务器状态
    public function getServerStatus($server_address, $server_type = 'java') {
        // 缓存键名
        $cache_key = $server_address . ':' . $server_type;
        $current_time = time();
        
        // 记录请求开始
        $this->log('开始请求服务器状态: ' . $server_address . ' (类型: ' . $server_type . ')');
        
        // 检查是否有缓存并且缓存未过期
        if (isset($this->status_cache[$cache_key]) && 
            ($current_time - $this->status_cache[$cache_key]['timestamp']) < $this->status_cache_ttl) {
            
            $this->log('使用缓存的服务器状态数据');
            return $this->status_cache[$cache_key]['data'];
        }
        
        // 检查请求间隔
        if (isset($this->last_request_times[$cache_key])) {
            $time_since_last_request = $current_time - $this->last_request_times[$cache_key];
            if ($time_since_last_request < $this->request_interval) {
                // 如果间隔太短，等待剩余时间
                $wait_time = $this->request_interval - $time_since_last_request;
                $this->log('请求间隔过短，等待 ' . $wait_time . ' 秒');
                sleep($wait_time);
                $current_time = time(); // 更新当前时间
            }
        }
        
        // 更新最后请求时间
        $this->last_request_times[$cache_key] = $current_time;
        
        // 根据服务器类型选择API端点
        $api_url = ($server_type === 'bedrock') ? $this->bedrock_api_url : $this->api_url;
        // 构建API请求URL
        $url = $api_url . urlencode($server_address);
        $this->log('请求URL: ' . $url);

        // 初始化cURL
        $ch = curl_init();

        // 设置cURL选项
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // 添加User-Agent头部以避免403错误
        curl_setopt($ch, CURLOPT_USERAGENT, 'Minecraft-Server-Status-Monitor/1.0');
        // 公益API不需要Authorization头
        
        // 设置cURL超时时间
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10秒超时
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // 5秒连接超时

        // 执行请求
        $this->log('开始执行cURL请求');
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->log('cURL请求完成，HTTP状态码: ' . $http_code);

        // 检查错误
        if (curl_errno($ch)) {
            $error_message = 'API请求失败: ' . curl_error($ch);
            $this->log($error_message);
            curl_close($ch);
            return ['error' => $error_message];
        }

        // 关闭cURL
        curl_close($ch);
        
        // 记录响应内容（限制长度，避免日志过大）
        $response_preview = strlen($response) > 500 ? substr($response, 0, 500) . '...' : $response;
        $this->log('响应内容: ' . $response_preview);

        // 解析JSON响应
        $this->log('开始解析JSON响应');
        $data = json_decode($response, true);

        // 检查响应是否有效
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_message = '无效的API响应: ' . json_last_error_msg();
            $this->log($error_message);
            return ['error' => $error_message];
        }
        
        // 记录解析后的响应数据结构
        $this->log('响应数据解析成功，数据结构: ' . print_r(array_keys((array)$data), true));
        
        // 根据返回的状态码判断服务器是否在线
        if (isset($data['online'])) {
            $status = $data['online'] ? '在线' : '离线';
            $this->log('服务器状态: ' . $status);
        }
        
        // 转换API响应格式以匹配index.php的期望格式
        $formatted_data = [];
        
        // 复制基本字段 - 处理mcsrvstat.us API格式
        if (isset($data['online'])) {
            $formatted_data['online'] = $data['online'];
        }
        
        // 转换玩家计数字段 - mcsrvstat.us将玩家信息放在players对象中
        if (isset($data['players']) && isset($data['players']['online'])) {
            $formatted_data['players_online'] = $data['players']['online'];
        }
        if (isset($data['players']) && isset($data['players']['max'])) {
            $formatted_data['players_max'] = $data['players']['max'];
        }
        
        // 复制版本信息
        if (isset($data['version'])) {
            $formatted_data['version'] = $data['version'];
        }
        
        // 处理MOTD - mcsrvstat.us提供多种格式的MOTD
        if (isset($data['motd'])) {
            // 保留HTML格式的MOTD用于前端显示
            if (isset($data['motd']['html'])) {
                // 合并多行HTML为带换行的内容
                $formatted_data['motd_html'] = implode('<br>', $data['motd']['html']);
            }
            
            // 同时保留纯文本格式用于备用显示
            if (isset($data['motd']['clean'])) {
                $formatted_data['motd'] = implode(' ', $data['motd']['clean']);
            } elseif (isset($data['motd']['html'])) {
                $formatted_data['motd'] = strip_tags(implode(' ', $data['motd']['html']));
            }
        }
        
        // 获取服务器图标，带本地缓存
        $formatted_data['server_icon'] = $this->getCachedServerIcon($server_address);
        
        // 为离线服务器添加额外信息，提高用户体验
        // 无论服务器是否在线，都添加基本连接信息
        // 始终显示请求的服务器地址
        $formatted_data['server_address'] = $server_address;
        
        // 如果API返回了主机名，显示它
        if (isset($data['hostname'])) {
            $formatted_data['hostname'] = $data['hostname'];
        }
        
        // 如果API返回了IP地址，显示它
        if (isset($data['ip'])) {
            $formatted_data['ip_address'] = $data['ip'];
        }
        
        // 特别处理离线服务器
        if (isset($data['online']) && !$data['online']) {
            // 如果服务器离线，设置默认的MOTD信息
            if (!isset($formatted_data['motd'])) {
                $formatted_data['motd'] = '服务器当前离线';
                $formatted_data['motd_html'] = '服务器当前离线';
            }
        }
        
        // 记录格式化后的数据
        $this->log('响应数据已格式化，包含字段: ' . print_r(array_keys($formatted_data), true));
        
        // 将结果存入缓存
        $cache_key = $server_address . ':' . $server_type;
        $this->status_cache[$cache_key] = array(
            'data' => $formatted_data,
            'timestamp' => time()
        );

        return $formatted_data;
    }
    
    // 获取缓存的服务器图标
    private function getCachedServerIcon($server_address) {
        // 确保缓存目录存在
        if (!file_exists($this->icon_cache_dir)) {
            mkdir($this->icon_cache_dir, 0755, true);
            $this->log('创建图标缓存目录: ' . $this->icon_cache_dir);
        }
        
        // 生成缓存文件名（使用服务器地址的哈希值）
        $cache_file = $this->icon_cache_dir . '/' . md5($server_address) . '.png';
        $this->log('检查图标缓存文件: ' . $cache_file);
        
        // 检查缓存文件是否存在且未过期
        if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $this->icon_cache_ttl) {
            $this->log('使用缓存的图标文件');
            return $cache_file;
        }
        
        // 如果缓存不存在或已过期，从API获取新图标并缓存
        $icon_url = 'https://api.mcsrvstat.us/icon/' . urlencode($server_address);
        $this->log('获取新图标: ' . $icon_url);
        
        // 使用cURL获取图标
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $icon_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Minecraft-Server-Status-Monitor/1.0');
        curl_setopt($ch, CURLOPT_HEADER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        
        curl_close($ch);
        
        // 检查响应
        if ($http_code == 200) {
            // 分离响应头和响应体
            $body = substr($response, $header_size);
            
            // 保存图标到缓存文件
            file_put_contents($cache_file, $body);
            $this->log('图标已保存到缓存文件');
            return $cache_file;
        } else {
            $this->log('无法获取图标，HTTP状态码: ' . $http_code);
            // 如果缓存文件存在但已过期，仍然返回它
            if (file_exists($cache_file)) {
                $this->log('返回已过期的缓存图标');
                return $cache_file;
            }
            // 返回默认的API链接作为备用
            return $icon_url;
        }
    }
    
    // 获取服务器在线人数历史数据
    public function getPlayerHistoryData($server_id, $days = 1) {
        require_once 'db.php';
        
        $db = new Database();
        $result = $db->getPlayerHistory($server_id, $days);
        
        // 初始化返回数据结构，确保即使没有数据也返回空数组
        $labels = array();
        $values = array();
        
        if ($result === false) {
            // 记录错误日志
            $this->log('获取历史数据失败，数据库查询错误');
            // 返回空的数据结构而不是false
            return array(
                'labels' => $labels,
                'values' => $values
            );
        }
        
        // 处理查询结果
        while ($row = $result->fetch_assoc()) {
            $labels[] = $row['time_label'];
            $values[] = round($row['avg_players']);
        }
        
        $this->log('历史数据查询完成，数据点数量: ' . count($values));
        
        return array(
            'labels' => $labels,
            'values' => $values
        );
    }
    
    // 主入口函数
    public function handleRequest() {
        if (!isset($_GET['action'])) {
            $this->sendErrorResponse('缺少操作参数');
            return;
        }
        
        $action = $_GET['action'];
        
        switch ($action) {
            case 'get_server_status':
                if (isset($_GET['server'])) {
                    $server = $_GET['server'];
                    $type = isset($_GET['type']) ? $_GET['type'] : 'java';
                    $status = $this->getServerStatus($server, $type);
                    if ($status) {
                        $this->sendSuccessResponse($status);
                    } else {
                        $this->sendErrorResponse('无法获取服务器状态');
                    }
                } else {
                    $this->sendErrorResponse('缺少服务器地址参数');
                }
                break;
            
            case 'get_player_history':
                if (isset($_GET['server_id'])) {
                    $server_id = $_GET['server_id'];
                    // 使用floatval保留小数天数，而不是intval
                    $days = isset($_GET['days']) ? floatval($_GET['days']) : 1;
                    // 允许0作为查询所有记录的特殊值
                    if ($days != 0) {
                        // 限制天数范围，允许小数天数但设置最小为0.01(约15分钟)
                        $days = max(0.01, min(30, $days));
                    }
                    $history_data = $this->getPlayerHistoryData($server_id, $days);
                    // 检查是否为数组，而不仅仅是真值检查，确保空数据也能正确返回
                    if (is_array($history_data)) {
                        $this->sendSuccessResponse($history_data);
                    } else {
                        $this->sendErrorResponse('获取历史数据失败');
                    }
                } else {
                    $this->sendErrorResponse('缺少服务器ID参数');
                }
                break;
            
            default:
                $this->sendErrorResponse('未知的操作');
        }
    }
    
    // 辅助方法: 清理数据中的无效字符和确保UTF-8编码
    private function sanitizeDataForJson($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                // 递归清理数组
                if (is_array($value)) {
                    $data[$key] = $this->sanitizeDataForJson($value);
                } 
                // 清理字符串
                else if (is_string($value)) {
                    // 确保字符串是UTF-8编码
                    if (!mb_check_encoding($value, 'UTF-8')) {
                        $data[$key] = mb_convert_encoding($value, 'UTF-8', 'auto');
                    }
                    // 移除控制字符（保留换行符和制表符）
                    $data[$key] = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);
                }
            }
        }
        return $data;
    }

    // 发送成功响应
    private function sendSuccessResponse($data) {
        // 确保没有任何先前的输出
        if (ob_get_level() > 0) {
            ob_clean(); // 清除任何之前的输出
        }
        
        // 设置JSON头
        header('Content-Type: application/json; charset=utf-8');
        
        // 清理数据以确保JSON编码成功
        $clean_data = $this->sanitizeDataForJson($data);
        
        // 准备响应数据
        $response = array(
            'success' => true,
            'data' => $clean_data
        );
        
        // 设置JSON编码选项
        $json_options = 0;
        // 添加JSON_UNESCAPED_UNICODE选项（如果可用）
        if (defined('JSON_UNESCAPED_UNICODE')) {
            $json_options |= JSON_UNESCAPED_UNICODE;
        }
        // 添加JSON_UNESCAPED_SLASHES选项
        if (defined('JSON_UNESCAPED_SLASHES')) {
            $json_options |= JSON_UNESCAPED_SLASHES;
        }
        // 添加PARTIAL_OUTPUT_ON_ERROR选项以获取部分输出
        if (defined('JSON_PARTIAL_OUTPUT_ON_ERROR')) {
            $json_options |= JSON_PARTIAL_OUTPUT_ON_ERROR;
        }
        
        // 尝试编码JSON，处理可能的错误
        $json = json_encode($response, $json_options);
        
        if ($json === false) {
            // JSON编码失败，记录错误并返回错误响应
            $json_error = json_last_error_msg();
            $error_code = json_last_error();
            
            // 记录详细错误信息
            $error_details = "JSON编码失败: $json_error (错误码: $error_code)\n";
            $error_details .= "数据类型: " . gettype($data) . "\n";
            if (is_array($data)) {
                $error_details .= "数据键: " . implode(", ", array_keys($data)) . "\n";
                if (isset($data['labels']) && is_array($data['labels'])) {
                    $error_details .= "Labels数量: " . count($data['labels']) . "\n";
                }
                if (isset($data['values']) && is_array($data['values'])) {
                    $error_details .= "Values数量: " . count($data['values']) . "\n";
                }
            }
            $this->log($error_details);
            
            // 返回简化的纯文本错误响应
            header('Content-Type: text/plain; charset=utf-8');
            echo "{\"success\":false,\"error\":\"JSON编码失败: $json_error\",\"error_code\":$error_code}";
        } else {
            // JSON编码成功，输出结果
            echo $json;
        }
        
        // 确保立即输出所有内容并终止脚本
        exit;
    }
    
    // 发送错误响应
    private function sendErrorResponse($message) {
        // 确保没有任何先前的输出
        if (ob_get_length() > 0) {
            ob_clean(); // 清除任何之前的输出
        }
        
        // 设置JSON头
        header('Content-Type: application/json; charset=utf-8');
        
        // 输出错误响应
        echo json_encode(array(
            'success' => false,
            'error' => $message
        ));
        
        // 确保立即输出所有内容并终止脚本
        exit;
    }
}

// 只有当本文件被直接访问时才实例化并处理请求
if (__FILE__ == $_SERVER['SCRIPT_FILENAME']) {
    $api = new MinecraftAPI();
    $api->handleRequest();
}
?>