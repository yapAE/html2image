<?php

// 简化测试路由功能

// 模拟请求URI和方法
$testCases = [
    [
        'uri' => '/api/screenshot',
        'method' => 'POST',
        'description' => 'API路由测试'
    ],
    [
        'uri' => '/screenshot',
        'method' => 'POST',
        'description' => '传统路由测试'
    ],
    [
        'uri' => '/unknown',
        'method' => 'GET',
        'description' => '未知路由测试'
    ]
];

foreach ($testCases as $case) {
    $_SERVER['REQUEST_URI'] = $case['uri'];
    $_SERVER['REQUEST_METHOD'] = $case['method'];
    
    echo "=== " . $case['description'] . " ===\n";
    echo "URI: " . $case['uri'] . "\n";
    echo "Method: " . $case['method'] . "\n";
    
    if (strpos($case['uri'], '/api/screenshot') === 0 && $case['method'] === 'POST') {
        echo "匹配: API路由 - 将返回JSON格式响应\n";
    } else if (strpos($case['uri'], '/screenshot') === 0 && $case['method'] === 'POST') {
        echo "匹配: 传统路由 - 将返回二进制数据\n";
    } else {
        echo "匹配: 默认路由 - 将返回404错误\n";
    }
    
    echo "\n";
}