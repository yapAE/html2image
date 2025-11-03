package main

import (
	"context"
	"log"
	"net/http"
	"strconv"
	"strings"

	"html2image/internal/converter"
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
	crop := r.FormValue("crop") == "true" || r.FormValue("crop") == "1"
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

	// 创建转换器工厂并获取默认转换器
	factory := converter.NewFactory()
	conv := factory.CreateDefaultConverter()

	// 设置转换选项
	options := converter.Options{
		Width:   width,
		Height:  height,
		Quality: quality,
		Scale:   scale,
		Crop:    crop,
		Timeout: 30,
	}

	var data []byte
	var err error

	// 支持上传 HTML 文件
	if files, ok := r.MultipartForm.File["file"]; ok && len(files) > 0 {
		file := files[0]
		src, err := file.Open()
		if err != nil {
			http.Error(w, "failed to open uploaded file: "+err.Error(), 500)
			return
		}
		defer src.Close()

		// 使用转换器转换HTML内容
		data, err = conv.Convert(context.Background(), src, converter.Format(format), options)
		if err != nil {
			http.Error(w, "conversion failed: "+err.Error(), 500)
			return
		}
	} else if url != "" {
		// 使用转换器转换URL
		data, err = conv.ConvertURL(context.Background(), url, converter.Format(format), options)
		if err != nil {
			http.Error(w, "conversion failed: "+err.Error(), 500)
			return
		}
	} else {
		http.Error(w, "missing file or url", 400)
		return
	}

	// 检查输出是否有效
	if len(data) < 100 {
		http.Error(w, "conversion failed: output too small, possibly corrupted", 500)
		return
	}

	log.Printf("✅ Output size: %d bytes", len(data))

	// 设置响应头
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
