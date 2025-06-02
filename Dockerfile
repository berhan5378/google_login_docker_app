FROM php:8.3-apache

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Install required PHP extensions
RUN apt-get update && apt-get install -y libzip-dev unzip git && docker-php-ext-install zip

# Set working directory
WORKDIR /var/www/html

# Copy the entire project (including vendor/)
COPY . /var/www/html

# Set document root to /public
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|' /etc/apache2/sites-available/000-default.conf

# Fix permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
