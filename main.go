package main

import (
	"bytes"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"strconv"
	"strings"
)

func main() {
	http.HandleFunc("/screenshot", handleScreenshot)
	fmt.Println("🚀 HTML → PNG/JPG/PDF 服务启动：http://0.0.0.0:8080/screenshot")
	log.Fatal(http.ListenAndServe(":8080", nil))
}

func handleScreenshot(w http.ResponseWriter, r *http.Request) {
	if err := r.ParseMultipartForm(32 << 20); err != nil {
		http.Error(w, "请求解析失败: "+err.Error(), 400)
		return
	}

	format := strings.ToLower(r.FormValue("format"))
	if format == "" {
		format = "png"
	}

	width := parseInt(r.FormValue("width"), 1200)
	height := parseInt(r.FormValue("height"), 0)
	quality := parseInt(r.FormValue("quality"), 100)
	orientation := r.FormValue("orientation")
	if orientation == "" {
		orientation = "portrait"
	}

	var inputPath string
	var cleanup bool

	if file, header, err := r.FormFile("file"); err == nil {
		defer file.Close()
		tmpPath := filepath.Join(os.TempDir(), header.Filename)
		out, _ := os.Create(tmpPath)
		defer out.Close()
		io.Copy(out, file)
		inputPath = tmpPath
		cleanup = true
	} else if url := r.FormValue("url"); url != "" {
		inputPath = url
	} else {
		http.Error(w, "请提供 file 或 url 参数", 400)
		return
	}

	defer func() {
		if cleanup {
			os.Remove(inputPath)
		}
	}()

	var cmd *exec.Cmd
	var args []string
	var out bytes.Buffer

	switch format {
	case "png", "jpg", "jpeg":
		args = []string{"--quality", strconv.Itoa(quality)}
		if width > 0 {
			args = append(args, "--width", strconv.Itoa(width))
		}
		if height > 0 {
			args = append(args, "--height", strconv.Itoa(height))
		}
		args = append(args, inputPath, "-")
		cmd = exec.Command("wkhtmltoimage", args...)
	case "pdf":
		args = []string{"--orientation", orientation}
		if width > 0 {
			args = append(args, "--page-width", fmt.Sprintf("%dmm", width/4))
		}
		if height > 0 {
			args = append(args, "--page-height", fmt.Sprintf("%dmm", height/4))
		}
		args = append(args, inputPath, "-")
		cmd = exec.Command("wkhtmltopdf", args...)
	default:
		http.Error(w, "format 参数必须是 png、jpg 或 pdf", 400)
		return
	}

	cmd.Stdout = &out
	cmd.Stderr = os.Stderr

	if err := cmd.Run(); err != nil {
		http.Error(w, "转换失败: "+err.Error(), 500)
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
	w.Write(out.Bytes())
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
