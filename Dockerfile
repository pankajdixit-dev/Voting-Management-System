FROM php:8.2-apache

# Install required system packages and PHP extension build dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd mysqli pdo_mysql zip \
    && a2enmod rewrite headers \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Global Apache reverse proxy configuration to prevent :10000 port leak in redirects
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && echo "UseCanonicalName Off" >> /etc/apache2/apache2.conf \
    && echo "UseCanonicalPhysicalPort Off" >> /etc/apache2/apache2.conf

# Enable AllowOverride All for .htaccess
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Set working directory
WORKDIR /var/www/html

# Copy all application files
COPY . /var/www/html/

# Make startup script executable and configure file permissions
RUN chmod +x /var/www/html/start.sh \
    && chown -R www-data:www-data /var/www/html

# Default port exposed by Render
EXPOSE 10000

# Start via startup script
CMD ["/var/www/html/start.sh"]
