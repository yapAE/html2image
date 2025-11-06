<?php

namespace App\Utils;

class ApiResponse
{
    /**
     * 返回标准错误响应
     *
     * @param string $code 错误码
     * @param string $message 错误信息
     * @param mixed $data 附加数据
     * @return array
     */
    public static function error(string $code, string $message, $data = null): array
    {
        return [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message
            ],
            'data' => $data
        ];
    }
    
    /**
     * 返回标准成功响应
     *
     * @param mixed $data 数据
     * @param string $message 成功消息
     * @return array
     */
    public static function success($data = null, string $message = '操作成功'): array
    {
        return [
            'success' => true,
            'data' => $data,
            'message' => $message
        ];
    }
    
    /**
     * 返回二进制数据成功响应
     *
     * @param string $type 数据类型(png/pdf)
     * @param string $data 二进制数据
     * @param int $size 数据大小
     * @return array
     */
    public static function binaryData(string $type, string $data, int $size): array
    {
        return [
            'success' => true,
            'data' => [
                'type' => $type,
                'data' => base64_encode($data),
                'size' => $size
            ],
            'message' => $type === 'png' ? 'PNG截图生成成功' : 'PDF文档生成成功'
        ];
    }
    
    /**
     * 返回OSS上传成功响应
     *
     * @param string $type 数据类型
     * @param string $ossUrl OSS链接
     * @return array
     */
    public static function ossUpload(string $type, string $ossUrl): array
    {
        return [
            'success' => true,
            'data' => [
                'type' => $type,
                'ossUrl' => $ossUrl
            ],
            'message' => '文件已上传到OSS'
        ];
    }
    
    /**
     * 返回批量处理结果
     *
     * @param array $results 成功结果
     * @param array $errors 错误信息
     * @return array
     */
    public static function batchResult(array $results, array $errors): array
    {
        $successCount = count($results);
        $failedCount = count($errors);
        
        return [
            'success' => true,
            'data' => [
                'results' => $results,
                'errors' => $errors,
                'summary' => [
                    'total' => $successCount + $failedCount,
                    'success' => $successCount,
                    'failed' => $failedCount
                ]
            ],
            'message' => sprintf('批量处理完成，成功%d项，失败%d项', $successCount, $failedCount)
        ];
    }
}