FROM php:8.4-cli-alpine

# Расширения для MySQL компилируются из исходников образа — без apk и без сети.
RUN docker-php-ext-install pdo pdo_mysql

WORKDIR /var/www/html
