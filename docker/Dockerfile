# The web server for CampusBridge: Apache + PHP 8.2 (the same PHP version as AMPPS).
FROM php:8.2-apache

# PHP extension for talking to MySQL through PDO.
RUN docker-php-ext-install pdo_mysql

# Serve the public/ folder only. src/, templates/ and database/ stay private.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
 && echo 'ServerName localhost' >> /etc/apache2/apache2.conf \
 && echo 'ServerTokens Prod' >> /etc/apache2/apache2.conf \
 && echo 'ServerSignature Off' >> /etc/apache2/apache2.conf \
 && echo 'ErrorDocument 404 /not-found.php' >> /etc/apache2/apache2.conf

# Hide the PHP version from response headers.
RUN echo 'expose_php = Off' > /usr/local/etc/php/conf.d/campusbridge.ini

COPY . /var/www/html
