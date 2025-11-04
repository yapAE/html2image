# 使用 PHP + Node 环境（Browsershot 需要 Node 和 Chromium）
FROM php:8.2-cli-bullseye

# 安装依赖：Node + Chromium + fonts
RUN apt-get update && apt-get install -y \
    git unzip curl gnupg fontconfig fonts-dejavu fonts-noto-cjk \
    chromium \
    nodejs npm \
    --no-install-recommends && \
    rm -rf /var/lib/apt/lists/*

# 安装 Composer
RUN curl -sS https://getcomposer.org/installer | php && mv composer.phar /usr/local/bin/composer

WORKDIR /app
COPY composer.json ./
RUN composer install --no-dev --optimize-autoloader

COPY . .

EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "index.php"]