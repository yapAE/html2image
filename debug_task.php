#!/usr/bin/env php
<?php
/**
 * 调试任务查询问题
 */

// 在容器环境中，确保正确的路径引用
$basePath = '/app';
require_once $basePath . '/vendor/autoload.php';

// 设置自动加载
set_include_path(get_include_path() . PATH_SEPARATOR . $basePath);
spl_autoload_extensions('.php');
spl_autoload_register();

use App\Utils\FileTaskStorage;
use App\Utils\FileQueue;

$taskId = $argv[1] ?? null;

if (!$taskId) {
    echo "请提供任务ID作为参数\n";
    exit(1);
}

echo "调试任务: $taskId\n";

// 检查旧的任务存储系统
echo "=== 检查旧的任务存储系统 ===\n";
$taskStorage = new FileTaskStorage();
$taskData = $taskStorage->getTask($taskId);

if ($taskData) {
    echo "在旧的任务存储系统中找到任务:\n";
    print_r($taskData);
} else {
    echo "在旧的任务存储系统中未找到任务\n";
    
    // 检查文件是否存在
    $filePath = "/tmp/batch_task_meta/" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $taskId) . '.json';
    echo "检查文件路径: $filePath\n";
    if (file_exists($filePath)) {
        echo "文件存在，内容:\n";
        echo file_get_contents($filePath) . "\n";
    } else {
        echo "文件不存在\n";
    }
}

// 检查队列系统
echo "\n=== 检查队列系统 ===\n";
$queue = new FileQueue('/app/queue');

// 检查各个目录
foreach (['pending', 'processing', 'done', 'failed'] as $dir) {
    $path = "/app/queue/{$dir}/{$taskId}.json";
    echo "检查目录 {$dir}: $path\n";
    if (file_exists($path)) {
        echo "文件存在，内容:\n";
        echo file_get_contents($path) . "\n";
    } else {
        echo "文件不存在\n";
    }
}

echo "\n=== 队列状态统计 ===\n";
$stats = $queue->stats();
print_r($stats);