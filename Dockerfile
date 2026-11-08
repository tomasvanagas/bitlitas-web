FROM php:7.4-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    libpng-dev \
    oniguruma-dev

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mbstring gd

# Get Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application
COPY . .

# Install composer dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Set up Laravel
RUN cp .env.example .env 2>/dev/null || ( \
        echo "APP_NAME=Bitlitas" > .env && \
        echo "APP_ENV=production" >> .env && \
        echo "APP_KEY=" >> .env && \
        echo "APP_URL=https://bitlitas.lt" >> .env && \
        echo "DB_CONNECTION=mysql" >> .env && \
        echo "DB_HOST=\${DB_HOST}" >> .env && \
        echo "DB_DATABASE=\${DB_DATABASE}" >> .env && \
        echo "DB_USERNAME=\${DB_USERNAME}" >> .env && \
        echo "DB_PASSWORD=\${DB_PASSWORD}" >> .env \
    ) \
    && mkdir -p bootstrap/cache storage/framework/{sessions,views,cache} \
    && echo "<?php return [];" > bootstrap/cache/packages.php \
    && php artisan key:generate --force \
    && chmod -R 777 storage bootstrap/cache

# Nginx config
RUN echo 'server { \
    listen 80; \
    root /var/www/html/public; \
    index index.php; \
    location / { try_files $uri $uri/ /index.php?$query_string; } \
    location ~ \.php$ { \
        fastcgi_pass 127.0.0.1:9000; \
        fastcgi_index index.php; \
        include fastcgi_params; \
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; \
    } \
}' > /etc/nginx/http.d/default.conf

# Supervisor config
RUN mkdir -p /etc/supervisor/conf.d && \
    echo '[supervisord]' > /etc/supervisor/conf.d/supervisord.conf && \
    echo 'nodaemon=true' >> /etc/supervisor/conf.d/supervisord.conf && \
    echo '[program:php-fpm]' >> /etc/supervisor/conf.d/supervisord.conf && \
    echo 'command=php-fpm -F' >> /etc/supervisor/conf.d/supervisord.conf && \
    echo 'autorestart=true' >> /etc/supervisor/conf.d/supervisord.conf && \
    echo '[program:nginx]' >> /etc/supervisor/conf.d/supervisord.conf && \
    echo 'command=nginx -g "daemon off;"' >> /etc/supervisor/conf.d/supervisord.conf && \
    echo 'autorestart=true' >> /etc/supervisor/conf.d/supervisord.conf

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
