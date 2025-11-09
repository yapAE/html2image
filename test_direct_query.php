#!/usr/bin/env php
<?php
/**
 * 直接测试任务查询逻辑
 */

require 'vendor/autoload.php';

// 设置自动加载
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__);
spl_autoload_extensions('.php');
spl_autoload_register();

use App\Utils\FileQueue;
use App\Utils\ApiResponse;

// 创建测试队列目录
$testQueueDir = __DIR__ . '/test_queue';
if (!is_dir($testQueueDir)) {
    mkdir($testQueueDir, 0755, true);
    foreach (['pending', 'processing', 'done', 'failed'] as $dir) {
        mkdir("{$testQueueDir}/{$dir}", 0755, true);
    }
}

// 创建测试任务文件
$taskId = 'task_690f19b0a34c69.59382715';
$testData = [
    'id' => $taskId,
    'status' => 'done',
    'created_at' => '2023-12-01T10:00:00+08:00',
    'started_at' => '2023-12-01T10:00:05+08:00',
    'finished_at' => '2023-12-01T10:00:30+08:00',
    'data' => [
        'urls' => ['https://example.com'],
        'format' => 'png'
    ],
    'results' => [
        [
            'identifier' => 'url_0',
            'type' => 'png',
            'ossUrl' => 'https://your-bucket.oss-region.aliyuncs.com/screenshots/2023/12/01/id1.png'
        ]
    ],
    'errors' => []
];

file_put_contents("{$testQueueDir}/done/{$taskId}.json", json_encode($testData, JSON_PRETTY_PRINT));

echo "创建测试任务文件完成\n";
echo "测试队列目录: $testQueueDir\n";
echo "任务ID: $taskId\n\n";

// 测试FileQueue类
echo "测试FileQueue类...\n";
$fileQueue = new FileQueue($testQueueDir);

// 按优先级顺序检查任务在各个状态目录中的存在情况
$directories = ['done', 'failed', 'processing', 'pending'];
$taskData = null;

foreach ($directories as $dir) {
    $filePath = "{$testQueueDir}/{$dir}/{$taskId}.json";
    echo "检查目录 {$dir}: $filePath\n";
    
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        $taskData = json_decode($content, true);
        if ($taskData) {
            // 添加状态字段
            $taskData['status'] = $dir;
            echo "  -> 找到任务文件，状态: $dir\n";
            break;
        }
    } else {
        echo "  -> 文件不存在\n";
    }
}

if ($taskData) {
    echo "任务查询成功:\n";
    echo json_encode(ApiResponse::success($taskData, '任务状态获取成功'), JSON_PRETTY_PRINT) . "\n";
} else {
    echo "任务未找到\n";
    echo json_encode(ApiResponse::error('TASK_NOT_FOUND', '任务不存在或已过期'), JSON_PRETTY_PRINT) . "\n";
}