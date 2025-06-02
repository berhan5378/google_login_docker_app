FROM php:8.3-apache

# Enable mod_rewrite
RUN a2enmod rewrite

# Install dependencies
RUN apt-get update && apt-get install -y libzip-dev unzip git && docker-php-ext-install zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html

# Move public files
RUN mkdir -p /var/www/html/public && mv /var/www/html/index.php /var/www/html/public/index.php

# Set document root to public
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|' /etc/apache2/sites-available/000-default.conf

# Permissions
RUN chown -R www-data:www-data /var/www/html

RUN docker-php-ext-install pdo pdo_mysql

EXPOSE 80