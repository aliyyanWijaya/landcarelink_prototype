FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql mysqli

ENV APACHE_DOCUMENT_ROOT=/var/www/html/backend/public

RUN sed -ri \
    -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf

WORKDIR /var/www/html
# Menyalin semua file proyek
COPY . .

# Pastikan rewrite aktif
RUN a2enmod rewrite

# SOLUSI: Paksa matikan MPM lain yang bentrok dan pastikan prefork yang aktif
RUN a2dismod mpm_event mpm_worker || true && a2enmod mpm_prefork

RUN printf '<Directory "%s">\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>\n' "$APACHE_DOCUMENT_ROOT" \
> /etc/apache2/conf-available/docroot-override.conf \
&& a2enconf docroot-override

EXPOSE 80