#!/bin/bash

# 容器环境下的启动脚本
set -e

echo "初始化应用环境..."

# 确保必要的目录存在并有正确权限
mkdir -p /tmp/batch_task_meta
chmod 777 /tmp/batch_task_meta

# 初始化队列目录
mkdir -p /app/queue/{pending,processing,done,failed}
chmod 777 /app/queue
chmod 777 /app/queue/pending
chmod 777 /app/queue/processing
chmod 777 /app/queue/done
chmod 777 /app/queue/failed

mkdir -p /var/log/php
touch /var/log/php/error.log
chmod 777 /var/log/php/error.log

mkdir -p /var/log/batch
touch /var/log/batch/cleanup.log
chmod 777 /var/log/batch/cleanup.log

# 设置定时任务
echo "设置定时清理任务..."
echo "0 * * * * /usr/bin/php /app/bin/cleanup_expired_tasks.php >> /var/log/batch/cleanup.log 2>&1" | crontab -
echo "*/30 * * * * /usr/bin/php /app/bin/check_timeout_tasks.php >> /var/log/batch/timeout_check.log 2>&1" | crontab -
# 启动cron服务
cron
echo "定时任务服务已启动"

# 启动队列处理守护进程
echo "启动队列处理守护进程..."
php /app/bin/queue_worker.php &

echo "启动主应用服务..."
exec php -S 0.0.0.0:8080 -d error_log=/var/log/php/error.log