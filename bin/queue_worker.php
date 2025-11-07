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

echo "启动队列处理守护进程...\n";

// 初始化队列和截图服务
$queue = new FileQueue('/app/queue');
$screenshotService = new ScreenshotService();

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
                        } catch (\Exception $e) {
                            $errors[] = [
                                'index' => $index,
                                'type' => 'url',
                                'value' => $url,
                                'error' => $e->getMessage()
                            ];
                            $failedItems++;
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
                        } catch (\Exception $e) {
                            $errors[] = [
                                'index' => $index,
                                'type' => 'html',
                                'value' => substr($html, 0, 50) . '...',
                                'error' => $e->getMessage()
                            ];
                            $failedItems++;
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
                        } catch (\Exception $e) {
                            $errors[] = [
                                'index' => $index,
                                'type' => 'item',
                                'value' => json_encode($item),
                                'error' => $e->getMessage()
                            ];
                            $failedItems++;
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
                
                // 标记任务完成
                $queue->done($task['id'], $task);
                echo "任务 {$task['id']} 处理完成\n";
                
            } catch (\Exception $e) {
                echo "任务 {$task['id']} 处理失败: " . $e->getMessage() . "\n";
                $queue->fail($task['id'], $e->getMessage());
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