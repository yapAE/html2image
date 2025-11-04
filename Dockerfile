# 基础镜像：PHP 8.2 + Debian Bullseye
FROM php:8.2-cli-bullseye

# 设置时区、避免交互
ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=Asia/Shanghai

# 安装系统依赖：Chromium + Node.js + 字体
RUN apt-get update && apt-get install -y \
    git unzip curl gnupg ca-certificates \
    fontconfig fonts-dejavu fonts-noto-cjk \
    chromium nodejs npm \
    --no-install-recommends && \
    rm -rf /var/lib/apt/lists/*

# 设置 Puppeteer 环境变量：跳过下载内置 Chrome
ENV PUPPETEER_SKIP_DOWNLOAD=true

# 安装 Puppeteer-Core（不带 Chrome）
RUN npm install -g puppeteer-core@21

# 安装 Composer
RUN curl -sS https://getcomposer.org/installer | php && mv composer.phar /usr/local/bin/composer

# 设置工作目录
WORKDIR /app

# 复制 composer 文件并安装 PHP 依赖
COPY composer.json ./
RUN composer install --no-dev --optimize-autoloader

# 复制应用代码
COPY . .

# 创建日志目录
RUN mkdir -p /var/log/php && \
    touch /var/log/php/error.log && \
    chmod 777 /var/log/php/error.log

# 设定 Puppeteer 默认执行路径（Browsershot 会自动读取）
ENV PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium

# 暴露端口
EXPOSE 8080

# 运行 PHP 内置服务器
CMD ["php", "-S", "0.0.0.0:8080", "-d", "error_log=/var/log/php/error.log", "index.php"]
