#!/bin/bash

# 启动批处理Worker的独立脚本
set -e

echo "启动批处理Worker..."

# 确保必要的目录存在
mkdir -p /tmp/batch_task_meta
mkdir -p /app/queue/{pending,processing,done,failed}
chmod 777 /tmp/batch_task_meta
chmod 777 /app/queue
chmod 777 /app/queue/pending
chmod 777 /app/queue/processing
chmod 777 /app/queue/done
chmod 777 /app/queue/failed

# 启动Worker进程（默认启动新的队列处理守护进程）
if [ "$1" = "legacy" ]; then
    echo "启动旧版Worker进程..."
    exec php /app/bin/process_batch_tasks.php
else
    echo "启动队列处理守护进程..."
    exec php /app/bin/queue_worker.php
fi