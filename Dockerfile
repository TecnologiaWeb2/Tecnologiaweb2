FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql mbstring soap \
    && apt-get purge -y --auto-remove libonig-dev \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite

COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY . /var/www/html/

RUN mkdir -p /var/www/html/datos/fotos \
    && chown -R www-data:www-data /var/www/html/datos

WORKDIR /var/www/html

CMD ["sh", "-c", "php docker/iniciar.php && exec apache2-foreground"]
