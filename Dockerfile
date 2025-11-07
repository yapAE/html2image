# 基础镜像
FROM php:8.2-cli-bullseye

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=Asia/Shanghai

# 安装系统依赖
RUN apt-get update && apt-get install -y \
    git unzip curl gnupg ca-certificates \
    fontconfig fonts-dejavu fonts-noto-cjk \
    chromium \
    cron \
    --no-install-recommends && \
    rm -rf /var/lib/apt/lists/*

# 安装 Node.js 18 + Puppeteer
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - && \
    apt-get install -y nodejs && \
    npm install -g puppeteer-core@21 puppeteer@21 && \
    rm -rf /var/lib/apt/lists/*

# 安装 Composer
RUN curl -sS https://getcomposer.org/installer | php && mv composer.phar /usr/local/bin/composer

WORKDIR /app

# 安装 PHP 依赖
COPY composer.json ./
RUN composer install --no-dev --optimize-autoloader

# 复制应用文件
COPY . .

# 创建日志目录和文件
RUN mkdir -p /var/log/php && touch /var/log/php/error.log && chmod 777 /var/log/php/error.log
RUN mkdir -p /var/log/batch && touch /var/log/batch/cleanup.log && chmod 777 /var/log/batch/cleanup.log

# 确保bin目录下的脚本可执行
RUN chmod +x /app/bin/*.sh /app/bin/*.php

# 创建批处理任务存储目录
RUN mkdir -p /tmp/batch_task_meta && chmod 777 /tmp/batch_task_meta

# 环境变量：浏览器路径和模块位置
ENV NODE_PATH=/usr/lib/node_modules
ENV PATH=$PATH:/usr/bin:/usr/local/bin
ENV PUPPETEER_SKIP_DOWNLOAD=true
ENV PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium

# 默认关闭沙盒（适用于生产 / root 环境）
ENV ENABLE_SANDBOX=false

EXPOSE 8080

# 使用新的启动脚本
CMD ["/app/bin/start_workers.sh"]