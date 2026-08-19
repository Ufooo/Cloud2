# Netipar Cloud - Domain Redirect Configuration
# Domain: {{ $domain }}
# Site: {{ $site->domain }}
# Redirects to: {{ $redirectTarget }}
# Generated: {{ now()->toISOString() }}

server {
    listen 80;
    listen [::]:80;
    server_name {{ $domain }}{{ $allowWildcard ? ' *.'.$domain : '' }};
    server_tokens off;

    # Allow Let's Encrypt renewals via HTTP
    location ^~ /.well-known/acme-challenge/ {
        default_type "text/plain";
        root /home/{{ $site->user }}/.letsencrypt;
    }

    location / {
        return 301 https://{{ $redirectTarget }}$request_uri;
    }
}

@if($certificate)
server {
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;
    server_name {{ $domain }}{{ $allowWildcard ? ' *.'.$domain : '' }};
    server_tokens off;

    ssl_certificate {{ $certificate->getCertPath() }}/fullchain.crt;
    ssl_certificate_key {{ $certificate->getCertPath() }}/private.key;

    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers off;

    return 301 https://{{ $redirectTarget }}$request_uri;
}
@endif
