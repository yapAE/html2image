#!/usr/bin/env php
<?php
/**
 * 模拟路由处理
 */

// 模拟请求
$requestUri = '/api/batch/screenshot/task_690f19b0a34c69.59382715';
$requestMethod = 'GET';

echo "模拟路由处理\n";
echo "请求URI: $requestUri\n";
echo "请求方法: $requestMethod\n\n";

// 解析路径信息
$pathInfo = parse_url($requestUri, PHP_URL_PATH);
echo "解析后的路径信息: $pathInfo\n";

// 路由处理
if (strpos($pathInfo, '/api/batch/screenshot') === 0 && ($requestMethod === 'POST' || $requestMethod === 'GET')) {
    echo "匹配异步批处理路由\n";
    
    // POST请求提交新任务
    if ($requestMethod === 'POST') {
        echo "处理POST请求 - 提交新任务\n";
        // 这里会创建新任务
    }
    
    // GET请求查询任务状态
    if ($requestMethod === 'GET') {
        echo "处理GET请求 - 查询任务状态\n";
        
        // 检查是否请求任务摘要（快速查询）
        $queryParams = [];
        if (strpos($requestUri, '?') !== false) {
            parse_str(parse_url($requestUri, PHP_URL_QUERY), $queryParams);
        }
        
        $summaryOnly = isset($queryParams['summary']) && $queryParams['summary'] === 'true';
        echo "是否请求摘要: " . ($summaryOnly ? '是' : '否') . "\n";
        
        // 获取任务ID（用于GET请求查询任务状态）
        if (preg_match('/\/([^\/]+)$/', $pathInfo, $matches)) {
            $taskId = $matches[1];
            echo "提取的任务ID: $taskId\n";
            
            // 模拟任务查询逻辑
            $directories = ['done', 'failed', 'processing', 'pending'];
            $taskData = null;
            
            foreach ($directories as $dir) {
                $filePath = __DIR__ . "/test_queue/{$dir}/{$taskId}.json";
                echo "检查文件: $filePath\n";
                
                if (file_exists($filePath)) {
                    $content = file_get_contents($filePath);
                    $taskData = json_decode($content, true);
                    if ($taskData) {
                        // 添加状态字段
                        $taskData['status'] = $dir;
                        echo "找到任务文件，状态: $dir\n";
                        break;
                    }
                }
            }
            
            if ($taskData) {
                if ($summaryOnly) {
                    // 只返回摘要信息
                    $summary = [
                        'taskId' => $taskData['id'] ?? $taskId,
                        'status' => $taskData['status'] ?? 'unknown',
                        'createdAt' => $taskData['created_at'] ?? null,
                        'startedAt' => $taskData['started_at'] ?? null,
                        'finishedAt' => $taskData['finished_at'] ?? null,
                        'failedAt' => $taskData['failed_at'] ?? null,
                        'error' => $taskData['error'] ?? null
                    ];
                    
                    // 如果有处理统计信息，也包含在摘要中
                    if (isset($taskData['completedItems'])) {
                        $summary['completedItems'] = $taskData['completedItems'];
                    }
                    if (isset($taskData['failedItems'])) {
                        $summary['failedItems'] = $taskData['failedItems'];
                    }
                    if (isset($taskData['totalItems'])) {
                        $summary['totalItems'] = $taskData['totalItems'];
                    }
                    
                    echo "返回任务摘要:\n";
                    echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
                } else {
                    echo "返回完整任务信息:\n";
                    echo json_encode($taskData, JSON_PRETTY_PRINT) . "\n";
                }
            } else {
                echo "任务未找到，返回404错误\n";
                echo json_encode([
                    'success' => false,
                    'error' => [
                        'code' => 'TASK_NOT_FOUND',
                        'message' => '任务不存在或已过期'
                    ],
                    'data' => null
                ], JSON_PRETTY_PRINT) . "\n";
            }
        } else {
            echo "未提供任务ID，返回400错误\n";
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => 'MISSING_TASK_ID',
                    'message' => '必须提供任务ID'
                ],
                'data' => null
            ], JSON_PRETTY_PRINT) . "\n";
        }
    }
} else {
    echo "未匹配任何路由，返回404错误\n";
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'ROUTE_NOT_FOUND',
            'message' => '请求的路由不存在'
        ],
        'data' => null
    ], JSON_PRETTY_PRINT) . "\n";
}