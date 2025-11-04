# Build stage
FROM quay.io/pypa/manylinux_2_28_x86_64:latest AS builder

# Install build dependencies for plutobook
RUN dnf install -y \
    git \
    gcc-c++ \
    pkg-config \
    libcurl-devel \
    libicu-devel \
    bzip2-devel \
    brotli-devel \
    gperf \
    && dnf clean all

# Install meson and ninja
RUN /opt/python/cp312-cp312/bin/python3 -m pip install --upgrade pip meson ninja

WORKDIR /tmp

# Build plutobook from source
RUN git clone https://github.com/plutoprint/plutobook.git && \
    cd plutobook && \
    /opt/python/cp312-cp312/bin/python3 -m pip install meson ninja && \
    meson setup build --buildtype=release --prefix=/usr -Dtools=enabled && \
    meson compile -C build && \
    meson install -C build --strip

# Switch to a Go environment
RUN dnf install -y golang && dnf clean all

WORKDIR /app

# Copy go mod files first for better caching
COPY go.mod go.sum ./
RUN go mod download

# Copy source code
COPY . .

# Build the binary
RUN CGO_ENABLED=0 GOOS=linux go build -a -installsuffix cgo -o /server main.go

# Final stage
FROM quay.io/pypa/manylinux_2_28_x86_64:latest

# Install runtime dependencies
RUN dnf install -y \
    curl \
    ca-certificates \
    fontconfig \
    libcurl \
    libicu \
    freetype \
    harfbuzz \
    cairo \
    libjpeg-turbo \
    libpng \
    libwebp \
    && dnf clean all

# Copy plutobook binaries from builder stage
COPY --from=builder /usr/bin/html2pdf /usr/bin/html2pdf
COPY --from=builder /usr/bin/html2png /usr/bin/html2png

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