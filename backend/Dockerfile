FROM php:8.4-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libicu-dev \
    default-mysql-client \
    nginx \
    supervisor \
    wget

# Install sqldef
RUN wget -O sqldef.tar.gz https://github.com/k0kubun/sqldef/releases/latest/download/mysqldef_linux_arm64.tar.gz \
    && tar -xzf sqldef.tar.gz \
    && mv mysqldef /usr/local/bin/ \
    && chmod +x /usr/local/bin/mysqldef \
    && rm sqldef.tar.gz

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Redis extension dependencies
RUN apt-get update && apt-get install -y \
    libhiredis-dev

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Xdebug for code coverage
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files first
COPY . .

# Install PHP dependencies with error handling
RUN composer install --optimize-autoloader --no-interaction --ignore-platform-reqs || \
    composer install --optimize-autoloader --no-interaction --no-dev --ignore-platform-reqs

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Copy Nginx configuration
COPY docker/nginx.conf /etc/nginx/sites-available/default
RUN rm /etc/nginx/sites-enabled/default && ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/

# Copy supervisor configuration
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Configure PHP-FPM to capture worker output
RUN echo "catch_workers_output = yes" >> /usr/local/etc/php-fpm.d/www.conf && \
    echo "decorate_workers_output = no" >> /usr/local/etc/php-fpm.d/www.conf

# Create log directories
RUN mkdir -p /var/log/supervisor

# Expose port 80
EXPOSE 80

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]