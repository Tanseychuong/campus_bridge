# The web server for CampusBridge: Apache + PHP 8.2 (the same PHP version as AMPPS).
FROM php:8.2-apache

# PHP extensions: pdo_mysql talks to MySQL; zip lets the resource scanner read Word/PowerPoint/Excel files.
RUN apt-get update \
 && apt-get install -y --no-install-recommends libzip-dev \
 && docker-php-ext-install pdo_mysql zip \
 && rm -rf /var/lib/apt/lists/*

# Serve the public/ folder only. src/, templates/ and database/ stay private.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
 && echo 'ServerName localhost' >> /etc/apache2/apache2.conf \
 && echo 'ServerTokens Prod' >> /etc/apache2/apache2.conf \
 && echo 'ServerSignature Off' >> /etc/apache2/apache2.conf \
 && echo 'ErrorDocument 404 /not-found.php' >> /etc/apache2/apache2.conf

# Hide the PHP version from response headers, and allow resource uploads up to 10 MB.
RUN printf 'expose_php = Off\nupload_max_filesize = 10M\npost_max_size = 12M\n' > /usr/local/etc/php/conf.d/campusbridge.ini

COPY . /var/www/html

# Uploaded resources are written here (outside public/), so the web server must be able to write to it.
RUN mkdir -p /var/www/html/storage/uploads && chown -R www-data:www-data /var/www/html/storage
