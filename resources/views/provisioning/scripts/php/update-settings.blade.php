#!/bin/bash
set -e

echo "Updating PHP settings..."

@foreach($versions as $version)
PHP_VERSION="{{ $version }}"
FPM_INI="/etc/php/${PHP_VERSION}/fpm/php.ini"
CLI_INI="/etc/php/${PHP_VERSION}/cli/php.ini"

if [ -f "$FPM_INI" ]; then
    echo "Configuring PHP ${PHP_VERSION} FPM..."

    # Max upload size
    sed -i "s/upload_max_filesize = .*/upload_max_filesize = {{ $maxUploadSize }}M/" "$FPM_INI"
    sed -i "s/post_max_size = .*/post_max_size = {{ $maxUploadSize + 1 }}M/" "$FPM_INI"

    # Max execution time
    sed -i "s/max_execution_time = .*/max_execution_time = {{ $maxExecutionTime }}/" "$FPM_INI"

    # OPcache
@if($opcacheEnabled)
    sed -i "s/;opcache.enable=.*/opcache.enable=1/" "$FPM_INI"
    sed -i "s/opcache.enable=.*/opcache.enable=1/" "$FPM_INI"
@else
    sed -i "s/;opcache.enable=.*/opcache.enable=0/" "$FPM_INI"
    sed -i "s/opcache.enable=.*/opcache.enable=0/" "$FPM_INI"
@endif

    # Restart PHP-FPM
    systemctl restart php${PHP_VERSION}-fpm
    echo "PHP ${PHP_VERSION} FPM restarted"
fi

if [ -f "$CLI_INI" ]; then
    echo "Configuring PHP ${PHP_VERSION} CLI..."

    # Max upload size (for CLI scripts)
    sed -i "s/upload_max_filesize = .*/upload_max_filesize = {{ $maxUploadSize }}M/" "$CLI_INI"
    sed -i "s/post_max_size = .*/post_max_size = {{ $maxUploadSize + 1 }}M/" "$CLI_INI"

    # Max execution time (0 = unlimited for CLI)
    sed -i "s/max_execution_time = .*/max_execution_time = 0/" "$CLI_INI"
fi

@endforeach

echo "PHP settings updated successfully"
