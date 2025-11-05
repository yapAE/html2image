<?php
require 'vendor/autoload.php';

use Spatie\Browsershot\Browsershot;
use OSS\OssClient;
use OSS\Core\OssException;

function handler()
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
        handleBatchRequest($data);
        return;
    }

    // 单个处理
    $url = $data['url'] ?? null;
    $html = $data['html'] ?? null;
    $format = strtolower($data['format'] ?? 'png');
    // OSS 配置参数
    $uploadToOSS = $data['uploadToOSS'] ?? false;
    $ossObjectName = $data['ossObjectName'] ?? null;
    
    // Browsershot 扩展参数
    $windowSize = $data['windowSize'] ?? null; // ['width' => 1280, 'height' => 800]
    $device = $data['device'] ?? null; // 'iPhone X'
    $fullPage = $data['fullPage'] ?? false;
    $delay = $data['delay'] ?? null; // 毫秒
    $waitUntilNetworkIdle = $data['waitUntilNetworkIdle'] ?? false;
    $userAgent = $data['userAgent'] ?? null;
    $mobile = $data['mobile'] ?? false;
    $touch = $data['touch'] ?? false;
    $hideBackground = $data['hideBackground'] ?? false;
    $disableImages = $data['disableImages'] ?? false;
    $pdfFormat = $data['pdfFormat'] ?? null; // 'A4', 'Letter' 等
    $landscape = $data['landscape'] ?? false;

    error_log("URL: " . ($url ?? 'null'));
    error_log("HTML: " . (substr($html ?? '', 0, 100) . (strlen($html ?? '') > 100 ? '...' : '')));
    error_log("Format: " . $format);
    error_log("Upload to OSS: " . ($uploadToOSS ? 'true' : 'false'));
    error_log("OSS Object Name: " . ($ossObjectName ?? 'null'));

    // 参数验证
    if (!$url && !$html) {
        http_response_code(400);
        echo json_encode(['error' => '必须提供 url 或 html']);
        return;
    }
    
    if ($windowSize && (!is_array($windowSize) || !isset($windowSize['width']) || !isset($windowSize['height']))) {
        http_response_code(400);
        echo json_encode(['error' => 'windowSize 必须是包含 width 和 height 的数组']);
        return;
    }
    
    if ($delay && (!is_numeric($delay) || $delay < 0)) {
        http_response_code(400);
        echo json_encode(['error' => 'delay 必须是非负整数']);
        return;
    }
    
    if ($format !== 'png' && $format !== 'pdf') {
        http_response_code(400);
        echo json_encode(['error' => 'format 仅支持 png/pdf']);
        return;
    }

    try {
        $shot = $url
            ? Browsershot::url($url)
            : Browsershot::html($html);

        error_log("Browsershot instance created");

        // === 环境变量控制沙盒模式 ===
        $sandboxEnabled = getenv('ENABLE_SANDBOX') === 'true';
        $args = $sandboxEnabled
            ? [] // 本地模式，保留沙盒
            : ['--no-sandbox', '--disable-setuid-sandbox'];

        error_log("Sandbox mode: " . ($sandboxEnabled ? "ENABLED" : "DISABLED"));

        $shot->setOption('args', $args)
            ->setOption('executablePath', getenv('PUPPETEER_EXECUTABLE_PATH') ?: '/usr/bin/chromium')
            ->setOption('headless', 'new');

        // 应用扩展参数
        if ($windowSize && isset($windowSize['width']) && isset($windowSize['height'])) {
            if (!is_numeric($windowSize['width']) || !is_numeric($windowSize['height']) || 
                $windowSize['width'] <= 0 || $windowSize['height'] <= 0) {
                throw new Exception('windowSize 的 width 和 height 必须是正整数');
            }
            $shot->windowSize((int)$windowSize['width'], (int)$windowSize['height']);
        }
        
        if ($device) {
            if (!is_string($device)) {
                throw new Exception('device 必须是字符串');
            }
            $shot->device($device);
        }
        
        if ($fullPage) {
            if (!is_bool($fullPage)) {
                throw new Exception('fullPage 必须是布尔值');
            }
            $shot->fullPage();
        }
        
        if ($delay) {
            if (!is_numeric($delay) || $delay < 0) {
                throw new Exception('delay 必须是非负整数');
            }
            $shot->setDelay((int)$delay);
        }
        
        if ($waitUntilNetworkIdle) {
            if (!is_bool($waitUntilNetworkIdle)) {
                throw new Exception('waitUntilNetworkIdle 必须是布尔值');
            }
            $shot->waitUntilNetworkIdle();
        }
        
        if ($userAgent) {
            if (!is_string($userAgent)) {
                throw new Exception('userAgent 必须是字符串');
            }
            $shot->userAgent($userAgent);
        }
        
        if ($mobile) {
            if (!is_bool($mobile)) {
                throw new Exception('mobile 必须是布尔值');
            }
            $shot->mobile();
        }
        
        if ($touch) {
            if (!is_bool($touch)) {
                throw new Exception('touch 必须是布尔值');
            }
            $shot->touch();
        }
        
        if ($hideBackground) {
            if (!is_bool($hideBackground)) {
                throw new Exception('hideBackground 必须是布尔值');
            }
            $shot->hideBackground();
        }
        
        if ($disableImages) {
            if (!is_bool($disableImages)) {
                throw new Exception('disableImages 必须是布尔值');
            }
            $shot->disableImages();
        }
        
        if ($format === 'pdf') {
            if ($pdfFormat) {
                if (!is_string($pdfFormat)) {
                    throw new Exception('pdfFormat 必须是字符串');
                }
                $shot->format($pdfFormat);
            }
            
            if ($landscape) {
                if (!is_bool($landscape)) {
                    throw new Exception('landscape 必须是布尔值');
                }
                $shot->landscape();
            }
        }

        error_log("Browsershot options set");

        switch ($format) {
            case 'png':
                error_log("Generating PNG screenshot");
                $image = $shot->screenshot();
                error_log("PNG generated, size: " . strlen($image) . " bytes");
                if (strlen($image) < 100) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Generated PNG too small, possibly corrupted']);
                    return;
                }
                
                // 如果需要上传到 OSS
                if ($uploadToOSS) {
                    $result = uploadToOSS($image, 'image/png', $ossObjectName);
                    if ($result['success']) {
                        echo json_encode([
                            'message' => 'PNG generated and uploaded to OSS',
                            'ossUrl' => $result['url']
                        ]);
                    } else {
                        http_response_code(500);
                        echo json_encode(['error' => 'Failed to upload to OSS: ' . $result['error']]);
                    }
                    return;
                }
                
                header('Content-Type: image/png');
                header('Content-Length: ' . strlen($image));
                echo $image;
                error_log("PNG response sent");
                break;

            case 'pdf':
                error_log("Generating PDF");
                $pdf = $shot->pdf();
                error_log("PDF generated, size: " . strlen($pdf) . " bytes");
                if (strlen($pdf) < 100) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Generated PDF too small, possibly corrupted']);
                    return;
                }
                
                // 如果需要上传到 OSS
                if ($uploadToOSS) {
                    $result = uploadToOSS($pdf, 'application/pdf', $ossObjectName);
                    if ($result['success']) {
                        echo json_encode([
                            'message' => 'PDF generated and uploaded to OSS',
                            'ossUrl' => $result['url']
                        ]);
                    } else {
                        http_response_code(500);
                        echo json_encode(['error' => 'Failed to upload to OSS: ' . $result['error']]);
                    }
                    return;
                }
                
                header('Content-Type: application/pdf');
                header('Content-Length: ' . strlen($pdf));
                echo $pdf;
                error_log("PDF response sent");
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'format 仅支持 png/pdf']);
                break;
        }
    } catch (Exception $e) {
        error_log("Exception occurred: " . $e->getMessage());
        error_log("Exception trace: " . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }

    error_log("=== Browsershot Request Completed ===");
}

