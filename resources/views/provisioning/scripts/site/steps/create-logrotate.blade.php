#!/bin/bash
set -e

# Netipar Cloud - Create Logrotate Configuration
# Site: {{ $domain }}

echo "Creating logrotate configuration..."

cat > /etc/logrotate.d/netipar-{{ $site->id }} << 'EOF'
{{ $siteRoot }}/storage/logs/*.log
{{ $siteRoot }}/shared/storage/logs/*.log {
    su {{ $user }} {{ $user }}
    weekly
    maxsize 100M
    missingok
    rotate 2
    compress
    notifempty
    create 755 {{ $user }} {{ $user }}
}
EOF

echo "Logrotate configuration created"
