FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

FROM node:24 AS frontend
WORKDIR /frontend
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build

FROM golang:1.27 AS warden
ARG OPEN_WARDEN_VERSION=v0.7.2
RUN CGO_ENABLED=0 go install -trimpath \
    github.com/esdras-schonevald/open-warden/cmd/open-warden@${OPEN_WARDEN_VERSION}

FROM debian:bookworm-slim AS engine
ARG OPENCODE_VERSION=2.0.29
RUN apt-get update \
    && apt-get install -y --no-install-recommends curl ca-certificates unzip \
    && rm -rf /var/lib/apt/lists/* \
    && curl -fsSL https://opencode.ai/v2/install | bash -s -- \
        --version "${OPENCODE_VERSION}" --no-modify-path

FROM php:8.4-apache
RUN apt-get update \
    && apt-get install -y --no-install-recommends git libpq-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql \
    && a2enmod rewrite \
    && rm -f /var/log/apache2/*.log \
    && touch /var/log/apache2/error.log /var/log/apache2/access.log /var/log/apache2/other_vhosts_access.log \
    && chown -R www-data:www-data /var/log/apache2 \
    && printf 'ServerName localhost\nErrorLog /var/log/apache2/error.log\n' > /etc/apache2/conf-enabled/zz-ensemble.conf

COPY apache.conf /etc/apache2/sites-available/000-default.conf
WORKDIR /var/www/html
COPY --from=vendor /app/vendor/ vendor/
COPY src/ src/
COPY config/ config/
COPY public/ public/
COPY --from=frontend /public/app/ public/app/
COPY bin/ bin/
RUN chmod -R a+x bin/ \
    && mkdir -p var /var/lib/ensemble/workdirs \
    && chown -R www-data:www-data var /var/lib/ensemble
COPY --from=warden /go/bin/open-warden /usr/local/bin/open-warden
COPY --from=engine /root/.opencode/bin/opencode /usr/local/bin/opencode

ENV APP_ENV=production
ENV ENSEMBLE_WORKDIR_ROOT=/var/lib/ensemble/workdirs
ENV ENSEMBLE_RUNNER_HEARTBEAT=/var/www/html/var/runner-heartbeat
VOLUME ["/var/www/html/var", "/var/lib/ensemble/workdirs"]
EXPOSE 80

COPY bin/entrypoint /usr/local/bin/entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint"]
