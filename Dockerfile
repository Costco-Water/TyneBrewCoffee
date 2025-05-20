# Use the official PHP Apache image
FROM php:8.2-apache

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Copy your website into the Apache web root
COPY . /var/www/html/

# Enable Apache rewrite module (optional but helpful)
RUN a2enmod rewrite

# Set permissions (optional)
RUN chown -R www-data:www-data /var/www/html/

# Expose the default HTTP port
EXPOSE 80
