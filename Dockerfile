FROM php:8.2-fpm

RUN docker-php-ext-install pdo pdo_mysql mysqli

# Install ekstensi GD untuk kompresi gambar
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd

COPY www.conf /usr/local/etc/php-fpm.d/www.conf
