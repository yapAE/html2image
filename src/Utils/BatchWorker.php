<?php

namespace App\Utils;

use App\Service\ScreenshotService;

class BatchWorker {
    private ScreenshotService $screenshotService;
    private FileTaskStorage $taskStorage;
    
    public function __construct() {
        $this->screenshotService = new ScreenshotService();
        $this->taskStorage = new FileTaskStorage();
    }
    
    /**
     * 处理批处理任务
     *
     * @param string $taskId 任务ID
     * @return bool 是否处理成功
     */
    public function processTask($taskId) {
        try {
            // 获取任务数据
            $taskData = $this->taskStorage->getTask($taskId);
            if (!$taskData) {
                error_log("任务不存在: $taskId");
                return false;
            }
            
            // 检查任务状态
            if ($taskData['status'] !== 'pending') {
                error_log("任务状态不正确: $taskId, 当前状态: " . $taskData['status']);
                return false;
            }
            
            // 更新任务状态为处理中
            $taskData['status'] = 'processing';
            $taskData['startedAt'] = time();
            if (!$this->taskStorage->updateTask($taskId, $taskData)) {
                error_log("无法更新任务状态: $taskId");
                return false;
            }
            
            // 处理请求数据
            $requestData = $taskData['requestData'] ?? [];
            if (empty($requestData)) {
                error_log("任务数据为空: $taskId");
                $this->markTaskAsFailed($taskId, '任务数据为空');
                return false;
            }
            
            // 处理每个子任务
            $results = [];
            $errors = [];
            $completedItems = 0;
            $failedItems = 0;
            $totalProcessed = 0; // 用于控制进度更新频率
            
            // 处理 URLs 数组
            if (isset($requestData['urls']) && is_array($requestData['urls'])) {
                foreach ($requestData['urls'] as $index => $url) {
                    try {
                        $itemData = ['url' => $url] + $requestData;
                        $result = $this->screenshotService->processSingleScreenshot($itemData);
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
                    
                    $totalProcessed++;
                    // 每处理5个任务更新一次进度，避免过于频繁的文件I/O操作
                    if ($totalProcessed % 5 === 0) {
                        $this->updateTaskProgress($taskId, $completedItems, $failedItems, $results, $errors);
                    }
                }
            }
            
            // 处理 HTMLs 数组
            if (isset($requestData['htmls']) && is_array($requestData['htmls'])) {
                foreach ($requestData['htmls'] as $index => $html) {
                    try {
                        $itemData = ['html' => $html] + $requestData;
                        $result = $this->screenshotService->processSingleScreenshot($itemData);
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
                    
                    $totalProcessed++;
                    // 每处理5个任务更新一次进度
                    if ($totalProcessed % 5 === 0) {
                        $this->updateTaskProgress($taskId, $completedItems, $failedItems, $results, $errors);
                    }
                }
            }
            
            // 处理 items 数组
            if (isset($requestData['items']) && is_array($requestData['items'])) {
                foreach ($requestData['items'] as $index => $item) {
                    try {
                        $itemData = $item + $requestData;
                        $result = $this->screenshotService->processSingleScreenshot($itemData);
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
                    
                    $totalProcessed++;
                    // 每处理5个任务更新一次进度
                    if ($totalProcessed % 5 === 0) {
                        $this->updateTaskProgress($taskId, $completedItems, $failedItems, $results, $errors);
                    }
                }
            }
            
            // 最后一次更新进度
            $this->updateTaskProgress($taskId, $completedItems, $failedItems, $results, $errors);
            
            // 标记任务完成
            $this->markTaskAsCompleted($taskId, $results, $errors, $completedItems, $failedItems);
            
            return true;
        } catch (\Exception $e) {
            error_log("处理任务失败: $taskId, 错误: " . $e->getMessage());
            $this->markTaskAsFailed($taskId, $e->getMessage());
            return false;
        }
    }
    
    /**
     * 更新任务进度
     */
    private function updateTaskProgress($taskId, $completedItems, $failedItems, $results, $errors) {
        try {
            $progressData = [
                'completedItems' => $completedItems,
                'failedItems' => $failedItems,
                'results' => array_slice($results, -5), // 只保留最近5个结果，减少数据量
                'errors' => array_slice($errors, -5),   // 只保留最近5个错误
                'updatedAt' => time()
            ];
            
            $this->taskStorage->updateTask($taskId, $progressData);
        } catch (\Exception $e) {
            error_log("更新任务进度失败: $taskId, 错误: " . $e->getMessage());
        }
    }
    
    /**
     * 标记任务完成
     */
    private function markTaskAsCompleted($taskId, $results, $errors, $completedItems, $failedItems) {
        try {
            $taskData = [
                'status' => 'completed',
                'completedAt' => time(),
                'results' => $results,
                'errors' => $errors,
                'completedItems' => $completedItems,
                'failedItems' => $failedItems,
                'summary' => [
                    'total' => $completedItems + $failedItems,
                    'success' => $completedItems,
                    'failed' => $failedItems
                ]
            ];
            
            $this->taskStorage->updateTask($taskId, $taskData);
        } catch (\Exception $e) {
            error_log("标记任务完成失败: $taskId, 错误: " . $e->getMessage());
        }
    }
    
    /**
     * 标记任务失败
     */
    private function markTaskAsFailed($taskId, $errorMessage) {
        try {
            $taskData = [
                'status' => 'failed',
                'failedAt' => time(),
                'errorMessage' => $errorMessage
            ];
            
            $this->taskStorage->updateTask($taskId, $taskData);
        } catch (\Exception $e) {
            error_log("标记任务失败失败: $taskId, 错误: " . $e->getMessage());
        }
    }
    
    /**
     * 启动Worker处理任务
     * 这个方法可以被CLI脚本调用
     */
    public static function runWorker($taskId = null) {
        $worker = new self();
        
        if ($taskId) {
            // 处理指定任务
            $worker->processTask($taskId);
        } else {
            // 处理所有待处理任务
            $storage = new FileTaskStorage();
            $taskIds = $storage->getAllTaskIds();
            
            foreach ($taskIds as $id) {
                $taskData = $storage->getTask($id);
                if ($taskData && $taskData['status'] === 'pending') {
                    $worker->processTask($id);
                }
            }
        }
    }
}