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
require_once 'db.php';

class MinecraftAPI {
    private $java_api_urls; // Java版API URL列表（支持多个URL按优先级顺序）
    private $bedrock_api_url;
    private $log_file = 'api.log';
    private $icon_cache_dir = 'cache/icons';
    private $icon_cache_ttl = 3600; // 缓存过期时间，单位秒（1小时）
    private $status_cache_ttl = 30; // 服务器状态缓存时间，单位秒（30秒）
    private $status_cache = array(); // 内存缓存
    private $request_interval = 5; // 最小请求间隔，单位秒（防止请求过快）
    private $last_request_times = array(); // 记录每个服务器的最后请求时间
    private $db; // 数据库连接
    private $api_retry_count = 1; // API请求重试次数

    // 构造函数
    public function __construct() {
        // 初始化API URL（从配置文件读取）
        $this->java_api_urls = JAVA_API_URLS; // Java版API URL列表
        $this->bedrock_api_url = BEDROCK_API_URL; // Bedrock版API URL
        
        // 初始化数据库连接
        $this->db = new Database();
    }

    // 日志记录方法
    private function log($message) {
        // 检查是否需要归档前一天的日志
        $this->archiveLogIfNeeded();
        
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] $message\n";
        file_put_contents($this->log_file, $log_entry, FILE_APPEND);
    }

    // 检查并归档日志文件（如果日期已变更）
    private function archiveLogIfNeeded() {
        // 如果日志文件不存在，不需要归档
        if (!file_exists($this->log_file)) {
            return;
        }
        
        // 获取日志文件的最后修改日期和当前日期
        $log_file_date = date('Y-m-d', filemtime($this->log_file));
        $current_date = date('Y-m-d');
        
        // 如果最后修改日期不是今天，说明是跨天了，需要归档
        if ($log_file_date !== $current_date) {
            // 确保logs目录存在
            if (!file_exists('logs')) {
                if (!mkdir('logs', 0755, true)) {
                    // 添加错误日志记录
                    $error_message = "[归档错误] 无法创建logs目录";
                    error_log($error_message);
                    return; // 无法创建目录，终止归档
                }
            }
            
            // 归档文件名：logs/YYYY-MM-DD_api.log.gz
            $archive_file = 'logs/' . $log_file_date . '_api.log.gz';
            
            try {
                // 读取当前日志内容
                $log_content = file_get_contents($this->log_file);
                if ($log_content === false) {
                    throw new Exception("无法读取日志文件内容");
                }
                
                // 尝试压缩归档 - 使用备用方法
                if (function_exists('gzopen')) {
                    // 方法1：使用gzopen/gzwrite
                    $gz_handle = gzopen($archive_file, 'w9'); // 使用最高压缩级别
                    if ($gz_handle === false) {
                        throw new Exception("无法创建GZIP文件");
                    }
                    
                    if (gzwrite($gz_handle, $log_content) === false) {
                        throw new Exception("写入GZIP文件失败");
                    }
                    
                    gzclose($gz_handle);
                } else {
                    // 方法2：备用方法，使用file_put_contents和base64_encode
                    // 如果服务器不支持gzip函数，使用这种方式保存非压缩版本
                    $archive_file = str_replace('.gz', '', $archive_file); // 移除.gz扩展名
                    if (file_put_contents($archive_file, $log_content) === false) {
                        throw new Exception("无法创建归档文件");
                    }
                }
                
                // 清空原日志文件
                if (file_put_contents($this->log_file, '') === false) {
                    throw new Exception("清空原日志文件失败");
                }
                
                // 记录归档操作
                $archive_message = "日志已归档到: $archive_file";
                $timestamp = date('Y-m-d H:i:s');
                file_put_contents($this->log_file, "[$timestamp] $archive_message\n", FILE_APPEND);
                
            } catch (Exception $e) {
                // 添加错误日志记录
                $error_message = "[归档错误] " . $e->getMessage();
                error_log($error_message);
                
                // 也记录到当前日志文件（如果可能）
                try {
                    $timestamp = date('Y-m-d H:i:s');
                    file_put_contents($this->log_file, "[$timestamp] $error_message\n", FILE_APPEND);
                } catch (Exception $e2) {
                    // 如果无法写入日志文件，忽略
                }
            }
        }
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
        
        // 尝试使用主API和备用API的故障转移逻辑
        // 首先尝试主API，如果主API失败才尝试备用API
        $api_urls = array();
        
        // 根据API配置构建优先顺序的URL数组
        foreach ($this->java_api_urls as $index => $api_url) {
            $api_urls['api_' . ($index + 1)] = $api_url;
        }
        
        $response = null;
        $http_code = 0;
        $error_message = '';
        $success = false;
        
        foreach ($api_urls as $api_name => $api_url) {
            // 只在主API失败时才尝试备用API
            if ($api_name == 'secondary' && $success) {
                $this->log('主API请求成功，跳过备用API');
                break;
            }
            
            $this->log('尝试使用' . ($api_name == 'primary' ? '主' : '备用') . 'API: ' . $api_url);
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
                // 如果是主API失败，尝试备用API
                if ($api_name == 'primary') {
                    $this->log('主API请求失败，尝试备用API...');
                    continue;
                }
            } else {
                // 检查是否为429状态码（Too many requests）
                if ($http_code == 429) {
                    $error_message = 'API请求频率过高，请稍后再试（Too many requests）';
                    $this->log($error_message);
                    curl_close($ch);
                    // 如果是主API遇到频率限制，尝试备用API
                    if ($api_name == 'primary') {
                        $this->log('主API请求频率过高，尝试备用API...');
                        continue;
                    } else {
                        // 备用API也遇到频率限制，则设置特殊错误信息
                        $error_message = '所有API都暂时无法处理请求，请稍后再试';
                        return ['error' => $error_message, 'http_code' => 429];
                    }
                }
                
                // 检查响应是否为空
                if (empty($response)) {
                    $error_message = 'API返回空响应';
                    $this->log($error_message);
                    curl_close($ch);
                    // 如果是主API失败，尝试备用API
                    if ($api_name == 'primary') {
                        $this->log('主API返回空响应，尝试备用API...');
                        continue;
                    }
                } else {
                    // 成功获取响应
                    $this->log($api_name . ' API请求成功，获取到有效响应');
                    $success = true;
                    curl_close($ch);
                    break;
                }
            }
        }
        
        if (!$success) {
            $this->log('所有API请求均失败');
            return ['error' => '无法连接到API服务器: ' . $error_message];
        }
        
        // 记录响应内容（限制长度，避免日志过大）
        $response_preview = strlen($response) > 500 ? substr($response, 0, 500) . '...' : $response;
        $this->log('响应内容: ' . $response_preview);

        // 解析JSON响应
        $this->log('开始解析JSON响应');
        $data = json_decode($response, true);

        // 检查响应是否有效
        if (json_last_error() !== JSON_ERROR_NONE) {
            // 获取JSON解析错误信息
            $json_error = json_last_error_msg();
            $error_message = '无效的API响应: ' . $json_error;
            $this->log($error_message);
            
            // 如果是429状态码，提供更友好的错误信息
            if (isset($http_code) && $http_code == 429) {
                $error_message = 'API请求过于频繁，请稍后再试';
            } 
            // 如果是非JSON格式的错误消息，尝试提取原始消息
            else if (!empty($response) && !is_array($data)) {
                // 去除多余的空白字符和HTML标签
                $clean_response = trim(strip_tags($response));
                // 如果清理后的响应不为空，使用它作为错误消息的一部分
                if (!empty($clean_response)) {
                    $error_message .= '，原始响应: ' . substr($clean_response, 0, 100);
                }
            }
            
            return ['error' => $error_message, 'http_code' => $http_code];
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
        
        // 复制基本字段 - 处理用户自建API格式
        if (isset($data['online'])) {
            $formatted_data['online'] = $data['online'];
        }
        
        // 转换玩家计数字段
        if (isset($data['players']) && isset($data['players']['online'])) {
            $formatted_data['players_online'] = $data['players']['online'];
        }
        if (isset($data['players']) && isset($data['players']['max'])) {
            $formatted_data['players_max'] = $data['players']['max'];
        }
        
        // 处理玩家列表
        if (isset($data['players']) && isset($data['players']['list']) && !empty($data['players']['list'])) {
            $player_list = $data['players']['list'];
            // 将逗号分隔的玩家列表转换为数组
            $players = explode(', ', $player_list);
            $formatted_data['player_list'] = $players;
        }
        
        // 复制版本信息
        if (isset($data['version'])) {
            $formatted_data['version'] = $data['version'];
        }
        
        // 处理MOTD - 支持mcsy.net返回的嵌套JSON格式的MOTD
        if (isset($data['motd'])) {
            // 尝试解析JSON格式的MOTD
            $motd_json = json_decode($data['motd'], true);
            if ($motd_json !== null) {
                // 提取纯文本MOTD内容
                $motd_text = $this->extractMOTDText($motd_json);
                $formatted_data['motd'] = $motd_text;
                
                // 生成HTML格式的MOTD（保留颜色和格式）
                $motd_html = $this->convertMOTDToHTML($motd_json);
                $formatted_data['motd_html'] = $motd_html;
            } else {
                // 如果解析失败，直接使用原始文本
                $formatted_data['motd'] = $data['motd'];
                $formatted_data['motd_html'] = $this->convertMinecraftColorsToHTML($data['motd']);
            }
        }
        
        // 处理favicon - 用户自建API可能直接返回base64编码的图标
        if (isset($data['favicon'])) {
            $formatted_data['server_icon'] = $data['favicon'];
        } else {
            // 否则从icon接口获取图标（如果需要）
            $formatted_data['server_icon'] = $this->getCachedServerIcon($server_address);
        }
        
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
    
    // 递归函数来解析嵌套的extra数组，提取纯文本
    private function extractMOTDText($motd_data) {
        $text = '';
        if (is_array($motd_data)) {
            // 如果有text字段，添加到结果中
            if (isset($motd_data['text'])) {
                $text .= $motd_data['text'];
            }
            // 如果有extra字段，递归处理
            if (isset($motd_data['extra']) && is_array($motd_data['extra'])) {
                foreach ($motd_data['extra'] as $extra) {
                    $text .= $this->extractMOTDText($extra);
                }
            }
        } elseif (is_string($motd_data)) {
            // 如果是字符串，直接返回
            $text .= $motd_data;
        }
        return $text;
    }
    
    // 递归函数来解析嵌套的extra数组并转换为HTML格式（保留颜色和格式）
    private function convertMOTDToHTML($motd_data) {
        $html = '';
        if (is_array($motd_data)) {
            // 处理样式
            $styles = [];
            if (isset($motd_data['color'])) {
                $color = $motd_data['color'];
                // 检查是否是十六进制颜色值（以#开头并至少有6个字符）
                if (strpos($color, '#') === 0 && strlen($color) >= 6) {
                    $styles[] = 'color: ' . $color;
                } else {
                    // Minecraft颜色代码映射到CSS颜色
                    $color_map = [
                        'black' => '#000000',
                        'dark_blue' => '#0000AA',
                        'dark_green' => '#00AA00',
                        'dark_aqua' => '#00AAAA',
                        'dark_red' => '#AA0000',
                        'dark_purple' => '#AA00AA',
                        'gold' => '#FFAA00',
                        'gray' => '#AAAAAA',
                        'dark_gray' => '#555555',
                        'blue' => '#5555FF',
                        'green' => '#55FF55',
                        'aqua' => '#55FFFF',
                        'red' => '#FF5555',
                        'light_purple' => '#FF55FF',
                        'yellow' => '#FFFF55',
                        'white' => '#FFFFFF'
                    ];
                    if (isset($color_map[$color])) {
                        $styles[] = 'color: ' . $color_map[$color];
                    }
                }
            }
            
            if (isset($motd_data['bold']) && $motd_data['bold']) {
                $styles[] = 'font-weight: bold';
            }
            
            if (isset($motd_data['italic']) && $motd_data['italic']) {
                $styles[] = 'font-style: italic';
            }
            
            if (isset($motd_data['underlined']) && $motd_data['underlined']) {
                $styles[] = 'text-decoration: underline';
            }
            
            // 开始样式
            if (!empty($styles)) {
                $html .= '<span style="' . implode('; ', $styles) . '">';
            }
            
            // 添加文本内容并处理换行符
            if (isset($motd_data['text'])) {
                $text = $motd_data['text'];
                // 特殊处理单独的换行符对象
                if ($text === "\n" || $text === "\\n") {
                    $html .= '<br>';
                } else {
                    // 增强换行符处理，确保所有类型的换行符都能被识别
                    // 先转义所有可能的换行符表示
                    $text_with_breaks = str_replace(['\\n', "\n", "\r\n", "\r"], '<br>', $text);
                    // 再处理Minecraft颜色代码
                    $text_with_colors = $this->convertMinecraftColorsToHTML($text_with_breaks);
                    $html .= $text_with_colors;
                }
            }
            
            // 处理嵌套的extra内容
            if (isset($motd_data['extra']) && is_array($motd_data['extra'])) {
                foreach ($motd_data['extra'] as $extra) {
                    $html .= $this->convertMOTDToHTML($extra);
                }
            }
            
            // 结束样式
            if (!empty($styles)) {
                $html .= '</span>';
            }
        } elseif (is_string($motd_data)) {
            // 如果是纯字符串，先处理换行符
            $text_with_breaks = str_replace(['\\n', "\n", "\r\n", "\r"], '<br>', $motd_data);
            // 再处理Minecraft颜色代码
            $text_with_colors = $this->convertMinecraftColorsToHTML($text_with_breaks);
            $html .= $text_with_colors;
        }
        return $html;
    }
    
    // 将Minecraft颜色代码转换为HTML
    private function convertMinecraftColorsToHTML($text) {
        // 检查是否包含Minecraft颜色代码
        if (strpos($text, '§') === false) {
            return htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8', false);
        }

        // Minecraft颜色代码映射
        $color_map = [
            '0' => '#000000', // 黑色
            '1' => '#0000AA', // 深蓝色
            '2' => '#00AA00', // 深绿色
            '3' => '#00AAAA', // 深青色
            '4' => '#AA0000', // 深红色
            '5' => '#AA00AA', // 深紫色
            '6' => '#FFAA00', // 金色
            '7' => '#AAAAAA', // 灰色
            '8' => '#555555', // 深灰色
            '9' => '#5555FF', // 蓝色
            'a' => '#55FF55', // 绿色
            'b' => '#55FFFF', // 青色
            'c' => '#FF5555', // 红色
            'd' => '#FF55FF', // 紫色
            'e' => '#FFFF55', // 黄色
            'f' => '#FFFFFF', // 白色
        ];
        
        // 样式代码映射
        $style_map = [
            'l' => 'font-weight: bold', // 粗体
            'o' => 'font-style: italic', // 斜体
            'n' => 'text-decoration: underline', // 下划线
            'm' => 'text-decoration: line-through', // 删除线
            'k' => 'text-shadow: 2px 2px 4px rgba(0,0,0,0.5);', // 闪烁（这里用阴影模拟）
            'r' => 'reset' // 重置样式
        ];
        
        $html = '';
        $current_styles = [];
        $length = mb_strlen($text, 'UTF-8');
        
        for ($i = 0; $i < $length; $i++) {
            // 使用mb_substr确保正确处理多字节字符
            $char = mb_substr($text, $i, 1, 'UTF-8');
            
            // 检查是否是颜色代码开始
            if ($char === '§' && $i + 1 < $length) {
                $code = mb_substr($text, $i + 1, 1, 'UTF-8');
                $code = strtolower($code);
                $i++;
                
                // 重置样式
                if ($code === 'r') {
                    if (!empty($current_styles)) {
                        $html .= '</span>';
                        $current_styles = [];
                    }
                }
                // 处理颜色代码
                else if (isset($color_map[$code])) {
                    // 关闭当前样式
                    if (!empty($current_styles)) {
                        $html .= '</span>';
                    }
                    // 添加新颜色样式
                    $current_styles = ['color: ' . $color_map[$code]];
                    $html .= '<span style="' . implode('; ', $current_styles) . '">';
                }
                // 处理样式代码
                else if (isset($style_map[$code])) {
                    if ($style_map[$code] === 'reset') {
                        if (!empty($current_styles)) {
                            $html .= '</span>';
                            $current_styles = [];
                        }
                    } else {
                        // 关闭当前样式
                        if (!empty($current_styles)) {
                            $html .= '</span>';
                        }
                        // 添加新样式
                        $current_styles = [$style_map[$code]];
                        $html .= '<span style="' . implode('; ', $current_styles) . '">';
                    }
                }
            } else {
                // 普通字符，转义HTML特殊字符
                $html .= htmlspecialchars($char, ENT_NOQUOTES, 'UTF-8', false);
            }
        }
        
        // 关闭所有未闭合的样式标签
        if (!empty($current_styles)) {
            $html .= '</span>';
        }
        
        return $html;
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
        
        // 如果缓存不存在或已过期，从用户自建API获取新图标并缓存
        $icon_url = 'http://cow.mc6.cn:10709/icon/' . urlencode($server_address);
        $this->log('获取新图标: ' . $icon_url);
        
        // 使用cURL获取图标
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $icon_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Minecraft-Server-Status-Monitor/1.0');
        curl_setopt($ch, CURLOPT_HEADER, false); // 不需要获取响应头
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5秒超时
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        
        // 检查响应
        if ($http_code == 200 && !empty($response)) {
            // 保存图标到缓存文件
            file_put_contents($cache_file, $response);
            $this->log('图标已保存到缓存: ' . $cache_file);
            return $cache_file;
        } else {
            $this->log('无法获取图标，HTTP状态码: ' . $http_code);
            // 如果缓存文件存在但已过期，仍然返回它
            if (file_exists($cache_file)) {
                $this->log('返回已过期的缓存图标');
                return $cache_file;
            }
            // 获取失败，返回默认图标
            $this->log('获取图标失败，返回默认图标');
            return 'assets/default-icon.png';
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
    
    // 获取服务器原始玩家历史数据（包含玩家列表）
    public function getRawPlayerHistoryData($server_id, $days = 1) {
        require_once 'db.php';
        
        $db = new Database();
        $result = $db->getRawPlayerHistory($server_id, $days);
        
        // 初始化返回数据结构，确保即使没有数据也返回空数组
        $labels = array();
        $values = array();
        $playerLists = array();
        
        if ($result === false) {
            // 记录错误日志
            $this->log('获取原始历史数据失败，数据库查询错误');
            // 返回空的数据结构而不是false
            return array(
                'labels' => $labels,
                'values' => $values,
                'playerLists' => $playerLists
            );
        }
        
        // 处理查询结果
        while ($row = $result->fetch_assoc()) {
            // 格式化时间标签
            $time_label = date('Y-m-d H:i', strtotime($row['record_time']));
            $labels[] = $time_label;
            $values[] = $row['players_online'];
            
            // 解析玩家列表JSON
            $player_list = array();
            if (!empty($row['player_list_json'])) {
                try {
                    $player_list = json_decode($row['player_list_json'], true);
                    // 确保是数组格式
                    if (!is_array($player_list)) {
                        $player_list = array();
                    }
                } catch (Exception $e) {
                    $this->log('解析玩家列表失败: ' . $e->getMessage());
                }
            }
            $playerLists[] = $player_list;
        }
        
        $this->log('原始历史数据查询完成，数据点数量: ' . count($values));
        
        return array(
            'labels' => $labels,
            'values' => $values,
            'playerLists' => $playerLists
        );
    }
    
    // 使用cURL多线程并行获取多个服务器状态
    public function getServersStatusInParallel($servers) {
        $current_time = time();
        $cache_key_prefix = 'parallel_';
        $mh = curl_multi_init();
        $curl_handles = array();
        $server_results = array();

        // 初始化API URL（从配置文件读取）
        $api_urls = array();
        foreach ($this->java_api_urls as $index => $api_url) {
            $api_urls['api_' . ($index + 1)] = $api_url;
        }
        $primary_api_url = reset($api_urls);

        // 为每个服务器创建cURL句柄
        foreach ($servers as $server) {
            $server_address = $server['address'];
            $server_type = isset($server['type']) ? $server['type'] : 'java';
            $cache_key = $cache_key_prefix . $server_address . ':' . $server_type;

            // 检查是否有缓存并且缓存未过期
            if (isset($this->status_cache[$cache_key]) && 
                ($current_time - $this->status_cache[$cache_key]['timestamp']) < $this->status_cache_ttl) {
                
                $this->log('使用缓存的服务器状态数据: ' . $server_address);
                $server_results[$server_address] = $this->status_cache[$cache_key]['data'];
                continue;
            }

            // 构建API请求URL
            $url = $primary_api_url . urlencode($server_address);
            $this->log('创建并行请求: ' . $url);

            // 初始化cURL
            $ch = curl_init();

            // 设置cURL选项
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Minecraft-Server-Status-Monitor/1.0');
            curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10秒超时
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // 5秒连接超时

            // 将cURL句柄添加到多线程批处理
            curl_multi_add_handle($mh, $ch);
            $curl_handles[$server_address] = $ch;
        }

        // 执行所有并行请求
        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running > 0);

        // 处理响应
        foreach ($curl_handles as $server_address => $ch) {
            $response = curl_multi_getcontent($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $this->log('并行请求完成: ' . $server_address . ', HTTP状态码: ' . $http_code);

            // 检查错误
            if (curl_errno($ch)) {
                $error_message = 'API请求失败: ' . curl_error($ch);
                $this->log($error_message);
                $server_results[$server_address] = ['error' => $error_message];
            } else {
                // 解析JSON响应
                $data = json_decode($response, true);

                // 检查响应是否有效
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $json_error = json_last_error_msg();
                    $error_message = '无效的API响应: ' . $json_error;
                    $this->log($error_message);
                    $server_results[$server_address] = ['error' => $error_message];
                } else {
                    // 转换API响应格式
                    $formatted_data = [];
                    if (isset($data['online'])) {
                        $formatted_data['online'] = $data['online'];
                    }
                    if (isset($data['players']) && isset($data['players']['online'])) {
                        $formatted_data['players_online'] = $data['players']['online'];
                    }
                    if (isset($data['players']) && isset($data['players']['max'])) {
                        $formatted_data['players_max'] = $data['players']['max'];
                    }
                    if (isset($data['players']) && isset($data['players']['list']) && !empty($data['players']['list'])) {
                        $player_list = $data['players']['list'];
                        $players = explode(', ', $player_list);
                        $formatted_data['player_list'] = $players;
                    }
                    if (isset($data['version'])) {
                        $formatted_data['version'] = $data['version'];
                    }
                    if (isset($data['motd'])) {
                        $motd_json = json_decode($data['motd'], true);
                        if ($motd_json !== null) {
                            $formatted_data['motd'] = $this->extractMOTDText($motd_json);
                            $formatted_data['motd_html'] = $this->convertMOTDToHTML($motd_json);
                        } else {
                            $formatted_data['motd'] = $data['motd'];
                            $formatted_data['motd_html'] = $this->convertMinecraftColorsToHTML($data['motd']);
                        }
                    }
                    if (isset($data['favicon'])) {
                        $formatted_data['server_icon'] = $data['favicon'];
                    } else {
                        $formatted_data['server_icon'] = $this->getCachedServerIcon($server_address);
                    }
                    $formatted_data['server_address'] = $server_address;
                    if (isset($data['hostname'])) {
                        $formatted_data['hostname'] = $data['hostname'];
                    }
                    if (isset($data['ip'])) {
                        $formatted_data['ip_address'] = $data['ip'];
                    }
                    if (isset($data['online']) && !$data['online']) {
                        if (!isset($formatted_data['motd'])) {
                            $formatted_data['motd'] = '服务器当前离线';
                            $formatted_data['motd_html'] = '服务器当前离线';
                        }
                    }

                    // 存入缓存
                    $cache_key = $cache_key_prefix . $server_address . ':' . 'java';
                    $this->status_cache[$cache_key] = array(
                        'data' => $formatted_data,
                        'timestamp' => time()
                    );

                    $server_results[$server_address] = $formatted_data;
                }
            }

            // 关闭cURL句柄
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }

        // 关闭多线程批处理
        curl_multi_close($mh);

        return $server_results;
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

            case 'get_servers_status_parallel':
                if (isset($_GET['servers'])) {
                    $servers_json = $_GET['servers'];
                    $servers = json_decode($servers_json, true);
                    if (is_array($servers) && !empty($servers)) {
                        $statuses = $this->getServersStatusInParallel($servers);
                        $this->sendSuccessResponse($statuses);
                    } else {
                        $this->sendErrorResponse('无效的服务器列表参数');
                    }
                } else {
                    $this->sendErrorResponse('缺少服务器列表参数');
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
            
            case 'get_player_list':
                if (isset($_GET['server_id'])) {
                    $server_id = $_GET['server_id'];
                    $player_data = $this->getPlayerListData($server_id);
                    if (is_array($player_data)) {
                        $this->sendSuccessResponse($player_data);
                    } else {
                        $this->sendErrorResponse('获取玩家列表失败');
                    }
                } else {
                    $this->sendErrorResponse('缺少服务器ID参数');
                }
                break;
                
            case 'get_raw_player_history':
                if (isset($_GET['server_id'])) {
                    $server_id = $_GET['server_id'];
                    // 使用floatval保留小数天数，而不是intval
                    $days = isset($_GET['days']) ? floatval($_GET['days']) : 1;
                    // 允许0作为查询所有记录的特殊值
                    if ($days != 0) {
                        // 限制天数范围，允许小数天数但设置最小为0.01(约15分钟)
                        $days = max(0.01, min(30, $days));
                    }
                    $history_data = $this->getRawPlayerHistoryData($server_id, $days);
                    // 检查是否为数组，而不仅仅是真值检查，确保空数据也能正确返回
                    if (is_array($history_data)) {
                        $this->sendSuccessResponse($history_data);
                    } else {
                        $this->sendErrorResponse('获取原始历史数据失败');
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

    // 获取玩家列表数据
    private function getPlayerListData($server_id) {
        try {
            // 从player_history表获取最新的玩家列表数据
            $sql = "SELECT player_list_json FROM player_history WHERE server_id = ? ORDER BY record_time DESC LIMIT 1";
            
            // 获取数据库连接
            $conn = $this->db->getConnection();
            
            // 使用mysqli连接对象准备语句
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $server_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $player_list_json = $row['player_list_json'];
                
                // 如果玩家列表不为空，则解析JSON
                if (!empty($player_list_json)) {
                    $player_list = json_decode($player_list_json, true);
                    return $player_list;
                }
            }
            
            // 如果没有数据或解析失败，返回空数组
            return array();
        } catch (Exception $e) {
            $this->log('获取玩家列表数据失败: ' . $e->getMessage());
            return false;
        }
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
    
    // 注意：玩家列表数据现在通过savePlayerHistory方法直接存储在player_history表的player_list_json字段中

}

// 只有当本文件被直接访问时才实例化并处理请求
if (__FILE__ == $_SERVER['SCRIPT_FILENAME']) {
    $api = new MinecraftAPI();
    $api->handleRequest();
}
?>