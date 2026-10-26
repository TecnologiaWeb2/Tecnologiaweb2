FROM php:8.4-cli

# Composer desde su imagen oficial
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# libxml2-dev: la extensión soap la necesita para compilar.
# La imagen oficial NO trae pdo_mysql ni soap: sin ellas fallan MySQL y /soap.
# (dom, xmlwriter, simplexml y libxml ya vienen incluidas en la imagen.)
RUN apt-get update && apt-get install -y --no-install-recommends libxml2-dev \
    && docker-php-ext-install pdo_mysql soap \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . .

ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer dump-autoload

EXPOSE 8000

# El último argumento (public/index.php) es el ROUTER / front controller
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]
