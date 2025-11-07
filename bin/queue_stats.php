#!/usr/bin/env php
<?php
/**
 * 队列状态统计API
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
$queue = new FileQueue('/app/queue');

// 获取队列状态统计
$stats = $queue->stats();

// 输出JSON格式的统计信息
header('Content-Type: application/json');
echo json_encode(ApiResponse::success($stats, '队列状态统计获取成功'));