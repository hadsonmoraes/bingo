FROM php:8.4-apache

# Composer dentro da imagem para `docker compose exec app composer ...`
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Extensões: zip (composer), gd (geração de PDF com imagens embutidas —
# dompdf exige GD para processar artes PNG/JPEG importadas)
RUN apt-get update && apt-get install -y --no-install-recommends libzip-dev libpng-dev libjpeg-dev libfreetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install zip gd \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers

# Document root -> Laravel public/
RUN sed -ri 's!DocumentRoot /var/www/html!DocumentRoot /var/www/html/public!g' \
    /etc/apache2/sites-available/000-default.conf && \
    sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' \
    /etc/apache2/apache2.conf

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
