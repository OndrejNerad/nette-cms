FROM php:8.1-fpm
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libjpeg-dev \
    libpng-dev \
    git \
    unzip \
    && docker-php-ext-configure intl \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install intl mysqli pdo pdo_mysql gd \
    && rm -rf /var/lib/apt/lists/*
RUN usermod -u 1000 www-data
WORKDIR /var/www/html