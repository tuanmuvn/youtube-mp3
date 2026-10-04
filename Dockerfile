FROM php:8.2-apache

# [1] Cài packages hệ thống
RUN apt-get update && apt-get install -y \
    ffmpeg \
    python3 \
    python3-pip \
    curl \
    git \
    && rm -rf /var/lib/apt/lists/*

# [2] Cài yt-dlp bản mới nhất
RUN curl -sSL https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp \
    -o /usr/local/bin/yt-dlp \
    && chmod a+rx /usr/local/bin/yt-dlp

# [3] Cấu hình PHP
RUN { \
    echo 'disable_functions ='; \
    echo 'max_execution_time = 300'; \
    echo 'max_input_time = 300'; \
    echo 'upload_max_filesize = 64M'; \
    echo 'post_max_size = 64M'; \
} >> /usr/local/etc/php/php.ini

# [4] Bật Apache mod_rewrite
RUN a2enmod rewrite \
    && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# [5] Copy toàn bộ source vào web root
COPY . /var/www/html/

# [6] Tạo thư mục downloads & cấp quyền
RUN mkdir -p /var/www/html/downloads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod 775 /var/www/html/downloads

EXPOSE 80