function handleBatchRequest($data)
{
    error_log("Handling batch request");
    
    $results = [];
    $errors = [];
    
    // 处理 URLs 数组
    if (isset($data['urls']) && is_array($data['urls'])) {
        foreach ($data['urls'] as $index => $url) {
            try {
                $itemData = ['url' => $url] + $data;
                $result = processSingleItem($itemData, "url_$index");
                $results[] = $result;
            } catch (Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'type' => 'url',
                    'value' => $url,
                    'error' => $e->getMessage()
                ];
                error_log("Batch URL processing error at index $index: " . $e->getMessage());
            }
        }
    }
    
    // 处理 HTMLs 数组
    if (isset($data['htmls']) && is_array($data['htmls'])) {
        foreach ($data['htmls'] as $index => $html) {
            try {
                $itemData = ['html' => $html] + $data;
                $result = processSingleItem($itemData, "html_$index");
                $results[] = $result;
            } catch (Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'type' => 'html',
                    'value' => substr($html, 0, 50) . '...',
                    'error' => $e->getMessage()
                ];
                error_log("Batch HTML processing error at index $index: " . $e->getMessage());
            }
        }
    }
    
    // 处理 items 数组
    if (isset($data['items']) && is_array($data['items'])) {
        foreach ($data['items'] as $index => $item) {
            try {
                $itemData = $item + $data;
                $result = processSingleItem($itemData, "item_$index");
                $results[] = $result;
            } catch (Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'type' => 'item',
                    'value' => json_encode($item),
                    'error' => $e->getMessage()
                ];
                error_log("Batch item processing error at index $index: " . $e->getMessage());
            }
        }
    }
    
    // 返回结果
    header('Content-Type: application/json');
    echo json_encode([
        'results' => $results,
        'errors' => $errors,
        'summary' => [
            'total' => count($results) + count($errors),
            'success' => count($results),
            'failed' => count($errors)
        ]
    ]);
    
    error_log("Batch request completed. Success: " . count($results) . ", Failed: " . count($errors));
}

