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

// PlutoBookConverter implements Converter interface using plutobook
type PlutoBookConverter struct{}

// NewPlutoBookConverter creates a new PlutoBookConverter
func NewPlutoBookConverter() *PlutoBookConverter {
	return &PlutoBookConverter{}
}

// Convert converts HTML content to the specified format using plutobook
func (p *PlutoBookConverter) Convert(ctx context.Context, htmlContent io.Reader, format Format, options Options) ([]byte, error) {
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
	return p.ConvertURL(ctx, tmpFile, format, options)
}

// ConvertURL converts a URL to the specified format using plutobook
func (p *PlutoBookConverter) ConvertURL(ctx context.Context, url string, format Format, options Options) ([]byte, error) {
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
			"--format", "pdf",
			"--width", strconv.Itoa(options.Width),
			"--scale", strconv.Itoa(options.Scale),
		}
		// Add user options
		args = append(args, url, "-")
		cmd = exec.CommandContext(timeoutCtx, "plutobook", args...)
	case FormatPNG, FormatJPG, FormatJPEG:
		formatStr := "png"
		if format == FormatJPG || format == FormatJPEG {
			formatStr = "jpeg"
		}

		args = []string{
			"--format", formatStr,
			"--width", strconv.Itoa(options.Width),
			"--quality", strconv.Itoa(options.Quality),
			"--scale", strconv.Itoa(options.Scale),
		}

		if options.Height > 0 {
			args = append(args, "--height", strconv.Itoa(options.Height))
		}

		// Add crop support
		if options.Crop {
			args = append(args, "--crop")
		}

		args = append(args, url, "-")
		cmd = exec.CommandContext(timeoutCtx, "plutobook", args...)
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
func (p *PlutoBookConverter) Name() string {
	return "plutobook"
}
