<?php

// 测试容器环境设置
echo "测试容器环境设置...\n";

// 检查必要的目录
$dirs = [
    '/tmp/batch_task_meta',
    '/var/log/php',
    '/var/log/batch'
];

foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        echo "目录存在: $dir\n";
        // 检查权限
        if (is_writable($dir)) {
            echo "  权限正常: 可写\n";
        } else {
            echo "  权限问题: 不可写\n";
        }
    } else {
        echo "目录不存在: $dir\n";
    }
}

// 检查必要的文件
$files = [
    '/var/log/php/error.log',
    '/var/log/batch/cleanup.log'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "文件存在: $file\n";
        // 检查权限
        if (is_writable($file)) {
            echo "  权限正常: 可写\n";
        } else {
            echo "  权限问题: 不可写\n";
        }
    } else {
        echo "文件不存在: $file\n";
    }
}

// 检查环境变量
$envVars = [
    'PUPPETEER_EXECUTABLE_PATH',
    'NODE_PATH'
];

foreach ($envVars as $var) {
    $value = getenv($var);
    if ($value !== false) {
        echo "环境变量 $var: $value\n";
    } else {
        echo "环境变量 $var: 未设置\n";
    }
}

echo "容器环境检查完成。\n";