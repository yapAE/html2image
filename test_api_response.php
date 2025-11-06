<?php

require 'vendor/autoload.php';

// 设置自动加载
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__);
spl_autoload_extensions('.php');
spl_autoload_register();

use App\Utils\ApiResponse;

// 测试各种API响应格式

echo "=== 测试成功响应 ===\n";
$successResponse = ApiResponse::success(['test' => 'data'], '操作成功');
echo json_encode($successResponse, JSON_PRETTY_PRINT) . "\n\n";

echo "=== 测试错误响应 ===\n";
$errorResponse = ApiResponse::error('TEST_ERROR', '这是一个测试错误');
echo json_encode($errorResponse, JSON_PRETTY_PRINT) . "\n\n";

echo "=== 测试二进制数据响应 ===\n";
$binaryResponse = ApiResponse::binaryData('png', 'fake_image_data', 100);
echo json_encode($binaryResponse, JSON_PRETTY_PRINT) . "\n\n";

echo "=== 测试OSS上传响应 ===\n";
$ossResponse = ApiResponse::ossUpload('png', 'https://bucket.endpoint/test.png');
echo json_encode($ossResponse, JSON_PRETTY_PRINT) . "\n\n";

echo "=== 测试批量处理响应 ===\n";
$batchResponse = ApiResponse::batchResult(
    [
        ['identifier' => 'url_0', 'type' => 'png', 'ossUrl' => 'https://bucket.endpoint/test1.png'],
        ['identifier' => 'url_1', 'type' => 'pdf', 'ossUrl' => 'https://bucket.endpoint/test1.pdf']
    ],
    [
        ['index' => 2, 'type' => 'url', 'value' => 'https://invalid.com', 'error' => '无法连接']
    ]
);
echo json_encode($batchResponse, JSON_PRETTY_PRINT) . "\n\n";

echo "所有测试完成！\n";