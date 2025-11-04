<?php
// test_browsershot.php - 用于本地测试的简化版本

// 模拟Browsershot类
class MockBrowsershot {
    private $url;
    private $html;
    
    public static function url($url) {
        $instance = new self();
        $instance->url = $url;
        return $instance;
    }
    
    public static function html($html) {
        $instance = new self();
        $instance->html = $html;
        return $instance;
    }
    
    public function setOption($key, $value) {
        // 模拟设置选项
        return $this;
    }
    
    public function windowSize($width, $height) {
        // 模拟设置窗口大小
        return $this;
    }
    
    public function waitUntilNetworkIdle() {
        // 模拟等待网络空闲
        return $this;
    }
    
    public function screenshot() {
        // 模拟生成截图
        return "mock_png_data_" . uniqid();
    }
    
    public function pdf() {
        // 模拟生成PDF
        return "mock_pdf_data_" . uniqid();
    }
}

// 模拟handler函数用于测试
function test_handler() {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Content-Type");

    // 添加日志记录
    error_log("=== Browsershot Request Started ===");
    
    // 模拟输入数据
    $data = [
        'url' => 'https://example.com',
        'format' => 'png'
    ];
    
    error_log("Input data: " . json_encode($data));

    $url = $data['url'] ?? null;
    $html = $data['html'] ?? null;
    $format = strtolower($data['format'] ?? 'png');

    error_log("URL: " . ($url ?? 'null'));
    error_log("HTML: " . (substr($html ?? '', 0, 100) . (strlen($html ?? '') > 100 ? '...' : '')));
    error_log("Format: " . $format);

    if (!$url && !$html) {
        error_log("Error: Missing url or html parameter");
        http_response_code(400);
        echo json_encode(['error' => '必须提供 url 或 html']);
        return;
    }

    try {
        $shot = $url
            ? MockBrowsershot::url($url)
            : MockBrowsershot::html($html);

        error_log("Browsershot instance created");

        $shot
            ->setOption('no-sandbox', true)
            ->setOption('disable-setuid-sandbox', true)
            ->windowSize(1280, 800)
            ->waitUntilNetworkIdle();

        error_log("Browsershot options set");

        switch ($format) {
            case 'png':
                error_log("Generating PNG screenshot");
                $image = $shot->screenshot();
                error_log("PNG generated, size: " . strlen($image) . " bytes");
                
                if (strlen($image) < 100) {
                    error_log("Error: Generated PNG too small, possibly corrupted");
                    http_response_code(500);
                    echo json_encode(['error' => 'Generated image too small, possibly corrupted']);
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
                    error_log("Error: Generated PDF too small, possibly corrupted");
                    http_response_code(500);
                    echo json_encode(['error' => 'Generated PDF too small, possibly corrupted']);
                    return;
                }
                
                header('Content-Type: application/pdf');
                header('Content-Length: ' . strlen($pdf));
                echo $pdf;
                error_log("PDF response sent");
                break;
            default:
                error_log("Error: Unsupported format " . $format);
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

// 运行测试
test_handler();
?>