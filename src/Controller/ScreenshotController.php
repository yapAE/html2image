<?php

namespace App\Controller;

use App\Service\ScreenshotService;
use App\Utils\ApiResponse;
use App\Utils\FileTaskStorage;

class ScreenshotController
{
    private ScreenshotService $screenshotService;
    private FileTaskStorage $taskStorage;
    
    public function __construct()
    {
        $this->screenshotService = new ScreenshotService();
        $this->taskStorage = new FileTaskStorage();
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
            
            // 创建异步任务
            $this->createAsyncBatchTask($data);
            return;
        }
        
        // GET请求查询任务状态
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            // 获取任务ID（用于GET请求查询任务状态）
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            $pathInfo = parse_url($requestUri, PHP_URL_PATH);
            if (preg_match('/\/([^\/]+)$/', $pathInfo, $matches)) {
                $taskId = $matches[1];
                $this->handleGetTaskStatus($taskId);
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
     * 创建异步批处理任务
     */
    private function createAsyncBatchTask(array $data): void
    {
        try {
            // 生成任务ID
            $taskId = 'batch_' . uniqid();
            
            // 计算总任务数
            $totalItems = 0;
            if (isset($data['urls']) && is_array($data['urls'])) {
                $totalItems += count($data['urls']);
            }
            if (isset($data['htmls']) && is_array($data['htmls'])) {
                $totalItems += count($data['htmls']);
            }
            if (isset($data['items']) && is_array($data['items'])) {
                $totalItems += count($data['items']);
            }
            
            // 创建任务元数据
            $taskMetadata = [
                'status' => 'pending',
                'totalItems' => $totalItems,
                'completedItems' => 0,
                'failedItems' => 0,
                'results' => [],
                'errors' => [],
                'requestData' => $data
            ];
            
            // 保存任务
            if ($this->taskStorage->saveTask($taskId, $taskMetadata)) {
                // 返回任务ID
                header('Content-Type: application/json');
                echo json_encode(ApiResponse::success([
                    'taskId' => $taskId,
                    'status' => 'pending',
                    'totalItems' => $totalItems,
                    'message' => '批处理任务已提交'
                ], '任务已提交，等待处理'));
            } else {
                http_response_code(500);
                echo json_encode(ApiResponse::error('INTERNAL_ERROR', '无法创建任务'));
            }
        } catch (\Exception $e) {
            error_log("创建异步任务失败: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(ApiResponse::error('INTERNAL_ERROR', $e->getMessage()));
        }
    }
    
    /**
     * 获取任务状态
     */
    private function handleGetTaskStatus(string $taskId): void
    {
        try {
            $taskData = $this->taskStorage->getTask($taskId);
            
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