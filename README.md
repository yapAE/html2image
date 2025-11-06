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
 ├─ index.php              # 入口文件
 ├─ composer.json          # 依赖配置
 ├─ Dockerfile             # Docker 配置
 ├─ s.yaml                 # 部署配置
 ├─ src/                   # 源代码目录
 │   ├─ Controller/        # 控制器层
 │   │   └─ ScreenshotController.php
 │   ├─ Service/           # 服务层
 │   │   └─ ScreenshotService.php
 │   └─ Model/             # 数据模型层（预留）
 └─ tests/                 # 测试目录（预留）
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

### 3️⃣ 高级参数使用示例

```bash
# 设置窗口大小并截取完整页面
curl -X POST https://your-function-domain/screenshot \
  -H "Content-Type: application/json" \
  -d '{"url":"https://example.com","format":"png","windowSize":{"width":1920,"height":1080},"fullPage":true}' \
  --output page.png

# 设备模拟（iPhone X）
curl -X POST https://your-function-domain/screenshot \
  -H "Content-Type: application/json" \
  -d '{"url":"https://example.com","format":"png","device":"iPhone X"}' \
  --output mobile.png

# 延迟截图（等待页面加载完成）
curl -X POST https://your-function-domain/screenshot \
  -H "Content-Type: application/json" \
  -d '{"url":"https://example.com","format":"png","delay":3000}' \
  --output page.png

# 等待网络空闲后截图
curl -X POST https://your-function-domain/screenshot \
  -H "Content-Type: application/json" \
  -d '{"url":"https://example.com","format":"png","waitUntilNetworkIdle":true}' \
  --output page.png

# 生成 PDF 并设置格式
curl -X POST https://your-function-domain/screenshot \
  -H "Content-Type: application/json" \
  -d '{"url":"https://example.com","format":"pdf","pdfFormat":"A4","landscape":true}' \
  --output document.pdf

# 上传到阿里云 OSS
curl -X POST https://your-function-domain/screenshot \
  -H "Content-Type: application/json" \
  -d '{"url":"https://example.com","format":"png","uploadToOSS":true,"ossObjectName":"my-screenshots/test.png"}' \
  --output result.json
```

### 4️⃣ 批量处理示例

```bash
# 批量处理多个 URL
curl -X POST https://your-function-domain/screenshot \
  -H "Content-Type: application/json" \
  -d '{"urls":["https://example.com","https://google.com"],"format":"png"}' \
  --output batch_result.json

# 批量处理多个 HTML 内容
curl -X POST https://your-function-domain/screenshot \
  -H "Content-Type: application/json" \
  -d '{"htmls":["<h1>Page 1</h1>","<h1>Page 2</h1>"],"format":"png"}' \
  --output batch_result.json

# 批量处理复杂项目（每个项目可以有自己的参数）
curl -X POST https://your-function-domain/screenshot \
  -H "Content-Type: application/json" \
  -d '{"items":[{"url":"https://example.com","format":"png"},{"html":"<h1>Test</h1>","format":"pdf","pdfFormat":"A4"}]}' \
  --output batch_result.json

# 批量处理并上传到 OSS
curl -X POST https://your-function-domain/screenshot \
  -H "Content-Type: application/json" \
  -d '{"urls":["https://example.com","https://google.com"],"format":"png","uploadToOSS":true}' \
  --output batch_result.json
```

## ⚙️ 支持的参数

### 单个处理参数

| 参数 | 类型 | 说明 |
|------|------|------|
| `url` | string | 要截图的网页 URL |
| `html` | string | 要截图的 HTML 内容 |
| `format` | string | 输出格式：`png` 或 `pdf` |
| `windowSize` | object | 窗口大小：`{"width": 1280, "height": 800}` |
| `device` | string | 设备模拟：如 `"iPhone X"` |
| `fullPage` | boolean | 是否截取完整页面 |
| `delay` | number | 延迟截图时间（毫秒） |
| `waitUntilNetworkIdle` | boolean | 等待网络空闲 |
| `userAgent` | string | 自定义 User Agent |
| `mobile` | boolean | 移动设备模式 |
| `touch` | boolean | 触摸模式 |
| `hideBackground` | boolean | 隐藏背景 |
| `disableImages` | boolean | 禁用图片 |
| `pdfFormat` | string | PDF 格式：`A4`, `Letter` 等 |
| `landscape` | boolean | PDF 横向模式 |
| `uploadToOSS` | boolean | 是否上传到阿里云 OSS |
| `ossObjectName` | string | OSS 对象名称 |

### 批量处理参数

| 参数 | 类型 | 说明 |
|------|------|------|
| `urls` | array | URL 数组，如 `["https://example.com", "https://google.com"]` |
| `htmls` | array | HTML 内容数组 |
| `items` | array | 复杂项目数组，每个项目可以包含上述所有参数 |

## 📦 部署

```
npm install -g @serverless-devs/s
s deploy
```

# HTML to Image/PDF Service

这是一个基于PHP和Browsershot的Web服务，可以将HTML内容转换为图像或PDF文件。

## 功能特性

- 将网页URL或HTML内容转换为PNG图像或PDF文档
- 支持批量处理多个URL或HTML内容
- 支持多种截图参数配置（窗口大小、设备模拟、全页截图等）
- 支持将生成的文件上传到阿里云OSS
- 统一的API响应格式，便于前端处理

## 环境要求

- PHP 8.2+
- Composer
- Chromium浏览器

## 安装步骤

1. 克隆项目代码
2. 安装依赖：`composer install`
3. 启动服务：`php -S localhost:8080`

## API接口

### 统一响应格式

#### 成功响应
```json
{
  "success": true,
  "data": {},
  "message": "操作成功"
}
```

#### 错误响应
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

### 接口示例

详细使用示例请查看 [examples.md](examples.md) 文件。

## 项目结构

```
.
├── src/
│   ├── Controller/           # 控制器层
│   ├── Service/              # 服务层
│   └── Utils/                # 工具类
├── examples.md              # 使用示例
├── index.php                # 入口文件
└── composer.json            # 依赖配置
```

## 配置环境变量

如需上传到阿里云OSS，请设置以下环境变量：

- `OSS_ACCESS_KEY_ID`
- `OSS_ACCESS_KEY_SECRET`
- `OSS_ENDPOINT`
- `OSS_BUCKET`

如需自定义Chromium路径，请设置：

- `PUPPETEER_EXECUTABLE_PATH`

## 错误处理

所有错误都遵循统一的响应格式，便于前端处理。

## 许可证

MIT
