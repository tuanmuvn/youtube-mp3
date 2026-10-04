#!/bin/bash
# ============================================================
#  Script deploy YouTube MP3 — chạy 1 lần duy nhất trên VPS
#  Dùng: bash deploy.sh
# ============================================================

set -e

REPO_URL="https://github.com/tuanmuvn/youtube-mp3.git"   # <-- Sửa thành repo của bạn
APP_DIR="$HOME/apps/youtube-mp3"
NETWORK="npm_proxy_network"   # <-- Sửa nếu tên network NPM của bạn khác

echo ""
echo "=============================="
echo "  YouTube MP3 — Auto Deploy"
echo "=============================="
echo ""

# 1. Kiểm tra Docker
if ! command -v docker &> /dev/null; then
    echo "[LỖI] Docker chưa được cài. Vui lòng cài Docker trước."
    exit 1
fi
echo "[✓] Docker đã sẵn sàng"

# 2. Tạo network nếu chưa có
if ! docker network ls | grep -q "$NETWORK"; then
    echo "[!] Tạo network '$NETWORK'..."
    docker network create "$NETWORK"
fi
echo "[✓] Network '$NETWORK' OK"

# 3. Clone hoặc cập nhật repo
if [ -d "$APP_DIR/.git" ]; then
    echo "[!] Repo đã tồn tại — đang pull bản mới nhất..."
    cd "$APP_DIR" && git pull origin main
else
    echo "[!] Clone repo từ GitHub..."
    mkdir -p "$APP_DIR"
    git clone --depth=1 "$REPO_URL" "$APP_DIR"
    cd "$APP_DIR"
fi

# 4. Tạo thư mục downloads
mkdir -p "$APP_DIR/downloads"
chmod 775 "$APP_DIR/downloads"
echo "[✓] Thư mục downloads OK"

# 5. Build & chạy container
echo "[!] Build và khởi động container..."
docker compose down 2>/dev/null || true
docker compose up -d --build

# 6. Kiểm tra
sleep 3
if docker ps | grep -q "youtube-mp3"; then
    echo ""
    echo "=============================="
    echo "  [✓] Deploy thành công!"
    echo "  Truy cập: http://$(curl -s ifconfig.me):80"
    echo "  Sau đó cấu hình NPM trỏ domain vào cổng 80"
    echo "=============================="
else
    echo "[LỖI] Container không khởi động được. Xem log:"
    docker compose logs --tail=30
fi
