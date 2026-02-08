# PHP + Apache Basisimage
FROM php:8.3-apache

# Arbeitsverzeichnis setzen
WORKDIR /var/www/html

# Systemabhängigkeiten installieren
RUN apt-get update && apt-get install -y unzip git

# Composer installieren
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Composer Abhängigkeiten installieren
COPY composer.json ./
RUN composer install

# Projektdateien kopieren
COPY . /var/www/html

# Rechte setzen
RUN chown -R www-data:www-data /var/www/html

# mod_rewrite aktivieren
RUN a2enmod rewrite

# Apache Webroot auf public setzen
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/apache2.conf
