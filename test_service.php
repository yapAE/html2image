<?php

require 'vendor/autoload.php';

// 设置自动加载
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__);
spl_autoload_extensions('.php');
spl_autoload_register();

use App\Service\ScreenshotService;

// 测试 Service 类
$service = new ScreenshotService();

// 测试数据
$testData = [
    'url' => 'https://example.com',
    'format' => 'png'
];

try {
    echo "Testing ScreenshotService...\n";
    // 这里只是测试类是否能正常加载，实际的截图测试需要 Puppeteer 环境
    echo "ScreenshotService loaded successfully!\n";
    echo "Class methods:\n";
    print_r(get_class_methods($service));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}