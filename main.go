package main

import (
	"bytes"
	"context"
	"io"
	"log"
	"net/http"
	"os"
	"os/exec"
	"strconv"
	"strings"
	"time"
)

func main() {
	http.HandleFunc("/screenshot", handleScreenshot)
	http.HandleFunc("/health", handleHealth)
	log.Println("🚀 Server running on :8080")
	http.ListenAndServe(":8080", nil)
}

func handleHealth(w http.ResponseWriter, r *http.Request) {
	w.WriteHeader(http.StatusOK)
	w.Write([]byte("OK"))
}

func handleScreenshot(w http.ResponseWriter, r *http.Request) {
	if err := r.ParseMultipartForm(32 << 20); err != nil {
		http.Error(w, "form parse error: "+err.Error(), 400)
		return
	}

	format := strings.ToLower(r.FormValue("format"))
	if format == "" {
		format = "png"
	}

	// 验证format参数
	if format != "png" && format != "jpg" && format != "jpeg" && format != "pdf" {
		http.Error(w, "unsupported format: "+format, 400)
		return
	}

	width := parseInt(r.FormValue("width"), 1280)
	height := parseInt(r.FormValue("height"), 0)
	quality := parseInt(r.FormValue("quality"), 100)
	crop := r.FormValue("crop")
	scale := parseInt(r.FormValue("scale"), 100)

	// 验证参数范围
	if width < 0 || width > 10000 {
		http.Error(w, "width must be between 0 and 10000", 400)
		return
	}
	if height < 0 || height > 10000 {
		http.Error(w, "height must be between 0 and 10000", 400)
		return
	}
	if quality < 1 || quality > 100 {
		http.Error(w, "quality must be between 1 and 100", 400)
		return
	}
	if scale < 1 || scale > 1000 {
		http.Error(w, "scale must be between 1 and 1000", 400)
		return
	}

	url := r.FormValue("url")

	var inputPath string
	var cleanup bool

	// 支持上传 HTML 文件
	if files, ok := r.MultipartForm.File["file"]; ok && len(files) > 0 {
		file := files[0]
		src, err := file.Open()
		if err != nil {
			http.Error(w, "failed to open uploaded file: "+err.Error(), 500)
			return
		}
		defer src.Close()

		// 使用安全的临时文件名
		tmpFile, err := os.CreateTemp("", "html2image-*.html")
		if err != nil {
			http.Error(w, "failed to create temporary file: "+err.Error(), 500)
			return
		}
		defer tmpFile.Close()
		tmpPath := tmpFile.Name()

		_, err = io.Copy(tmpFile, src)
		if err != nil {
			http.Error(w, "failed to save uploaded file: "+err.Error(), 500)
			return
		}
		inputPath = tmpPath
		cleanup = true
	} else if url != "" {
		inputPath = url
	} else {
		http.Error(w, "missing file or url", 400)
		return
	}

	defer func() {
		if cleanup {
			// 确保临时文件被删除
			err := os.Remove(inputPath)
			if err != nil {
				log.Printf("Warning: failed to remove temporary file %s: %v", inputPath, err)
			}
		}
	}()

	var out bytes.Buffer
	var stderr bytes.Buffer
	var cmd *exec.Cmd

	switch format {
	case "png", "jpg", "jpeg":
		args := []string{
			"--enable-local-file-access",
			"--quality", strconv.Itoa(quality),
		}
		if width > 0 {
			args = append(args, "--width", strconv.Itoa(width))
		}
		if height > 0 {
			args = append(args, "--height", strconv.Itoa(height))
		}
		// 添加缩放支持
		if scale != 100 {
			args = append(args, "--zoom", strconv.FormatFloat(float64(scale)/100.0, 'f', 2, 64))
		}
		// 添加裁剪支持
		if crop == "true" || crop == "1" {
			args = append(args, "--crop-h", strconv.Itoa(height), "--crop-w", strconv.Itoa(width))
		}
		args = append(args, inputPath, "-")
		cmd = exec.Command("wkhtmltoimage", args...)
	case "pdf":
		args := []string{
			"--enable-local-file-access",
			inputPath, "-",
		}
		cmd = exec.Command("wkhtmltopdf", args...)
	default:
		http.Error(w, "unsupported format", 400)
		return
	}

	cmd.Stdout = &out
	cmd.Stderr = &stderr

	// 添加超时控制
	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()
	cmd = exec.CommandContext(ctx, cmd.Path, cmd.Args[1:]...)
	cmd.Stdout = &out
	cmd.Stderr = &stderr

	if err := cmd.Run(); err != nil {
		log.Printf("❌ wkhtmltoimage error: %v", err)
		log.Printf("stderr: %s", stderr.String())
		// 检查是否是超时错误
		if ctx.Err() == context.DeadlineExceeded {
			http.Error(w, "conversion timeout", 504)
		} else {
			http.Error(w, "conversion failed: "+stderr.String(), 500)
		}
		return
	}

	data := out.Bytes()
	log.Printf("✅ Output size: %d bytes", len(data))
	
	// 检查输出是否有效
	if len(data) < 100 {
		log.Printf("⚠️ Suspiciously small output, stderr: %s", stderr.String())
		// 检查stderr中是否包含错误信息
		if stderr.Len() > 0 {
			http.Error(w, "conversion failed: "+stderr.String(), 500)
			return
		}
		// 如果没有错误信息但输出仍然很小，也返回错误
		http.Error(w, "conversion failed: output too small, possibly corrupted", 500)
		return
	}

	switch format {
	case "pdf":
		w.Header().Set("Content-Type", "application/pdf")
	case "jpg", "jpeg":
		w.Header().Set("Content-Type", "image/jpeg")
	default:
		w.Header().Set("Content-Type", "image/png")
	}
	w.WriteHeader(200)
	w.Write(data)
}

func parseInt(s string, def int) int {
	if s == "" {
		return def
	}
	v, err := strconv.Atoi(s)
	if err != nil {
		return def
	}
	return v
}
