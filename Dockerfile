FROM golang:1.21-alpine AS builder
WORKDIR /app
COPY . .
RUN go mod tidy && go build -o /server main.go

# === Runtime Stage ===
FROM alpine:3.20

# 安装字体支持 + 字体渲染库
RUN apk add --no-cache \
    fontconfig \
    ttf-dejavu \
    ttf-freefont \
    ttf-liberation \
    freetype \
    libjpeg-turbo \
    libxrender \
    libxext \
    libpng \
    curl

# 安装静态版 wkhtmltoimage / wkhtmltopdf
RUN curl -L -o /tmp/wkhtml.tar.xz \
    https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6-1/wkhtmltox-0.12.6-1.alpine3.20-amd64.tar.xz && \
    tar -xJf /tmp/wkhtml.tar.xz -C /tmp && \
    cp /tmp/wkhtmltox/bin/wkhtmlto* /usr/local/bin/ && \
    rm -rf /tmp/*

COPY --from=builder /server /server

EXPOSE 8080
CMD ["/server"]
