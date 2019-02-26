FROM php:7.2-apache

RUN apt-get update && \
        apt-get install -y libmemcached-dev zlib1g-dev && \
        pecl install redis memcached apcu && \
        docker-php-ext-enable redis memcached apcu

COPY "." "/var/www/html/"

EXPOSE 80/tcp
