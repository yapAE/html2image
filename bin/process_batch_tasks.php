#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

// 设置自动加载
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/..');
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
    echo "处理所有待处理任务\n";
    BatchWorker::runWorker();
}

echo "Worker执行完成\n";