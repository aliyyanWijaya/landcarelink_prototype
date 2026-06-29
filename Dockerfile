FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql mysqli

RUN apache2ctl -V
RUN apache2ctl -M