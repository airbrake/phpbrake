FROM php:7.2-cli
RUN apt-get update && apt-get install -y --no-install-recommends git libzip-dev
RUN docker-php-ext-install zip
COPY --from=composer/composer:latest-bin /composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN mkdir /phpbrake
WORKDIR /phpbrake
COPY composer.json .
RUN composer install
COPY . .
CMD ["./vendor/bin/phpunit"]