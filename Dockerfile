FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite headers

# Copy project files
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html/ \
    && chmod -R 755 /var/www/html/ \
    && mkdir -p /var/www/html/monitors \
    && chmod 755 /var/www/html/monitors

# ============================================================
# CRITICAL: Add CORS headers directly to Apache config
# ============================================================
RUN echo '<Directory /var/www/html/>' >> /etc/apache2/apache2.conf \
    && echo '    AllowOverride All' >> /etc/apache2/apache2.conf \
    && echo '    Header set Access-Control-Allow-Origin "*"' >> /etc/apache2/apache2.conf \
    && echo '    Header set Access-Control-Allow-Methods "GET, POST, DELETE, OPTIONS"' >> /etc/apache2/apache2.conf \
    && echo '    Header set Access-Control-Allow-Headers "Content-Type, X-PulseCheck-Token"' >> /etc/apache2/apache2.conf \
    && echo '    Header set Access-Control-Max-Age "86400"' >> /etc/apache2/apache2.conf \
    && echo '</Directory>' >> /etc/apache2/apache2.conf

# Ensure OPTIONS requests are handled
RUN echo 'RewriteEngine On' >> /var/www/html/.htaccess \
    && echo 'RewriteCond %{REQUEST_METHOD} OPTIONS' >> /var/www/html/.htaccess \
    && echo 'RewriteRule ^(.*)$ $1 [R=200,L]' >> /var/www/html/.htaccess

EXPOSE 80
CMD ["apache2-foreground"]
