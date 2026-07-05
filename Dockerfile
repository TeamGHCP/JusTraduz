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

# Copy application files into the web root
COPY . /var/www/html

# Enable Apache modules required by JusTraduz and keep only one MPM active
RUN rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf \
    /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf \
    && a2enmod mpm_prefork rewrite headers

# Configure PHP settings for JusTraduz
RUN echo "file_uploads = On\n\
memory_limit = 256M\n\
upload_max_filesize = 64M\n\
post_max_size = 64M\n\
max_execution_time = 600\n\
date.timezone = America/Sao_Paulo\n\
" > /usr/local/etc/php/conf.d/justraduz-limits.ini

# Set directory permissions for web server
WORKDIR /var/www/html

RUN chown -R www-data:www-data /var/www/html/backend/storage /var/www/html/storage-private || true

RUN printf '%s\n' \
    '#!/bin/sh' \
    'set -e' \
    ': "${PORT:=80}"' \
    ': "${APP_BASE_PATH:=}"' \
    'export APP_BASE_PATH' \
    'rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf' \
    'rm -f /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf' \
    'sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf' \
    'sed -i "s/<VirtualHost \*:[0-9][0-9]*/<VirtualHost *:${PORT}/" /etc/apache2/sites-available/000-default.conf' \
    'sed -i "s#DocumentRoot .*#DocumentRoot /var/www/html/frontend#" /etc/apache2/sites-available/000-default.conf' \
    'cat > /etc/apache2/conf-available/justraduz-root.conf <<EOF' \
    'DirectoryIndex index.php index.html' \
    'ServerName localhost' \
    'Alias /favicon.ico /var/www/html/frontend/assets/img/icon.ico' \
    'Alias /frontend /var/www/html/frontend' \
    'Alias /backend/public /var/www/html/backend/public' \
    'Alias /frontend /var/www/html/frontend' \
    'AliasMatch ^/JusTraduz/frontend/(.*)$ /var/www/html/frontend/$1' \
    'AliasMatch ^/JusTraduz/backend/public/(.*)$ /var/www/html/backend/public/$1' \
    '<Directory /var/www/html/frontend>' \
    '    Options -Indexes' \
    '    DirectorySlash Off' \
    '    AllowOverride All' \
    '    Require all granted' \
    '</Directory>' \
    '<Directory /var/www/html/backend/public>' \
    '    Options -Indexes' \
    '    AllowOverride None' \
    '    Require all granted' \
    '</Directory>' \
    'RewriteEngine On' \
    'RewriteRule ^/?$ /index.php [L]' \
    'RewriteRule ^/blog/?$ /blog/index.php [L]' \
    'RewriteRule ^blog/?$ /blog/index.php [L]' \
    'RewriteCond /var/www/html/frontend%{REQUEST_URI}.php -f' \
    'RewriteRule ^/(.*?)/?$ /$1.php [L]' \
    'RewriteCond /var/www/html/frontend/$1.php -f' \
    'RewriteRule ^(.*?)/?$ /$1.php [L]' \
    'RewriteCond /var/www/html/frontend%{REQUEST_URI}.html -f' \
    'RewriteRule ^/(.*?)/?$ /$1.html [L]' \
    'RewriteCond /var/www/html/frontend/$1.html -f' \
    'RewriteRule ^(.*?)/?$ /$1.html [L]' \
    'EOF' \
    'a2enconf justraduz-root >/dev/null' \
    'exec apache2-foreground' \
    > /usr/local/bin/justraduz-start \
    && chmod +x /usr/local/bin/justraduz-start

EXPOSE 80

CMD ["justraduz-start"]
