# ===================================================================
# Dockerfile PHP Multi-versión (7.4 → 8.5) para WordPress
# ===================================================================

# Argumento para seleccionar la versión de PHP en tiempo de build
ARG PHP_VERSION=8.2

# Usamos la imagen oficial de WordPress con el formato correcto
FROM wordpress:php${PHP_VERSION}-fpm

LABEL maintainer="tu@email.com"
LABEL description="WordPress PHP-FPM optimizado con WP-CLI"

# ─── 1. Dependencias del sistema ───
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev libavif-dev \
    unzip git curl cron supervisor pkg-config \
    libicu-dev libzip-dev libxml2-dev libcurl4-openssl-dev \
    libmagickwand-dev libonig-dev libreadline-dev libtidy-dev \
    libxslt1-dev libgmp-dev libmemcached-dev zlib1g-dev libssl-dev \
    && rm -rf /var/lib/apt/lists/*

# ─── 2. Compilación de extensiones nativas ───
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp \
    --with-avif \
    && docker-php-ext-install -j$(nproc) \
    gd mysqli pdo_mysql intl zip curl mbstring xml dom soap \
    bcmath exif calendar sockets tidy xsl gmp opcache

# ─── 3. Extensiones PECL (Redis e Imagick) ───
RUN pecl install redis \
    && docker-php-ext-enable redis

# Imagick es OPCIONAL - si falla, WordPress usa GD
RUN set -ex; \
    if pecl install imagick