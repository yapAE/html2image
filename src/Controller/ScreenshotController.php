<?php

namespace App\Controller;

use App\Service\ScreenshotService;
use App\Utils\ApiResponse;
use App\Utils\FileQueue;

class ScreenshotController
{
    private ScreenshotService $screenshotService;
    private FileQueue $fileQueue;
    
    public function __construct()
    {
        $this->screenshotService = new ScreenshotService();
        // 检查环境变量或使用默认队列目录
        $queueDir = getenv('QUEUE_DIR') ?: '/app/queue';
        $this->fileQueue = new FileQueue($queueDir);
    }
    
    /**
     * 处理 HTTP 请求（传统方式 - 返回二进制数据）
     */
    public function handleRequest(): void
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type");
        header("Access-Control-Allow-Methods: POST, OPTIONS");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        error_log("=== Browsershot Request Started ===");
        
        $input = file_get_contents('php://input');
        error_log("Input data: " . $input);
        
        $data = json_decode($input, true);
        error_log("Parsed data: " . print_r($data, true));

        // 检查是否是批量处理请求
        $urls = $data['urls'] ?? null;
        $htmls = $data['htmls'] ?? null;
        $items = $data['items'] ?? null;
        
        if ($urls || $htmls || $items) {
            // 批量处理
            $this->handleBatchRequest($data, false); // 传统模式
            return;
        }

        // 单个处理
        $url = $data['url'] ?? null;
        $html = $data['html'] ?? null;
        $format = strtolower($data['format'] ?? 'png');

        error_log("URL: " . ($url ?? 'null'));
        error_log("HTML: " . (substr($html ?? '', 0, 100) . (strlen($html ?? '') > 100 ? '...' : '')));
        error_log("Format: " . $format);

        // 参数验证
        if (!$url && !$html) {
            http_response_code(400);
            echo json_encode(ApiResponse::error('MISSING_REQUIRED_FIELD', '必须提供 url 或 html'));
            return;
        }
        
        if ($format !== 'png' && $format !== 'pdf') {
            http_response_code(400);
            echo json_encode(ApiResponse::error('UNSUPPORTED_FORMAT', 'format 仅支持 png/pdf'));
            return;
        }

        try {
            $result = $this->screenshotService->processSingleScreenshot($data);
            
            if (isset($result['ossUrl'])) {
                // 上传到 OSS 的情况
                header('Content-Type: application/json');
                echo json_encode(ApiResponse::ossUpload($result['type'], $result['ossUrl']));
            } else {
                // 直接返回数据的情况
                if ($result['type'] === 'png') {
                    header('Content-Type: image/png');
                    header('Content-Length: ' . $result['size']);
                    echo $result['data'];
                } else {
                    header('Content-Type: application/pdf');
                    header('Content-Length: ' . $result['size']);
                    echo $result['data'];
                }
            }
        } catch (\Exception $e) {
            error_log("Exception occurred: " . $e->getMessage());
            error_log("Exception trace: " . $e->getTraceAsString());
            http_response_code(500);
            echo json_encode(ApiResponse::error('INTERNAL_ERROR', $e->getMessage()));
        }

