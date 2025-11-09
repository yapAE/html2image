#!/usr/bin/env php
<?php
/**
 * 测试服务器响应
 */

// 模拟服务器环境变量
$_SERVER['REQUEST_URI'] = '/api/batch/screenshot/task_690f19b0a34c69.59382715';
$_SERVER['REQUEST_METHOD'] = 'GET';

echo "模拟服务器环境\n";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n\n";

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

// 修改FileQueue类的基础目录
echo "修改FileQueue类的基础目录为: $testQueueDir\n";

// 包含实际的index.php逻辑
require_once __DIR__ . '/index.php';