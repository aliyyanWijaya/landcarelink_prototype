FROM php:8.2-apache

# install extension database
RUN docker-php-ext-install mysqli pdo pdo_mysql

# set document root ke public folder
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

# copy semua file backend
COPY . /var/www/html/

EXPOSE 80