        error_log("=== Browsershot Request Completed ===");
    }
    
    /**
     * 处理 API 请求（返回JSON格式）
     */
    public function handleApiRequest(): void
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type");
        header("Access-Control-Allow-Methods: POST, OPTIONS");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        error_log("=== Browsershot API Request Started ===");
        
        $input = file_get_contents('php://input');
        error_log("Input data: " . $input);
        
        $data = json_decode($input, true);
        error_log("Parsed data: " . print_r($data, true));

        // 检查是否是批量处理请求
        $urls = $data['urls'] ?? null;
        $htmls = $data['htmls'] ?? null;
        $items = $data['items'] ?? null;
        
        if ($urls || $htmls || $items) {
            // 批量处理
            $this->handleBatchRequest($data, true); // API模式
            return;
        }

        // 单个处理
        $url = $data['url'] ?? null;
        $html = $data['html'] ?? null;
        $format = strtolower($data['format'] ?? 'png');

        error_log("URL: " . ($url ?? 'null'));
        error_log("HTML: " . (substr($html ?? '', 0, 100) . (strlen($html ?? '') > 100 ? '...' : '')));
        error_log("Format: " . $format);

        // 参数验证
        if (!$url && !$html) {
            http_response_code(400);
            echo json_encode(ApiResponse::error('MISSING_REQUIRED_FIELD', '必须提供 url 或 html'));
            return;
        }
        
        if ($format !== 'png' && $format !== 'pdf') {
            http_response_code(400);
            echo json_encode(ApiResponse::error('UNSUPPORTED_FORMAT', 'format 仅支持 png/pdf'));
            return;
        }

        try {
            $result = $this->screenshotService->processSingleScreenshot($data);
            
            if (isset($result['ossUrl'])) {
                // 上传到 OSS 的情况
                header('Content-Type: application/json');
                echo json_encode(ApiResponse::ossUpload($result['type'], $result['ossUrl']));
            } else {
                // 返回base64编码的数据而不是二进制数据
                header('Content-Type: application/json');
                echo json_encode(ApiResponse::binaryData($result['type'], $result['data'], $result['size']));
            }
        } catch (\Exception $e) {
            error_log("Exception occurred: " . $e->getMessage());
            error_log("Exception trace: " . $e->getTraceAsString());
            http_response_code(500);
            echo json_encode(ApiResponse::error('INTERNAL_ERROR', $e->getMessage()));
        }

        error_log("=== Browsershot API Request Completed ===");
    }
    
    /**
     * 处理异步批处理请求
     */
    public function handleAsyncBatchRequest(): void
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type");
        header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        error_log("=== Browsershot Async Batch Request Started ===");
        
        // POST请求提交新任务
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = file_get_contents('php://input');
            error_log("Input data: " . $input);
            
            $data = json_decode($input, true);
            error_log("Parsed data: " . print_r($data, true));
            
            // 检查是否是批量处理请求
            $urls = $data['urls'] ?? null;
            $htmls = $data['htmls'] ?? null;
            $items = $data['items'] ?? null;
            
            if (!$urls && !$htmls && !$items) {
                http_response_code(400);
                echo json_encode(ApiResponse::error('MISSING_REQUIRED_FIELD', '必须提供 urls、htmls 或 items'));
                return;
            }
            
            // 创建异步任务（使用新的队列系统）
            $this->createAsyncBatchTaskWithQueue($data);
            return;
        }
        
        // GET请求查询任务状态
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            // 检查是否请求任务摘要（快速查询）
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            $queryParams = [];
            if (strpos($requestUri, '?') !== false) {
                parse_str(parse_url($requestUri, PHP_URL_QUERY), $queryParams);
            }
            
            $summaryOnly = isset($queryParams['summary']) && $queryParams['summary'] === 'true';
            
            // 获取任务ID（用于GET请求查询任务状态）
            $pathInfo = parse_url($requestUri, PHP_URL_PATH);
            if (preg_match('/\/([^\/]+)$/', $pathInfo, $matches)) {
                $taskId = $matches[1];
                if ($summaryOnly) {
                    $this->handleGetTaskSummary($taskId);
                } else {
                    $this->handleGetTaskStatus($taskId);
                }
                return;
            } else {
                http_response_code(400);
                echo json_encode(ApiResponse::error('MISSING_TASK_ID', '必须提供任务ID'));
                return;
            }
        }
        
        // 不支持的请求方法
        http_response_code(405);
        echo json_encode(ApiResponse::error('METHOD_NOT_ALLOWED', '不支持的请求方法'));
    }
    
    /**
     * 创建异步批处理任务（使用队列系统）
     */
    private function createAsyncBatchTaskWithQueue(array $data): void
    {
        try {
            // 添加任务到队列
            $taskData = [
                'data' => $data,
                'created_at' => date('c')
            ];
            
            $taskId = $this->fileQueue->push($taskData);
            
            // 返回任务ID
            header('Content-Type: application/json');
            echo json_encode(ApiResponse::success([
                'taskId' => $taskId,
                'status' => 'pending',
                'message' => '批处理任务已提交到队列'
            ], '任务已提交，等待处理'));
        } catch (\Exception $e) {
            error_log("创建异步任务失败: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(ApiResponse::error('INTERNAL_ERROR', $e->getMessage()));
        }
    }
    
    /**
     * 获取任务状态（直接从队列系统查询）
     */
    private function handleGetTaskStatus(string $taskId): void
    {
        try {
            // 检查队列目录是否存在
            $baseDir = $this->fileQueue->getBaseDir();
            if (!is_dir($baseDir)) {
                http_response_code(500);
                echo json_encode(ApiResponse::error('QUEUE_DIR_NOT_FOUND', '队列目录不存在: ' . $baseDir));
                return;
            }
            
            // 检查各个子目录是否存在
            $directories = ['done', 'failed', 'processing', 'pending'];
            foreach ($directories as $dir) {
                $dirPath = "{$baseDir}/{$dir}";
                if (!is_dir($dirPath)) {
                    http_response_code(500);
                    echo json_encode(ApiResponse::error('QUEUE_SUBDIR_NOT_FOUND', '队列子目录不存在: ' . $dirPath));
                    return;
                }
            }
            
            // 按优先级顺序检查任务在各个状态目录中的存在情况
            $taskData = null;
            
            foreach ($directories as $dir) {
                $filePath = "{$baseDir}/{$dir}/{$taskId}.json";
                if (file_exists($filePath)) {
                    $content = file_get_contents($filePath);
                    $taskData = json_decode($content, true);
                    if ($taskData) {
                        // 添加状态字段
                        $taskData['status'] = $dir;
                        break;
                    }
                }
            }
            
            if (!$taskData) {
                http_response_code(404);
                echo json_encode(ApiResponse::error('TASK_NOT_FOUND', '任务不存在或已过期'));
                return;
            }
            
            header('Content-Type: application/json');
            echo json_encode(ApiResponse::success($taskData, '任务状态获取成功'));
        } catch (\Exception $e) {
            error_log("获取任务状态失败: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(ApiResponse::error('INTERNAL_ERROR', $e->getMessage()));
        }
    }
    
    /**
     * 获取任务摘要（快速查询，直接从队列系统查询）
     */
    private function handleGetTaskSummary(string $taskId): void
    {
        try {
            // 检查队列目录是否存在
            $baseDir = $this->fileQueue->getBaseDir();
            if (!is_dir($baseDir)) {
                http_response_code(500);
                echo json_encode(ApiResponse::error('QUEUE_DIR_NOT_FOUND', '队列目录不存在: ' . $baseDir));
                return;
            }
            
            // 检查各个子目录是否存在
            $directories = ['done', 'failed', 'processing', 'pending'];
            foreach ($directories as $dir) {
                $dirPath = "{$baseDir}/{$dir}";
                if (!is_dir($dirPath)) {
                    http_response_code(500);
                    echo json_encode(ApiResponse::error('QUEUE_SUBDIR_NOT_FOUND', '队列子目录不存在: ' . $dirPath));
                    return;
                }
            }
            
            // 按优先级顺序检查任务在各个状态目录中的存在情况
            $taskData = null;
            
            foreach ($directories as $dir) {
                $filePath = "{$baseDir}/{$dir}/{$taskId}.json";
                if (file_exists($filePath)) {
                    $content = file_get_contents($filePath);
                    $taskData = json_decode($content, true);
                    if ($taskData) {
                        // 添加状态字段
                        $taskData['status'] = $dir;
                        break;
                    }
                }
            }
            
            if (!$taskData) {
                http_response_code(404);
                echo json_encode(ApiResponse::error('TASK_NOT_FOUND', '任务不存在或已过期'));
                return;
            }
            
            // 只返回摘要信息，不包含详细的结果数据
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
            
            header('Content-Type: application/json');
            echo json_encode(ApiResponse::success($summary, '任务摘要获取成功'));
        } catch (\Exception $e) {
            error_log("获取任务摘要失败: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(ApiResponse::error('INTERNAL_ERROR', $e->getMessage()));
        }
    }
    
    /**
     * 处理批量请求
     *
     * @param array $data 请求数据
     * @param bool $apiMode 是否为API模式
     */
    private function handleBatchRequest(array $data, bool $apiMode = false): void
    {
        error_log("Handling batch request, API mode: " . ($apiMode ? 'true' : 'false'));
        
        try {
            $result = $this->screenshotService->processBatchScreenshot($data);
            
            // 返回结果
            header('Content-Type: application/json');
            if ($apiMode) {
                echo json_encode(ApiResponse::batchResult($result['results'], $result['errors']));
            } else {
                echo json_encode($result);
            }
            
            error_log("Batch request completed. Success: " . $result['summary']['success'] . ", Failed: " . $result['summary']['failed']);
        } catch (\Exception $e) {
            error_log("Batch processing error: " . $e->getMessage());
            http_response_code(500);
            if ($apiMode) {
                echo json_encode(ApiResponse::error('INTERNAL_ERROR', '批量处理失败: ' . $e->getMessage()));
            } else {
                echo json_encode(['error' => 'Batch processing failed: ' . $e->getMessage()]);
            }
        }
    }
}