#!/bin/bash
set -e

# Netipar Cloud - Delete Site
# Site: {{ $domain }}
# User: {{ $user }}

echo "Deleting site {{ $domain }}..."

#
# Disable and Remove Nginx Configuration for All Domains
#

# Primary domain
if [ -e "/etc/nginx/sites-enabled/{{ $domain }}" ]; then
    echo "Disabling site in Nginx..."
    rm -f /etc/nginx/sites-enabled/{{ $domain }}
fi

if [ -e "/etc/nginx/sites-available/{{ $domain }}" ]; then
    echo "Removing Nginx site configuration..."
    rm -f /etc/nginx/sites-available/{{ $domain }}
fi

# Domain aliases and additional domains
@foreach($domainRecords as $domainName)
@if($domainName !== $domain)
if [ -e "/etc/nginx/sites-enabled/{{ $domainName }}" ]; then
    echo "Disabling alias {{ $domainName }} in Nginx..."
    rm -f /etc/nginx/sites-enabled/{{ $domainName }}
fi

if [ -e "/etc/nginx/sites-available/{{ $domainName }}" ]; then
    echo "Removing Nginx configuration for {{ $domainName }}..."
    rm -f /etc/nginx/sites-available/{{ $domainName }}
fi

# Remove SSL certificates for alias
if [ -d "/etc/nginx/ssl/{{ $domainName }}" ]; then
    echo "Removing SSL certificates for {{ $domainName }}..."
    rm -rf /etc/nginx/ssl/{{ $domainName }}
fi
@endif
@endforeach

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

@if(count($scheduledJobs) > 0)
#
# Remove Scheduled Job Cron Entries
#

echo "Removing scheduled job cron entries..."
@foreach($scheduledJobs as $job)
# Remove job {{ $job['id'] }} from user {{ $job['user'] }}'s crontab
CURRENT_CRONTAB=$(crontab -u "{{ $job['user'] }}" -l 2>/dev/null || echo "")
NEW_CRONTAB=$(echo "${CURRENT_CRONTAB}" | grep -v "# netipar-job:{{ $job['id'] }}$" || true)
if [ -z "${NEW_CRONTAB}" ]; then
    crontab -u "{{ $job['user'] }}" -r 2>/dev/null || true
else
    echo "${NEW_CRONTAB}" | crontab -u "{{ $job['user'] }}" -
fi
echo "Removed scheduled job {{ $job['id'] }}"
@endforeach
@endif

@if(count($backgroundProcesses) > 0)
#
# Remove Background Process Supervisor Configs
#

echo "Removing background process supervisor configs..."
@foreach($backgroundProcesses as $processId)
if [ -f "/etc/supervisor/conf.d/worker-{{ $processId }}.conf" ]; then
    rm -f /etc/supervisor/conf.d/worker-{{ $processId }}.conf
    echo "Removed supervisor config for worker {{ $processId }}"
fi
@endforeach

# Update Supervisor Configuration State
supervisorctl reread
supervisorctl update
@endif

@if($shouldDeletePool)
#
# Remove Isolated PHP-FPM Pool (no other sites using this user)
#

if [ -f "/etc/php/{{ $phpVersion }}/fpm/pool.d/{{ $user }}.conf" ]; then
    echo "Removing isolated PHP-FPM pool for user {{ $user }}..."
    rm -f /etc/php/{{ $phpVersion }}/fpm/pool.d/{{ $user }}.conf
fi

# Remove PHP-FPM socket if exists
if [ -S "/var/run/php/php{{ $phpVersion }}-fpm-{{ $user }}.sock" ]; then
    rm -f /var/run/php/php{{ $phpVersion }}-fpm-{{ $user }}.sock
fi
@else
echo "Keeping PHP-FPM pool for user {{ $user }} (other sites still use it)"
@endif

#
# Reload All PHP-FPM Versions
#

echo "Reloading PHP-FPM services..."
@foreach($installedPhpVersions as $version)
service php{{ $version }}-fpm reload > /dev/null 2>&1 || true
@endforeach

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
