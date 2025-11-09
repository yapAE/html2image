#!/usr/bin/env php
<?php
/**
 * 调试任务文件检查
 */

$taskId = $argv[1] ?? null;

if (!$taskId) {
    echo "请提供任务ID作为参数\n";
    echo "用法: php debug_task_check.php <task_id>\n";
    exit(1);
}

echo "检查任务ID: $taskId\n";

// 检查各个目录
$directories = ['pending', 'processing', 'done', 'failed'];
$found = false;

foreach ($directories as $dir) {
    $filePath = "/app/queue/{$dir}/{$taskId}.json";
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
    
    // 列出所有目录中的文件
    echo "列出所有目录中的任务文件:\n";
    foreach ($directories as $dir) {
        $dirPath = "/app/queue/{$dir}/";
        if (is_dir($dirPath)) {
            echo "目录 {$dir}:\n";
            $files = glob("{$dirPath}*.json");
            foreach ($files as $file) {
                $fileName = basename($file);
                echo "  - $fileName\n";
            }
            echo "\n";
        }
    }
}