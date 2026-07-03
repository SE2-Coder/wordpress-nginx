# ===================================================================
# Dockerfile PHP Multi-versión (7.4 → 8.5) para WordPress
# ===================================================================

# Argumento para seleccionar la versión de PHP en tiempo de build
ARG PHP_VERSION=8.2

# Usamos la imagen oficial sin fijar el OS (bullseye/bookworm) 
# para que Docker elija el base correcto según la versión de PHP.
FROM wordpress:php${PHP_VERSION}-fpm

LABEL maintainer="tu@email.com"
LABEL description="WordPress PHP-FPM optimizado con WP-CLI"

# ─── 1. Dependencias del sistema ───
# Instalamos librerías necesarias para compilar las extensiones de PHP.
RUN apt-get update && apt-get install -y --no-install-recommends \
    # Imágenes y fuentes
    libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev libavif-dev \
    # Utilidades y compilación
    unzip git curl cron supervisor pkg-config \
    # Extensiones específicas
    libicu-dev libzip-dev libxml2-dev libcurl4-openssl-dev \
    libmagickwand-dev libonig-dev libreadline-dev libtidy-dev \
    libxslt1-dev libgmp-dev libmemcached-dev zlib1g-dev libssl-dev \
    && rm -rf /var/lib/apt/lists/*

# ─── 2. Compilación de extensiones nativas ───
# Configuramos GD para soportar los formatos de imagen modernos
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp \
    --with-avif \
    && docker-php-ext-install -j$(nproc) \
    gd mysqli pdo_mysql intl zip curl mbstring xml dom soap \
    bcmath exif calendar sockets tidy xsl gmp opcache

# ─── 3. Extensiones PECL (Redis e Imagick) ───
# Instalamos Redis para Object Cache y sesiones.
RUN pecl install redis \
    && docker-php-ext-enable redis

# Instalamos Imagick para manipulación avanzada de imágenes.
# Nota: En PHP 8.4/8.5 a veces requiere forzar la instalación de la versión beta/alpha si falla.
RUN pecl install imagick \
    && docker-php-ext-enable imagick

# ─── 4. WP-CLI (WordPress Command Line Interface) ───
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp \
    && wp --allow-root --version

# ── 5. Configuración de Cron para WP-Cron (Alternativa al pseudo-cron de WP) ───
# Desactivamos el WP-Cron nativo en wp-config.php y usamos el cron del sistema.
RUN echo "* * * * * www-data /usr/local/bin/php /var/www/html/wp-cron.php > /dev/null 2>&1" \
    | crontab -u www-data -

# ─── 6. Copia de configuraciones personalizadas ───
COPY config/php/php.ini /usr/local/etc/php/php.ini
COPY config/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY config/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# ─── 7. Permisos y usuario ───
# Creamos el directorio de WP y asignamos permisos (se montará el volumen después)
RUN mkdir -p /var/www/html \
    && chown -R www-data:www-data /var/www/html

USER www-data
WORKDIR /var/www/html

EXPOSE 9000

CMD ["php-fpm"]