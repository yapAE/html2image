<?php

namespace App\Service;

use Spatie\Browsershot\Browsershot;
use OSS\OssClient;
use OSS\Core\OssException;

class ScreenshotService
{
    /**
     * 处理单个截图请求
     *
     * @param array $data 请求数据
     * @return array 处理结果
     * @throws \Exception
     */
    public function processSingleScreenshot(array $data): array
    {
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
            throw new \Exception('必须提供 url 或 html');
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
                throw new \Exception('windowSize 的 width 和 height 必须是正整数');
            }
            $shot->windowSize((int)$windowSize['width'], (int)$windowSize['height']);
        }
        
        if ($device) {
            if (!is_string($device)) {
                throw new \Exception('device 必须是字符串');
            }
            $shot->device($device);
        }
        
        if ($fullPage) {
            if (!is_bool($fullPage)) {
                throw new \Exception('fullPage 必须是布尔值');
            }
            $shot->fullPage();
        }
        
        if ($delay) {
            if (!is_numeric($delay) || $delay < 0) {
                throw new \Exception('delay 必须是非负整数');
            }
            $shot->setDelay((int)$delay);
        }
        
        if ($waitUntilNetworkIdle) {
            if (!is_bool($waitUntilNetworkIdle)) {
                throw new \Exception('waitUntilNetworkIdle 必须是布尔值');
            }
            $shot->waitUntilNetworkIdle();
        }
        
        if ($userAgent) {
            if (!is_string($userAgent)) {
                throw new \Exception('userAgent 必须是字符串');
            }
            $shot->userAgent($userAgent);
        }
        
        if ($mobile) {
            if (!is_bool($mobile)) {
                throw new \Exception('mobile 必须是布尔值');
            }
            $shot->mobile();
        }
        
        if ($touch) {
            if (!is_bool($touch)) {
                throw new \Exception('touch 必须是布尔值');
            }
            $shot->touch();
        }
        
        if ($hideBackground) {
            if (!is_bool($hideBackground)) {
                throw new \Exception('hideBackground 必须是布尔值');
            }
            $shot->hideBackground();
        }
        
        if ($disableImages) {
            if (!is_bool($disableImages)) {
                throw new \Exception('disableImages 必须是布尔值');
            }
            $shot->disableImages();
        }
        
        if ($format === 'pdf') {
            if ($pdfFormat) {
                if (!is_string($pdfFormat)) {
                    throw new \Exception('pdfFormat 必须是字符串');
                }
                $shot->format($pdfFormat);
            }
            
            if ($landscape) {
                if (!is_bool($landscape)) {
                    throw new \Exception('landscape 必须是布尔值');
                }
                $shot->landscape();
            }
        }

        switch ($format) {
            case 'png':
                $image = $shot->screenshot();
                if (strlen($image) < 100) {
                    throw new \Exception('Generated PNG too small, possibly corrupted');
                }
                
                if ($uploadToOSS) {
                    $result = $this->uploadToOSS($image, 'image/png', $ossObjectName);
                    if (!$result['success']) {
                        throw new \Exception('Failed to upload to OSS: ' . $result['error']);
                    }
                    return [
                        'type' => 'png',
                        'ossUrl' => $result['url'],
                        'message' => 'PNG generated and uploaded to OSS'
                    ];
                } else {
                    return [
                        'type' => 'png',
                        'data' => base64_encode($image),
                        'size' => strlen($image)
                    ];
                }
                
            case 'pdf':
                $pdf = $shot->pdf();
                if (strlen($pdf) < 100) {
                    throw new \Exception('Generated PDF too small, possibly corrupted');
                }
                
                if ($uploadToOSS) {
                    $result = $this->uploadToOSS($pdf, 'application/pdf', $ossObjectName);
                    if (!$result['success']) {
                        throw new \Exception('Failed to upload to OSS: ' . $result['error']);
                    }
                    return [
                        'type' => 'pdf',
                        'ossUrl' => $result['url'],
                        'message' => 'PDF generated and uploaded to OSS'
                    ];
                } else {
                    return [
                        'type' => 'pdf',
                        'data' => base64_encode($pdf),
                        'size' => strlen($pdf)
                    ];
                }
                
            default:
                throw new \Exception('format 仅支持 png/pdf');
        }
    }
    
    /**
     * 批量处理截图请求
     *
     * @param array $data 请求数据
     * @return array 处理结果
     */
    public function processBatchScreenshot(array $data): array
    {
        $results = [];
        $errors = [];
        
        // 处理 URLs 数组
        if (isset($data['urls']) && is_array($data['urls'])) {
            foreach ($data['urls'] as $index => $url) {
                try {
                    $itemData = ['url' => $url] + $data;
                    $result = $this->processSingleScreenshot($itemData);
                    $result['identifier'] = "url_$index";
                    $results[] = $result;
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'type' => 'url',
                        'value' => $url,
                        'error' => $e->getMessage()
                    ];
                }
            }
        }
        
        // 处理 HTMLs 数组
        if (isset($data['htmls']) && is_array($data['htmls'])) {
            foreach ($data['htmls'] as $index => $html) {
                try {
                    $itemData = ['html' => $html] + $data;
                    $result = $this->processSingleScreenshot($itemData);
                    $result['identifier'] = "html_$index";
                    $results[] = $result;
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'type' => 'html',
                        'value' => substr($html, 0, 50) . '...',
                        'error' => $e->getMessage()
                    ];
                }
            }
        }
        
        // 处理 items 数组
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $index => $item) {
                try {
                    $itemData = $item + $data;
                    $result = $this->processSingleScreenshot($itemData);
                    $result['identifier'] = "item_$index";
                    $results[] = $result;
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'type' => 'item',
                        'value' => json_encode($item),
                        'error' => $e->getMessage()
                    ];
                }
            }
        }
        
        return [
            'results' => $results,
            'errors' => $errors,
            'summary' => [
                'total' => count($results) + count($errors),
                'success' => count($results),
                'failed' => count($errors)
            ]
        ];
    }
    
    /**
     * 上传文件到阿里云 OSS
     *
     * @param string $content 文件内容
     * @param string $contentType 内容类型
     * @param string|null $objectName 对象名称
     * @return array 上传结果
     */
    private function uploadToOSS(string $content, string $contentType, ?string $objectName = null): array
    {
        // 从环境变量获取 OSS 配置
        $accessKeyId = getenv('OSS_ACCESS_KEY_ID');
        $accessKeySecret = getenv('OSS_ACCESS_KEY_SECRET');
        $endpoint = getenv('OSS_ENDPOINT');
        $bucket = getenv('OSS_BUCKET');
        
        // 验证必要配置
        if (!$accessKeyId || !$accessKeySecret || !$endpoint || !$bucket) {
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
            
            return [
                'success' => true,
                'url' => $ossUrl
            ];
        } catch (OssException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}