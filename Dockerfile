FROM php:8.4-cli-alpine

RUN apk add --no-cache \
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

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/web

# Copy codebase
COPY . .

# Install Python dependencies for Google News URL decoding in an isolated venv
RUN python3 -m venv /opt/google-news-venv \
    && /opt/google-news-venv/bin/pip install --no-cache-dir -r /var/web/scripts/google-news/requirements.txt

# Run composer installation
RUN composer install --no-interaction --optimize-autoloader

# Expose Laravel development port
EXPOSE 8000

# Start Laravel built-in web server
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
