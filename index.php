<?php

require 'vendor/autoload.php';

// 设置自动加载
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__);
spl_autoload_extensions('.php');
spl_autoload_register();

use App\Controller\ScreenshotController;

// 获取请求路径
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// 解析路径信息
$pathInfo = parse_url($requestUri, PHP_URL_PATH);

// 路由处理
if (strpos($pathInfo, '/api/queue/stats') === 0 && $requestMethod === 'GET') {
    // 队列状态统计API
    header('Content-Type: application/json');
    require_once 'bin/queue_stats.php';
} else if (strpos($pathInfo, '/api/batch/screenshot') === 0 && ($requestMethod === 'POST' || $requestMethod === 'GET')) {
    // 异步批处理路由
    header('Content-Type: application/json');
    $controller = new ScreenshotController();
    $controller->handleAsyncBatchRequest();
} else if (strpos($pathInfo, '/api/screenshot') === 0 && $requestMethod === 'POST') {
    // API路由 - 返回JSON格式响应
    header('Content-Type: application/json');
    $controller = new ScreenshotController();
    $controller->handleApiRequest();
} else if (strpos($pathInfo, '/screenshot') === 0 && $requestMethod === 'POST') {
    // 传统路由 - 返回二进制数据
    $controller = new ScreenshotController();
    $controller->handleRequest();
} else {
    // 默认路由
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'ROUTE_NOT_FOUND',
            'message' => '请求的路由不存在'
        ]
    ]);
}