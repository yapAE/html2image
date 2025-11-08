#!/usr/bin/env php
<?php
/**
 * 基于文件队列的任务处理守护进程
 */

// 在容器环境中，确保正确的路径引用
$basePath = '/app';
require_once $basePath . '/vendor/autoload.php';

// 设置自动加载
set_include_path(get_include_path() . PATH_SEPARATOR . $basePath);
spl_autoload_extensions('.php');
spl_autoload_register();

use App\Utils\FileQueue;
use App\Service\ScreenshotService;
use App\Utils\ApiResponse;
use App\Utils\FileTaskStorage;

echo "启动队列处理守护进程...\n";

// 初始化队列和截图服务
$queue = new FileQueue('/app/queue');
$screenshotService = new ScreenshotService();
$taskStorage = new FileTaskStorage();

// 持续处理任务
while (true) {
    try {
        // 获取一个待处理任务
        $task = $queue->pop();
        
        if ($task) {
            echo "处理任务: {$task['id']}\n";
            
            try {
                // 处理任务数据
                $requestData = $task['data'] ?? [];
                
                if (empty($requestData)) {
                    throw new Exception('任务数据为空');
                }
                
                // 创建任务存储记录
                $taskId = $task['id'];
                $totalItems = 0;
                if (isset($requestData['urls']) && is_array($requestData['urls'])) {
                    $totalItems += count($requestData['urls']);
                }
                if (isset($requestData['htmls']) && is_array($requestData['htmls'])) {
                    $totalItems += count($requestData['htmls']);
                }
                if (isset($requestData['items']) && is_array($requestData['items'])) {
                    $totalItems += count($requestData['items']);
                }
                
                // 检查任务是否已存在，如果不存在则创建
                $existingTask = $taskStorage->getTask($taskId);
                if (!$existingTask) {
                    // 创建任务元数据
                    $taskMetadata = [
                        'status' => 'processing',
                        'totalItems' => $totalItems,
                        'completedItems' => 0,
                        'failedItems' => 0,
                        'results' => [],
                        'errors' => [],
                        'requestData' => $requestData
                    ];
                    
                    // 保存任务到旧的存储系统，以便API可以查询
                    $taskStorage->saveTask($taskId, $taskMetadata);
                    echo "创建新任务记录: $taskId\n";
                } else {
                    // 更新任务状态为处理中
                    $taskStorage->updateTask($taskId, ['status' => 'processing']);
                    echo "更新现有任务记录: $taskId\n";
                }
                
                // 处理请求数据
                $results = [];
                $errors = [];
                $completedItems = 0;
                $failedItems = 0;
                
                // 处理 URLs 数组
                if (isset($requestData['urls']) && is_array($requestData['urls'])) {
                    foreach ($requestData['urls'] as $index => $url) {
                        try {
                            $itemData = ['url' => $url] + $requestData;
                            $result = $screenshotService->processSingleScreenshot($itemData);
                            $result['identifier'] = "url_$index";
                            $results[] = $result;
                            $completedItems++;
                            
                            // 更新进度
                            $progressData = [
                                'completedItems' => $completedItems,
                                'failedItems' => $failedItems,
                                'results' => $results,
                                'errors' => $errors
                            ];
                            $taskStorage->updateTask($taskId, $progressData);
                        } catch (\Exception $e) {
                            $errors[] = [
                                'index' => $index,
                                'type' => 'url',
                                'value' => $url,
                                'error' => $e->getMessage()
                            ];
                            $failedItems++;
                            
                            // 更新进度
                            $progressData = [
                                'completedItems' => $completedItems,
                                'failedItems' => $failedItems,
                                'results' => $results,
                                'errors' => $errors
                            ];
                            $taskStorage->updateTask($taskId, $progressData);
                        }
                    }
                }
                
                // 处理 HTMLs 数组
                if (isset($requestData['htmls']) && is_array($requestData['htmls'])) {
                    foreach ($requestData['htmls'] as $index => $html) {
                        try {
                            $itemData = ['html' => $html] + $requestData;
                            $result = $screenshotService->processSingleScreenshot($itemData);
                            $result['identifier'] = "html_$index";
                            $results[] = $result;
                            $completedItems++;
                            
                            // 更新进度
                            $progressData = [
                                'completedItems' => $completedItems,
                                'failedItems' => $failedItems,
                                'results' => $results,
                                'errors' => $errors
                            ];
                            $taskStorage->updateTask($taskId, $progressData);
                        } catch (\Exception $e) {
                            $errors[] = [
                                'index' => $index,
                                'type' => 'html',
                                'value' => substr($html, 0, 50) . '...',
                                'error' => $e->getMessage()
                            ];
                            $failedItems++;
                            
                            // 更新进度
                            $progressData = [
                                'completedItems' => $completedItems,
                                'failedItems' => $failedItems,
                                'results' => $results,
                                'errors' => $errors
                            ];
                            $taskStorage->updateTask($taskId, $progressData);
                        }
                    }
                }
                
                // 处理 items 数组
                if (isset($requestData['items']) && is_array($requestData['items'])) {
                    foreach ($requestData['items'] as $index => $item) {
                        try {
                            $itemData = $item + $requestData;
                            $result = $screenshotService->processSingleScreenshot($itemData);
                            $result['identifier'] = "item_$index";
                            $results[] = $result;
                            $completedItems++;
                            
                            // 更新进度
                            $progressData = [
                                'completedItems' => $completedItems,
                                'failedItems' => $failedItems,
                                'results' => $results,
                                'errors' => $errors
                            ];
                            $taskStorage->updateTask($taskId, $progressData);
                        } catch (\Exception $e) {
                            $errors[] = [
                                'index' => $index,
                                'type' => 'item',
                                'value' => json_encode($item),
                                'error' => $e->getMessage()
                            ];
                            $failedItems++;
                            
                            // 更新进度
                            $progressData = [
                                'completedItems' => $completedItems,
                                'failedItems' => $failedItems,
                                'results' => $results,
                                'errors' => $errors
                            ];
                            $taskStorage->updateTask($taskId, $progressData);
                        }
                    }
                }
                
                // 更新任务数据
                $task['results'] = $results;
                $task['errors'] = $errors;
                $task['completedItems'] = $completedItems;
                $task['failedItems'] = $failedItems;
                $task['summary'] = [
                    'total' => $completedItems + $failedItems,
                    'success' => $completedItems,
                    'failed' => $failedItems
                ];
                
                // 标记任务完成（在队列系统中）
                $queue->done($task['id'], $task);
                
                // 更新任务状态（在旧的存储系统中）
                $finalData = [
                    'status' => 'completed',
                    'completedItems' => $completedItems,
                    'failedItems' => $failedItems,
                    'results' => $results,
                    'errors' => $errors,
                    'summary' => [
                        'total' => $completedItems + $failedItems,
                        'success' => $completedItems,
                        'failed' => $failedItems
                    ],
                    'completedAt' => time()
                ];
                $taskStorage->updateTask($taskId, $finalData);
                
                echo "任务 {$task['id']} 处理完成\n";
                
            } catch (\Exception $e) {
                echo "任务 {$task['id']} 处理失败: " . $e->getMessage() . "\n";
                $queue->fail($task['id'], $e->getMessage());
                
                // 更新任务状态为失败（在旧的存储系统中）
                $failData = [
                    'status' => 'failed',
                    'failedAt' => time(),
                    'errorMessage' => $e->getMessage()
                ];
                $taskStorage->updateTask($taskId, $failData);
            }
        } else {
            // 没有待处理任务，短暂休眠
            sleep(2);
        }
    } catch (\Exception $e) {
        echo "处理任务时发生错误: " . $e->getMessage() . "\n";
        sleep(5);
    }
}