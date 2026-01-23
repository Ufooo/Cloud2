#!/bin/bash
set -eo pipefail

# Netipar Cloud - Update Site PHP Version
# Site: {{ $domain }}
# Old Version: {{ $oldVersion }}
# New Version: {{ $newVersion }}

echo "Updating PHP version for {{ $domain }} from {{ $oldVersion }} to {{ $newVersion }}..."

NGINX_CONF="/etc/nginx/sites-available/{{ $domain }}"

if [[ ! -f "$NGINX_CONF" ]]; then
    echo "Error: Nginx configuration not found at $NGINX_CONF"
    exit 1
fi

#
# Update Nginx Configuration
#

echo "Updating Nginx configuration..."

# Update socket path (all sites use isolated PHP-FPM pools)
OLD_SOCKET="php{{ $oldVersion }}-fpm-{{ $user }}.sock"
NEW_SOCKET="php{{ $newVersion }}-fpm-{{ $user }}.sock"

sed -i.bak "s|$OLD_SOCKET|$NEW_SOCKET|g" "$NGINX_CONF"

#
# Test Nginx Configuration
#

echo "Testing Nginx configuration..."
if ! nginx -t; then
    echo "Nginx configuration test failed, restoring backup..."
    mv "$NGINX_CONF.bak" "$NGINX_CONF"
    exit 1
fi

#
# Reload Nginx
#

echo "Reloading Nginx..."
systemctl reload nginx

#
# Ensure user has sudoers permissions for deployment
#

echo "Ensuring sudoers permissions for {{ $user }}..."

# PHP-FPM reload permission
if ! grep -q "^{{ $user }} .*php\*-fpm" /etc/sudoers.d/php-fpm 2>/dev/null; then
    echo "{{ $user }} ALL=NOPASSWD: /usr/sbin/service php*-fpm reload" >> /etc/sudoers.d/php-fpm
    echo "Added PHP-FPM sudoers entry for {{ $user }}"
fi

# Supervisor permission
if ! grep -q "^{{ $user }} .*supervisorctl" /etc/sudoers.d/supervisor 2>/dev/null; then
    echo "{{ $user }} ALL=NOPASSWD: /usr/bin/supervisorctl *" >> /etc/sudoers.d/supervisor
    echo "Added Supervisor sudoers entry for {{ $user }}"
fi

# Nginx permission
if ! grep -q "^{{ $user }} .*nginx" /etc/sudoers.d/nginx 2>/dev/null; then
    echo "{{ $user }} ALL=NOPASSWD: /usr/sbin/service nginx *" >> /etc/sudoers.d/nginx
    echo "Added Nginx sudoers entry for {{ $user }}"
fi

#
# Note: Isolated PHP-FPM pool management is handled by separate jobs
# (CreateIsolatedPhpFpmPoolJob / DeleteIsolatedPhpFpmPoolJob)
#

#
# Cleanup
#

rm -f "$NGINX_CONF.bak"

echo "PHP version updated successfully to {{ $newVersion }} for {{ $domain }}!"
