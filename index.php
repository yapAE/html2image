<?php
require 'vendor/autoload.php';

use Spatie\Browsershot\Browsershot;

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

    $url = $data['url'] ?? null;
    $html = $data['html'] ?? null;
    $format = strtolower($data['format'] ?? 'png');

    error_log("URL: " . ($url ?? 'null'));
    error_log("HTML: " . (substr($html ?? '', 0, 100) . (strlen($html ?? '') > 100 ? '...' : '')));
    error_log("Format: " . $format);

    if (!$url && !$html) {
        http_response_code(400);
        echo json_encode(['error' => '必须提供 url 或 html']);
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

        $shot
            ->setOption('args', $args)
            ->setOption('executablePath', getenv('PUPPETEER_EXECUTABLE_PATH') ?: '/usr/bin/chromium')
            ->setOption('headless', 'new')
            ->windowSize(1280, 800)
            ->waitUntilNetworkIdle();

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

handler();