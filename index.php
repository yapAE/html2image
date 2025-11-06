<?php

require 'vendor/autoload.php';

// 设置自动加载
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__);
spl_autoload_extensions('.php');
spl_autoload_register();

use App\Controller\ScreenshotController;

// 创建控制器并处理请求
$controller = new ScreenshotController();
$controller->handleRequest();