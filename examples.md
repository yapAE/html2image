# Browsershot FC Service 使用示例

## 1. 单个 URL 截图（返回 PNG 二进制数据）

```bash
curl -X POST http://localhost:8080/screenshot \
  -H "Content-Type: application/json" \
  -d '{"url":"https://example.com","format":"png"}' \
  --output screenshot.png
```

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
  -d '{"urls":["https://example.com","https://google.com","https://github.com"],"format":"png"}' \
  --output batch_result.json
```

## 6. 批量处理多个 HTML 内容

```bash
curl -X POST http://localhost:8080/screenshot \
  -H "Content-Type: application/json" \
  -d '{"htmls":["<h1>Page 1</h1><p>Content 1</p>","<h1>Page 2</h1><p>Content 2</p>"],"format":"png"}' \
  --output batch_result.json
```

## 7. 复杂批量处理（每个项目可以有自己的参数）

```bash
curl -X POST http://localhost:8080/screenshot \
  -H "Content-Type: application/json" \
  -d '{"items":[{"url":"https://example.com","format":"png","fullPage":true},{"html":"<h1>Test PDF</h1><p>PDF content</p>","format":"pdf","pdfFormat":"A4"}]}' \
  --output batch_result.json
```

## 8. 截图并上传到 OSS

```bash
curl -X POST http://localhost:8080/screenshot \
  -H "Content-Type: application/json" \
  -d '{"url":"https://example.com","format":"png","uploadToOSS":true,"ossObjectName":"test/screenshot.png"}' \
  --output upload_result.json
```

## 9. 批量处理并上传到 OSS

```bash
curl -X POST http://localhost:8080/screenshot \
  -H "Content-Type: application/json" \
  -d '{"urls":["https://example.com","https://google.com"],"format":"png","uploadToOSS":true}' \
  --output batch_upload_result.json
```

## 注意事项：

1. 请将 `http://localhost:8080` 替换为实际的服务地址
2. 批量处理会返回 JSON 格式的结果，包含每个项目的处理状态
3. 单个项目处理时，如果未指定上传到 OSS，则直接返回二进制数据
4. 如果指定上传到 OSS，则返回包含 OSS URL 的 JSON 结果