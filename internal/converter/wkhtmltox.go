package converter

import (
	"bytes"
	"context"
	"fmt"
	"io"
	"os"
	"os/exec"
	"strconv"
	"time"
)

// WkhtmltoxConverter implements Converter interface using wkhtmltox
type WkhtmltoxConverter struct{}

// NewWkhtmltoxConverter creates a new WkhtmltoxConverter
func NewWkhtmltoxConverter() *WkhtmltoxConverter {
	return &WkhtmltoxConverter{}
}

// Convert converts HTML content to the specified format using wkhtmltox
func (w *WkhtmltoxConverter) Convert(ctx context.Context, htmlContent io.Reader, format Format, options Options) ([]byte, error) {
	// Read HTML content
	htmlBytes, err := io.ReadAll(htmlContent)
	if err != nil {
		return nil, fmt.Errorf("failed to read HTML content: %w", err)
	}

	// Create temporary file for HTML content
	tmpFile, err := createTempFile(htmlBytes)
	if err != nil {
		return nil, fmt.Errorf("failed to create temporary file: %w", err)
	}
	defer os.Remove(tmpFile)

	// Convert URL to the specified format
	return w.ConvertURL(ctx, tmpFile, format, options)
}

// ConvertURL converts a URL to the specified format using wkhtmltox
func (w *WkhtmltoxConverter) ConvertURL(ctx context.Context, url string, format Format, options Options) ([]byte, error) {
	// Set default values
	if options.Width <= 0 {
		options.Width = 1280
	}
	if options.Quality <= 0 {
		options.Quality = 100
	}
	if options.Scale <= 0 {
		options.Scale = 100
	}
	if options.Timeout <= 0 {
		options.Timeout = 30
	}

	// Create context with timeout
	timeoutCtx, cancel := context.WithTimeout(ctx, time.Duration(options.Timeout)*time.Second)
	defer cancel()

	var cmd *exec.Cmd
	var args []string

	// Select appropriate command based on format
	switch format {
	case FormatPDF:
		args = []string{
			"--enable-local-file-access",
			"--quiet",
		}
		// Add user options
		args = append(args, url, "-")
		cmd = exec.CommandContext(timeoutCtx, "wkhtmltopdf", args...)
	case FormatPNG, FormatJPG, FormatJPEG:
		args = []string{
			"--enable-local-file-access",
			"--quiet",
			"--quality", strconv.Itoa(options.Quality),
		}
		if options.Width > 0 {
			args = append(args, "--width", strconv.Itoa(options.Width))
		}
		if options.Height > 0 {
			args = append(args, "--height", strconv.Itoa(options.Height))
		}
		// Add scale support
		if options.Scale != 100 {
			args = append(args, "--zoom", strconv.FormatFloat(float64(options.Scale)/100.0, 'f', 2, 64))
		}
		// Add crop support
		if options.Crop {
			args = append(args, "--crop-h", strconv.Itoa(options.Height), "--crop-w", strconv.Itoa(options.Width))
		}
		args = append(args, url, "-")
		cmd = exec.CommandContext(timeoutCtx, "wkhtmltoimage", args...)
	default:
		return nil, fmt.Errorf("unsupported format: %s", format)
	}

	var out bytes.Buffer
	var stderr bytes.Buffer
	cmd.Stdout = &out
	cmd.Stderr = &stderr

	// Execute command
	if err := cmd.Run(); err != nil {
		return nil, fmt.Errorf("failed to execute command: %w, stderr: %s", err, stderr.String())
	}

	return out.Bytes(), nil
}

// Name returns the name of the converter
func (w *WkhtmltoxConverter) Name() string {
	return "wkhtmltox"
}

// createTempFile creates a temporary file with the given content
func createTempFile(content []byte) (string, error) {
	tmpFile, err := os.CreateTemp("", "html2image-*.html")
	if err != nil {
		return "", err
	}
	defer tmpFile.Close()

	_, err = tmpFile.Write(content)
	if err != nil {
		return "", err
	}

	return tmpFile.Name(), nil
}
