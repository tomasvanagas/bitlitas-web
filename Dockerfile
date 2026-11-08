FROM php:8.4.27-apache

# Install system dependencies: libpng for gd, unzip for composer
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions; PDO and mbstring come with the image
RUN docker-php-ext-install pdo_mysql gd

# php.ini is PHP's production file: errors go to Apache's error log, never
# into a page. It leaves expose_php on, which would put X-Powered-By with
# the exact version on every response, so that goes off
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && sed -i 's/^expose_php = On$/expose_php = Off/' "$PHP_INI_DIR/php.ini"

# Session upload progress off: PHP's own mitigation of CVE-2026-115560,
# which no release fixes yet (8.4.27 does not). The site uploads nothing,
# so nothing depends on the feature
RUN echo "session.upload_progress.enabled = 0" > "$PHP_INI_DIR/conf.d/zz-session-upload-progress.ini"

# Apache: mod_rewrite for the front controller, mod_remoteip for the
# visitor's address behind the endpoint, the site on port 8080
RUN a2enmod rewrite remoteip
COPY apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY apache/ports.conf /etc/apache2/ports.conf

# Get Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application
COPY . .

# Install composer dependencies; its scripts run Laravel's package
# discovery, which writes the package manifest to bootstrap/cache
RUN mkdir -p bootstrap/cache && composer install --no-dev --optimize-autoloader --no-interaction

# Set up Laravel. The .env holds no key: APP_KEY comes from the stack, so
# the image carries no secret. package:discover boots the app with the
# packages composer found, which writes the services manifest to
# bootstrap/cache: the read-only container could not write it
RUN cp .env.example .env 2>/dev/null || ( \
        echo "APP_NAME=Bitlitas" > .env && \
        echo "APP_ENV=production" >> .env && \
        echo "APP_URL=https://bitlitas.lt" >> .env && \
        echo "DB_CONNECTION=mysql" >> .env && \
        echo "DB_HOST=\${DB_HOST}" >> .env && \
        echo "DB_DATABASE=\${DB_DATABASE}" >> .env && \
        echo "DB_USERNAME=\${DB_USERNAME}" >> .env && \
        echo "DB_PASSWORD=\${DB_PASSWORD}" >> .env \
    ) \
    && mkdir -p bootstrap/cache storage/framework/sessions storage/framework/views storage/framework/cache \
    && php artisan package:discover \
    && test -f bootstrap/cache/services.php \
    && chmod -R 777 storage bootstrap/cache

EXPOSE 8080

# Run as user 1000, as docker-compose.yml runs the container
USER 1000:1000
