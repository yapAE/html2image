FROM golang:1.21-bullseye AS builder
WORKDIR /app
COPY . .
RUN go mod init html2image && go mod tidy && go build -o /server main.go

FROM debian:bullseye
RUN apt-get update && apt-get install -y \
    wkhtmltopdf \
    xfonts-75dpi xfonts-base fontconfig libjpeg62-turbo \
    --no-install-recommends && \
    rm -rf /var/lib/apt/lists/*
COPY --from=builder /server /server
EXPOSE 8080
CMD ["/server"]
