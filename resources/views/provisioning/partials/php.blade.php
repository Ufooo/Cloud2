{{-- PHP Installation for Server Provisioning --}}
{{-- Variables: $phpVersion, $server --}}

# Ensure apt-get Is Up To Date
apt-get update -o Acquire::AllowReleaseInfoChange=true

# Install PHP Packages
@include('provisioning.scripts.php.packages', ['version' => $phpVersion])

# Install Composer Package Manager
if [ ! -f /usr/local/bin/composer ]; then
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
    echo "netipar ALL=(root) NOPASSWD: /usr/local/bin/composer self-update*" > /etc/sudoers.d/composer
fi

# Configure PHP
@include('provisioning.scripts.php.configure', ['version' => $phpVersion])

# Set as default PHP version
update-alternatives --set php /usr/bin/php{{ $phpVersion }}

provisionPing {{ $server->id }} 5
