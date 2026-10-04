<div align="center">

# 🎵 YouTube MP3 Downloader

**Công cụ tải nhạc YouTube sang MP3 — tự host, miễn phí, không giới hạn**

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=flat-square&logo=php&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-ready-2496ED?style=flat-square&logo=docker&logoColor=white)
![yt-dlp](https://img.shields.io/badge/yt--dlp-latest-FF0000?style=flat-square&logo=youtube&logoColor=white)
![License](https://img.shields.io/badge/License-Personal%20Use%20Only-red?style=flat-square)

</div>

---

## 📌 Giới thiệu

**YouTube MP3 Downloader** là công cụ web tự host cho phép chuyển đổi và tải nhạc từ YouTube sang định dạng MP3 chất lượng cao (320kbps) trực tiếp trên server của bạn.

### ✨ Tính năng

- 🔍 **Xem trước thông tin video** — thumbnail, tên bài, kênh, lượt xem, thời lượng
- ⬇️ **Tải MP3 chất lượng cao** — 320kbps, convert tự động qua ffmpeg
- 📝 **Tên file thông minh** — tự động chuyển tên bài sang dạng không dấu có dấu gạch nối
- 🗑️ **Tự dọn dẹp** — file MP3 tự xóa sau 1 giờ, tiết kiệm dung lượng
- 🌐 **Hỗ trợ** YouTube và YouTube Shorts
- 🎨 **Giao diện tối** — responsive, hiển thị tốt trên mobile

### 🛠️ Công nghệ sử dụng

| Thành phần | Vai trò |
|---|---|
| PHP 8.2 + Apache | Backend xử lý request |
| [yt-dlp](https://github.com/yt-dlp/yt-dlp) | Tải video từ YouTube |
| ffmpeg | Convert audio sang MP3 |
| Docker | Đóng gói và triển khai |
| Nginx Proxy Manager | Reverse proxy + SSL |

---

## ⚙️ Yêu cầu hệ thống

- VPS Linux (Ubuntu 20.04+ / Debian 11+)
- Docker Engine 20.10+
- Docker Compose v2+
- Nginx Proxy Manager (đang chạy)
- Domain trỏ về IP VPS (nếu muốn dùng HTTPS)

---

## 🚀 Cài đặt

### Bước 1 — Clone repo

```bash
git clone https://github.com/tuanmuvn/youtube-mp3.git ~/apps/youtube-mp3
cd ~/apps/youtube-mp3
```

### Bước 2 — Tạo thư mục downloads

```bash
mkdir -p downloads
chmod 777 downloads
```

### Bước 3 — Kiểm tra tên network của Nginx Proxy Manager

```bash
docker network ls
```

Tìm network liên quan đến NPM (thường là `npm_proxy_network`). Nếu khác, sửa trong `docker-compose.yml`:

```yaml
networks:
  npm_proxy_network:   # ← Sửa thành tên đúng
    external: true
```

### Bước 4 — Build và chạy

```bash
docker compose up -d --build
```

Lần đầu build sẽ mất 3–5 phút do cài ffmpeg và yt-dlp.

### Bước 5 — Kiểm tra container

```bash
docker compose ps
docker compose logs -f
```

Container chạy thành công khi thấy:
```
apache2 -D FOREGROUND
```

### Bước 6 — Cấu hình Nginx Proxy Manager

Vào giao diện NPM (`http://IP:81`) → **Proxy Hosts** → **Add Proxy Host**:

| Trường | Giá trị |
|---|---|
| Domain Names | `mp3.yourdomain.com` |
| Scheme | `http` |
| Forward Hostname | `youtube-mp3` |
| Forward Port | `80` |

Sang tab **SSL** → **Request a new SSL Certificate** → bật **Force SSL** → **Save**.

### Bước 7 — Trỏ DNS

Vào nơi quản lý domain, thêm record:

```
Type : A
Name : mp3
Value: <IP VPS>
TTL  : 300
```

Sau vài phút truy cập `https://mp3.yourdomain.com` là xong. 🎉

---

## 🔄 Cập nhật

Khi có code mới, cập nhật bằng 2 lệnh:

```bash
cd ~/apps/youtube-mp3
git pull origin main
docker compose up -d --build
```

> Container sẽ tự rebuild với code mới. Downtime khoảng 30–60 giây.

---

## 🔧 Bảo trì

### Cập nhật yt-dlp (quan trọng!)

YouTube thường xuyên thay đổi, yt-dlp cần cập nhật định kỳ (1–2 tuần/lần):

```bash
docker exec youtube-mp3 yt-dlp -U
```

Hoặc rebuild container để cài bản mới nhất:

```bash
docker compose up -d --build
```

### Xem log realtime

```bash
docker compose logs -f
```

### Xem log Apache

```bash
docker exec youtube-mp3 tail -f /var/log/apache2/error.log
```

### Dọn dẹp file MP3 thủ công

```bash
docker exec youtube-mp3 find /var/www/html/downloads -name "*.mp3" -delete
echo "Đã xóa toàn bộ file MP3"
```

### Kiểm tra dung lượng thư mục downloads

```bash
docker exec youtube-mp3 du -sh /var/www/html/downloads
```

### Restart container

```bash
docker compose restart
```

### Dừng và xóa container

```bash
docker compose down
```

### Xem tài nguyên container đang dùng

```bash
docker stats youtube-mp3
```

---

## 🐛 Xử lý lỗi thường gặp

| Lỗi | Nguyên nhân | Cách fix |
|---|---|---|
| `502 Bad Gateway` | Sai tên network NPM | Kiểm tra `docker network ls`, sửa `docker-compose.yml` |
| `Permission denied` | Thư mục downloads sai quyền | `docker exec youtube-mp3 chmod 777 /var/www/html/downloads` |
| `Không lấy được thông tin video` | yt-dlp cũ hoặc YouTube thay đổi | `docker exec youtube-mp3 yt-dlp -U` |
| `Lỗi khi tải xuống` | ffmpeg chưa cài hoặc lỗi convert | `docker compose logs -f` để xem chi tiết |
| Container không start | Port 8050 bị chiếm | `ss -tlnp | grep 8050` rồi đổi port trong compose |

---

## 📁 Cấu trúc thư mục

```
youtube-mp3/
├── Dockerfile            # Image PHP 8.2 + ffmpeg + yt-dlp
├── docker-compose.yml    # Cấu hình service và network
├── .htaccess             # Cấu hình Apache rewrite
├── youtube-mp3.php       # Backend xử lý API
├── youtube-mp3.html      # Giao diện frontend
├── deploy.sh             # Script deploy tự động
├── update.sh             # Script cập nhật nhanh
├── downloads/            # Thư mục lưu file MP3 tạm (tự tạo)
└── README.md             # File này
```

---

## ⚖️ Điều khoản sử dụng

> ⚠️ **Chỉ dành cho mục đích cá nhân, phi thương mại.**

- ✅ Nghe nhạc offline cho cá nhân
- ✅ Lưu trữ bản sao để nghe khi không có mạng
- ❌ **Nghiêm cấm** phân phối lại, bán, kiếm tiền từ nội dung tải xuống
- ❌ **Nghiêm cấm** sử dụng cho mục đích thương mại dưới mọi hình thức

Mọi nội dung tải xuống thuộc bản quyền của tác giả và nhà phân phối gốc. Người dùng hoàn toàn chịu trách nhiệm pháp lý nếu vi phạm bản quyền hoặc [Điều khoản dịch vụ của YouTube](https://www.youtube.com/t/terms).

---

<div align="center">

Made with ❤️ · Chỉ dùng cho mục đích cá nhân

</div>
