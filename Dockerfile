FROM php:8.2-apache

# Install system dependencies, ClamAV and Tesseract OCR with Portuguese support
RUN apt-get update && apt-get install -y \
    clamav \
    tesseract-ocr \
    tesseract-ocr-por \
    && rm -rf /var/lib/apt/lists/*

# Initialize ClamAV database (ignoring failure if offline so build doesn't break)
RUN freshclam || true

# Install PDO MySQL extension
RUN docker-php-ext-install pdo_mysql

# Enable Apache rewrite and headers modules
RUN a2enmod rewrite headers

# Configure PHP settings for JusTraduz
RUN echo "file_uploads = On\n\
memory_limit = 256M\n\
upload_max_filesize = 64M\n\
post_max_size = 64M\n\
max_execution_time = 600\n\
date.timezone = America/Sao_Paulo\n\
" > /usr/local/etc/php/conf.d/justraduz-limits.ini

# Set directory permissions for web server
WORKDIR /var/www/html/JusTraduz

EXPOSE 80
