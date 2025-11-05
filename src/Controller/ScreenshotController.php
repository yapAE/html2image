<?php

namespace App\Controller;

use App\Service\ScreenshotService;

class ScreenshotController
{
    private ScreenshotService $screenshotService;
    
    public function __construct()
    {
        $this->screenshotService = new ScreenshotService();
    }
    
    /**
     * 处理 HTTP 请求
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
            $this->handleBatchRequest($data);
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
            echo json_encode(['error' => '必须提供 url 或 html']);
            return;
        }
        
        if ($format !== 'png' && $format !== 'pdf') {
            http_response_code(400);
            echo json_encode(['error' => 'format 仅支持 png/pdf']);
            return;
        }

        try {
            $result = $this->screenshotService->processSingleScreenshot($data);
            
            if (isset($result['ossUrl'])) {
                // 上传到 OSS 的情况
                echo json_encode($result);
            } else {
                // 直接返回数据的情况
                if ($result['type'] === 'png') {
                    header('Content-Type: image/png');
                    header('Content-Length: ' . $result['size']);
                    echo base64_decode($result['data']);
                } else {
                    header('Content-Type: application/pdf');
                    header('Content-Length: ' . $result['size']);
                    echo base64_decode($result['data']);
                }
            }
        } catch (\Exception $e) {
            error_log("Exception occurred: " . $e->getMessage());
            error_log("Exception trace: " . $e->getTraceAsString());
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }

        error_log("=== Browsershot Request Completed ===");
    }
    
    /**
     * 处理批量请求
     *
     * @param array $data 请求数据
     */
    private function handleBatchRequest(array $data): void
    {
        error_log("Handling batch request");
        
        try {
            $result = $this->screenshotService->processBatchScreenshot($data);
            
            // 返回结果
            header('Content-Type: application/json');
            echo json_encode($result);
            
            error_log("Batch request completed. Success: " . $result['summary']['success'] . ", Failed: " . $result['summary']['failed']);
        } catch (\Exception $e) {
            error_log("Batch processing error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Batch processing failed: ' . $e->getMessage()]);
        }
    }
}