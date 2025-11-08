<?php
/**
 * 文件系统队列管理模块
 * 无需数据库，支持多进程安全。
 * 
 * @author 
 * @version 1.0
 */

namespace App\Utils;

class FileQueue
{
    private string $baseDir;
    private int $processingTimeout; // 处理超时时间（秒）

    public function __construct(string $baseDir = '/app/queue', int $timeout = 3600)
    {
        $this->baseDir = rtrim($baseDir, '/');
        $this->processingTimeout = $timeout;
        $this->initDirectories();
    }

    /**
     * 初始化目录结构
     */
    private function initDirectories(): void
    {
        foreach (['pending', 'processing', 'done', 'failed'] as $dir) {
            $path = "{$this->baseDir}/{$dir}";
            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }
        }
    }

    /**
     * 添加任务到队列
     * 
     * @param array $data 任务内容（任意键值对）
     * @return string 任务ID
     */
    public function push(array $data): string
    {
        $id = uniqid('task_', true);
        $data['id'] = $id;
        $data['status'] = 'pending';
        $data['created_at'] = date('c');

        $file = "{$this->baseDir}/pending/{$id}.json";
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        error_log("Queue: Task {$id} pushed");
        return $id;
    }

    /**
     * 获取一个待处理任务（原子转移到 processing）
     * 
     * @return array|null
     */
    public function pop(): ?array
    {
        // 首先检查是否有超时的任务需要重新处理
        $this->checkTimeoutTasks();
        
        $files = glob("{$this->baseDir}/pending/*.json");
        if (empty($files)) {
            return null;
        }

        $file = $files[0];
        $taskId = basename($file, '.json');
        $processingFile = "{$this->baseDir}/processing/{$taskId}.json";

        if (!@rename($file, $processingFile)) {
            // 其他进程可能已抢到任务
            return null;
        }

        $data = json_decode(file_get_contents($processingFile), true);
        $data['status'] = 'processing';
        $data['started_at'] = date('c');

        file_put_contents($processingFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        error_log("Queue: Task {$taskId} started processing");
        return $data;
    }

    /**
     * 标记任务成功
     */
    public function done(string $id, ?array $data = null): void
    {
        $src = "{$this->baseDir}/processing/{$id}.json";
        $dst = "{$this->baseDir}/done/{$id}.json";

        if (!file_exists($src)) {
            error_log("Queue: Task {$id} not found in processing");
            return;
        }

        $info = $data ?? json_decode(file_get_contents($src), true);
        $info['status'] = 'done';
        $info['finished_at'] = date('c');

        rename($src, $dst);
        // 确保写入数据，即使$data为null也要写入基本信息
        $content = json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($content === false) {
            $content = json_encode(['id' => $id, 'status' => 'done', 'finished_at' => date('c')], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        file_put_contents($dst, $content);
        error_log("Queue: Task {$id} completed");
    }

    /**
     * 标记任务失败
     */
    public function fail(string $id, string $reason = 'unknown error'): void
    {
        $src = "{$this->baseDir}/processing/{$id}.json";
        $dst = "{$this->baseDir}/failed/{$id}.json";

        $info = file_exists($src)
            ? json_decode(file_get_contents($src), true)
            : ['id' => $id, 'status' => 'failed'];

        $info['status'] = 'failed';
        $info['error'] = $reason;
        $info['failed_at'] = date('c');

        @rename($src, $dst);
        file_put_contents($dst, json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        error_log("Queue: Task {$id} failed ({$reason})");
    }

    /**
     * 获取队列状态统计
     */
    public function stats(): array
    {
        $count = fn($path) => count(glob("{$path}/*.json"));
        return [
            'pending' => $count("{$this->baseDir}/pending"),
            'processing' => $count("{$this->baseDir}/processing"),
            'done' => $count("{$this->baseDir}/done"),
            'failed' => $count("{$this->baseDir}/failed"),
        ];
    }
    
    /**
     * 重试失败的任务
     */
    public function retry(string $id): bool
    {
        $src = "{$this->baseDir}/failed/{$id}.json";
        $dst = "{$this->baseDir}/pending/{$id}.json";

        if (!file_exists($src)) {
            error_log("Queue: Failed task {$id} not found");
            return false;
        }

        $info = json_decode(file_get_contents($src), true);
        $info['status'] = 'pending';
        $info['retried_at'] = date('c');
        // 移除之前的错误信息
        unset($info['error']);
        unset($info['failed_at']);

        if (@rename($src, $dst)) {
            file_put_contents($dst, json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            error_log("Queue: Failed task {$id} moved back to pending");
            return true;
        }
        
        error_log("Queue: Failed to move task {$id} back to pending");
        return false;
    }
    
    /**
     * 重试所有失败的任务
     */
    public function retryAllFailed(): int
    {
        $count = 0;
        $files = glob("{$this->baseDir}/failed/*.json");
        
        foreach ($files as $file) {
            $taskId = basename($file, '.json');
            if ($this->retry($taskId)) {
                $count++;
            }
        }
        
        error_log("Queue: Retried {$count} failed tasks");
        return $count;
    }
    
    /**
     * 清理已完成的任务（保留一段时间后删除）
     * 
     * @param int $days 保留天数
     */
    public function cleanup(int $days = 3): void
    {
        $this->cleanupDirectory("{$this->baseDir}/done", $days);
        $this->cleanupDirectory("{$this->baseDir}/failed", $days);
    }
    
    /**
     * 清理指定目录中的旧文件
     * 
     * @param string $path 目录路径
     * @param int $days 保留天数
     */
    private function cleanupDirectory(string $path, int $days): void
    {
        $files = glob("{$path}/*.json");
        $cutoffTime = time() - ($days * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
                error_log("Queue: Cleaned up old task file {$file}");
            }
        }
    }
    
    /**
     * 检查超时的任务并将其移回pending队列
     */
    private function checkTimeoutTasks(): void
    {
        $files = glob("{$this->baseDir}/processing/*.json");
        $timeoutTime = time() - $this->processingTimeout;
        
        foreach ($files as $file) {
            // 检查文件修改时间是否超过超时时间
            if (filemtime($file) < $timeoutTime) {
                $taskId = basename($file, '.json');
                $info = json_decode(file_get_contents($file), true);
                
                // 标记任务为超时失败
                $info['status'] = 'failed';
                $info['error'] = 'processing timeout';
                $info['failed_at'] = date('c');
                
                $dst = "{$this->baseDir}/failed/{$taskId}.json";
                @rename($file, $dst);
                file_put_contents($dst, json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                
                error_log("Queue: Task {$taskId} timeout and moved to failed");
            }
        }
    }
    
    /**
     * 获取超时的任务数量
     */
    public function getTimeoutTaskCount(): int
    {
        $count = 0;
        $files = glob("{$this->baseDir}/processing/*.json");
        $timeoutTime = time() - $this->processingTimeout;
        
        foreach ($files as $file) {
            if (filemtime($file) < $timeoutTime) {
                $count++;
            }
        }
        
        return $count;
    }
}