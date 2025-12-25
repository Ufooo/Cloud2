#!/bin/bash
set -e

echo "Uninstalling PHP {{ $version }}..."

# Stop PHP-FPM service first
echo "Stopping PHP-FPM service..."
systemctl stop php{{ $version }}-fpm || true
systemctl disable php{{ $version }}-fpm || true

# Wait for any running apt operations to complete
while fuser /var/lib/dpkg/lock-frontend >/dev/null 2>&1; do
    echo "Waiting for apt lock..."
    sleep 5
done

echo "Removing PHP {{ $version }} packages..."

# Remove all PHP packages for this version
apt-get purge -y php{{ $version }}-* || true
apt-get autoremove -y

# Remove sudoers entry for this PHP version
LINE="ALL=NOPASSWD: /usr/sbin/service php{{ $version }}-fpm reload"
FILE="/etc/sudoers.d/php-fpm"
sed -i "\|^netipar $LINE|d" "$FILE" 2>/dev/null || true

echo "PHP {{ $version }} uninstalled successfully"
