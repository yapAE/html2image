# Build stage
FROM golang:1.21-bullseye AS builder
WORKDIR /app

# Install build dependencies for plutobook
RUN apt-get update && apt-get install -y \
    git \
    build-essential \
    cmake \
    pkg-config \
    python3-pip \
    libexpat1-dev \
    libicu-dev \
    libfreetype6-dev \
    libfontconfig1-dev \
    libharfbuzz-dev \
    libcairo2-dev \
    libcurl4-openssl-dev \
    libjpeg-turbo8-dev \
    libpng-dev \
    libwebp-dev \
    && rm -rf /var/lib/apt/lists/*

# Copy go mod files first for better caching
COPY go.mod go.sum ./
RUN go mod download

# Copy source code
COPY . .

# Build the binary
RUN CGO_ENABLED=0 GOOS=linux go build -a -installsuffix cgo -o /server main.go

# Build plutobook from source
RUN git clone https://github.com/plutoprint/plutobook.git /plutobook && \
    cd /plutobook && \
    # Install meson and ninja build tools \
    pip3 install meson ninja && \
    # Build using meson with tools enabled \
    meson setup builddir -Dtools=enabled && \
    meson compile -C builddir && \
    # Install to default location \
    DESTDIR=/usr/local meson install -C builddir

# Final stage
FROM debian:bullseye-slim

# Install runtime dependencies
RUN apt-get update && apt-get install -y \
    curl \
    ca-certificates \
    fontconfig \
    libexpat1 \
    libicu72 \
    libfreetype6 \
    libharfbuzz0b \
    libcairo2 \
    libcurl4 \
    libjpeg-turbo8 \
    libpng16-16 \
    libwebp7 \
    --no-install-recommends && \
    rm -rf /var/lib/apt/lists/*

# Copy plutobook binary from builder stage
COPY --from=builder /usr/local/bin/plutobook /usr/local/bin/plutobook

# Create non-root user
RUN groupadd -r appuser && useradd -r -g appuser appuser

# Copy the binary from builder stage
COPY --from=builder /server /server

# Change ownership of the binary
RUN chown appuser:appuser /server

# Switch to non-root user
USER appuser

# Expose port
EXPOSE 8080

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
  CMD curl -f http://localhost:8080/health || exit 1

# Run the binary
CMD ["/server"]