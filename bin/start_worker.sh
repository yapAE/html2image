#!/bin/bash

# 启动批处理Worker的独立脚本
set -e

echo "启动批处理Worker..."

# 确保必要的目录存在
mkdir -p /tmp/batch_task_meta
chmod 777 /tmp/batch_task_meta

# 启动Worker进程
exec php /app/bin/process_batch_tasks.php