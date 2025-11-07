#!/usr/bin/env php
<?php

// 在容器环境中，确保正确的路径引用
$basePath = '/app';
require_once $basePath . '/vendor/autoload.php';

// 设置自动加载
set_include_path(get_include_path() . PATH_SEPARATOR . $basePath);
spl_autoload_extensions('.php');
spl_autoload_register();

use App\Utils\BatchWorker;

// 获取命令行参数
$options = getopt("t:");
$taskId = $options['t'] ?? null;

echo "启动批处理任务Worker...\n";

if ($taskId) {
    echo "处理指定任务: $taskId\n";
    BatchWorker::runWorker($taskId);
} else {
    echo "启动持续任务处理模式\n";
    // 持续处理任务模式
    while (true) {
        try {
            // 处理所有待处理任务
            BatchWorker::runWorker();
            
            // 等待一段时间再检查新任务
            echo "等待5秒后检查新任务...\n";
            sleep(5);
        } catch (Exception $e) {
            echo "处理任务时发生错误: " . $e->getMessage() . "\n";
            // 等待一段时间再重试
            sleep(10);
        }
    }
}

echo "Worker执行完成\n";