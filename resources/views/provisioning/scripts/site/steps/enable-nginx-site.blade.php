#!/bin/bash
set -e

# Netipar Cloud - Enable Nginx Site
# Site: {{ $domain }}

echo "Enabling Nginx site {{ $domain }}..."

if [ ! -f "/etc/nginx/sites-available/{{ $domain }}" ]; then
    echo "Nginx configuration file does not exist: /etc/nginx/sites-available/{{ $domain }}"
    exit 1
fi

if [ ! -L "/etc/nginx/sites-enabled/{{ $domain }}" ]; then
    ln -s /etc/nginx/sites-available/{{ $domain }} /etc/nginx/sites-enabled/{{ $domain }}
    echo "Site enabled in Nginx"
else
    echo "Site already enabled in Nginx"
fi