function processSingleItem($data, $identifier)
{
    error_log("Processing single item: $identifier");
    
    $url = $data['url'] ?? null;
    $html = $data['html'] ?? null;
    $format = strtolower($data['format'] ?? 'png');
    $uploadToOSS = $data['uploadToOSS'] ?? false;
    $ossObjectName = $data['ossObjectName'] ?? null;
    
    // Browsershot 扩展参数
    $windowSize = $data['windowSize'] ?? null;
    $device = $data['device'] ?? null;
    $fullPage = $data['fullPage'] ?? false;
    $delay = $data['delay'] ?? null;
    $waitUntilNetworkIdle = $data['waitUntilNetworkIdle'] ?? false;
    $userAgent = $data['userAgent'] ?? null;
    $mobile = $data['mobile'] ?? false;
    $touch = $data['touch'] ?? false;
    $hideBackground = $data['hideBackground'] ?? false;
    $disableImages = $data['disableImages'] ?? false;
    $pdfFormat = $data['pdfFormat'] ?? null;
    $landscape = $data['landscape'] ?? false;

    if (!$url && !$html) {
        throw new Exception('必须提供 url 或 html');
    }
    
    $shot = $url
        ? Browsershot::url($url)
        : Browsershot::html($html);

    // === 环境变量控制沙盒模式 ===
    $sandboxEnabled = getenv('ENABLE_SANDBOX') === 'true';
    $args = $sandboxEnabled
        ? [] // 本地模式，保留沙盒
        : ['--no-sandbox', '--disable-setuid-sandbox'];

    $shot->setOption('args', $args)
        ->setOption('executablePath', getenv('PUPPETEER_EXECUTABLE_PATH') ?: '/usr/bin/chromium')
        ->setOption('headless', 'new');

    // 应用扩展参数
    if ($windowSize && isset($windowSize['width']) && isset($windowSize['height'])) {
        if (!is_numeric($windowSize['width']) || !is_numeric($windowSize['height']) || 
            $windowSize['width'] <= 0 || $windowSize['height'] <= 0) {
            throw new Exception('windowSize 的 width 和 height 必须是正整数');
        }
        $shot->windowSize((int)$windowSize['width'], (int)$windowSize['height']);
    }
    
    if ($device) {
        if (!is_string($device)) {
            throw new Exception('device 必须是字符串');
        }
        $shot->device($device);
    }
    
    if ($fullPage) {
        if (!is_bool($fullPage)) {
            throw new Exception('fullPage 必须是布尔值');
        }
        $shot->fullPage();
    }
    
    if ($delay) {
        if (!is_numeric($delay) || $delay < 0) {
            throw new Exception('delay 必须是非负整数');
        }
        $shot->setDelay((int)$delay);
    }
    
    if ($waitUntilNetworkIdle) {
        if (!is_bool($waitUntilNetworkIdle)) {
            throw new Exception('waitUntilNetworkIdle 必须是布尔值');
        }
        $shot->waitUntilNetworkIdle();
    }
    
    if ($userAgent) {
        if (!is_string($userAgent)) {
            throw new Exception('userAgent 必须是字符串');
        }
        $shot->userAgent($userAgent);
    }
    
    if ($mobile) {
        if (!is_bool($mobile)) {
            throw new Exception('mobile 必须是布尔值');
        }
        $shot->mobile();
    }
    
    if ($touch) {
        if (!is_bool($touch)) {
            throw new Exception('touch 必须是布尔值');
        }
        $shot->touch();
    }
    
    if ($hideBackground) {
        if (!is_bool($hideBackground)) {
            throw new Exception('hideBackground 必须是布尔值');
        }
        $shot->hideBackground();
    }
    
    if ($disableImages) {
        if (!is_bool($disableImages)) {
            throw new Exception('disableImages 必须是布尔值');
        }
        $shot->disableImages();
    }
    
    if ($format === 'pdf') {
        if ($pdfFormat) {
            if (!is_string($pdfFormat)) {
                throw new Exception('pdfFormat 必须是字符串');
            }
            $shot->format($pdfFormat);
        }
        
        if ($landscape) {
            if (!is_bool($landscape)) {
                throw new Exception('landscape 必须是布尔值');
            }
            $shot->landscape();
        }
    }

    switch ($format) {
        case 'png':
            $image = $shot->screenshot();
            if (strlen($image) < 100) {
                throw new Exception('Generated PNG too small, possibly corrupted');
            }
            
            if ($uploadToOSS) {
                $result = uploadToOSS($image, 'image/png', $ossObjectName);
                if (!$result['success']) {
                    throw new Exception('Failed to upload to OSS: ' . $result['error']);
                }
                return [
                    'identifier' => $identifier,
                    'type' => 'png',
                    'ossUrl' => $result['url'],
                    'message' => 'PNG generated and uploaded to OSS'
                ];
            } else {
                // 对于批量处理，我们返回 base64 编码的数据
                return [
                    'identifier' => $identifier,
                    'type' => 'png',
                    'data' => base64_encode($image),
                    'size' => strlen($image)
                ];
            }
            
        case 'pdf':
            $pdf = $shot->pdf();
            if (strlen($pdf) < 100) {
                throw new Exception('Generated PDF too small, possibly corrupted');
            }
            
            if ($uploadToOSS) {
                $result = uploadToOSS($pdf, 'application/pdf', $ossObjectName);
                if (!$result['success']) {
                    throw new Exception('Failed to upload to OSS: ' . $result['error']);
                }
                return [
                    'identifier' => $identifier,
                    'type' => 'pdf',
                    'ossUrl' => $result['url'],
                    'message' => 'PDF generated and uploaded to OSS'
                ];
            } else {
                // 对于批量处理，我们返回 base64 编码的数据
                return [
                    'identifier' => $identifier,
                    'type' => 'pdf',
                    'data' => base64_encode($pdf),
                    'size' => strlen($pdf)
                ];
            }
            
        default:
            throw new Exception('format 仅支持 png/pdf');
    }
}

