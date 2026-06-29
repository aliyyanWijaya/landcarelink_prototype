FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql mysqli

ENV APACHE_DOCUMENT_ROOT=/var/www/html/backend/public

RUN sed -ri \
    -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf

WORKDIR /var/www/html
COPY . .

RUN a2enmod rewrite

# --- SEKSI PERBAIKAN RADIKAL ---
# Hapus paksa file load mpm_event dan mpm_worker dari mods-enabled agar TIDAK BISA di-load sama sekali
RUN rm -f /etc/apache2/mods-enabled/mpm_event.load \
    && rm -f /etc/apache2/mods-enabled/mpm_worker.load \
    && ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load
# ---------------------------------

RUN printf '<Directory "%s">\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>\n' "$APACHE_DOCUMENT_ROOT" \
> /etc/apache2/conf-available/docroot-override.conf \
&& a2enconf docroot-override

EXPOSE 80