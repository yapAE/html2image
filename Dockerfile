# 使用 PHP + Node 环境（Browsershot 需要 Node 和 Chromium）
FROM php:8.2-cli-bullseye

# 安装依赖：Node + Chromium + fonts
RUN apt-get update && apt-get install -y \
    git unzip curl gnupg fontconfig fonts-dejavu fonts-noto-cjk \
    chromium \
    --no-install-recommends && \
    rm -rf /var/lib/apt/lists/*

# 安装 Node.js 18.x (LTS版本)
RUN curl -sL https://deb.nodesource.com/setup_18.x | bash - && \
    apt-get install -y nodejs

# 安装 Composer
RUN curl -sS https://getcomposer.org/installer | php && mv composer.phar /usr/local/bin/composer

# 设置工作目录
WORKDIR /app

# 复制composer文件并安装依赖
COPY composer.json ./
RUN composer install --no-dev --optimize-autoloader

# 复制应用代码
COPY . .

# 创建日志目录
RUN mkdir -p /var/log/php && touch /var/log/php/error.log && chmod 777 /var/log/php/error.log

# 暴露端口
EXPOSE 8080

# 启动命令
CMD ["php", "-S", "0.0.0.0:8080", "-d", "error_log=/var/log/php/error.log", "index.php"]