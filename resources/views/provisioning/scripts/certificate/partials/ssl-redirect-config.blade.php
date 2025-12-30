@php
// Check if this is a wildcard certificate (any domain starts with *.)
$isWildcard = collect($domains)->contains(fn($d) => str_starts_with($d, '*.'));

// For wildcard: use .basedomain syntax (covers all subdomains)
// For normal: list all domains explicitly
if ($isWildcard) {
    // Get the base domain (remove *. prefix from wildcard domain)
    $baseDomain = collect($domains)
        ->map(fn($d) => str_starts_with($d, '*.') ? substr($d, 2) : $d)
        ->unique()
        ->first();
    $serverName = '.' . $baseDomain;
} else {
    $serverName = implode(' ', $domains);
}
@endphp
# Create SSL redirect configuration for HTTP to HTTPS

mkdir -p "$SITE_CONF_DIR/before"

cat > "$SITE_CONF_DIR/before/ssl_redirect.conf" << 'REDIRECTEOF'
# HTTP to HTTPS redirect
server {
    listen 80;
    listen [::]:80;
    server_name {{ $serverName }};

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
