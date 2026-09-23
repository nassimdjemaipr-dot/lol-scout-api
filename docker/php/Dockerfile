FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libicu-dev \
    libzip-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install \
    pdo_mysql \
    intl \
    zip \
    opcache

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY docker/php/php.dev.ini /usr/local/etc/php/conf.d/zz-dev.ini

# var/ et vendor/ sont des volumes nommes. Docker les initialise a partir de
# l image, en reprenant le proprietaire : ces dossiers doivent donc appartenir
# a www-data ici, sinon php-fpm ne peut pas y ecrire ses proxys Doctrine.
RUN mkdir -p /var/www/html/var /var/www/html/vendor     && chown -R www-data:www-data /var/www/html/var /var/www/html/vendor

WORKDIR /var/www/html
