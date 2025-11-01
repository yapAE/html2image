package main

import (
	"bytes"
	"fmt"
	"io"
	"log"
	"mime/multipart"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"strconv"
	"strings"
)

func main() {
	http.HandleFunc("/screenshot", handleScreenshot)
	log.Println("🚀 Server running on :8080")
	http.ListenAndServe(":8080", nil)
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

	width := parseInt(r.FormValue("width"), 1280)
	height := parseInt(r.FormValue("height"), 0)
	quality := parseInt(r.FormValue("quality"), 100)
	url := r.FormValue("url")

	var inputPath string
	var cleanup bool

	// 支持上传 HTML 文件
	if files, ok := r.MultipartForm.File["file"]; ok && len(files) > 0 {
		file := files[0]
		src, _ := file.Open()
		defer src.Close()
		tmpPath := filepath.Join(os.TempDir(), file.Filename)
		dst, _ := os.Create(tmpPath)
		defer dst.Close()
		io.Copy(dst, src)
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
			os.Remove(inputPath)
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

	if err := cmd.Run(); err != nil {
		log.Printf("❌ wkhtmltoimage error: %v", err)
		log.Printf("stderr: %s", stderr.String())
		http.Error(w, "conversion failed: "+stderr.String(), 500)
		return
	}

	data := out.Bytes()
	log.Printf("✅ Output size: %d bytes", len(data))
	if len(data) < 100 {
		log.Printf("⚠️ Suspiciously small output, stderr: %s", stderr.String())
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
