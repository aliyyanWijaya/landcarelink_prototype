FROM php:8.2-apache

# Install system dependencies untuk composer
RUN apt-get update && apt-get install -y \
    unzip \
    curl

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# FIX MPM conflict
RUN a2dismod mpm_event mpm_worker || true
RUN a2enmod mpm_prefork

ENV APACHE_DOCUMENT_ROOT=/var/www/html/backend/public

RUN sed -ri \
    -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf

WORKDIR /var/www/html

# Copy composer dulu (biar cache optimal)
COPY composer.json composer.lock ./

# Install dependency
RUN composer install --no-dev --optimize-autoloader

# Baru copy semua file
COPY . .

RUN a2enmod rewrite

RUN printf '<Directory "%s">\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>\n' "$APACHE_DOCUMENT_ROOT" \
> /etc/apache2/conf-available/docroot-override.conf \
&& a2enconf docroot-override

EXPOSE 80