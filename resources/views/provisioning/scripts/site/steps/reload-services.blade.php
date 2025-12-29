#!/bin/bash
set -e

# Netipar Cloud - Reload Services
# Site: {{ $domain }}

#
# Test and Reload Nginx
#

echo "Testing Nginx configuration..."
nginx -t

echo "Reloading Nginx..."
service nginx reload

#
# Reload All PHP-FPM Versions
#

echo "Reloading PHP-FPM services..."
@foreach($installedPhpVersions as $version)
service php{{ $version }}-fpm reload > /dev/null 2>&1 || true
@endforeach

echo "Services reloaded successfully"
