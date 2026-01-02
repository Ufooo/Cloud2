#!/bin/bash
set -e

echo "Installing PHP {{ $version }}..."

# Wait for any running apt operations to complete
while fuser /var/lib/dpkg/lock-frontend >/dev/null 2>&1; do
    echo "Waiting for apt lock..."
    sleep 5
done

# Add Ondřej Surý's PPA for PHP if not already added
if ! grep -q "ondrej/php" /etc/apt/sources.list.d/*.list 2>/dev/null; then
    echo "Adding PHP PPA repository..."
    add-apt-repository -y ppa:ondrej/php
    apt-get update -y
fi

# Wait for apt lock again
while fuser /var/lib/dpkg/lock-frontend >/dev/null 2>&1; do
    echo "Waiting for apt lock..."
    sleep 5
done

echo "Installing PHP {{ $version }} packages..."

# Install PHP Packages
@include('provisioning.scripts.php.packages')

echo "Configuring PHP {{ $version }}..."

# Configure PHP
@include('provisioning.scripts.php.configure', ['version' => $version, 'unixUsers' => $unixUsers])

# Enable and start PHP-FPM
echo "Starting PHP-FPM service..."
systemctl enable php{{ $version }}-fpm
systemctl start php{{ $version }}-fpm

echo "PHP {{ $version }} installed and configured successfully"
