#!/bin/bash
set -e

# Netipar Cloud - Create Isolated PHP-FPM Pool
# Site: {{ $domain }}
# PHP Version: {{ $phpVersion }}

echo "Creating isolated PHP-FPM pool for {{ $domain }}..."

cat > /etc/php/{{ $phpVersion }}/fpm/pool.d/{{ $domain }}.conf << 'EOF'
[{{ $domain }}]
user = {{ $user }}
group = {{ $user }}
listen = /var/run/php/php{{ $phpVersion }}-fpm-{{ $domain }}.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.process_idle_timeout = 10s
pm.max_requests = 500

chdir = {{ $fullPath }}

php_admin_value[error_log] = /var/log/php{{ $phpVersion }}-fpm-{{ $domain }}.log
php_admin_flag[log_errors] = on
EOF

echo "Reloading PHP-FPM {{ $phpVersion }}..."
service php{{ $phpVersion }}-fpm reload

echo "Isolated PHP-FPM pool created for {{ $domain }}"
