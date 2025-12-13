#
# Create Isolated PHP-FPM Pool
#

echo "Creating isolated PHP-FPM pool for {{ $domain }}..."

cat > /etc/php/{{ $phpVersion }}/fpm/pool.d/{{ $domain }}.conf << 'FPMEOF'
[{{ $domain }}]
user = {{ $user }}
group = {{ $user }}
listen = /var/run/php/php{{ $phpVersion }}-fpm-{{ $domain }}.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0666

pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 500

request_terminate_timeout = 60

php_admin_value[error_log] = {{ $fullPath }}/storage/logs/php-fpm.log
php_admin_flag[log_errors] = on
php_admin_value[memory_limit] = 256M

chdir = {{ $fullPath }}/current
FPMEOF

echo "Reloading PHP-FPM..."
service php{{ $phpVersion }}-fpm reload
