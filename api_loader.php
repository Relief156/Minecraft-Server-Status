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