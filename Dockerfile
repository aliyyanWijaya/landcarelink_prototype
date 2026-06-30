FROM php:8.2-apache

# sqlite3 CLI is needed by entrypoint.sh to seed the database on first start.
# unzip is needed by Composer to extract packages.
RUN apt-get update \
 && apt-get install -y --no-install-recommends unzip sqlite3 \
 && rm -rf /var/lib/apt/lists/*

# Enable PDO and the SQLite driver.
RUN docker-php-ext-install pdo pdo_sqlite

# Install Composer from its official image.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# mod_rewrite is required for the .htaccess rewrite rules in backend/public.
RUN a2enmod rewrite

# Point Apache's document root at backend/public.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/backend/public

RUN sed -ri \
    -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf

# Allow .htaccess overrides so RewriteRule directives are honoured.
RUN printf '<Directory "%s">\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
    "${APACHE_DOCUMENT_ROOT}" \
    > /etc/apache2/conf-available/docroot-override.conf \
 && a2enconf docroot-override

WORKDIR /var/www/html

# Copy composer manifests first so dependency installation is a cached layer.
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

# Copy the full application.
COPY . .

# Serve the frontend from the same origin as the API.
# Apache serves static files directly; .htaccess routes everything else to index.php.
RUN cp -r frontend/. backend/public/

# Create the database directory and hand ownership to the Apache user.
# On Render the persistent disk is mounted here, so the image directory only
# matters for local `docker run` usage.
RUN mkdir -p database \
 && chown -R www-data:www-data database

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80
CMD ["/entrypoint.sh"]
