package plutobook

import (
	"bytes"
	"fmt"
	"os/exec"
)

// PlutoBookCLIConverter 使用命令行形式的 plutobook 工具
type PlutoBookCLIConverter struct {
	BinaryPath string
}

// ConvertHTMLToPDF 将 HTML 转换为 PDF
func (p *PlutoBookCLIConverter) ConvertHTMLToPDF(htmlContent string) ([]byte, error) {
	// 创建命令
	// 假设 plutobook 提供命令行工具，类似：plutobook --format pdf --output - input.html
	cmd := exec.Command(p.BinaryPath, "--format", "pdf", "--output", "-", "--input-html", htmlContent)

	var out bytes.Buffer
	var stderr bytes.Buffer
	cmd.Stdout = &out
	cmd.Stderr = &stderr

	// 执行命令
	err := cmd.Run()
	if err != nil {
		return nil, fmt.Errorf("failed to execute plutobook: %v, stderr: %s", err, stderr.String())
	}

	return out.Bytes(), nil
}

// ExampleUsage 示例用法
func ExampleUsage() {
	converter := &PlutoBookCLIConverter{
		BinaryPath: "/usr/local/bin/plutobook",
	}
	
	// 示例 HTML 内容
	htmlContent := `
	<!DOCTYPE html>
	<html>
	<head>
		<meta charset="UTF-8">
		<title>Test</title>
	</head>
	<body>
		<h1>Hello, PlutoBook!</h1>
		<p>This is a test document.</p>
	</body>
	</html>
	`

	// 转换 HTML 到 PDF
	pdfData, err := converter.ConvertHTMLToPDF(htmlContent)
	if err != nil {
		fmt.Printf("Error converting HTML to PDF: %v\n", err)
		return
	}

	fmt.Printf("Generated PDF size: %d bytes\n", len(pdfData))
}