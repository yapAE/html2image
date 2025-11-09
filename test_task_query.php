#!/usr/bin/env php
<?php
/**
 * 测试任务查询逻辑
 */

// 模拟任务ID
$taskId = 'task_690f19b0a34c69.59382715';

echo "测试任务查询逻辑\n";
echo "任务ID: $taskId\n\n";

// 检查各个目录
$directories = ['pending', 'processing', 'done', 'failed'];
$found = false;

foreach ($directories as $dir) {
    $filePath = __DIR__ . "/test_queue/{$dir}/{$taskId}.json";
    echo "检查目录 {$dir}: $filePath\n";
    
    if (file_exists($filePath)) {
        echo "  -> 文件存在!\n";
        $content = file_get_contents($filePath);
        $data = json_decode($content, true);
        echo "  -> 文件大小: " . strlen($content) . " 字节\n";
        echo "  -> JSON解析: " . ($data ? "成功" : "失败") . "\n";
        if ($data) {
            echo "  -> 状态: " . ($data['status'] ?? '未知') . "\n";
            echo "  -> 创建时间: " . ($data['created_at'] ?? '未知') . "\n";
        }
        $found = true;
    } else {
        echo "  -> 文件不存在\n";
    }
    echo "\n";
}

if (!$found) {
    echo "任务文件在所有目录中都未找到\n";
    
    // 创建测试目录和文件
    echo "创建测试目录和文件...\n";
    foreach ($directories as $dir) {
        $dirPath = __DIR__ . "/test_queue/{$dir}/";
        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0755, true);
        }
    }
    
    // 创建一个测试任务文件
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
    
    $testFilePath = __DIR__ . "/test_queue/done/{$taskId}.json";
    file_put_contents($testFilePath, json_encode($testData, JSON_PRETTY_PRINT));
    echo "创建测试任务文件: $testFilePath\n\n";
    
    // 再次检查
    echo "再次检查任务文件:\n";
    $content = file_get_contents($testFilePath);
    $data = json_decode($content, true);
    echo "文件内容:\n";
    echo $content . "\n\n";
}