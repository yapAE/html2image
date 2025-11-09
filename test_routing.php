#!/usr/bin/env php
<?php
/**
 * 测试路由解析
 */

// 模拟请求URI
$requestUri = '/api/batch/screenshot/task_690f19b0a34c69.59382715';
$requestMethod = 'GET';

echo "请求URI: $requestUri\n";
echo "请求方法: $requestMethod\n\n";

// 解析路径信息
$pathInfo = parse_url($requestUri, PHP_URL_PATH);
echo "解析后的路径信息: $pathInfo\n";

// 检查路由匹配
$matchesRoute = strpos($pathInfo, '/api/batch/screenshot') === 0 && ($requestMethod === 'POST' || $requestMethod === 'GET');
echo "是否匹配异步批处理路由: " . ($matchesRoute ? '是' : '否') . "\n";

// 提取任务ID
if (preg_match('/\/([^\/]+)$/', $pathInfo, $matches)) {
    $taskId = $matches[1];
    echo "提取的任务ID: $taskId\n";
} else {
    echo "未能提取任务ID\n";
}

echo "\n";

// 测试不同的URI
$testUris = [
    '/api/batch/screenshot/task_690f19b0a34c69.59382715',
    '/api/batch/screenshot/task_690f19b0a34c69.59382715?summary=true',
    '/api/batch/screenshot/',
    '/api/batch/screenshot'
];

foreach ($testUris as $uri) {
    echo "测试URI: $uri\n";
    $pathInfo = parse_url($uri, PHP_URL_PATH);
    echo "  路径信息: $pathInfo\n";
    
    if (preg_match('/\/([^\/]+)$/', $pathInfo, $matches)) {
        $taskId = $matches[1];
        echo "  提取的任务ID: $taskId\n";
    } else {
        echo "  未能提取任务ID\n";
    }
    echo "\n";
}