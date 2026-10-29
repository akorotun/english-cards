
ARG PHP_VERSION=8.4.27
FROM php:${PHP_VERSION}-fpm-bookworm

ARG UID=1000
ARG GID=1000

# System packages and PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    curl \
    libicu-dev \
    libzip-dev \
    libonig-dev \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        intl \
        mbstring \
        pdo_mysql \
        zip \
        pcntl \
        opcache \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Application user with matching host UID/GID
RUN set -eux; \
    if ! getent group "${GID}" > /dev/null; then \
        groupadd -g "${GID}" app; \
    fi; \
    useradd -m -u "${UID}" -g "${GID}" -s /bin/bash app

WORKDIR /var/www/html

USER app

EXPOSE 9000

CMD ["php-fpm"]

