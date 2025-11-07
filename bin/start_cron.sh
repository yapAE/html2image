#!/bin/bash

# 添加定时任务
(crontab -l 2>/dev/null; echo "0 * * * * /usr/bin/php /Users/solo/Documents/projects/b-gg/html2image/bin/cleanup_expired_tasks.php >> /var/log/batch_task_cleanup.log 2>&1") | crontab -

echo "已添加定时任务，每小时清理一次过期任务"