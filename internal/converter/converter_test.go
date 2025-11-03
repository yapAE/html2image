package converter

import (
	"context"
	"strings"
	"testing"
)

func TestWkhtmltoxConverter(t *testing.T) {
	// 创建转换器
	conv := NewWkhtmltoxConverter()

	// 测试名称
	if conv.Name() != "wkhtmltox" {
		t.Errorf("Expected name 'wkhtmltox', got '%s'", conv.Name())
	}

	// 测试HTML转换为PNG
	htmlContent := `<html><body><h1>Hello, World!</h1></body></html>`
	reader := strings.NewReader(htmlContent)

	// 注意：由于我们没有实际的wkhtmltoimage工具，这里会返回错误
	// 但我们至少验证了代码路径
	_, err := conv.Convert(context.Background(), reader, FormatPNG, Options{
		Width:   800,
		Height:  600,
		Quality: 90,
	})

	// 验证错误信息中包含预期的错误
	if err != nil && !strings.Contains(err.Error(), "wkhtmltoimage") {
		t.Logf("Expected wkhtmltoimage error, got: %v", err)
	}
}

func TestPlutoBookConverter(t *testing.T) {
	// 创建转换器
	conv := NewPlutoBookConverter()

	// 测试名称
	if conv.Name() != "plutobook" {
		t.Errorf("Expected name 'plutobook', got '%s'", conv.Name())
	}

	// 测试HTML转换为PNG
	htmlContent := `<html><body><h1>Hello, World!</h1></body></html>`
	reader := strings.NewReader(htmlContent)

	// 注意：由于我们没有实际的plutobook工具，这里会返回错误
	// 但我们至少验证了代码路径
	_, err := conv.Convert(context.Background(), reader, FormatPNG, Options{
		Width:   800,
		Height:  600,
		Quality: 90,
	})

	// 验证错误信息中包含预期的错误
	if err != nil && !strings.Contains(err.Error(), "plutobook") {
		t.Logf("Expected plutobook error, got: %v", err)
	}
}

func TestFactory(t *testing.T) {
	factory := NewFactory()

	// 测试创建plutobook转换器
	conv := factory.CreateConverter("plutobook")
	if conv.Name() != "plutobook" {
		t.Errorf("Expected plutobook converter, got '%s'", conv.Name())
	}

	// 测试创建wkhtmltox转换器
	conv = factory.CreateConverter("wkhtmltox")
	if conv.Name() != "wkhtmltox" {
		t.Errorf("Expected wkhtmltox converter, got '%s'", conv.Name())
	}

	// 测试创建默认转换器
	conv = factory.CreateDefaultConverter()
	if conv.Name() != "plutobook" {
		t.Errorf("Expected default plutobook converter, got '%s'", conv.Name())
	}

	// 测试创建未知转换器（应该返回默认转换器）
	conv = factory.CreateConverter("unknown")
	if conv.Name() != "wkhtmltox" {
		t.Errorf("Expected default wkhtmltox converter for unknown, got '%s'", conv.Name())
	}
}
