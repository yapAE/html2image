FROM golang:1.21-bullseye

# 安装 wkhtmltopdf (包含 wkhtmltoimage)
RUN apt-get update && apt-get install -y \
    wkhtmltopdf \
    xfonts-75dpi xfonts-base fontconfig libjpeg62-turbo \
    --no-install-recommends && \
    rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY go.mod ./
COPY main.go ./

RUN go mod tidy && go build -o /usr/local/bin/html2image main.go

EXPOSE 8080
ENTRYPOINT ["html2image"]
