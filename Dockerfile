FROM php:8.2-fpm

RUN docker-php-ext-install pdo pdo_mysql mysqli

COPY www.conf /usr/local/etc/php-fpm.d/www.conf