function uploadToOSS($content, $contentType, $objectName = null)
{
    // 从环境变量获取 OSS 配置
    $accessKeyId = getenv('OSS_ACCESS_KEY_ID');
    $accessKeySecret = getenv('OSS_ACCESS_KEY_SECRET');
    $endpoint = getenv('OSS_ENDPOINT');
    $bucket = getenv('OSS_BUCKET');
    
    // 验证必要配置
    if (!$accessKeyId || !$accessKeySecret || !$endpoint || !$bucket) {
        error_log("OSS configuration missing");
        return [
            'success' => false,
            'error' => 'OSS configuration missing'
        ];
    }
    
    // 如果没有指定对象名，则生成一个唯一的名称
    if (!$objectName) {
        $extension = ($contentType == 'image/png') ? '.png' : '.pdf';
        $objectName = 'screenshots/' . date('Y/m/d/') . uniqid() . $extension;
    }
    
    try {
        $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
        
        // 上传文件
        $ossClient->putObject($bucket, $objectName, $content, [
            'Content-Type' => $contentType
        ]);
        
        // 构造访问 URL
        $ossUrl = str_replace('//', '//', "https://$bucket.$endpoint/$objectName");
        if (strpos($ossUrl, 'http:') === 0) {
            $ossUrl = str_replace('http:', 'https:', $ossUrl);
        }
        
        error_log("Successfully uploaded to OSS: " . $ossUrl);
        return [
            'success' => true,
            'url' => $ossUrl
        ];
    } catch (OssException $e) {
        error_log("OSS Exception: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    } catch (Exception $e) {
        error_log("General Exception during OSS upload: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

handler();