FROM php:8.2-apache

RUN apt-get update && apt-get install -y libpng-dev libjpeg62-turbo-dev libwebp-dev libonig-dev unzip git \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_mysql gd mbstring \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

RUN { echo 'upload_max_filesize=10M'; echo 'post_max_size=12M'; echo 'memory_limit=128M'; echo 'display_errors=Off'; } > /usr/local/etc/php/conf.d/navalha.ini
WORKDIR /var/www/html
