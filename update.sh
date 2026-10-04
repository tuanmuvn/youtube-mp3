#!/bin/bash
# Script cập nhật code mới từ GitHub
set -e
APP_DIR="$HOME/apps/youtube-mp3"

echo "[!] Kéo code mới từ GitHub..."
cd "$APP_DIR" && git pull origin main

echo "[!] Rebuild container..."
docker compose up -d --build

echo "[✓] Cập nhật xong!"
docker compose ps
