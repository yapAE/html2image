<?php

namespace App\Utils;

class FileTaskStorage {
    private $storagePath;
    
    public function __construct($storagePath = '/tmp/batch_task_meta') {
        $this->storagePath = $storagePath;
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }
    
    /**
     * 保存任务
     *
     * @param string $taskId 任务ID
     * @param array $taskData 任务数据
     * @return bool 是否保存成功
     */
    public function saveTask($taskId, $taskData) {
        try {
            $filePath = $this->getTaskFilePath($taskId);
            $taskData['taskId'] = $taskId;
            $taskData['createdAt'] = time();
            
            // 设置默认过期时间为24小时后
            if (!isset($taskData['expiresAt'])) {
                $taskData['expiresAt'] = time() + 86400; // 24小时
            }
            
            // 最长不超过72小时
            $taskData['expiresAt'] = min($taskData['expiresAt'], time() + 259200);
            
            $result = file_put_contents($filePath, json_encode($taskData, JSON_UNESCAPED_UNICODE));
            return $result !== false;
        } catch (\Exception $e) {
            error_log("保存任务失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取任务
     *
     * @param string $taskId 任务ID
     * @return array|null 任务数据，如果任务不存在或已过期则返回null
     */
    public function getTask($taskId) {
        try {
            $filePath = $this->getTaskFilePath($taskId);
            if (!file_exists($filePath)) {
                return null;
            }
            
            // 检查文件修改时间是否过期（性能优化）
            $fileModifiedTime = filemtime($filePath);
            if (time() - $fileModifiedTime > 259200) { // 72小时
                unlink($filePath);
                return null;
            }
            
            // 检查是否过期
            $data = file_get_contents($filePath);
            $taskData = json_decode($data, true);
            
            if (!$taskData || !isset($taskData['expiresAt'])) {
                return null;
            }
            
            // 如果已过期，删除文件并返回null
            if (time() > $taskData['expiresAt']) {
                unlink($filePath);
                return null;
            }
            
            return $taskData;
        } catch (\Exception $e) {
            error_log("获取任务失败: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 获取任务摘要信息（仅包含基本信息，不包含详细结果数据）
     *
     * @param string $taskId 任务ID
     * @return array|null 任务摘要信息
     */
    public function getTaskSummary($taskId) {
        try {
            $filePath = $this->getTaskFilePath($taskId);
            if (!file_exists($filePath)) {
                return null;
            }
            
            // 检查文件修改时间是否过期（性能优化）
            $fileModifiedTime = filemtime($filePath);
            if (time() - $fileModifiedTime > 259200) { // 72小时
                unlink($filePath);
                return null;
            }
            
            // 读取文件并解析JSON
            $data = file_get_contents($filePath);
            $taskData = json_decode($data, true);
            
            if (!$taskData || !isset($taskData['expiresAt'])) {
                return null;
            }
            
            // 如果已过期，删除文件并返回null
            if (time() > $taskData['expiresAt']) {
                unlink($filePath);
                return null;
            }
            
            // 只返回摘要信息，不包含详细的结果数据
            $summary = [
                'taskId' => $taskData['taskId'] ?? $taskId,
                'status' => $taskData['status'] ?? 'unknown',
                'totalItems' => $taskData['totalItems'] ?? 0,
                'completedItems' => $taskData['completedItems'] ?? 0,
                'failedItems' => $taskData['failedItems'] ?? 0,
                'createdAt' => $taskData['createdAt'] ?? null,
                'updatedAt' => $taskData['updatedAt'] ?? null,
                'expiresAt' => $taskData['expiresAt'] ?? null
            ];
            
            // 如果任务已完成或失败，也包含summary信息
            if (isset($taskData['summary'])) {
                $summary['summary'] = $taskData['summary'];
            }
            
            // 如果任务已完成或失败，也包含时间信息
            if (isset($taskData['completedAt'])) {
                $summary['completedAt'] = $taskData['completedAt'];
            }
            
            if (isset($taskData['failedAt'])) {
                $summary['failedAt'] = $taskData['failedAt'];
            }
            
            return $summary;
        } catch (\Exception $e) {
            error_log("获取任务摘要失败: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 更新任务
     *
     * @param string $taskId 任务ID
     * @param array $taskData 要更新的任务数据
     * @return bool 是否更新成功
     */
    public function updateTask($taskId, $taskData) {
        try {
            $existingData = $this->getTask($taskId);
            if (!$existingData) {
                return false;
            }
            
            // 合并数据
            $mergedData = array_merge($existingData, $taskData);
            
            // 如果任务接近完成，可以适当延长过期时间，但不超过72小时
            if (isset($taskData['completedItems']) && isset($mergedData['totalItems'])) {
                $completionRatio = $taskData['completedItems'] / $mergedData['totalItems'];
                if ($completionRatio > 0.8) {
                    $mergedData['expiresAt'] = min(
                        time() + 259200, // 72小时
                        max($mergedData['expiresAt'], time() + 86400) // 至少延长24小时
                    );
                }
            }
            
            $mergedData['updatedAt'] = time();
            
            $filePath = $this->getTaskFilePath($taskId);
            $result = file_put_contents($filePath, json_encode($mergedData, JSON_UNESCAPED_UNICODE));
            return $result !== false;
        } catch (\Exception $e) {
            error_log("更新任务失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 删除任务
     *
     * @param string $taskId 任务ID
     * @return bool 是否删除成功
     */
    public function deleteTask($taskId) {
        try {
            $filePath = $this->getTaskFilePath($taskId);
            if (file_exists($filePath)) {
                return unlink($filePath);
            }
            return true;
        } catch (\Exception $e) {
            error_log("删除任务失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取任务文件路径
     *
     * @param string $taskId 任务ID
     * @return string 文件路径
     */
    private function getTaskFilePath($taskId) {
        // 确保taskId是安全的文件名
        $safeTaskId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $taskId);
        return $this->storagePath . '/' . $safeTaskId . '.json';
    }
    
    /**
     * 清理过期任务
     *
     * @return int 清理的任务数量
     */
    public function cleanupExpiredTasks() {
        try {
            $cleanedCount = 0;
            $files = glob($this->storagePath . '/*.json');
            
            foreach ($files as $file) {
                // 检查文件是否过期
                $data = file_get_contents($file);
                $taskData = json_decode($data, true);
                
                if (!$taskData || !isset($taskData['expiresAt']) || time() > $taskData['expiresAt']) {
                    if (unlink($file)) {
                        $cleanedCount++;
                    }
                }
            }
            
            return $cleanedCount;
        } catch (\Exception $e) {
            error_log("清理过期任务失败: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 获取所有未过期任务的ID列表
     *
     * @return array 任务ID列表
     */
    public function getAllTaskIds() {
        try {
            $taskIds = [];
            $files = glob($this->storagePath . '/*.json');
            
            foreach ($files as $file) {
                $data = file_get_contents($file);
                $taskData = json_decode($data, true);
                
                if ($taskData && isset($taskData['taskId']) && isset($taskData['expiresAt']) && time() <= $taskData['expiresAt']) {
                    $taskIds[] = $taskData['taskId'];
                }
            }
            
            return $taskIds;
        } catch (\Exception $e) {
            error_log("获取任务列表失败: " . $e->getMessage());
            return [];
        }
    }
}