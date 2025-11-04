# Browsershot FC Service

一套完整、可直接部署到 **阿里云函数计算 FC** 的 **Browsershot（PHP + headless Chromium）截图服务**，使用 **自定义容器镜像（Custom Container Runtime）** 架构。

## 🚀 项目目标

> 接口： `POST /screenshot`
> 输入 JSON：

```json
{
  "url": "https://example.com",
  "format": "png"
}
```

返回截图的 PNG 文件。

## 📁 目录结构

```
browsershot-fc/
 ├─ index.php
 ├─ composer.json
 ├─ Dockerfile
 ├─ s.yaml
```

## 🧪 调用测试

### 1️⃣ URL 截图

```bash
curl -X POST https://your-function-domain/screenshot \
  -H "Content-Type: application/json" \
  -d '{"url":"https://example.com","format":"png"}' \
  --output page.png
```

### 2️⃣ HTML 内容截图

```bash
curl -X POST https://your-function-domain/screenshot \
  -H "Content-Type: application/json" \
  -d '{"html":"<h1>Hello FC</h1><p>This is a test.</p>","format":"pdf"}' \
  --output test.pdf
```

## 📦 部署

```bash
npm install -g @serverless-devs/s
s deploy
```