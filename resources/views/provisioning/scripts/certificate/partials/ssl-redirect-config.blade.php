# Create SSL redirect configuration for HTTP to HTTPS

# Determine nginx version for http2 syntax
NGINX_VERSION=$(nginx -v 2>&1 | grep -o '[0-9.]\+')
VERSION_NUM=$(echo "$NGINX_VERSION" | awk -F. '{ printf("%d%03d%03d%03d\n", $1,$2,$3,$4); }')
MIN_VERSION=$(echo "1.26.0" | awk -F. '{ printf("%d%03d%03d%03d\n", $1,$2,$3,$4); }')

mkdir -p "$SITE_CONF_DIR/before"

if [ $VERSION_NUM -ge $MIN_VERSION ]; then
    # Nginx 1.26+ uses http2 as a directive
    cat > "$SITE_CONF_DIR/before/ssl_redirect.conf" << 'REDIRECTEOF'
# HTTP to HTTPS redirect
server {
    listen 80;
    listen [::]:80;
    server_name {{ implode(' ', $domains) }};

    location / {
        return 301 https://$host$request_uri;
    }

    # Allow Let's Encrypt renewals via HTTP
    location ^~ /.well-known/acme-challenge/ {
        default_type "text/plain";
        root {{ $wellknown }};
    }
}
REDIRECTEOF
else
    # Older nginx versions use http2 with listen directive
    cat > "$SITE_CONF_DIR/before/ssl_redirect.conf" << 'REDIRECTEOF'
# HTTP to HTTPS redirect
server {
    listen 80;
    listen [::]:80;
    server_name {{ implode(' ', $domains) }};

    location / {
        return 301 https://$host$request_uri;
    }

    # Allow Let's Encrypt renewals via HTTP
    location ^~ /.well-known/acme-challenge/ {
        default_type "text/plain";
        root {{ $wellknown }};
    }
}
REDIRECTEOF
fi
