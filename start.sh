#!/bin/bash
set -e

# Dynamically assigned port by Render (default 10000)
PORT="${PORT:-10000}"

echo "=================================================="
echo "Starting Voting Management System on PORT: $PORT"
echo "=================================================="

# Update Apache port configuration
sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost \*:$PORT>/g" /etc/apache2/sites-available/000-default.conf

# Configure Apache reverse proxy settings
cat << 'EOF' > /etc/apache2/conf-available/render-reverse-proxy.conf
ServerName localhost
UseCanonicalName Off
UseCanonicalPhysicalPort Off

<Directory /var/www/html>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

# Preserve SSL status forwarded by Render Load Balancer
SetEnvIf X-Forwarded-Proto "^https$" HTTPS=on
EOF

a2enconf render-reverse-proxy > /dev/null 2>&1 || true

# Start Apache in the foreground
exec apache2-foreground
