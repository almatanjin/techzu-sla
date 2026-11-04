# Development image for the Laravel API, queue worker and scheduler.
FROM php:8.5-fpm

ARG UID=1000
ARG GID=1000

# PHP extension installer resolves system libraries for each extension.
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip default-mysql-client \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions pdo_mysql intl zip bcmath pcntl opcache gd

COPY php.ini /usr/local/etc/php/conf.d/zz-app.ini

# Run as an unprivileged user whose ids match the host user on Linux,
# so files created in bind mounts are not owned by root.
RUN groupmod -o -g "${GID}" www-data \
    && usermod -o -u "${UID}" -g "${GID}" www-data \
    && mkdir -p /var/www/html /var/www/.composer \
    && chown -R www-data:www-data /var/www

ENV COMPOSER_HOME=/var/www/.composer

WORKDIR /var/www/html
USER www-data

EXPOSE 9000
CMD ["php-fpm"]
