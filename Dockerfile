FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql \
    && { \
      echo 'upload_max_filesize=4M'; \
      echo 'post_max_size=6M'; \
    } > /usr/local/etc/php/conf.d/reserva-upload.ini

COPY . /var/www/html/

RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && { \
      echo 'Alias /admin /var/www/html/admin'; \
      echo 'Alias /assets /var/www/html/assets'; \
      echo '<Directory /var/www/html/admin>'; \
      echo '  Require all granted'; \
      echo '</Directory>'; \
      echo '<Directory /var/www/html/assets>'; \
      echo '  Require all granted'; \
      echo '</Directory>'; \
    } > /etc/apache2/conf-available/reserva-online.conf \
    && a2enconf reserva-online \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80
