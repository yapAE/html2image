#!/usr/bin/env php
<?php

// 在容器环境中，确保正确的路径引用
$basePath = dirname(__DIR__);
require_once $basePath . '/vendor/autoload.php';

// 设置自动加载
set_include_path(get_include_path() . PATH_SEPARATOR . $basePath);
spl_autoload_extensions('.php');
spl_autoload_register();

use App\Utils\FileTaskStorage;

echo "开始清理过期任务...\n";

try {
    $storage = new FileTaskStorage();
    $cleanedCount = $storage->cleanupExpiredTasks();
    
    echo "清理完成，共清理 $cleanedCount 个过期任务\n";
} catch (Exception $e) {
    echo "清理过程中发生错误: " . $e->getMessage() . "\n";
    exit(1);
}