# Browsershot FC Service 使用示例

## API 响应格式说明

### 成功响应格式
所有成功的API调用都会返回以下格式的JSON响应：
```json
{
  "success": true,
  "data": {},
  "message": "操作成功"
}
```

### 错误响应格式
所有错误情况都会返回以下格式的JSON响应：
```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "错误描述"
  },
  "data": null
}
```

## 1. 单个 URL 截图（返回 PNG 二进制数据）

```bash
curl -X POST http://localhost:8080/screenshot \
  -H "Content-Type: application/json" \
  -d '{"url":"https://example.com","format":"png"}' \
  --output screenshot.png
```

注意：当不指定`uploadToOSS`参数或设置为`false`时，直接返回二进制数据流。

## 2. HTML 内容截图（返回 PDF 二进制数据）

```bash
curl -X POST http://localhost:8080/screenshot \
  -H "Content-Type: application/json" \
  -d '{"html":"<h1>Hello World</h1><p>This is a test.</p>","format":"pdf"}' \
  --output document.pdf
```

## 3. 带参数的截图

```bash
curl -X POST http://localhost:8080/screenshot \
  -H "Content-Type: application/json" \
  -d '{"url":"https://example.com","format":"png","windowSize":{"width":1920,"height":1080},"fullPage":true}' \
  --output fullpage.png
```

## 4. 设备模拟截图

```bash
curl -X POST http://localhost:8080/screenshot \
  -H "Content-Type: application/json" \
  -d '{"url":"https://example.com","format":"png","device":"iPhone X"}' \
  --output mobile.png
```

## 5. 批量处理多个 URL

```bash
curl -X POST http://localhost:8080/screenshot \
  -H "Content-Type: application/json" \
  -d '{"urls":["https://example.com","https://google.com","https://github.com"],"format":"png"}'
```

成功响应示例：
```json
{
  "success": true,
  "data": {
    "results": [
      {
        "identifier": "url_0",
        "type": "png",
        "data": "base64_encoded_data...",
        "size": 12345
      }
    ],
    "errors": [],
    "summary": {
      "total": 3,
      "success": 3,
      "failed": 0
    }
  },
  "message": "批量处理完成，成功3项，失败0项"
}
```

## 6. 批量处理多个 HTML 内容

```bash
curl -X POST http://localhost:8080/screenshot \
  -H "Content-Type: application/json" \
  -d '{"htmls":["<h1>Page 1</h1><p>Content 1</p>","<h1>Page 2</h1><p>Content 2</p>"],"format":"png"}'
```

## 7. 复杂批量处理（每个项目可以有自己的参数）

```bash
curl -X POST http://localhost:8080/screenshot \
  -H "Content-Type: application/json" \
  -d '{"items":[{"url":"https://example.com","format":"png","fullPage":true},{"html":"<h1>Test PDF</h1><p>PDF content</p>","format":"pdf","pdfFormat":"A4"}]}'
```

## 8. 截图并上传到 OSS

```bash
curl -X POST http://localhost:8080/screenshot \
  -H "Content-Type: application/json" \
  -d '{"url":"https://example.com","format":"png","uploadToOSS":true,"ossObjectName":"test/screenshot.png"}'
```

成功响应示例：
```json
{
  "success": true,
  "data": {
    "type": "png",
    "ossUrl": "https://your-bucket.oss-region.aliyuncs.com/test/screenshot.png"
  },
  "message": "文件已上传到OSS"
}
```

## 9. 批量处理并上传到 OSS

```bash
curl -X POST http://localhost:8080/screenshot \
  -H "Content-Type: application/json" \
  -d '{"urls":["https://example.com","https://google.com"],"format":"png","uploadToOSS":true}'
```

成功响应示例：
```json
{
  "success": true,
  "data": {
    "results": [
      {
        "identifier": "url_0",
        "type": "png",
        "ossUrl": "https://your-bucket.oss-region.aliyuncs.com/screenshots/2023/12/01/id1.png"
      },
      {
        "identifier": "url_1",
        "type": "png",
        "ossUrl": "https://your-bucket.oss-region.aliyuncs.com/screenshots/2023/12/01/id2.png"
      }
    ],
    "errors": [],
    "summary": {
      "total": 2,
      "success": 2,
      "failed": 0
    }
  },
  "message": "批量处理完成，成功2项，失败0项"
}
```

## 错误响应示例

参数错误：
```json
{
  "success": false,
  "error": {
    "code": "MISSING_REQUIRED_FIELD",
    "message": "必须提供 url 或 html"
  },
  "data": null
}
```

不支持的格式：
```json
{
  "success": false,
  "error": {
    "code": "UNSUPPORTED_FORMAT",
    "message": "format 仅支持 png/pdf"
  },
  "data": null
}
```

服务器内部错误：
```json
{
  "success": false,
  "error": {
    "code": "INTERNAL_ERROR",
    "message": "生成截图时发生错误"
  },
  "data": null
}
```

## 注意事项：

1. 请将 `http://localhost:8080` 替换为实际的服务地址
2. 批量处理会返回 JSON 格式的结果，包含每个项目的处理状态
3. 单个项目处理时，如果未指定上传到 OSS，则直接返回二进制数据
4. 如果指定上传到 OSS，则返回包含 OSS URL 的 JSON 结果
5. 所有成功的响应都遵循统一的格式：`{"success": true, "data": {}, "message": "..."}`
6. 所有错误响应都遵循统一的格式：`{"success": false, "error": {"code": "...", "message": "..."}}`