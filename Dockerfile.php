# ===================================================================
# Dockerfile PHP Multi-versión (7.4 → 8.4) para WordPress
# ===================================================================

ARG PHP_VERSION=8.2

FROM wordpress:php${PHP_VERSION}-fpm

LABEL maintainer="tu@email.com"
LABEL description="WordPress PHP-FPM optimizado con WP-CLI"

# ─── 1. Dependencias del sistema ───
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev \
    unzip git curl cron supervisor pkg-config \
    libicu-dev libzip-dev libxml2-dev libcurl4-openssl-dev \
    libonig-dev libreadline-dev libtidy-dev \
    libxslt1-dev libgmp-dev libmemcached-dev zlib1g-dev libssl-dev \
    && rm -rf /var/lib/apt/lists/*

# ─── 2. Extensiones nativas de PHP ───
# Configuramos GD con soporte para formatos modernos
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp \
    && docker-php-ext-install -j$(nproc) \
    gd mysqli pdo_mysql intl zip curl mbstring xml dom soap \
    bcmath exif calendar sockets tidy xsl gmp opcache

# ─── 3. Redis (PECL) ───
RUN pecl install redis \
    && docker-php-ext-enable redis

# ─── 4. WP-CLI ───
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp \
    && wp --allow-root --version

# ─── 5. Cron para WP-Cron ───
RUN echo "* * * * * www-data /usr/local/bin/php /var/www/html/wp-cron.php > /dev/null 2>&1" \
    | crontab -u www-data -

# ─── 6. Copia de configuraciones ───
COPY config/php/php.ini /usr/local/etc/php/php.ini
COPY config/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY config/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# ─── 7. Permisos ───
RUN mkdir -p /var/www/html \
    && chown -R www-data:www-data /var/www/html

USER www-data
WORKDIR /var/www/html

EXPOSE 9000

CMD ["php-fpm"]