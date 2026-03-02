FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    bash \
    curl \
    git \
    libpng-dev \
    libpq-dev \
    libzip-dev \
    libxml2-dev \
    oniguruma-dev \
    openssl-dev \
    postgresql-client \
    supervisor \
    nodejs \
    npm \
    unzip \
    zip \
    icu-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    zlib-dev

# Install PHP extensions using install-php-extensions (much more reliable)
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo \
        pdo_pgsql \
        pgsql \
        redis \
        sockets \
        xml \
        zip

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Create non-root user
RUN addgroup -g 1000 www && adduser -u 1000 -G www -s /bin/bash -D www

# Set permissions
RUN chown -R www:www /var/www/html

USER www

EXPOSE 9000

CMD ["php-fpm"]
