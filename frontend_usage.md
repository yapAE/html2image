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

## 异步批处理使用指南

对于大量URL或HTML内容的处理，建议使用异步批处理接口 `/api/batch/screenshot`，避免长时间等待。

### 1. 提交异步批处理任务

```javascript
// 提交异步批处理任务
async function submitAsyncBatchTask(data) {
    const response = await fetch('http://localhost:8080/api/batch/screenshot', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    });
    
    const result = await response.json();
    if (result.success) {
        return result.data.taskId;
    } else {
        throw new Error(result.error.message);
    }
}

// 使用示例
const batchData = {
    urls: [
        'https://example1.com',
        'https://example2.com',
        'https://example3.com'
    ],
    format: 'png',
    windowSize: {width: 1920, height: 1080},
    fullPage: true
};

submitAsyncBatchTask(batchData)
    .then(taskId => {
        console.log('批处理任务已提交，任务ID:', taskId);
        // 开始轮询任务状态
        pollTaskStatus(taskId);
    })
    .catch(error => {
        console.error('提交批处理任务失败:', error);
        alert('提交批处理任务失败: ' + error.message);
    });
```

### 2. 轮询任务状态

```javascript
// 轮询任务状态
async function pollTaskStatus(taskId) {
    const pollInterval = setInterval(async () => {
        try {
            const response = await fetch(`http://localhost:8080/api/batch/screenshot/${taskId}`);
            const result = await response.json();
            
            if (result.success) {
                const task = result.data;
                
                // 更新进度显示
                updateProgress(task.completedItems || 0, task.totalItems || 0);
                
                // 显示部分已完成的结果
                if (task.results && task.results.length > 0) {
                    displayPartialResults(task.results);
                }
                
                // 任务完成时停止轮询
                if (task.status === 'completed') {
                    clearInterval(pollInterval);
                    handleBatchCompletion(task);
                } else if (task.status === 'failed') {
                    clearInterval(pollInterval);
                    handleBatchFailure(task);
                }
            } else {
                console.error('获取任务状态失败:', result.error.message);
                clearInterval(pollInterval);
            }
        } catch (error) {
            console.error('轮询任务状态失败:', error);
        }
    }, 3000); // 每3秒查询一次
}

// 更新进度显示
function updateProgress(completed, total) {
    const progressElement = document.getElementById('progress');
    if (progressElement) {
        progressElement.textContent = `进度: ${completed}/${total}`;
        if (total > 0) {
            progressElement.style.width = `${(completed / total) * 100}%`;
        }
    }
}

// 显示部分已完成的结果
function displayPartialResults(results) {
    const container = document.getElementById('partial-results');
    if (container) {
        results.forEach(result => {
            if (result.ossUrl && !document.getElementById(`result-${result.identifier}`)) {
                const div = document.createElement('div');
                div.id = `result-${result.identifier}`;
                div.innerHTML = `
                    <h4>${result.identifier}</h4>
                    <img src="${result.ossUrl}" style="max-width: 200px;" />
                    <a href="${result.ossUrl}" target="_blank">查看原图</a>
                `;
                container.appendChild(div);
            }
        });
    }
}

// 处理批处理完成
function handleBatchCompletion(task) {
    console.log('批处理任务完成:', task);
    alert(`批处理任务完成！成功: ${task.summary.success}, 失败: ${task.summary.failed}`);
    
    // 显示所有结果
    displayFinalResults(task.results, task.errors);
}

// 处理批处理失败
function handleBatchFailure(task) {
    console.error('批处理任务失败:', task);
    alert('批处理任务失败: ' + (task.errorMessage || '未知错误'));
}

// 显示最终结果
function displayFinalResults(results, errors) {
    const resultsContainer = document.getElementById('final-results');
    if (resultsContainer) {
        resultsContainer.innerHTML = '<h3>处理结果</h3>';
        
        results.forEach(result => {
            const div = document.createElement('div');
            div.innerHTML = `
                <h4>${result.identifier}</h4>
                ${result.ossUrl ? 
                    `<img src="${result.ossUrl}" style="max-width: 200px;" />
                     <a href="${result.ossUrl}" target="_blank">查看原图</a>` : 
                    '<p>数据已生成但未上传到OSS</p>'
                }
            `;
            resultsContainer.appendChild(div);
        });
    }
    
    // 显示错误信息
    if (errors && errors.length > 0) {
        const errorsContainer = document.getElementById('error-results');
        if (errorsContainer) {
            errorsContainer.innerHTML = '<h3>错误信息</h3>';
            errors.forEach(error => {
                const div = document.createElement('div');
                div.innerHTML = `
                    <h4>${error.identifier || error.index}</h4>
                    <p>错误: ${error.error}</p>
                    <p>值: ${error.value}</p>
                `;
                errorsContainer.appendChild(div);
            });
        }
    }
}
```

### 3. 完整的异步批处理示例

```html
<!DOCTYPE html>
<html>
<head>
    <title>异步批处理示例</title>
</head>
<body>
    <h1>异步批处理示例</h1>
    
    <button id="startBatch">开始批处理</button>
    
    <div id="progress-container">
        <div id="progress" style="width: 0%; height: 20px; background-color: #4CAF50; text-align: center; line-height: 20px; color: white;"></div>
    </div>
    
    <div id="partial-results"></div>
    <div id="final-results"></div>
    <div id="error-results"></div>
    
    <script>
        document.getElementById('startBatch').addEventListener('click', function() {
            const batchData = {
                urls: [
                    'https://example.com',
                    'https://google.com',
                    'https://github.com'
                ],
                format: 'png',
                windowSize: {width: 1920, height: 1080}
            };
            
            submitAsyncBatchTask(batchData)
                .then(taskId => {
                    console.log('批处理任务已提交，任务ID:', taskId);
                    pollTaskStatus(taskId);
                })
                .catch(error => {
                    console.error('提交批处理任务失败:', error);
                    alert('提交批处理任务失败: ' + error.message);
                });
        });
        
        // 这里包含上面定义的所有函数
        // submitAsyncBatchTask, pollTaskStatus, updateProgress, 
        // displayPartialResults, handleBatchCompletion, handleBatchFailure, displayFinalResults
    </script>
</body>
</html>
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
6. **异步处理**: 对于大量任务，使用异步批处理接口避免长时间等待
7. **任务状态**: 异步任务有24小时的有效期，过期后将无法查询