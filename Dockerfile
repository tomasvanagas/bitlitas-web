FROM php:8.4.27-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    libpng-dev \
    oniguruma-dev

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mbstring gd

# Session upload progress off: PHP's own mitigation of CVE-2026-115560,
# which no release fixes yet (8.4.27 does not). The site uploads nothing,
# so nothing depends on the feature
RUN echo "session.upload_progress.enabled = 0" > "$PHP_INI_DIR/conf.d/zz-session-upload-progress.ini"

# The pool runs as whoever starts php-fpm, user 1000, so it names no user
# or group: a php-fpm that is not root would only warn about them
RUN sed -i -e '/^user = /d' -e '/^group = /d' /usr/local/etc/php-fpm.d/www.conf \
    && ! grep -qE '^(user|group) = ' /usr/local/etc/php-fpm.d/www.conf

# Get Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application
COPY . .

# Install composer dependencies; its scripts run Laravel's package
# discovery, which writes the package manifest to bootstrap/cache
RUN mkdir -p bootstrap/cache && composer install --no-dev --optimize-autoloader --no-interaction

# Set up Laravel. key:generate boots the app, which writes the services
# manifest to bootstrap/cache: the read-only container could not write it
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
    && mkdir -p bootstrap/cache storage/framework/sessions storage/framework/views storage/framework/cache \
    && php artisan key:generate --force \
    && test -f bootstrap/cache/services.php \
    && chmod -R 777 storage bootstrap/cache

# nginx as user 1000: no user to switch to, and its logs to the
# container's output; its pid file and temporary files go to /tmp
RUN sed -i -e '/^user nginx;$/d' \
           -e 's|^error_log /var/log/nginx/error.log warn;$|error_log /dev/stderr warn;|' \
           -e 's|access_log /var/log/nginx/access.log main;$|access_log /dev/stdout main;|' \
           /etc/nginx/nginx.conf \
    && ! grep -qE '^user |/var/log/nginx' /etc/nginx/nginx.conf

# Nginx config
RUN echo 'client_body_temp_path /tmp/nginx-client-body; \
fastcgi_temp_path /tmp/nginx-fastcgi; \
proxy_temp_path /tmp/nginx-proxy; \
scgi_temp_path /tmp/nginx-scgi; \
uwsgi_temp_path /tmp/nginx-uwsgi; \
server { \
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

# Supervisor config: its own log and both servers' output go to the
# container's output, its pid file to /tmp
RUN mkdir -p /etc/supervisor/conf.d && printf '%s\n' \
        '[supervisord]' \
        'nodaemon=true' \
        'logfile=/dev/null' \
        'logfile_maxbytes=0' \
        'pidfile=/tmp/supervisord.pid' \
        '[program:php-fpm]' \
        'command=php-fpm -F' \
        'autorestart=true' \
        'stdout_logfile=/dev/stdout' \
        'stdout_logfile_maxbytes=0' \
        'stderr_logfile=/dev/stderr' \
        'stderr_logfile_maxbytes=0' \
        '[program:nginx]' \
        'command=nginx -e /dev/stderr -g "daemon off; pid /tmp/nginx.pid;"' \
        'autorestart=true' \
        'stdout_logfile=/dev/stdout' \
        'stdout_logfile_maxbytes=0' \
        'stderr_logfile=/dev/stderr' \
        'stderr_logfile_maxbytes=0' \
        > /etc/supervisor/conf.d/supervisord.conf

EXPOSE 80

# Run as user 1000, as docker-compose.yml runs the container
USER 1000:1000

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
