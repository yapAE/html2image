# 前端使用指南

本文档说明如何在前端使用 `/api/screenshot` 接口返回的JSON数据。

## API响应格式

所有通过 `/api/screenshot` 路由的请求都会返回统一的JSON格式：

### 成功响应
```json
{
  "success": true,
  "data": {},
  "message": "操作成功"
}
```

### 错误响应
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

## 前端处理示例

### 1. 处理PNG截图数据

```javascript
// 发送请求
fetch('http://localhost:8080/api/screenshot', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    url: 'https://example.com',
    format: 'png'
  })
})
.then(response => response.json())
.then(result => {
  if (result.success) {
    // 处理成功响应
    const imageData = result.data; // {type: 'png', data: 'base64...', size: 12345}
    
    // 方式1: 在img标签中显示
    const imgElement = document.getElementById('screenshot');
    imgElement.src = `data:image/png;base64,${imageData.data}`;
    
    // 方式2: 创建下载链接
    const downloadLink = document.createElement('a');
    downloadLink.href = `data:image/png;base64,${imageData.data}`;
    downloadLink.download = 'screenshot.png';
    downloadLink.textContent = '下载截图';
    document.body.appendChild(downloadLink);
    
    // 方式3: 在新窗口中打开
    const newWindow = window.open();
    newWindow.document.write(`<img src="data:image/png;base64,${imageData.data}" />`);
  } else {
    // 处理错误响应
    console.error('错误代码:', result.error.code);
    console.error('错误信息:', result.error.message);
    alert(`操作失败: ${result.error.message}`);
  }
})
.catch(error => {
  console.error('网络错误:', error);
  alert('网络请求失败');
});
```

### 2. 处理PDF文档数据

```javascript
// 发送请求
fetch('http://localhost:8080/api/screenshot', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    html: '<h1>Hello World</h1><p>This is a test.</p>',
    format: 'pdf'
  })
})
.then(response => response.json())
.then(result => {
  if (result.success) {
    // 处理成功响应
    const pdfData = result.data; // {type: 'pdf', data: 'base64...', size: 12345}
    
    // 方式1: 在新窗口中打开PDF
    const binaryData = atob(pdfData.data);
    const byteNumbers = new Array(binaryData.length);
    for (let i = 0; i < binaryData.length; i++) {
      byteNumbers[i] = binaryData.charCodeAt(i);
    }
    const byteArray = new Uint8Array(byteNumbers);
    const blob = new Blob([byteArray], {type: 'application/pdf'});
    const url = URL.createObjectURL(blob);
    window.open(url, '_blank');
    
    // 方式2: 嵌入PDF查看器
    const pdfContainer = document.getElementById('pdf-container');
    pdfContainer.innerHTML = `
      <embed src="data:application/pdf;base64,${pdfData.data}" 
             type="application/pdf" 
             width="100%" 
             height="600px" />
    `;
    
    // 方式3: 提供下载链接
    const downloadLink = document.createElement('a');
    downloadLink.href = `data:application/pdf;base64,${pdfData.data}`;
    downloadLink.download = 'document.pdf';
    downloadLink.textContent = '下载PDF文档';
    document.body.appendChild(downloadLink);
  } else {
    // 处理错误响应
    console.error('错误代码:', result.error.code);
    console.error('错误信息:', result.error.message);
    alert(`操作失败: ${result.error.message}`);
  }
})
.catch(error => {
  console.error('网络错误:', error);
  alert('网络请求失败');
});
```

### 3. 处理OSS上传结果

```javascript
// 发送请求（上传到OSS）
fetch('http://localhost:8080/api/screenshot', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    url: 'https://example.com',
    format: 'png',
    uploadToOSS: true,
    ossObjectName: 'screenshots/test.png'
  })
})
.then(response => response.json())
.then(result => {
  if (result.success) {
    // 处理成功响应
    const ossData = result.data; // {type: 'png', ossUrl: 'https://...'}
    
    // 直接使用OSS URL
    const imgElement = document.getElementById('screenshot');
    imgElement.src = ossData.ossUrl;
    
    // 或者提供下载链接
    const downloadLink = document.createElement('a');
    downloadLink.href = ossData.ossUrl;
    downloadLink.download = 'screenshot.png';
    downloadLink.textContent = '下载截图';
    document.body.appendChild(downloadLink);
  } else {
    // 处理错误响应
    console.error('错误代码:', result.error.code);
    console.error('错误信息:', result.error.message);
    alert(`操作失败: ${result.error.message}`);
  }
})
.catch(error => {
  console.error('网络错误:', error);
  alert('网络请求失败');
});
```

### 4. 处理批量处理结果

```javascript
// 发送批量处理请求
fetch('http://localhost:8080/api/screenshot', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    urls: ['https://example.com', 'https://google.com'],
    format: 'png'
  })
})
.then(response => response.json())
.then(result => {
  if (result.success) {
    // 处理成功响应
    const batchData = result.data;
    const results = batchData.results;
    const errors = batchData.errors;
    const summary = batchData.summary;
    
    console.log(`总共处理: ${summary.total}`);
    console.log(`成功: ${summary.success}`);
    console.log(`失败: ${summary.failed}`);
    
    // 处理成功的项目
    results.forEach(item => {
      if (item.ossUrl) {
        // OSS上传结果
        console.log(`项目 ${item.identifier} 已上传到: ${item.ossUrl}`);
      } else {
        // Base64数据结果
        console.log(`项目 ${item.identifier} 数据大小: ${item.data.size}`);
        // 可以在这里处理显示或下载逻辑
      }
    });
    
    // 处理错误的项目
    if (errors.length > 0) {
      console.error('以下项目处理失败:');
      errors.forEach(error => {
        console.error(`  ${error.type} ${error.value}: ${error.error}`);
      });
    }
  } else {
    // 处理错误响应
    console.error('错误代码:', result.error.code);
    console.error('错误信息:', result.error.message);
    alert(`批量处理失败: ${result.error.message}`);
  }
})
.catch(error => {
  console.error('网络错误:', error);
  alert('网络请求失败');
});
```

## 错误处理最佳实践

```javascript
// 通用错误处理函数
function handleApiResponse(result) {
  if (!result.success) {
    switch (result.error.code) {
      case 'MISSING_REQUIRED_FIELD':
        alert('请提供URL或HTML内容');
        break;
      case 'UNSUPPORTED_FORMAT':
        alert('仅支持PNG和PDF格式');
        break;
      case 'INTERNAL_ERROR':
        alert('服务器内部错误，请稍后重试');
        break;
      default:
        alert(`操作失败: ${result.error.message}`);
    }
    return false;
  }
  return true;
}

// 使用示例
fetch('http://localhost:8080/api/screenshot', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    url: 'https://example.com',
    format: 'png'
  })
})
.then(response => response.json())
.then(result => {
  if (handleApiResponse(result)) {
    // 处理成功响应
    const imageData = result.data;
    document.getElementById('screenshot').src = `data:image/png;base64,${imageData.data}`;
  }
})
.catch(error => {
  console.error('网络错误:', error);
  alert('网络请求失败');
});
```

## 注意事项

1. **Base64解码**: 处理二进制数据时需要正确解码base64字符串
2. **内存管理**: 处理大文件时注意内存使用，及时释放URL对象
3. **错误处理**: 始终检查API响应的success字段
4. **跨域问题**: 确保服务端正确设置了CORS头部
5. **文件大小**: 大文件的base64数据可能很长，注意性能影响