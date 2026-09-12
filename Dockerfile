FROM php:8.4-cli-alpine

RUN apk add --no-cache \
    tzdata \
    sqlite-dev \
    postgresql-dev \
    postgresql-client \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl \
    python3 \
    py3-pip \
    py3-virtualenv \
    oniguruma-dev \
    libzip-dev \
    ca-certificates \
    && update-ca-certificates \
    && echo "curl.cainfo=/etc/ssl/certs/ca-certificates.crt" >> /usr/local/etc/php/conf.d/docker-php-ext-ca.ini \
    && echo "openssl.cafile=/etc/ssl/certs/ca-certificates.crt" >> /usr/local/etc/php/conf.d/docker-php-ext-ca.ini

RUN docker-php-ext-install pdo pdo_sqlite pdo_pgsql pgsql mbstring gd xml pcntl zip

# Configure PHP settings (file upload limits & memory)
RUN echo "upload_max_filesize = 100M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 100M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/uploads.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/web

# Copy codebase
COPY . .

RUN chmod +x /var/web/scripts/docker-entrypoint.sh

# Install Python dependencies for Google News URL decoding in an isolated venv
RUN python3 -m venv /opt/google-news-venv \
    && /opt/google-news-venv/bin/pip install --no-cache-dir -r /var/web/scripts/google-news/requirements.txt

# Vendor already installed locally — skip composer download inside Docker
# RUN COMPOSER_PROCESS_TIMEOUT=600 composer install --no-interaction --optimize-autoloader --no-scripts --prefer-dist --no-dev

# Expose Laravel development port
EXPOSE 8000

ENTRYPOINT ["sh", "/var/web/scripts/docker-entrypoint.sh"]

# Start Laravel built-in web server
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
