<?php
require 'vendor/autoload.php';

use Spatie\Browsershot\Browsershot;

function handler()
{
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Content-Type");

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $url = $data['url'] ?? null;
    $html = $data['html'] ?? null;
    $format = strtolower($data['format'] ?? 'png');

    if (!$url && !$html) {
        http_response_code(400);
        echo json_encode(['error' => '必须提供 url 或 html']);
        return;
    }

    try {
        $shot = $url
            ? Browsershot::url($url)
            : Browsershot::html($html);

        $shot
            ->setOption('no-sandbox', true)
            ->setOption('disable-setuid-sandbox', true)
            ->windowSize(1280, 800)
            ->waitUntilNetworkIdle();

        switch ($format) {
            case 'png':
                $image = $shot->screenshot();
                header('Content-Type: image/png');
                echo $image;
                break;
            case 'pdf':
                $pdf = $shot->pdf();
                header('Content-Type: application/pdf');
                echo $pdf;
                break;
            default:
                http_response_code(400);
                echo json_encode(['error' => 'format 仅支持 png/pdf']);
                break;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

handler();