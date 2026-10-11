FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist

FROM php:8.4-apache
COPY apache.conf /etc/apache2/sites-available/000-default.conf
COPY public/ /var/www/html/public/
COPY src/ /var/www/html/src/
COPY --from=vendor /app/vendor/ /var/www/html/vendor/
RUN mkdir -p /var/www/html/var && chown -R www-data:www-data /var/www/html/var
VOLUME /var/www/html/var
EXPOSE 80
