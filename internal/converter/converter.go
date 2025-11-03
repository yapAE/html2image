package converter

import (
	"context"
	"io"
)

// Format represents the output format
type Format string

const (
	FormatPNG  Format = "png"
	FormatJPG  Format = "jpg"
	FormatJPEG Format = "jpeg"
	FormatPDF  Format = "pdf"
)

// Options represents conversion options
type Options struct {
	Width   int
	Height  int
	Quality int
	Scale   int
	Crop    bool
	Timeout int // in seconds
}

// Converter is the interface that wraps the basic Convert method
type Converter interface {
	// Convert converts HTML content to the specified format
	Convert(ctx context.Context, htmlContent io.Reader, format Format, options Options) ([]byte, error)

	// ConvertURL converts a URL to the specified format
	ConvertURL(ctx context.Context, url string, format Format, options Options) ([]byte, error)

	// Name returns the name of the converter
	Name() string
}
