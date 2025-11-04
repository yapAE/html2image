# 使用 PHP 8.2 + Debian 作为基础镜像
FROM php:8.2-cli-bullseye

# 设置时区和非交互安装
ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=Asia/Shanghai

# 安装依赖：Chromium、Node.js、字体等
RUN apt-get update && apt-get install -y \
    git unzip curl gnupg ca-certificates \
    fontconfig fonts-dejavu fonts-noto-cjk \
    chromium \
    --no-install-recommends && \
    rm -rf /var/lib/apt/lists/*

# 安装 Node.js 18.x (LTS)
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - && \
    apt-get install -y nodejs && \
    npm install -g puppeteer-core@21 puppeteer@21 && \
    rm -rf /var/lib/apt/lists/*

# 安装 Composer
RUN curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer

# 设置工作目录
WORKDIR /app

# 复制 composer.json 并安装 PHP 依赖
COPY composer.json ./
RUN composer install --no-dev --optimize-autoloader

# 复制应用代码
COPY . .

# 创建日志目录
RUN mkdir -p /var/log/php && \
    touch /var/log/php/error.log && \
    chmod 777 /var/log/php/error.log

# 设置 Puppeteer 环境变量
ENV NODE_PATH=/usr/lib/node_modules
ENV PATH=$PATH:/usr/bin:/usr/local/bin
ENV PUPPETEER_SKIP_DOWNLOAD=true
ENV PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium

# 暴露端口（供阿里云 FC 或 Docker 调用）
EXPOSE 8080

# 启动 PHP 内置 Web 服务器
CMD ["php", "-S", "0.0.0.0:8080", "-d", "error_log=/var/log/php/error.log", "index.php"]