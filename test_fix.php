#!/usr/bin/env php
<?php
/**
 * 测试修复后的功能
 */

// 在容器环境中，确保正确的路径引用
$basePath = '/app';
require_once $basePath . '/vendor/autoload.php';

// 设置自动加载
set_include_path(get_include_path() . PATH_SEPARATOR . $basePath);
spl_autoload_extensions('.php');
spl_autoload_register();

use App\Utils\FileQueue;
use App\Utils\FileTaskStorage;

echo "测试修复后的功能...\n";

// 初始化队列和任务存储
$queue = new FileQueue('/app/queue');
$taskStorage = new FileTaskStorage();

// 创建一个测试任务
echo "创建测试任务...\n";
$taskId = $queue->push([
    'data' => [
        'urls' => ['https://example.com'],
        'format' => 'png'
    ],
    'created_at' => date('c')
]);

echo "已创建任务: $taskId\n";

// 检查任务是否在队列中
echo "检查任务是否在队列中...\n";
$pendingFiles = glob("/app/queue/pending/*.json");
foreach ($pendingFiles as $file) {
    $content = json_decode(file_get_contents($file), true);
    if (isset($content['id']) && $content['id'] === $taskId) {
        echo "任务在pending队列中找到\n";
        break;
    }
}

// 检查任务是否在旧的存储系统中
echo "检查任务是否在旧的存储系统中...\n";
$taskData = $taskStorage->getTask($taskId);
if ($taskData) {
    echo "任务在旧的存储系统中找到\n";
    print_r($taskData);
} else {
    echo "任务在旧的存储系统中未找到\n";
}

echo "测试完成\n";