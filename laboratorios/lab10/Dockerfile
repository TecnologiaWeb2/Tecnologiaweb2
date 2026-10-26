FROM php:8.4-cli

# Composer desde su imagen oficial
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# La imagen oficial de PHP NO trae pdo_mysql: sin esto, PDO no puede conectar con MySQL
RUN docker-php-ext-install pdo_mysql

WORKDIR /app
COPY . .

# Evita el aviso de Composer al ejecutarse como root dentro del contenedor
ENV COMPOSER_ALLOW_SUPERUSER=1

# Genera vendor/autoload.php (PSR-4 + src/funciones.php)
RUN composer dump-autoload

EXPOSE 8000

# El último argumento (public/index.php) es el ROUTER / front controller
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]
