FROM php:8.4-cli-alpine

# Установка системных зависимостей для MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Папка для кода
WORKDIR /var/www/html