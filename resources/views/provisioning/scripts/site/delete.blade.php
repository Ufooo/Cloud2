#!/bin/bash
set -e

# Netipar Cloud - Delete Site
# Site: {{ $domain }}
# User: {{ $user }}

echo "Deleting site {{ $domain }}..."

#
# Disable and Remove Nginx Configuration
#

if [ -L "/etc/nginx/sites-enabled/{{ $domain }}" ]; then
    echo "Disabling site in Nginx..."
    rm -f /etc/nginx/sites-enabled/{{ $domain }}
fi

if [ -f "/etc/nginx/sites-available/{{ $domain }}" ]; then
    echo "Removing Nginx site configuration..."
    rm -f /etc/nginx/sites-available/{{ $domain }}
fi

#
# Remove Nginx Configuration Directory
#

if [ -d "/etc/nginx/netipar-conf/{{ $site->id }}" ]; then
    echo "Removing Nginx configuration directory..."
    rm -rf /etc/nginx/netipar-conf/{{ $site->id }}
fi

#
# Remove SSL Certificates
#

if [ -d "/etc/nginx/ssl/{{ $domain }}" ]; then
    echo "Removing SSL certificates..."
    rm -rf /etc/nginx/ssl/{{ $domain }}
fi

#
# Remove Let's Encrypt Renewal Cron
#

if [ -f "/etc/cron.d/letsencrypt-renew-{{ $site->id }}" ]; then
    echo "Removing Let's Encrypt renewal cron..."
    rm -f /etc/cron.d/letsencrypt-renew-{{ $site->id }}
fi

#
# Remove Logrotate Configuration
#

if [ -f "/etc/logrotate.d/netipar-{{ $domain }}" ]; then
    echo "Removing logrotate configuration..."
    rm -f /etc/logrotate.d/netipar-{{ $domain }}
fi

#
# Remove Error Logs
#

if [ -f "/var/log/nginx/{{ $domain }}-error.log" ]; then
    echo "Removing Nginx error log..."
    rm -f /var/log/nginx/{{ $domain }}-error.log
fi

@if($isIsolated)
#
# Remove Isolated PHP-FPM Pool
#

if [ -f "/etc/php/{{ $phpVersion }}/fpm/pool.d/{{ $domain }}.conf" ]; then
    echo "Removing isolated PHP-FPM pool..."
    rm -f /etc/php/{{ $phpVersion }}/fpm/pool.d/{{ $domain }}.conf
fi

# Remove PHP-FPM socket if exists
if [ -S "/var/run/php/php{{ $phpVersion }}-fpm-{{ $domain }}.sock" ]; then
    rm -f /var/run/php/php{{ $phpVersion }}-fpm-{{ $domain }}.sock
fi

echo "Reloading PHP-FPM..."
service php{{ $phpVersion }}-fpm reload || true
@endif

#
# Reload Nginx
#

echo "Testing Nginx configuration..."
nginx -t

echo "Reloading Nginx..."
service nginx reload

#
# Remove Site Directory
#

SITE_PATH="{{ $fullPath }}"

if [ -d "$SITE_PATH" ]; then
    echo "Removing site directory at $SITE_PATH..."
    rm -rf "$SITE_PATH"
    echo "Site directory removed"
else
    echo "Site directory does not exist at $SITE_PATH"
fi

#
# Remove Deploy Key from SSH (if exists)
#

SSH_KEY_FILE="/home/{{ $user }}/.ssh/id_rsa_{{ $site->id }}"
if [ -f "$SSH_KEY_FILE" ]; then
    echo "Removing deploy key..."
    rm -f "$SSH_KEY_FILE"
    rm -f "${SSH_KEY_FILE}.pub"
fi

echo "Site {{ $domain }} deleted successfully!"
