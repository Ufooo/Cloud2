#!/bin/bash
set -e

# Netipar Cloud - Install Site
# Site: {{ $domain }}
# User: {{ $user }}
# Type: {{ $siteType->value }}

echo "Installing site {{ $domain }}..."

#
# Ensure DH Parameters Exist
#

if [ ! -f /etc/nginx/dhparams.pem ]; then
    echo "Generating dhparams.pem file..."
    openssl dhparam -out /etc/nginx/dhparams.pem 2048
fi

#
# Create FastCGI Defaults
#

if [ ! -f /etc/nginx/netipar_fastcgi_defaults ]; then
    echo "Creating FastCGI defaults..."
    cat > /etc/nginx/netipar_fastcgi_defaults << 'EOF'
# Netipar FastCGI Defaults
fastcgi_buffers 32 32k;
fastcgi_buffer_size 64k;
EOF
fi

#
# Create Directory Structure
#

SITE_PATH="{{ $fullPath }}"
WEB_PATH="{{ $webPath }}"

if [ -d "$SITE_PATH" ]; then
    echo "Site directory already exists at $SITE_PATH"
else
    echo "Creating site directory at $SITE_PATH..."
    mkdir -p "$SITE_PATH"

@if($siteType->supportsZeroDowntime())
    # Zero-downtime deployment structure (Laravel, Statamic, Symfony)
    mkdir -p "$SITE_PATH/releases"
    mkdir -p "$SITE_PATH/storage"
    mkdir -p "$SITE_PATH/storage/app/public"
    mkdir -p "$SITE_PATH/storage/framework/cache"
    mkdir -p "$SITE_PATH/storage/framework/sessions"
    mkdir -p "$SITE_PATH/storage/framework/views"
    mkdir -p "$SITE_PATH/storage/logs"

    # Create initial release directory
    TARGET_DIR="$SITE_PATH/releases/initial"
    mkdir -p "$TARGET_DIR"

    # Create public directory
    mkdir -p "$TARGET_DIR{{ $webDirectory }}"

    # Create default index file
{!! $defaultIndexScript !!}

    # Create symlink to current release
    ln -sfn "$TARGET_DIR" "$SITE_PATH/current"

    # Create shared .env file
    if [ ! -f "$SITE_PATH/.env" ]; then
        touch "$SITE_PATH/.env"
        chown {{ $user }}:{{ $user }} "$SITE_PATH/.env"
        chmod 644 "$SITE_PATH/.env"
    fi

    # Symlink .env to initial release
    ln -sfn "$SITE_PATH/.env" "$TARGET_DIR/.env"

    # Symlink storage to initial release
    ln -sfn "$SITE_PATH/storage" "$TARGET_DIR/storage"
@else
    # Simple directory structure (WordPress, HTML, PHP, etc.)
    TARGET_DIR="$SITE_PATH"
    mkdir -p "$TARGET_DIR{{ $webDirectory }}"

    # Create default index file
{!! $defaultIndexScript !!}
@endif

    # Set proper ownership
    chown -R {{ $user }}:{{ $user }} "$SITE_PATH"
    chmod -R 755 "$SITE_PATH"
@if($siteType->supportsZeroDowntime())
    chmod -R 775 "$SITE_PATH/storage"
@endif

    echo "Site directory structure created successfully"
fi

#
# Create Nginx Configuration Directory
#

NGINX_CONF_DIR="/etc/nginx/netipar-conf/{{ $site->id }}"
mkdir -p "$NGINX_CONF_DIR"
mkdir -p "$NGINX_CONF_DIR/before"
mkdir -p "$NGINX_CONF_DIR/server"
mkdir -p "$NGINX_CONF_DIR/after"

#
# Create www to non-www Redirect
#

echo "Creating www redirect configuration..."
cat > "$NGINX_CONF_DIR/before/redirect.conf" << 'EOF'
server {
    listen 80;
    listen [::]:80;
    server_tokens off;
    server_name www.{{ $domain }};

    if ($http_x_forwarded_proto = 'https') {
        return 301 https://{{ $domain }}$request_uri;
    }

    return 301 $scheme://{{ $domain }}$request_uri;
}
EOF

#
# Create Nginx Site Configuration
#

echo "Creating Nginx configuration for {{ $domain }}..."

cat > /etc/nginx/sites-available/{{ $domain }} << 'NGINXEOF'
{!! $nginxConfig !!}
NGINXEOF

#
# Enable Site
#

if [ ! -L "/etc/nginx/sites-enabled/{{ $domain }}" ]; then
    ln -s /etc/nginx/sites-available/{{ $domain }} /etc/nginx/sites-enabled/{{ $domain }}
    echo "Site enabled in Nginx"
fi

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
service php8.4-fpm reload > /dev/null 2>&1 || true
service php8.3-fpm reload > /dev/null 2>&1 || true
service php8.2-fpm reload > /dev/null 2>&1 || true
service php8.1-fpm reload > /dev/null 2>&1 || true
service php8.0-fpm reload > /dev/null 2>&1 || true

{!! $isolatedFpmScript !!}

#
# Create Logrotate Configuration
#

echo "Creating logrotate configuration..."
cat > /etc/logrotate.d/netipar-{{ $domain }} << 'EOF'
{{ $fullPath }}/storage/logs/*.log
{{ $fullPath }}/shared/storage/logs/*.log {
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

@if($siteType === \Nip\Site\Enums\SiteType::WordPress)
#
# Install WordPress
#
@include('provisioning.scripts.site.partials.install-wordpress', [
    'site' => $site,
    'user' => $user,
    'fullPath' => $fullPath,
    'webDirectory' => $webDirectory,
    'phpVersion' => $phpVersion,
])
@endif

echo "Site {{ $domain }} installed successfully!"
