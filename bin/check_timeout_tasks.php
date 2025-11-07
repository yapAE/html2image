#!/usr/bin/env php
<?php
/**
 * 检查超时任务并重试失败任务
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

// 初始化队列
$queue = new FileQueue('/app/queue', 3600); // 1小时超时

// 检查超时任务
$timeoutCount = $queue->getTimeoutTaskCount();
echo "发现 {$timeoutCount} 个超时任务\n";

// 重试所有失败的任务
$retriedCount = $queue->retryAllFailed();
echo "重试了 {$retriedCount} 个失败任务\n";

// 输出队列状态统计
$stats = $queue->stats();
echo "队列状态统计:\n";
print_r($stats);

// 输出JSON格式的统计信息
header('Content-Type: application/json');
echo json_encode(ApiResponse::success([
    'timeout_tasks' => $timeoutCount,
    'retried_tasks' => $retriedCount,
    'queue_stats' => $stats
], '超时任务检查和重试完成'));