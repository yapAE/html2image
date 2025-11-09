#!/usr/bin/env php
<?php
/**
 * 调试服务器响应问题
 */

// 模拟实际的服务器环境
$_SERVER['REQUEST_URI'] = '/api/batch/screenshot/task_690f19b0a34c69.59382715';
$_SERVER['REQUEST_METHOD'] = 'GET';

echo "=== 调试服务器响应问题 ===\n";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n\n";

// 包含index.php来测试路由
require_once __DIR__ . '/index.php';