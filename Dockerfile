FROM php:8.3-apache

# Set environment variables for non-interactive apt-get
ENV DEBIAN_FRONTEND=noninteractive

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Install required system dependencies for PHP extensions and Composer
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev \
        unzip \
        git \
        # Add any other system dependencies your app might need, e'g., libicu-dev for intl
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
# Add 'intl' if your app might deal with internationalization (good practice)
# Add 'gd' if your app handles image manipulation
RUN docker-php-ext-install pdo_mysql zip \
    && docker-php-ext-enable zip # zip is often installed but not enabled by default for older versions

# Install Composer globally
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Set working directory inside the container
# This is where your application's code will reside
WORKDIR /var/www/html

# Copy your application's source code into the container
# IMPORTANT: This assumes your .gitignore correctly excludes 'vendor/'
COPY . /var/www/html

# Install PHP dependencies using Composer
# This command needs to run *after* your source code is copied
RUN composer install --no-dev --optimize-autoloader

# Set Apache's document root to /var/www/html/public
# This ensures that Apache serves files from the 'public' directory
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's|<Directory /var/www/>|<Directory /var/www/html/public>|' /etc/apache2/apache2.conf \
    && a2enmod rewrite # Ensure rewrite module is enabled and loaded again after conf change (good practice)

# Fix permissions for the web server user
# This is crucial for allowing Apache to read and write files (like token.json if used, or logs)
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} + \
    && find /var/www/html -type f -exec chmod 644 {} +

# Expose port 80 to allow external access to the web server
EXPOSE 80

# The default command for php:apache images is to start Apache
# CMD ["apache2-foreground"] # This is typically implicit