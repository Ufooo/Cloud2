#!/bin/bash
set -e

echo "Updating PHP settings..."

#
# Max Upload Size - Nginx Configuration
#

rm -f /etc/nginx/conf.d/limits.conf

if grep client_max_body_size /etc/nginx/nginx.conf; then
    sed -i '/client_max_body_size/d' /etc/nginx/nginx.conf
fi

echo "client_max_body_size {{ $maxUploadSize }}M;" > /etc/nginx/conf.d/uploads.conf
echo "Nginx client_max_body_size set to {{ $maxUploadSize }}M"

#
# Max Execution Time - Nginx Configuration
#

if grep fastcgi_read_timeout /etc/nginx/nginx.conf; then
    sed -i '/fastcgi_read_timeout/d' /etc/nginx/nginx.conf
fi

echo "fastcgi_read_timeout {{ $maxExecutionTime }};" > /etc/nginx/conf.d/timeout.conf
echo "Nginx fastcgi_read_timeout set to {{ $maxExecutionTime }}s"

#
# PHP Configuration
#

@foreach($versions as $version)
PHP_VERSION="{{ $version }}"
FPM_INI="/etc/php/${PHP_VERSION}/fpm/php.ini"
CLI_INI="/etc/php/${PHP_VERSION}/cli/php.ini"
POOL_CONF="/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf"

if [ -f "$FPM_INI" ]; then
    echo "Configuring PHP ${PHP_VERSION} FPM..."

    # Max upload size
    sed -i "s/upload_max_filesize = .*/upload_max_filesize = {{ $maxUploadSize }}M/" "$FPM_INI"
    sed -i "s/post_max_size = .*/post_max_size = {{ $maxUploadSize }}M/" "$FPM_INI"

    # Max execution time
    sed -i "s/max_execution_time = .*/max_execution_time = {{ $maxExecutionTime }}/" "$FPM_INI"

    # OPcache - patterns handle both "key=val" and "key = val" formats
@if($opcacheEnabled)
    sed -i "s/^;\?\s*opcache\.enable\s*=.*/opcache.enable = 1/" "$FPM_INI"
    sed -i "s/^;\?\s*opcache\.memory_consumption\s*=.*/opcache.memory_consumption = 512/" "$FPM_INI"
    sed -i "s/^;\?\s*opcache\.interned_strings_buffer\s*=.*/opcache.interned_strings_buffer = 64/" "$FPM_INI"
    sed -i "s/^;\?\s*opcache\.max_accelerated_files\s*=.*/opcache.max_accelerated_files = 30000/" "$FPM_INI"
    sed -i "s/^;\?\s*opcache\.validate_timestamps\s*=.*/opcache.validate_timestamps = 0/" "$FPM_INI"
    sed -i "s/^;\?\s*opcache\.save_comments\s*=.*/opcache.save_comments = 1/" "$FPM_INI"
@else
    sed -i "s/^;\?\s*opcache\.enable\s*=.*/opcache.enable = 0/" "$FPM_INI"
@endif

    echo "PHP ${PHP_VERSION} FPM configured"
fi

if [ -f "$CLI_INI" ]; then
    echo "Configuring PHP ${PHP_VERSION} CLI..."

    # Max upload size (for CLI scripts)
    sed -i "s/upload_max_filesize = .*/upload_max_filesize = {{ $maxUploadSize }}M/" "$CLI_INI"
    sed -i "s/post_max_size = .*/post_max_size = {{ $maxUploadSize }}M/" "$CLI_INI"

    # Max execution time (0 = unlimited for CLI)
    sed -i "s/max_execution_time = .*/max_execution_time = 0/" "$CLI_INI"

    echo "PHP ${PHP_VERSION} CLI configured"
fi

# PHP-FPM Pool - request_terminate_timeout
if [ -f "$POOL_CONF" ]; then
    sed -i "s/request_terminate_timeout\( *\)=.*/request_terminate_timeout = {{ $maxExecutionTime }}/" "$POOL_CONF"
    echo "PHP ${PHP_VERSION} pool request_terminate_timeout set to {{ $maxExecutionTime }}s"
fi

@endforeach

#
# Restart Services
#

echo "Restarting services..."

# Reload Nginx
NGINX=$(ps aux | grep nginx | grep -v grep)
if [[ -z $NGINX ]]; then
    service nginx start
    echo "Started Nginx"
else
    service nginx reload
    echo "Reloaded Nginx"
fi

# Reload PHP-FPM for all versions
PHP=$(ps aux | grep php-fpm | grep -v grep)
if [[ ! -z $PHP ]]; then
@foreach($versions as $version)
    service php{{ $version }}-fpm reload > /dev/null 2>&1 || true
@endforeach
    echo "Reloaded PHP-FPM services"
fi

echo "PHP settings updated successfully"
