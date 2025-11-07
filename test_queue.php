#!/usr/bin/env php
<?php
/**
 * 测试队列系统
 */

// 在容器环境中，确保正确的路径引用
$basePath = '/app';
require_once $basePath . '/vendor/autoload.php';

// 设置自动加载
set_include_path(get_include_path() . PATH_SEPARATOR . $basePath);
spl_autoload_extensions('.php');
spl_autoload_register();

use App\Utils\FileQueue;
use App\Utils\ApiResponse;

echo "测试队列系统...\n";

// 初始化队列
$queue = new FileQueue('/app/queue');

// 测试添加任务
echo "添加测试任务...\n";
$taskId1 = $queue->push([
    'urls' => ['https://example.com', 'https://google.com'],
    'format' => 'png'
]);

$taskId2 = $queue->push([
    'html' => '<h1>Test HTML</h1><p>This is a test.</p>',
    'format' => 'pdf'
]);

echo "已添加任务: $taskId1, $taskId2\n";

// 测试获取任务
echo "获取待处理任务...\n";
$task = $queue->pop();
if ($task) {
    echo "获取到任务: {$task['id']}\n";
    print_r($task);
    
    // 测试标记任务完成
    echo "标记任务完成...\n";
    $queue->done($task['id'], array_merge($task, ['test_result' => 'success']));
} else {
    echo "没有待处理任务\n";
}

// 测试队列统计
echo "队列状态统计:\n";
$stats = $queue->stats();
print_r($stats);

echo "队列系统测试完成\n";