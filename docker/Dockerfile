FROM php:8.2-apache

# Dependências do sistema + extensões PHP
RUN apt-get update && apt-get install -y \
      libpng-dev libjpeg62-turbo-dev libwebp-dev libonig-dev unzip git curl \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_mysql gd mbstring \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Configurações PHP
RUN { \
      echo 'upload_max_filesize=10M'; \
      echo 'post_max_size=12M'; \
      echo 'memory_limit=128M'; \
      echo 'display_errors=Off'; \
    } > /usr/local/etc/php/conf.d/navalha.ini

# Instala o Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copia composer.json e instala dependências (sem dev, com cache otimizado)
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader 2>/dev/null || \
    composer install --no-dev --no-interaction --prefer-dist

# Apache: aponta DocumentRoot para /var/www/public
RUN sed -i 's|/var/www/html|/var/www/public|g' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's|/var/www/html|/var/www/public|g' /etc/apache2/apache2.conf \
    && echo '<Directory /var/www/public>\n    AllowOverride All\n    Require all granted\n</Directory>' \
       >> /etc/apache2/apache2.conf

WORKDIR /var/www/public
