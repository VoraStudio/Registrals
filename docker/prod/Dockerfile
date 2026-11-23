FROM php:8.3-fpm-alpine

# Dependències de sistema per a MySQL, GD/WebP, PDF (Chromium), Intl i Zip
RUN apk add --no-cache \
    acl \
    bash \
    chromium \
    fcgi \
    file \
    freetype-dev \
    gettext \
    git \
    harfbuzz \
    icu-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libzip-dev \
    nss \
    openssl \
    ttf-freefont

# Extensions PHP requerides per Registrals
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        gd \
        intl \
        opcache \
        pdo_mysql \
        zip

# Composer oficial
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configuració PHP optimitzada per a VPS de 4 GB
RUN echo "memory_limit = 512M" > /usr/local/etc/php/conf.d/memory-limit.ini \
    && echo "upload_max_filesize = 50M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 50M" >> /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/registrals
