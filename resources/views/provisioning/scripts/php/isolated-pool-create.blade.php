#!/bin/bash
set -eo pipefail

# Netipar Cloud - Create Isolated PHP-FPM Pool
# User: {{ $user }}
# PHP Version: {{ $phpVersion }}

POOL_CONF="/etc/php/{{ $phpVersion }}/fpm/pool.d/{{ $user }}.conf"

if [[ -f "$POOL_CONF" ]]; then
    echo "Isolated PHP-FPM pool for {{ $user }} (PHP {{ $phpVersion }}) already exists."
    exit 0
fi

echo "Creating isolated PHP-FPM pool for {{ $user }} (PHP {{ $phpVersion }})..."

cat > "$POOL_CONF" << EOF
[{{ $user }}]
user = {{ $user }}
group = {{ $user }}

listen = /var/run/php/php{{ $phpVersion }}-fpm-{{ $user }}.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0666

pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 500

request_terminate_timeout = 60

php_admin_flag[log_errors] = on
php_admin_value[memory_limit] = 256M
php_admin_value[upload_max_filesize] = 100M
php_admin_value[post_max_size] = 100M

security.limit_extensions = .php
EOF

echo "Reloading PHP {{ $phpVersion }} FPM..."
systemctl reload php{{ $phpVersion }}-fpm

echo "Isolated PHP-FPM pool for {{ $user }} (PHP {{ $phpVersion }}) created successfully!"
