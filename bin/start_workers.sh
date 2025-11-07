#!/bin/bash

# 容器环境下的启动脚本
set -e

# 获取项目根目录
PROJECT_DIR="/app"

echo "初始化应用环境..."

# 确保必要的目录存在并有正确权限
mkdir -p /tmp/batch_task_meta
chmod 777 /tmp/batch_task_meta

mkdir -p /var/log/php
touch /var/log/php/error.log
chmod 777 /var/log/php/error.log

mkdir -p /var/log/batch
touch /var/log/batch/cleanup.log
chmod 777 /var/log/batch/cleanup.log

# 设置定时任务
echo "设置定时清理任务..."
echo "0 * * * * /usr/bin/php $PROJECT_DIR/bin/cleanup_expired_tasks.php >> /var/log/batch/cleanup.log 2>&1" | crontab -
# 启动cron服务
cron
echo "定时任务服务已启动"

echo "启动主应用服务..."
exec php -S 0.0.0.0:8080 -d error_log=/var/log/php/error.log